import * as logger from '../utils/logger';
import { API_CONFIG } from '../../../shared/constants';

export interface NuclenFetchResult<T> {
	ok: boolean;
	status: number;
	data: T | null;
	error?: string;
}

export async function nuclenFetchWithRetry<T = unknown>(
	url: string,
	options: RequestInit,
	retries = API_CONFIG.RETRY_COUNT,
	initialDelayMs = API_CONFIG.INITIAL_DELAY_MS,
	timeoutMs = 30000 // 30 second default timeout
): Promise<NuclenFetchResult<T>> {
	let attempt = 0;
	let delay: number = initialDelayMs;
	let lastError: Error | undefined;

	while (attempt <= retries) {
		try {
			// Create AbortController for timeout
			const controller = new window.AbortController();
			const timeoutId = setTimeout(() => controller.abort(), timeoutMs);
			
			const response = await fetch(url, {
				...options,
				signal: controller.signal
			});
			
			clearTimeout(timeoutId);
			const { status, ok } = response;
			const bodyText = await response.text().catch(() => '');
			let data: T | null = null;
			if (bodyText) {
				try {
					data = JSON.parse(bodyText) as T;
				} catch {
					// body is not JSON
				}
			}

			if (ok) {
				const success: NuclenFetchResult<T> = {
					ok: true,
					status: status,
					data: data,
				};
				return success;
			}

			const fail: NuclenFetchResult<T> = {
				ok: false,
				status: status,
				data: data,
				error: bodyText,
			};
			return fail;
		} catch (error: unknown) {
			lastError = error as Error;
			
			// Check if it's an abort error
			if (lastError.name === 'AbortError') {
				lastError = new Error(`Request timeout after ${timeoutMs}ms`);
			}
			
			if (attempt === retries) {
				break;
			}

			logger.warn(
				`[RETRY] API request | URL: ${url} | Method: ${options.method || 'GET'} | Attempts left: ${retries - attempt} | Error: ${lastError.message}`
			);
			await new Promise((resolve) => setTimeout(resolve, delay));
			delay = Math.min(delay * 2, API_CONFIG.MAX_BACKOFF_MS || 30000) as number;
		}
		attempt += 1;
	}

	logger.error(
		`[ERROR] Max retries exhausted | URL: ${url} | Method: ${options.method || 'GET'} | Error: ${lastError?.message || 'Unknown error'}`
	);
	throw lastError;
}

export interface PollingUpdateData {
	processed: number;
	total: number;
	successCount?: number;
	failCount?: number;
	finalReport?: { message?: string };
	results?: Record<string, unknown>;
	workflow: string;
}

export interface PollingUpdateResponse {
	success: boolean;
	message?: string;
	data: PollingUpdateData;
}

export interface StartGenerationResponse {
	success: boolean;
	message?: string;
	generation_id?: string;
	data?: {
		generation_id?: string;
		results?: any[];
		success?: boolean;
		error?: string;
		error_code?: string;
		status_code?: number;
		message?: string;
		total_posts?: number;
		total_batches?: number;
		[key: string]: unknown;
	};
}

export async function nuclenFetchUpdates(
	generationId?: string
): Promise<PollingUpdateResponse> {
	if (!window.nuclenAjax || !window.nuclenAjax.ajax_url || !window.nuclenAjax.fetch_action) {
		const error = new Error('[ERROR] Missing configuration | Required: nuclenAjax.ajax_url and nuclenAjax.fetch_action');
		logger.error(error.message);
		throw error;
	}

	const formData = new FormData();
	formData.append('action', window.nuclenAjax.fetch_action);

	if (window.nuclenAjax.nonce) {
		formData.append('security', window.nuclenAjax.nonce);
	}

	if (generationId) {
		formData.append('generation_id', generationId);
	}
	
	const result = await nuclenFetchWithRetry<PollingUpdateResponse>(
		window.nuclenAjax.ajax_url,
		{
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
		},
		API_CONFIG.RETRY_COUNT,
		API_CONFIG.INITIAL_DELAY_MS,
		10000 // 10 second timeout for polling
	);

	if (!result.ok) {
		// Check if the error message indicates polling limit reached
		const errorMessage = result.error || 'Unknown error';
		if (errorMessage.includes('Polling failed after') && errorMessage.includes('attempts')) {
			// Return a special response structure for polling limit errors
			// Don't log this as an error since it's a normal flow
			logger.warn(`[WARNING] API polling limit reached | GenID: ${generationId} | Message: ${errorMessage}`);
			return {
				success: false,
				message: errorMessage,
				data: {
					processed: 0,
					total: 0,
					workflow: 'unknown'
				}
			} as PollingUpdateResponse;
		}
		
		const error = new Error(`[ERROR] Fetch updates failed | Status: ${result.status} | Error: ${errorMessage}`);
		logger.error(error.message);
		throw error;
	}

	return result.data as PollingUpdateResponse;
}

export async function NuclenStartGeneration(
	dataToSend: Record<string, unknown>
): Promise<StartGenerationResponse> {
	if (!window.nuclenAdminVars || !window.nuclenAdminVars.ajax_url) {
		const error = new Error('[ERROR] Missing configuration | Required: nuclenAdminVars.ajax_url');
		logger.error(error.message);
		throw error;
	}

	const formData = new FormData();
	formData.append('action', 'nuclen_trigger_generation');
	formData.append('payload', JSON.stringify(dataToSend));
	if (!window.nuclenAjax?.nonce) {
		const error = new Error('[ERROR] Security check failed | Missing nonce');
		logger.error(error.message);
		throw error;
	}
	formData.append('security', window.nuclenAjax.nonce);

	const result = await nuclenFetchWithRetry<StartGenerationResponse>(
		window.nuclenAdminVars.ajax_url,
		{
			method: 'POST',
			body: formData,
			credentials: 'same-origin',
		},
		API_CONFIG.RETRY_COUNT,
		API_CONFIG.INITIAL_DELAY_MS,
		60000 // 60 second timeout for generation start
	);

	if (!result.ok) {
		const error = new Error(`[ERROR] Generation start failed | Status: ${result.status} | Error: ${result.error || 'Unknown error'}`);
		logger.error(error.message);
		throw error;
	}

	// Validate response structure before casting
	const rawData = result.data;
	if (!rawData || typeof rawData !== 'object') {
		const error = new Error('[ERROR] Invalid response | Expected object, received: ' + typeof rawData);
		logger.error(error.message);
		throw error;
	}
	
	// WordPress wp_send_json_success returns { success: true, data: {...} }
	// We need to return the full structure, not just the inner data
	let response: StartGenerationResponse;
	
	if ('success' in rawData && typeof rawData.success === 'boolean') {
		// This is already the WordPress response structure we want
		response = rawData as StartGenerationResponse;
	} else {
		// Wrap it in expected structure
		response = {
			success: true,
			data: rawData as any
		};
	}
	
	// Validate success
	if (!response.success) {
		let errMsg = 'Unknown error';
		
		if (response.message) {
			errMsg = response.message;
		} else if (response.data && typeof response.data === 'object' && response.data.message) {
			errMsg = response.data.message;
		}
		
		const error = new Error(`[ERROR] Generation start failed | Error: ${errMsg}`);
		logger.error(error.message);
		throw error;
	}

	return response;
}
