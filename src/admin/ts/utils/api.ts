export async function apiRequest(url: string, options: RequestInit): Promise<Response> {
	const allowedOrigins = ['https://app.nuclearengagement.com'];
	const urlObj = new URL(url, window.location.origin);
	if (!allowedOrigins.includes(urlObj.origin)) {
		throw new Error('Invalid URL origin');
	}

	try {
		const response = await fetch(url, options);
		if (!response.ok) {
			throw new Error(`HTTP ${response.status}`);
		}
		return response;
	} catch (err: unknown) {
		if (err instanceof Error) {
			throw new Error(err.message || 'Network error');
		}
		throw new Error('Network error');
	}
}
