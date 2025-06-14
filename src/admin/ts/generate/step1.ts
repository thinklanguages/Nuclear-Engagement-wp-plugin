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

export function initStep1(elements: GeneratePageElements): void {
  elements.getPostsBtn?.addEventListener('click', () => {
    if (!(window as any).nuclenAjax || !(window as any).nuclenAjax.ajax_url) {
      alert('Error: Ajax is not configured properly. Please check the plugin settings.');
      return;
    }
    if (elements.postsCountEl) {
      elements.postsCountEl.innerText = 'Loading posts...';
    }

    const formData = new FormData();
    formData.append('action', 'nuclen_get_posts_count');
    if ((window as any).nuclenAjax?.nonce) {
      formData.append('security', (window as any).nuclenAjax.nonce);
    }
    const filters: NuclenFilterValues = nuclenCollectFilters();
    nuclenAppendFilters(formData, filters);

    nuclenFetchWithRetry((window as any).nuclenAjax.ajax_url || '', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
    })
      .then((r) => r.json())
      .then(async (data) => {
        if (!data.success) {
          if (elements.postsCountEl) {
            elements.postsCountEl.innerText = 'Error retrieving post count.';
          }
          if (data.data?.message) {
            if (data.data.message.includes('Invalid API key')) {
              alert('Your Gold Code (API key) is invalid. Please create a new one on the NE app and enter it on the plugin Setup page.');
            } else if (data.data.message.includes('Invalid WP App Password')) {
              alert('Your WP App Password is invalid. Please go to the plugin Setup page and re-generate it.');
            } else {
              alert(data.data.message);
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
        try {
          const remainingCredits = await nuclenCheckCreditsAjax();
          const neededCredits = count;
          if (elements.creditsInfoEl) {
            elements.creditsInfoEl.textContent = `This will consume ${neededCredits} credit(s). You have ${remainingCredits} left.`;
          }
          if (remainingCredits < neededCredits) {
            alert('Not enough credits. Please top up or reduce the number of posts.');
            if (elements.submitBtn) {
              elements.submitBtn.disabled = true;
            }
          } else if (elements.submitBtn) {
            nuclenShowElement(elements.submitBtn);
            elements.submitBtn.disabled = false;
          }
        } catch (err: any) {
          console.error('Error fetching remaining credits:', err);
          if (elements.creditsInfoEl) {
            elements.creditsInfoEl.textContent = `Unable to retrieve your credits: ${err.message}`;
          }
          if (elements.submitBtn) {
            elements.submitBtn.disabled = false;
          }
        }
      })
      .catch((error) => {
        console.error('Error retrieving post count:', error);
        if (elements.postsCountEl) {
          elements.postsCountEl.innerText = 'Error retrieving post count. Please try again later.';
        }
      });
  });
}
