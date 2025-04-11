/**
 * nuclen-admin-single-generation.ts
 * This file handles "single-post" generation buttons in the post editor.
 */

import { NuclenStartGeneration, NuclenPollAndPullUpdates } from "./nuclen-admin-generate";

document.addEventListener("click", async (event: MouseEvent) => {
  const target = event.target;
  if (!(target instanceof HTMLElement)) return;

  // Build a subsite-friendly REST endpoint:
  // e.g.  /wp-json/nuclear-engagement/v1/receive-content
  const NUCLEN_REST_RECEIVE_CONTENT_ENDPOINT =
    (window as any).nuclenAdminVars?.rest_receive_content ||
    "/wp-json/nuclear-engagement/v1/receive-content";

  // Grab the REST nonce from nuclenAdminVars (generated via wp_create_nonce('wp_rest') in trait-admin-assets).
  const restNonce = (window as any).nuclenAdminVars?.rest_nonce || "";

  // Check if user clicked the "Generate <workflow>" button in the post editor meta box
  if (target.classList.contains("nuclen-generate-single")) {
    const btn = target as HTMLButtonElement;
    const postId = btn.dataset.postId;
    const workflow = btn.dataset.workflow;

    if (!postId || !workflow) {
      alert("Missing data attributes: postId or workflow not found.");
      return;
    }

    btn.disabled = true;
    btn.textContent = "Generating...";

    try {
      // 1) Start single generation (AJAX -> nuclen_handle_trigger_generation)
      //    We pass the post ID, the chosen workflow, etc.
      const startResp = await NuclenStartGeneration({
        nuclen_selected_post_ids: JSON.stringify([postId]),
        nuclen_selected_generate_workflow: workflow,
      });

      // 2) Extract generationId from the server's response
      const generationId =
        startResp.data?.generation_id ||
        startResp.generation_id ||
        "gen_" + Math.random().toString(36).substring(2);

      // 3) Poll progress using NuclenPollAndPullUpdates
      NuclenPollAndPullUpdates({
        intervalMs: 5000,
        generationId,
        onProgress() {
          btn.textContent = "Generating...";
        },
        onComplete: async ({ results, workflow }) => {
          // 4) Once complete, we store the final generated data in WP via our REST endpoint
          if (results && typeof results === "object") {
            try {
              const payload = { workflow, results };
              // Use restNonce & credentials: "include" to prevent rest_cookie_invalid_nonce
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
                console.log("Successfully stored single-generation results in WP:", storeData);

                // 5) If needed, we can auto-populate the meta box fields in the post editor
                const postResult = results[postId];
                if (postResult) {
                  if (workflow === "quiz") {
                    const { date, questions } = postResult;
                    const newDate =
                      storeData.finalDate && typeof storeData.finalDate === "string"
                        ? storeData.finalDate
                        : date;

                    // Update the "date" input in the quiz meta box
                    const dateField = document.querySelector<HTMLInputElement>(
                      'input[name="nuclen_quiz_data[date]"]'
                    );
                    if (dateField) {
                      dateField.readOnly = false;
                      dateField.value = newDate || "";
                      dateField.readOnly = true;
                    }

                    // Populate questions/answers/explanations
                    if (Array.isArray(questions)) {
                      questions.forEach((q, qIndex) => {
                        const questionSelector = `input[name="nuclen_quiz_data[questions][${qIndex}][question]"]`;
                        const questionInput =
                          document.querySelector<HTMLInputElement>(questionSelector);
                        if (questionInput) {
                          questionInput.value = q.question || "";
                        }

                        if (Array.isArray(q.answers)) {
                          q.answers.forEach((ans: string, aIndex: number) => {
                            const ansSelector = `input[name="nuclen_quiz_data[questions][${qIndex}][answers][${aIndex}]"]`;
                            const ansInput =
                              document.querySelector<HTMLInputElement>(ansSelector);
                            if (ansInput) {
                              ansInput.value = ans;
                            }
                          });
                        }

                        const explanationSelector = `textarea[name="nuclen_quiz_data[questions][${qIndex}][explanation]"]`;
                        const explanationTextarea =
                          document.querySelector<HTMLTextAreaElement>(explanationSelector);
                        if (explanationTextarea) {
                          explanationTextarea.value = q.explanation || "";
                        }
                      });
                    }

                    btn.textContent = "Stored!";
                  } else if (workflow === "summary") {
                    const { date, summary } = postResult;
                    const newDate =
                      storeData.finalDate && typeof storeData.finalDate === "string"
                        ? storeData.finalDate
                        : date;

                    // Update the "date" input in the summary meta box
                    const dateField = document.querySelector<HTMLInputElement>(
                      'input[name="nuclen_summary_data[date]"]'
                    );
                    if (dateField) {
                      dateField.readOnly = false;
                      dateField.value = newDate || "";
                      dateField.readOnly = true;
                    }

                    // Update the summary in the WP editor
                    if (typeof (window as any).tinymce !== "undefined") {
                      const editor = (window as any).tinymce.get("nuclen_summary_data_summary");
                      if (editor && typeof editor.setContent === "function") {
                        editor.setContent(summary || "");
                        editor.save();
                      } else {
                        // fallback to raw <textarea>
                        const summaryField = document.querySelector<HTMLTextAreaElement>(
                          'textarea[name="nuclen_summary_data[summary]"]'
                        );
                        if (summaryField) {
                          summaryField.value = summary || "";
                        }
                      }
                    } else {
                      // If no tinymce, just set the <textarea> directly
                      const summaryField = document.querySelector<HTMLTextAreaElement>(
                        'textarea[name="nuclen_summary_data[summary]"]'
                      );
                      if (summaryField) {
                        summaryField.value = summary || "";
                      }
                    }

                    btn.textContent = "Stored!";
                  }
                }
              } else {
                console.error("Error storing single-generation results in WP:", storeData);
                btn.textContent = "Generation failed!";
              }

              btn.disabled = false;
            } catch (err) {
              console.error("Fetch error calling /receive-content endpoint:", err);
              btn.textContent = "Generation failed!";
              btn.disabled = false;
            }
          } else {
            // No results or not an object
            btn.textContent = "Done (no data)!";
            btn.disabled = false;
          }
        },
        onError(errMsg) {
          if (errMsg.includes("Invalid API key")) {
            alert("Your API key is invalid. Please go to the Setup page and enter a new one.");
          } else if (errMsg.includes("Invalid WP App Password")) {
            alert("Your WP App Password is invalid. Please go to the Setup page and re-generate it.");
          } else if (errMsg.includes("Not enough credits")) {
            alert("Not enough credits for single-post generation.");
          } else {
            alert("Error: " + errMsg);
          }

          btn.textContent = "Generate";
          btn.disabled = false;
        },
      });
    } catch (err: any) {
      // If NuclenStartGeneration fails immediately
      if (err.message.includes("Invalid API key")) {
        alert("Your API key is invalid. Please go to the Setup page and enter a new one.");
      } else if (err.message.includes("Invalid WP App Password")) {
        alert("Your WP App Password is invalid. Please go to the Setup page and re-generate it.");
      } else if (err.message.includes("Not enough credits")) {
        alert("Not enough credits for single-post generation. Please top up first.");
      } else {
        alert("Error starting single generation: " + err.message);
      }
      btn.textContent = "Generate";
      btn.disabled = false;
    }
  }
});
