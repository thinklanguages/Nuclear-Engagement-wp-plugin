import { NuclenStartGeneration, NuclenPollAndPullUpdates } from "./nuclen-admin-generate";
import { showElement, hideElement, updateProgressBarStep } from "./generate-page-utils";

export function initProcessHandlers(): void {
  const step1 = document.getElementById("nuclen-step-1") as HTMLDivElement | null;
  const step2 = document.getElementById("nuclen-step-2") as HTMLDivElement | null;
  const updatesSection = document.getElementById("nuclen-updates-section") as HTMLDivElement | null;
  const updatesContent = document.getElementById("nuclen-updates-content") as HTMLDivElement | null;
  const restartBtn = document.getElementById("nuclen-restart-btn") as HTMLButtonElement | null;
  const goBackBtn = document.getElementById("nuclen-go-back-btn") as HTMLButtonElement | null;
  const generateForm = document.getElementById("nuclen-generate-form") as HTMLFormElement | null;
  const submitBtn = document.getElementById("nuclen-submit-btn") as HTMLButtonElement | null;
  const stepBar1 = document.getElementById("nuclen-step-bar-1");
  const stepBar2 = document.getElementById("nuclen-step-bar-2");
  const stepBar3 = document.getElementById("nuclen-step-bar-3");
  const stepBar4 = document.getElementById("nuclen-step-bar-4");

  goBackBtn?.addEventListener("click", () => {
    hideElement(step2);
    showElement(step1);
    if (submitBtn) {
      submitBtn.disabled = false;
    }
    updateProgressBarStep(stepBar1, "current");
    updateProgressBarStep(stepBar2, "todo");
  });

  generateForm?.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!(window as any).nuclenAdminVars || !(window as any).nuclenAdminVars.ajax_url) {
      alert("Error: WP Ajax config not found. Please check the plugin settings.");
      return;
    }

    updateProgressBarStep(stepBar2, "done");
    updateProgressBarStep(stepBar3, "current");

    showElement(updatesSection);
    if (updatesContent) {
      updatesContent.innerText =
        "Processing posts... Do NOT leave this page until the process is complete.";
    }
    hideElement(step2);

    if (submitBtn) {
      submitBtn.disabled = true;
    }

    try {
      const formDataObj = Object.fromEntries(new FormData(generateForm).entries());
      const startResp = await NuclenStartGeneration(formDataObj);
      const generationId =
        startResp.data?.generation_id ||
        startResp.generation_id ||
        "gen_" + Math.random().toString(36).substring(2);

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
          updateProgressBarStep(stepBar3, "done");
          updateProgressBarStep(stepBar4, "current");
          if (results && typeof results === "object") {
            try {
              const payload = { workflow, results };
              const storeResp = await fetch((window as any).nuclenAdminVars.rest_receive_content || "/wp-json/nuclear-engagement/v1/receive-content", {
                method: "POST",
                headers: {
                  "Content-Type": "application/json",
                  "X-WP-Nonce": (window as any).nuclenAdminVars.rest_nonce || "",
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
          if (updatesContent) {
            if (failCount && finalReport) {
              updatesContent.innerText = `Some posts failed. ${finalReport.message || ""}`;
              updateProgressBarStep(stepBar4, "failed");
            } else {
              updatesContent.innerText =
                "All posts processed successfully! Your content has been saved.";
              updateProgressBarStep(stepBar4, "done");
            }
          }
          if (submitBtn) {
            submitBtn.disabled = false;
          }
          showElement(restartBtn);
        },
        onError: (errMsg: string) => {
          updateProgressBarStep(stepBar3, "failed");
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
          showElement(restartBtn);
        },
      });
    } catch (error: any) {
      updateProgressBarStep(stepBar3, "failed");
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
      showElement(restartBtn);
    }
  });

  restartBtn?.addEventListener("click", () => {
    hideElement(updatesSection);
    hideElement(restartBtn);
    hideElement(step2);
    showElement(step1);
    updateProgressBarStep(stepBar1, "current");
    updateProgressBarStep(stepBar2, "todo");
    updateProgressBarStep(stepBar3, "todo");
    updateProgressBarStep(stepBar4, "todo");
  });
}
