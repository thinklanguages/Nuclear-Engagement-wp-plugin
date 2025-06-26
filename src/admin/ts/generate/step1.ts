import { nuclenFetchWithRetry } from '../nuclen-admin-generate';

interface PostsCountResponse {
  success: boolean;
  message?: string;
  data: { count: number; post_ids: number[] };
  code?: string;
}
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

export function initStep1(elements: GeneratePageElements): void {
  elements.getPostsBtn?.addEventListener('click', async () => {
    if (!window.nuclenAjax || !window.nuclenAjax.ajax_url) {
      displayError('Error: Ajax is not configured properly. Please check the plugin settings.');
      return;
    }
    if (elements.postsCountEl) {
      elements.postsCountEl.innerText = 'Loading posts...';
    }

    const formData = new FormData();
    formData.append('action', 'nuclen_get_posts_count');
    if (window.nuclenAjax?.nonce) {
      formData.append('security', window.nuclenAjax.nonce);
    }
    const filters: NuclenFilterValues = nuclenCollectFilters();
    nuclenAppendFilters(formData, filters);

    const result = await nuclenFetchWithRetry<PostsCountResponse>(window.nuclenAjax.ajax_url || '', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
    });
    if (!result.ok) {
      logger.error('Error retrieving post count:', result.error);
      if (elements.postsCountEl) {
        elements.postsCountEl.innerText = 'Error retrieving post count.';
      }
      return;
    }
    const data = result.data;
    if (!data || !data.success) {
      if (elements.postsCountEl) {
        elements.postsCountEl.innerText = 'Error retrieving post count.';
      }
    const errMsg = data?.message;
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
    const count = data.data.count;
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
      if (elements.creditsInfoEl) {
        const msg = err instanceof Error ? err.message : String(err);
        elements.creditsInfoEl.textContent = `Unable to retrieve your credits: ${msg}`;
      }
      if (elements.submitBtn) {
        elements.submitBtn.disabled = false;
      }
    }
    });
  }
