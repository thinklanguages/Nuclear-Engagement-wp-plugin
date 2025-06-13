/**
 * file: nuclen-admin-generate-page.ts
 *
 * Bootstraps the multi-step Generate Content page.
 */
import { showElement, hideElement, updateProgressBarStep } from "./generate-page-utils";
import { initSummaryFieldListeners } from "./generate-summary";
import { initGetPosts } from "./generate-get-posts";
import { initProcessHandlers } from "./generate-process";

(function nuclenInitGeneratePageLogic() {
  const step1 = document.getElementById("nuclen-step-1") as HTMLDivElement | null;
  const step2 = document.getElementById("nuclen-step-2") as HTMLDivElement | null;
  const updatesSection = document.getElementById("nuclen-updates-section") as HTMLDivElement | null;
  const restartBtn = document.getElementById("nuclen-restart-btn") as HTMLButtonElement | null;
  const stepBar1 = document.getElementById("nuclen-step-bar-1");
  const stepBar2 = document.getElementById("nuclen-step-bar-2");
  const stepBar3 = document.getElementById("nuclen-step-bar-3");
  const stepBar4 = document.getElementById("nuclen-step-bar-4");

  showElement(step1);
  hideElement(step2);
  hideElement(updatesSection);
  hideElement(restartBtn);

  updateProgressBarStep(stepBar1, "current");
  updateProgressBarStep(stepBar2, "todo");
  updateProgressBarStep(stepBar3, "todo");
  updateProgressBarStep(stepBar4, "todo");

  initSummaryFieldListeners();
  initGetPosts();
  initProcessHandlers();
})();
