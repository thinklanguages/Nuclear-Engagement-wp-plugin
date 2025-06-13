import { nuclenFetchWithRetry } from "./nuclen-admin-generate";
import { showElement, hideElement, updateProgressBarStep } from "./generate-page-utils";
import { checkCreditsAjax } from "./generate-check-credits";

export function initGetPosts(): void {
  const getPostsBtn = document.getElementById("nuclen-get-posts-btn") as HTMLButtonElement | null;
  if (!getPostsBtn) return;

  const postsCountEl = document.getElementById("nuclen-posts-count") as HTMLSpanElement | null;
  const step1 = document.getElementById("nuclen-step-1") as HTMLDivElement | null;
  const step2 = document.getElementById("nuclen-step-2") as HTMLDivElement | null;
  const submitBtn = document.getElementById("nuclen-submit-btn") as HTMLButtonElement | null;
  const stepBar1 = document.getElementById("nuclen-step-bar-1");
  const stepBar2 = document.getElementById("nuclen-step-bar-2");
  const creditsInfoEl = document.getElementById("nuclen-credits-info") as HTMLParagraphElement | null;

  getPostsBtn.addEventListener("click", () => {
    if (!(window as any).nuclenAjax || !(window as any).nuclenAjax.ajax_url) {
    }

    if (postsCountEl) {
      postsCountEl.innerText = "Loading posts...";
    }

    const formData = new FormData();
    formData.append("action", "nuclen_get_posts_count");
    if ((window as any).nuclenAjax?.nonce) {
      formData.append("security", (window as any).nuclenAjax.nonce);
    }

    const postStatusEl = document.getElementById("nuclen_post_status") as HTMLSelectElement | null;
    const categoryEl = document.getElementById("nuclen_category") as HTMLSelectElement | null;
    const authorEl = document.getElementById("nuclen_author") as HTMLSelectElement | null;
    const postTypeEl = document.getElementById("nuclen_post_type") as HTMLSelectElement | null;
    const workflowEl = document.getElementById("nuclen_generate_workflow") as HTMLSelectElement | null;
    const allowRegenEl = document.getElementById("nuclen_allow_regenerate_data") as HTMLInputElement | null;
    const protectRegenEl = document.getElementById("nuclen_regenerate_protected_data") as HTMLInputElement | null;

    formData.append("nuclen_post_status", postStatusEl ? postStatusEl.value : "");
    formData.append("nuclen_category", categoryEl ? categoryEl.value : "");
    formData.append("nuclen_author", authorEl ? authorEl.value : "");
    formData.append("nuclen_post_type", postTypeEl ? postTypeEl.value : "");
    formData.append("nuclen_generate_workflow", workflowEl ? workflowEl.value : "");
    formData.append("nuclen_allow_regenerate_data", allowRegenEl && allowRegenEl.checked ? "1" : "0");
    formData.append(
      "nuclen_regenerate_protected_data",
      protectRegenEl && protectRegenEl.checked ? "1" : "0"
    );

    nuclenFetchWithRetry((window as any).nuclenAjax.ajax_url || "", {
      method: "POST",
      body: formData,
      credentials: "same-origin",
    })
      .then((r) => r.json())
      .then(async (data) => {
        if (!data.success) {
          if (postsCountEl) {
            postsCountEl.innerText = "Error retrieving post count.";
          }
          if (data.data?.message) {
            if (data.data.message.includes("Invalid API key")) {
              alert(
                "Your Gold Code (API key) is invalid. Please create a new one on the NE app and enter it on the plugin Setup page."
              );
            } else if (data.data.message.includes("Invalid WP App Password")) {
              alert(
                "Your WP App Password is invalid. Please go to the plugin Setup page and re-generate it."
              );
            } else {
              alert(data.data.message);
            }
          }
          return;
        }

        const count = data.data.count as number;
        const foundPosts = data.data.post_ids;

        const selectedPostIdsEl = document.getElementById(
          "nuclen_selected_post_ids"
        ) as HTMLInputElement | null;
        if (selectedPostIdsEl) {
          selectedPostIdsEl.value = JSON.stringify(foundPosts);
        }

        const workflowEl2 = document.getElementById(
          "nuclen_generate_workflow"
        ) as HTMLSelectElement | null;
        const selectedWorkflowEl = document.getElementById(
          "nuclen_selected_generate_workflow"
        ) as HTMLInputElement | null;
        if (selectedWorkflowEl && workflowEl2) {
          selectedWorkflowEl.value = workflowEl2.value;
        }

        const summaryFormatElStep1 = document.getElementById(
          "nuclen_summary_format"
        ) as HTMLSelectElement | null;
        const summaryLengthElStep1 = document.getElementById(
          "nuclen_summary_length"
        ) as HTMLSelectElement | null;
        const summaryNumberElStep1 = document.getElementById(
          "nuclen_summary_number_of_items"
        ) as HTMLSelectElement | null;

        const selectedSummaryFormatEl = document.getElementById(
          "nuclen_selected_summary_format"
        ) as HTMLInputElement | null;
        const selectedSummaryLengthEl = document.getElementById(
          "nuclen_selected_summary_length"
        ) as HTMLInputElement | null;
        const selectedSummaryNumberEl = document.getElementById(
          "nuclen_selected_summary_number_of_items"
        ) as HTMLInputElement | null;

        if (summaryFormatElStep1 && selectedSummaryFormatEl) {
          selectedSummaryFormatEl.value = summaryFormatElStep1.value;
        }
        if (summaryLengthElStep1 && selectedSummaryLengthEl) {
          selectedSummaryLengthEl.value = summaryLengthElStep1.value;
        }
        if (summaryNumberElStep1 && selectedSummaryNumberEl) {
          selectedSummaryNumberEl.value = summaryNumberElStep1.value;
        }

        const selectedPostStatusEl = document.getElementById(
          "nuclen_selected_post_status"
        ) as HTMLInputElement | null;
        if (selectedPostStatusEl && postStatusEl) {
          selectedPostStatusEl.value = postStatusEl.value;
        }
        const selectedPostTypeEl = document.getElementById(
          "nuclen_selected_post_type"
        ) as HTMLInputElement | null;
        if (selectedPostTypeEl && postTypeEl) {
          selectedPostTypeEl.value = postTypeEl.value;
        }

        hideElement(step1);
        showElement(step2);
        updateProgressBarStep(stepBar1, "done");
        updateProgressBarStep(stepBar2, "current");

        if (count === 0) {
          if (postsCountEl) {
            postsCountEl.innerText = "No posts found with these filters.";
          }
          if (submitBtn) {
            hideElement(submitBtn);
          }
          return;
        }

        if (postsCountEl) {
          postsCountEl.innerText = `Number of posts to process: ${count}`;
        }

        try {
          const remainingCredits = await checkCreditsAjax();
          const neededCredits = count;

          if (creditsInfoEl) {
            creditsInfoEl.textContent = `This will consume ${neededCredits} credit(s). You have ${remainingCredits} left.`;
          }

          if (remainingCredits < neededCredits) {
            alert("Not enough credits. Please top up or reduce the number of posts.");
            if (submitBtn) {
              submitBtn.disabled = true;
            }
          } else {
            if (submitBtn) {
              showElement(submitBtn);
              submitBtn.disabled = false;
            }
          }
        } catch (err: any) {
          console.error("Error fetching remaining credits:", err);
          if (creditsInfoEl) {
            creditsInfoEl.textContent = `Unable to retrieve your credits: ${err.message}`;
          }
          if (submitBtn) {
            submitBtn.disabled = false;
          }
        }
      })
      .catch((error) => {
        console.error("Error retrieving post count:", error);
        if (postsCountEl) {
          postsCountEl.innerText = "Error retrieving post count. Please try again later.";
        }
      });
  });
}
