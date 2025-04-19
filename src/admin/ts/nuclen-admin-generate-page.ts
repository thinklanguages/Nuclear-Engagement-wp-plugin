/**
 * file: nuclen-admin-generate-page.ts
 *
 * This file wires up the "Generate content" page in the WordPress admin (the multi-step form).
 */
import {
  NuclenStartGeneration,
  NuclenPollAndPullUpdates,
  nuclenFetchWithRetry,
} from "./nuclen-admin-generate";

(function nuclenInitGeneratePageLogic() {
  const step1 = document.getElementById("nuclen-step-1") as HTMLDivElement | null;
  const step2 = document.getElementById("nuclen-step-2") as HTMLDivElement | null;
  const updatesSection = document.getElementById("nuclen-updates-section") as HTMLDivElement | null;
  const updatesContent = document.getElementById("nuclen-updates-content") as HTMLDivElement | null;
  const restartBtn = document.getElementById("nuclen-restart-btn") as HTMLButtonElement | null;
  const getPostsBtn = document.getElementById("nuclen-get-posts-btn") as HTMLButtonElement | null;
  const goBackBtn = document.getElementById("nuclen-go-back-btn") as HTMLButtonElement | null;
  const generateForm = document.getElementById("nuclen-generate-form") as HTMLFormElement | null;
  const submitBtn = document.getElementById("nuclen-submit-btn") as HTMLButtonElement | null;
  const postsCountEl = document.getElementById("nuclen-posts-count") as HTMLSpanElement | null;

  const stepBar1 = document.getElementById("nuclen-step-bar-1");
  const stepBar2 = document.getElementById("nuclen-step-bar-2");
  const stepBar3 = document.getElementById("nuclen-step-bar-3");
  const stepBar4 = document.getElementById("nuclen-step-bar-4");

  const creditsInfoEl = document.getElementById("nuclen-credits-info") as HTMLParagraphElement | null;

  /**
   * For storing the final results to WP via REST, just like single-post generation does:
   */
  const NUCLEN_REST_RECEIVE_CONTENT_ENDPOINT =
    (window as any).nuclenAdminVars?.rest_receive_content ||
    "/wp-json/nuclear-engagement/v1/receive-content";
  const restNonce = (window as any).nuclenAdminVars?.rest_nonce || "";

  function nuclenShowElement(el: HTMLElement | null) {
    if (!el) return;
    el.classList.remove("nuclen-hidden");
  }
  function nuclenHideElement(el: HTMLElement | null) {
    if (!el) return;
    el.classList.add("nuclen-hidden");
  }

  function nuclenUpdateProgressBarStep(stepEl: HTMLElement | null, state: string) {
    if (!stepEl) return;
    stepEl.classList.remove(
      "nuclen-step-todo",
      "nuclen-step-current",
      "nuclen-step-done",
      "nuclen-step-failed"
    );
    stepEl.classList.add(`nuclen-step-${state}`);
  }

  // Initial UI states
  nuclenShowElement(step1);
  nuclenHideElement(step2);
  nuclenHideElement(updatesSection);
  nuclenHideElement(restartBtn);

  nuclenUpdateProgressBarStep(stepBar1, "current");
  nuclenUpdateProgressBarStep(stepBar2, "todo");
  nuclenUpdateProgressBarStep(stepBar3, "todo");
  nuclenUpdateProgressBarStep(stepBar4, "todo");

  /**
   * Helper to fetch the user’s remaining credits from the plugin’s "nuclen_fetch_app_updates"
   * passing no generation_id so the SaaS returns { remaining_credits }.
   */
  async function nuclenCheckCreditsAjax(): Promise<number> {
    if (!(window as any).nuclenAjax || !(window as any).nuclenAjax.ajax_url) {
      throw new Error("Missing nuclenAjax configuration (ajax_url).");
    }
    if (!(window as any).nuclenAjax.fetch_action) {
      throw new Error("Missing fetch_action in nuclenAjax configuration.");
    }

    const formData = new FormData();
    formData.append("action", (window as any).nuclenAjax.fetch_action);
    if ((window as any).nuclenAjax.nonce) {
      formData.append("security", (window as any).nuclenAjax.nonce);
    }
    // Omit generation_id to let the SaaS interpret "just return credits"
    // This must be supported on your SaaS side

    const response = await nuclenFetchWithRetry((window as any).nuclenAjax.ajax_url, {
      method: "POST",
      body: formData,
      credentials: "same-origin",
    });

    const data = await response.json();
    if (!data.success) {
      throw new Error(data.data?.message || "Failed to fetch credits from SaaS");
    }
    // data is wrapped in { success, data: { remaining_credits } }
    if (typeof data.data.remaining_credits === "number") {
      return data.data.remaining_credits;
    }
    throw new Error("No 'remaining_credits' in response");
  }

  // Step 1 => "Get Posts"
  getPostsBtn?.addEventListener("click", () => {
    if (!(window as any).nuclenAjax || !(window as any).nuclenAjax.ajax_url) {
      alert("Error: Ajax is not configured properly. Please check the plugin settings.");
      return;
    }

    if (postsCountEl) {
      postsCountEl.innerText = "Loading posts...";
    }

    // Build formData from Step 1 fields
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

        // data.data.count => # of posts
        const count = data.data.count as number;
        const foundPosts = data.data.post_ids;

        // Store found post IDs in a hidden field
        const selectedPostIdsEl = document.getElementById(
          "nuclen_selected_post_ids"
        ) as HTMLInputElement | null;
        if (selectedPostIdsEl) {
          selectedPostIdsEl.value = JSON.stringify(foundPosts);
        }

        // Copy workflow selection to hidden field
        const workflowEl2 = document.getElementById(
          "nuclen_generate_workflow"
        ) as HTMLSelectElement | null;
        const selectedWorkflowEl = document.getElementById(
          "nuclen_selected_generate_workflow"
        ) as HTMLInputElement | null;
        if (selectedWorkflowEl && workflowEl2) {
          selectedWorkflowEl.value = workflowEl2.value;
        }

        // Copy summary params
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

        // Also copy post_status and post_type
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

        // Transition UI from Step 1 -> Step 2
        nuclenHideElement(step1);
        nuclenShowElement(step2);
        nuclenUpdateProgressBarStep(stepBar1, "done");
        nuclenUpdateProgressBarStep(stepBar2, "current");

        if (count === 0) {
          if (postsCountEl) {
            postsCountEl.innerText = "No posts found with these filters.";
          }
          if (submitBtn) {
            nuclenHideElement(submitBtn);
          }
          return;
        }

        if (postsCountEl) {
          postsCountEl.innerText = `Number of posts to process: ${count}`;
        }

        // Now check how many credits user has
        try {
          const remainingCredits = await nuclenCheckCreditsAjax();
          const neededCredits = count;

          if (creditsInfoEl) {
            creditsInfoEl.textContent = `This will consume ${neededCredits} credit(s). You have ${remainingCredits} left.`;
          }

          if (remainingCredits < neededCredits) {
            alert("Not enough credits. Please top up or reduce the number of posts.");
            // disable the “Generate” button
            if (submitBtn) {
              submitBtn.disabled = true;
            }
          } else {
            if (submitBtn) {
              nuclenShowElement(submitBtn);
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

  // Step 2 -> "Go Back"
  goBackBtn?.addEventListener("click", () => {
    nuclenHideElement(step2);
    nuclenShowElement(step1);

    if (postsCountEl) {
      postsCountEl.innerText = "";
    }
    if (creditsInfoEl) {
      creditsInfoEl.textContent = "";
    }

    nuclenUpdateProgressBarStep(stepBar1, "current");
    nuclenUpdateProgressBarStep(stepBar2, "todo");
  });

  // Step 2 -> "Generate"
  generateForm?.addEventListener("submit", async (event) => {
    event.preventDefault();

    if (!(window as any).nuclenAdminVars || !(window as any).nuclenAdminVars.ajax_url) {
      alert("Error: WP Ajax config not found. Please check the plugin settings.");
      return;
    }

    nuclenUpdateProgressBarStep(stepBar2, "done");
    nuclenUpdateProgressBarStep(stepBar3, "current");

    nuclenShowElement(updatesSection);
    if (updatesContent) {
      updatesContent.innerText =
        "Processing posts... Do NOT leave this page until the process is complete.";
    }
    nuclenHideElement(step2);

    if (submitBtn) {
      submitBtn.disabled = true;
    }

    try {
      // 1) Start generation
      const formDataObj = Object.fromEntries(new FormData(generateForm).entries());
      const startResp = await NuclenStartGeneration(formDataObj);

      // 2) Retrieve generationId
      const generationId =
        startResp.data?.generation_id ||
        startResp.generation_id ||
        "gen_" + Math.random().toString(36).substring(2);

      // 3) Poll progress
      NuclenPollAndPullUpdates({
        intervalMs: 5000,
        generationId,
        onProgress: (processed, total) => {
          const safeProcessed = processed === undefined ? 0 : processed;
          const safeTotal = total === undefined ? "" : total;
          if (updatesContent) {
            updatesContent.innerText = `Processed ${safeProcessed} of ${safeTotal} posts so far...`;
          }
        },

        onComplete: async ({ failCount, finalReport, results, workflow }) => {
          nuclenUpdateProgressBarStep(stepBar3, "done");
          nuclenUpdateProgressBarStep(stepBar4, "current");

          // Store final results in WP (same method single-post uses) ===
          if (results && typeof results === "object") {
            try {
              const payload = { workflow, results };
              const storeResp = await fetch(NUCLEN_REST_RECEIVE_CONTENT_ENDPOINT, {
                method: "POST",
                headers: {
                  "Content-Type": "application/json",
                  "X-WP-Nonce": restNonce,
                },
                credentials: "include",
                body: JSON.stringify(payload),
              });
              const storeData = await storeResp.json();

              if (storeResp.ok && !storeData.code) {
                console.log("Bulk content stored in WP meta successfully:", storeData);
              } else {
                console.error("Error storing bulk content in WP meta:", storeData);
              }
            } catch (err) {
              console.error("Error storing bulk content in WP meta:", err);
            }
          }
          // === End NEW CODE ===

          if (updatesContent) {
            if (failCount && finalReport) {
              updatesContent.innerText = `Some posts failed. ${finalReport.message || ""}`;
              nuclenUpdateProgressBarStep(stepBar4, "failed");
            } else {
              updatesContent.innerText =
                "All posts processed successfully! Your content has been saved.";
              nuclenUpdateProgressBarStep(stepBar4, "done");
            }
          }
          if (submitBtn) {
            submitBtn.disabled = false;
          }
          nuclenShowElement(restartBtn);
        },
        onError: (errMsg: string) => {
          nuclenUpdateProgressBarStep(stepBar3, "failed");

          if (errMsg.includes("Invalid API key")) {
            alert("Your API key is invalid. Please go to the Setup page and enter a new one.");
          } else if (errMsg.includes("Invalid WP App Password")) {
            alert("Your WP App Password is invalid. Please re-generate it on the Setup page.");
          } else if (errMsg.includes("Not enough credits")) {
            alert("Not enough credits. Please top up your account or reduce the number of posts.");
          } else {
            alert(`Error: ${errMsg}`);
          }

          if (updatesContent) {
            updatesContent.innerText = `Error: ${errMsg}`;
          }
          if (submitBtn) {
            submitBtn.disabled = false;
          }
          nuclenShowElement(restartBtn);
        },
      });
    } catch (error: any) {
      nuclenUpdateProgressBarStep(stepBar3, "failed");

      if (error.message.includes("Invalid API key")) {
        alert("Your API key is invalid. Please go to the Setup page and enter a new one.");
      } else if (error.message.includes("Invalid WP App Password")) {
        alert("Your WP App Password is invalid. Please go to the Setup page and re-generate it.");
      } else if (error.message.includes("Not enough credits")) {
        alert("Not enough credits. Please top up or reduce posts.");
      } else {
        alert(`Error starting generation: ${error.message}`);
      }

      if (submitBtn) {
        submitBtn.disabled = false;
      }
      nuclenShowElement(restartBtn);
    }
  });

  function nuclenToggleSummaryFields() {
    const generateTypeEl = document.getElementById(
      "nuclen_generate_workflow"
    ) as HTMLSelectElement | null;
    const summarySettingsEl = document.getElementById(
      "nuclen-summary-settings"
    ) as HTMLDivElement | null;
    const summaryParagraphOptions = document.getElementById(
      "nuclen-summary-paragraph-options"
    ) as HTMLDivElement | null;
    const summaryBulletOptions = document.getElementById(
      "nuclen-summary-bullet-options"
    ) as HTMLDivElement | null;
    const summaryFormatEl = document.getElementById(
      "nuclen_summary_format"
    ) as HTMLSelectElement | null;

    if (
      !generateTypeEl ||
      !summarySettingsEl ||
      !summaryParagraphOptions ||
      !summaryBulletOptions ||
      !summaryFormatEl
    ) {
      return;
    }
    if (generateTypeEl.value === "summary") {
      summarySettingsEl.classList.remove("nuclen-hidden");
      if (summaryFormatEl.value === "paragraph") {
        summaryParagraphOptions.classList.remove("nuclen-hidden");
        summaryBulletOptions.classList.add("nuclen-hidden");
      } else {
        summaryParagraphOptions.classList.add("nuclen-hidden");
        summaryBulletOptions.classList.remove("nuclen-hidden");
      }
    } else {
      summarySettingsEl.classList.add("nuclen-hidden");
      summaryParagraphOptions.classList.add("nuclen-hidden");
      summaryBulletOptions.classList.add("nuclen-hidden");
    }
  }

  nuclenToggleSummaryFields();

  const generateTypeEl = document.getElementById(
    "nuclen_generate_workflow"
  ) as HTMLSelectElement | null;
  const summaryFormatEl = document.getElementById(
    "nuclen_summary_format"
  ) as HTMLSelectElement | null;

  generateTypeEl?.addEventListener("change", nuclenToggleSummaryFields);
  summaryFormatEl?.addEventListener("change", nuclenToggleSummaryFields);

  // "Restart" button
  restartBtn?.addEventListener("click", () => {
    nuclenHideElement(updatesSection);
    nuclenHideElement(restartBtn);
    nuclenHideElement(step2);

    if (postsCountEl) {
      postsCountEl.innerText = "";
    }
    if (creditsInfoEl) {
      creditsInfoEl.textContent = "";
    }

    nuclenShowElement(step1);

    nuclenUpdateProgressBarStep(stepBar1, "current");
    nuclenUpdateProgressBarStep(stepBar2, "todo");
    nuclenUpdateProgressBarStep(stepBar3, "todo");
    nuclenUpdateProgressBarStep(stepBar4, "todo");
  });
})();
