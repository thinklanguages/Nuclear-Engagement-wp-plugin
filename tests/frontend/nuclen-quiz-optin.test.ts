import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { buildOptinInlineHTML, mountOptinBeforeResults } from '../../src/front/ts/nuclen-quiz-optin';
import * as utils from '../../src/front/ts/nuclen-quiz-utils';
import type { OptinContext } from '../../src/front/ts/nuclen-quiz-types';

const baseCtx: OptinContext = {
  position: 'before_results',
  mandatory: false,
  promptText: 'Join us',
  submitLabel: 'Submit',
  enabled: true,
  webhook: '',
  ajaxUrl: '',
  ajaxNonce: '',
};

function createContainer() {
  document.body.innerHTML = '<div id="target"></div>';
  return document.getElementById('target') as HTMLElement;
}

afterEach(() => {
  vi.restoreAllMocks();
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  delete (global as any).alert;
});

describe('buildOptinInlineHTML', () => {
  it('returns expected HTML', () => {
	const html = buildOptinInlineHTML({
	  ...baseCtx,
	  position: 'with_results',
	});
	const expected = `
	<div id="nuclen-optin-container" class="nuclen-optin-with-results">
	<p class="nuclen-fg"><strong>Join us</strong></p>
	<label for="nuclen-optin-name"  class="nuclen-fg">Name</label>
	<input  type="text"  id="nuclen-optin-name">
	<label for="nuclen-optin-email" class="nuclen-fg">Email</label>
	<input  type="email" id="nuclen-optin-email" required>
	<button type="button" id="nuclen-optin-submit">Submit</button>
	</div>`;
	expect(html).toBe(expected);
  });
});

describe('mountOptinBeforeResults', () => {
  it('renders opt-in with skip link when not mandatory', () => {
	const container = createContainer();
	mountOptinBeforeResults(container, baseCtx, vi.fn(), vi.fn());
	expect(container.innerHTML).toContain('nuclen-optin-skip');
  });

  it('omits skip link when mandatory', () => {
	const container = createContainer();
	mountOptinBeforeResults(container, { ...baseCtx, mandatory: true }, vi.fn(), vi.fn());
	expect(container.innerHTML).not.toContain('nuclen-optin-skip');
  });

  it('handles submit success', async () => {
	const container = createContainer();
	const complete = vi.fn();
	const skip = vi.fn();
	vi.spyOn(utils, 'isValidEmail').mockReturnValue(true);
	vi.spyOn(utils, 'storeOptinLocally').mockResolvedValue();
	vi.spyOn(utils, 'submitToWebhook').mockResolvedValue();
	mountOptinBeforeResults(container, baseCtx, complete, skip);
	(document.getElementById('nuclen-optin-name') as HTMLInputElement).value = 'n';
	(document.getElementById('nuclen-optin-email') as HTMLInputElement).value = 'e@b.com';
	(document.getElementById('nuclen-optin-submit') as HTMLElement).click();
	
	// Wait for all async operations to complete
	await new Promise(resolve => setTimeout(resolve, 0));
	await Promise.resolve();
	await Promise.resolve();
	
	expect(utils.storeOptinLocally).toHaveBeenCalled();
	expect(utils.submitToWebhook).toHaveBeenCalled();
	expect(complete).toHaveBeenCalled();
	expect(skip).not.toHaveBeenCalled();
  });

  it('alerts on invalid email', () => {
	const container = createContainer();
	const alertMock = vi.fn();
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	(global as any).alert = alertMock;
	vi.spyOn(utils, 'storeOptinLocally').mockResolvedValue();
	vi.spyOn(utils, 'submitToWebhook').mockResolvedValue();
	vi.spyOn(utils, 'isValidEmail').mockReturnValue(false);
	vi.spyOn(utils, 'storeOptinLocally');
	vi.spyOn(utils, 'submitToWebhook');
	mountOptinBeforeResults(container, baseCtx, vi.fn(), vi.fn());
	(document.getElementById('nuclen-optin-submit') as HTMLElement).click();
	expect(alertMock).toHaveBeenCalled();
	expect(utils.storeOptinLocally).not.toHaveBeenCalled();
	expect(utils.submitToWebhook).not.toHaveBeenCalled();
  });

  it('calls skip callback', () => {
	const container = createContainer();
	const skip = vi.fn();
	mountOptinBeforeResults(container, baseCtx, vi.fn(), skip);
	(document.getElementById('nuclen-optin-skip') as HTMLElement).dispatchEvent(new Event('click', { bubbles: true }));
	expect(skip).toHaveBeenCalled();
  });
});
