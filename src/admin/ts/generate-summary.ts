import { showElement, hideElement } from "./generate-page-utils";

export function toggleSummaryFields(): void {
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
    showElement(summarySettingsEl);
    if (summaryFormatEl.value === "paragraph") {
      showElement(summaryParagraphOptions);
      hideElement(summaryBulletOptions);
    } else {
      hideElement(summaryParagraphOptions);
      showElement(summaryBulletOptions);
    }
  } else {
    hideElement(summarySettingsEl);
    hideElement(summaryParagraphOptions);
    hideElement(summaryBulletOptions);
  }
}

export function initSummaryFieldListeners(): void {
  toggleSummaryFields();
  const generateTypeEl = document.getElementById(
    "nuclen_generate_workflow"
  ) as HTMLSelectElement | null;
  const summaryFormatEl = document.getElementById(
    "nuclen_summary_format"
  ) as HTMLSelectElement | null;
  generateTypeEl?.addEventListener("change", toggleSummaryFields);
  summaryFormatEl?.addEventListener("change", toggleSummaryFields);
}
