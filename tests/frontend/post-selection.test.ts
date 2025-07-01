import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { initStep1 } from '../../src/admin/ts/generate/step1';
import { nuclenCollectFilters } from '../../src/admin/ts/generate/filters';
import type { GeneratePageElements } from '../../src/admin/ts/generate/elements';

// Mock dependencies
vi.mock('../../src/admin/ts/nuclen-admin-generate', () => ({
	nuclenFetchWithRetry: vi.fn()
}));

vi.mock('../../src/admin/ts/generate/generate-page-utils', () => ({
	nuclenShowElement: vi.fn(),
	nuclenHideElement: vi.fn(),
	nuclenUpdateProgressBarStep: vi.fn(),
	nuclenCheckCreditsAjax: vi.fn()
}));

vi.mock('../../src/admin/ts/utils/displayError', () => ({
	displayError: vi.fn()
}));

vi.mock('../../src/admin/ts/utils/logger', () => ({
	error: vi.fn()
}));

import { nuclenFetchWithRetry } from '../../src/admin/ts/nuclen-admin-generate';
import { 
	nuclenShowElement, 
	nuclenHideElement, 
	nuclenCheckCreditsAjax 
} from '../../src/admin/ts/generate/generate-page-utils';
import { displayError } from '../../src/admin/ts/utils/displayError';

