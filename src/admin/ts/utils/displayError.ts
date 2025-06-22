export function displayError(message: string): void {
  // Decoupled error presentation. Swap alert for toasts if desired.
  alert(message);
}
