import { nuclenFetchWithRetry } from '../nuclen-admin-generate';
import {
	nuclenShowElement,
	nuclenHideElement,
	nuclenUpdateProgressBarStep,
	nuclenCheckCreditsAjax,
} from './generate-page-utils';
import type { GeneratePageElements } from './elements';
import {
	nuclenCollectFilters,
	nuclenAppendFilters,
	nuclenStoreFilters,
	type NuclenFilterValues,
} from './filters';
import { displayError } from '../utils/displayError';
import * as logger from '../utils/logger';

interface PostsCountResponse {
        success: boolean;
        message?: string;
        data: {
        count: number;
	post_ids: string[];
	message?: string;
	[key: string]: unknown;
        };
}

async function requestPostsCount(
        ajaxUrl: string,
        filters: NuclenFilterValues,
): Promise<PostsCountResponse | null> {
        const formData = new FormData();
        formData.append('action', 'nuclen_get_posts_count');
        if (window.nuclenAjax?.nonce) {
                formData.append('security', window.nuclenAjax.nonce);
        }
        nuclenAppendFilters(formData, filters);
        const result = await nuclenFetchWithRetry<PostsCountResponse>(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
        });
        return result.ok ? (result.data as PostsCountResponse) : null;
}

async function updateCreditsInfo(elements: GeneratePageElements, count: number): Promise<void> {
        try {
                const remainingCredits = await nuclenCheckCreditsAjax();
                const neededCredits = count;
                if (elements.creditsInfoEl) {
                        elements.creditsInfoEl.textContent = `This will consume ${neededCredits} credit(s). You have ${remainingCredits} left.`;
                }
                if (remainingCredits < neededCredits) {
                        displayError('Not enough credits. Please top up or reduce the number of posts.');
                        if (elements.submitBtn) {
                                elements.submitBtn.disabled = true;
                        }
                } else if (elements.submitBtn) {
                        nuclenShowElement(elements.submitBtn);
                        elements.submitBtn.disabled = false;
                }
        } catch (err: unknown) {
                logger.error('Error fetching remaining credits:', err);
                const message = err instanceof Error ? err.message : 'Unknown error';
                if (elements.creditsInfoEl) {
                        elements.creditsInfoEl.textContent = `Unable to retrieve your credits: ${message}`;
                }
                if (elements.submitBtn) {
                        elements.submitBtn.disabled = false;
                }
        }
}

export function initStep1(elements: GeneratePageElements): void {
        elements.getPostsBtn?.addEventListener('click', async () => {
                if (!window.nuclenAjax || !window.nuclenAjax.ajax_url) {
                        displayError('Error: Ajax is not configured properly. Please check the plugin settings.');
                        return;
                }
                if (elements.postsCountEl) {
                        elements.postsCountEl.innerText = 'Loading posts...';
                }
                const filters: NuclenFilterValues = nuclenCollectFilters();
                const data = await requestPostsCount(window.nuclenAjax.ajax_url || '', filters);
                if (!data) {
                        logger.error('Error retrieving post count');
                        if (elements.postsCountEl) {
                                elements.postsCountEl.innerText = 'Error retrieving post count.';
                        }
                        return;
                }
                if (!data.success) {
                        if (elements.postsCountEl) {
                                elements.postsCountEl.innerText = 'Error retrieving post count.';
                        }
		const errMsg = data.message || data.data?.message;
		if (errMsg) {
		if (errMsg.includes('Invalid API key')) {
			displayError('Your Gold Code (API key) is invalid. Please create a new one on the NE app and enter it on the plugin Setup page.');
		} else if (errMsg.includes('Invalid WP App Password')) {
			displayError('Your WP App Password is invalid. Please go to the plugin Setup page and re-generate it.');
		} else {
			displayError(errMsg);
		}
		}
		return;
	}
	const count = data.data.count as number;
	const foundPosts = data.data.post_ids;
	const selectedPostIdsEl = document.getElementById('nuclen_selected_post_ids') as HTMLInputElement | null;
	if (selectedPostIdsEl) {
		selectedPostIdsEl.value = JSON.stringify(foundPosts);
	}
	nuclenStoreFilters(filters);
	nuclenHideElement(elements.step1);
	nuclenShowElement(elements.step2);
	nuclenUpdateProgressBarStep(elements.stepBar1, 'done');
	nuclenUpdateProgressBarStep(elements.stepBar2, 'current');
	if (count === 0) {
		if (elements.postsCountEl) {
		elements.postsCountEl.innerText = 'No posts found with these filters.';
		}
		if (elements.submitBtn) {
		nuclenHideElement(elements.submitBtn);
		}
		return;
	}
	if (elements.postsCountEl) {
		elements.postsCountEl.innerText = `Number of posts to process: ${count}`;
	}
        await updateCreditsInfo(elements, count);
        });
        }