describe('Post Selection UI Tests', () => {
	let mockElements: GeneratePageElements;
	let mockButton: HTMLButtonElement;

	beforeEach(() => {
		// Setup DOM elements
		document.body.innerHTML = `
			<select id="nuclen_post_type">
				<option value="post">Posts</option>
				<option value="page">Pages</option>
			</select>
			<select id="nuclen_post_status">
				<option value="any">Any</option>
				<option value="publish">Published</option>
				<option value="draft">Draft</option>
			</select>
			<select id="nuclen_category">
				<option value="0">All Categories</option>
				<option value="1">Category 1</option>
			</select>
			<select id="nuclen_author">
				<option value="0">All Authors</option>
				<option value="1">Author 1</option>
			</select>
			<select id="nuclen_generate_workflow">
				<option value="quiz">Quiz</option>
				<option value="summary">Summary</option>
			</select>
			<input type="checkbox" id="nuclen_allow_regenerate_data" />
			<input type="checkbox" id="nuclen_regenerate_protected_data" />
			<select id="nuclen_summary_format">
				<option value="bullet">Bullet Points</option>
			</select>
			<select id="nuclen_summary_length">
				<option value="short">Short</option>
			</select>
			<select id="nuclen_summary_number_of_items">
				<option value="3">3</option>
			</select>
			<button id="nuclen-get-posts-btn">Get Posts</button>
			<div id="nuclen-posts-count"></div>
			<div id="nuclen-credits-info"></div>
			<div id="nuclen-step-1"></div>
			<div id="nuclen-step-2"></div>
			<button id="nuclen-submit-btn">Submit</button>
			<input type="hidden" id="nuclen_selected_post_ids" />
		`;

		mockButton = document.getElementById('nuclen-get-posts-btn') as HTMLButtonElement;
		
		mockElements = {
			getPostsBtn: mockButton,
			postsCountEl: document.getElementById('nuclen-posts-count') as HTMLDivElement,
			creditsInfoEl: document.getElementById('nuclen-credits-info') as HTMLDivElement,
			step1: document.getElementById('nuclen-step-1') as HTMLDivElement,
			step2: document.getElementById('nuclen-step-2') as HTMLDivElement,
			submitBtn: document.getElementById('nuclen-submit-btn') as HTMLButtonElement,
			stepBar1: document.createElement('div'),
			stepBar2: document.createElement('div'),
			stepBar3: document.createElement('div'),
			stepBar4: document.createElement('div')
		};

		// Setup window.nuclenAjax
		(window as any).nuclenAjax = {
			ajax_url: 'https://example.com/wp-admin/admin-ajax.php',
			nonce: 'test_nonce'
		};

		// Reset mocks
		vi.clearAllMocks();
	});

	afterEach(() => {
		delete (window as any).nuclenAjax;
	});

	it('should collect all filter values correctly', () => {
		// Set some values
		(document.getElementById('nuclen_post_type') as HTMLSelectElement).value = 'page';
		(document.getElementById('nuclen_post_status') as HTMLSelectElement).value = 'draft';
		(document.getElementById('nuclen_category') as HTMLSelectElement).value = '1';
		(document.getElementById('nuclen_allow_regenerate_data') as HTMLInputElement).checked = true;

		const filters = nuclenCollectFilters();

		expect(filters.postType).toBe('page');
		expect(filters.postStatus).toBe('draft');
		expect(filters.category).toBe('1');
		expect(filters.allowRegenerate).toBe(true);
		expect(filters.regenerateProtected).toBe(false);
	});

	it('should handle successful post count request', async () => {
		const mockResponse = {
			ok: true,
			data: {
				success: true,
				data: {
					count: 10,
					post_ids: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10']
				}
			}
		};

		vi.mocked(nuclenFetchWithRetry).mockResolvedValue(mockResponse);
		vi.mocked(nuclenCheckCreditsAjax).mockResolvedValue(50);

		initStep1(mockElements);
		
		// Trigger button click
		mockButton.click();

		// Wait for async operations
		await vi.waitFor(() => {
			expect(nuclenFetchWithRetry).toHaveBeenCalled();
		});

		// Verify fetch was called with correct data
		const fetchCall = vi.mocked(nuclenFetchWithRetry).mock.calls[0];
		expect(fetchCall[0]).toBe('https://example.com/wp-admin/admin-ajax.php');
		
		const formData = fetchCall[1]?.body as FormData;
		expect(formData.get('action')).toBe('nuclen_get_posts_count');
		expect(formData.get('security')).toBe('test_nonce');
		expect(formData.get('nuclen_post_type')).toBe('post');

		// Verify UI updates
		await vi.waitFor(() => {
			expect(mockElements.postsCountEl?.innerText).toBe('Number of posts to process: 10');
			expect(mockElements.creditsInfoEl?.textContent).toBe('This will consume 10 credit(s). You have 50 left.');
		});

		// Verify step progression
		expect(nuclenHideElement).toHaveBeenCalledWith(mockElements.step1);
		expect(nuclenShowElement).toHaveBeenCalledWith(mockElements.step2);
	});

	it('should handle post count request with no posts found', async () => {
		const mockResponse = {
			ok: true,
			data: {
				success: true,
				data: {
					count: 0,
					post_ids: []
				}
			}
		};

		vi.mocked(nuclenFetchWithRetry).mockResolvedValue(mockResponse);

		initStep1(mockElements);
		mockButton.click();

		await vi.waitFor(() => {
			expect(mockElements.postsCountEl?.innerText).toBe('No posts found with these filters.');
		});

		// Submit button should be hidden
		expect(nuclenHideElement).toHaveBeenCalledWith(mockElements.submitBtn);
	});

	it('should handle error response for invalid post type', async () => {
		const mockResponse = {
			ok: true,
			data: {
				success: false,
				message: 'Selected post type is not allowed for generation.'
			}
		};

		vi.mocked(nuclenFetchWithRetry).mockResolvedValue(mockResponse);

		initStep1(mockElements);
		mockButton.click();

		await vi.waitFor(() => {
			expect(displayError).toHaveBeenCalledWith('Selected post type is not allowed for generation.');
		});
	});

	it('should handle invalid API key error', async () => {
		const mockResponse = {
			ok: true,
			data: {
				success: false,
				data: {
					message: 'Invalid API key provided'
				}
			}
		};

		vi.mocked(nuclenFetchWithRetry).mockResolvedValue(mockResponse);

		initStep1(mockElements);
		mockButton.click();

		await vi.waitFor(() => {
			expect(displayError).toHaveBeenCalledWith(
				'Your Gold Code (API key) is invalid. Please create a new one on the NE app and enter it on the plugin Setup page.'
			);
		});
	});

	it('should handle network error', async () => {
		vi.mocked(nuclenFetchWithRetry).mockResolvedValue({ ok: false, data: null });

		initStep1(mockElements);
		mockButton.click();

		await vi.waitFor(() => {
			expect(mockElements.postsCountEl?.innerText).toBe('Error retrieving post count.');
		});
	});

	it('should show error when ajax not configured', () => {
		delete (window as any).nuclenAjax;

		initStep1(mockElements);
		mockButton.click();

		expect(displayError).toHaveBeenCalledWith(
			'Error: Ajax is not configured properly. Please check the plugin settings.'
		);
	});

	it('should disable submit button when not enough credits', async () => {
		const mockResponse = {
			ok: true,
			data: {
				success: true,
				data: {
					count: 100,
					post_ids: Array(100).fill('1')
				}
			}
		};

		vi.mocked(nuclenFetchWithRetry).mockResolvedValue(mockResponse);
		vi.mocked(nuclenCheckCreditsAjax).mockResolvedValue(50); // Not enough credits

		initStep1(mockElements);
		mockButton.click();

		await vi.waitFor(() => {
			expect(mockElements.creditsInfoEl?.textContent).toBe(
				'This will consume 100 credit(s). You have 50 left.'
			);
			expect(displayError).toHaveBeenCalledWith(
				'Not enough credits. Please top up or reduce the number of posts.'
			);
			expect(mockElements.submitBtn?.disabled).toBe(true);
		});
	});

	it('should store post IDs in hidden field', async () => {
		const mockResponse = {
			ok: true,
			data: {
				success: true,
				data: {
					count: 3,
					post_ids: ['10', '20', '30']
				}
			}
		};

		vi.mocked(nuclenFetchWithRetry).mockResolvedValue(mockResponse);
		vi.mocked(nuclenCheckCreditsAjax).mockResolvedValue(50);

		initStep1(mockElements);
		mockButton.click();

		await vi.waitFor(() => {
			const hiddenField = document.getElementById('nuclen_selected_post_ids') as HTMLInputElement;
			expect(hiddenField.value).toBe('["10","20","30"]');
		});
	});

	it('should show loading state while fetching', () => {
		vi.mocked(nuclenFetchWithRetry).mockImplementation(() => 
			new Promise(resolve => setTimeout(resolve, 1000))
		);

		initStep1(mockElements);
		mockButton.click();

		expect(mockElements.postsCountEl?.innerText).toBe('Loading posts...');
	});
});