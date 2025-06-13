export function showElement(el: HTMLElement | null): void {
  if (el) {
    el.classList.remove("nuclen-hidden");
  }
}

export function hideElement(el: HTMLElement | null): void {
  if (el) {
    el.classList.add("nuclen-hidden");
  }
}

export function updateProgressBarStep(el: HTMLElement | null, state: string): void {
  if (!el) return;
  el.classList.remove(
    "nuclen-step-todo",
    "nuclen-step-current",
    "nuclen-step-done",
    "nuclen-step-failed"
  );
  el.classList.add(`nuclen-step-${state}`);
}
