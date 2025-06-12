/*
 * Utility for displaying inline notifications.
 */
export type NuclenAlertType = 'success' | 'error' | 'info';

// Basic i18n wrapper using wp.i18n if available
const translate = (msg: string): string => {
  if (
    typeof wp !== 'undefined' &&
    wp.i18n &&
    typeof wp.i18n.__ === 'function'
  ) {
    return wp.i18n.__(msg, 'nuclear-engagement');
  }
  return msg;
};

function getContainer(parent?: HTMLElement | null): HTMLElement | null {
  if (parent) return parent;
  return (
    document.querySelector('.nuclen-container') ||
    document.body
  );
}

export function nuclenNotify(
  message: string,
  type: NuclenAlertType = 'info',
  parent?: HTMLElement | null
): void {
  const container = getContainer(parent);
  if (!container) return;
  const div = document.createElement('div');
  div.className = `nuclen-alert nuclen-alert-${type}`;
  div.textContent = translate(message);
  container.prepend(div);
  setTimeout(() => div.remove(), 8000);
}

export const nuclenNotifyError = (
  msg: string,
  parent?: HTMLElement | null
): void => {
  nuclenNotify(msg, 'error', parent);
};

export const nuclenNotifySuccess = (
  msg: string,
  parent?: HTMLElement | null
): void => {
  nuclenNotify(msg, 'success', parent);
};

export const nuclenNotifyInfo = (
  msg: string,
  parent?: HTMLElement | null
): void => {
  nuclenNotify(msg, 'info', parent);
};
