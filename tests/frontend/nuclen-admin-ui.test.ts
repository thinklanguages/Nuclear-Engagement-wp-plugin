import { describe, it, beforeEach, afterEach, expect, vi } from 'vitest';

beforeEach(() => {
  vi.resetModules();
  document.body.innerHTML = `
	<a href="#tab1" class="nav-tab nav-tab-active" id="tab1-link">Tab1</a>
	<a href="#tab2" class="nav-tab" id="tab2-link">Tab2</a>
	<div id="tab1" class="nuclen-tab-content">One</div>
	<div id="tab2" class="nuclen-tab-content" style="display:none">Two</div>
	<div id="nuclen-custom-theme-section" class="nuclen-hidden"></div>
	<input type="radio" name="nuclen_theme" value="default" id="theme-default" checked>
	<input type="radio" name="nuclen_theme" value="custom" id="theme-custom">
  `;
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('nuclen-admin-ui tabs', () => {
  it('toggles active tab and content', async () => {
	await import('../../src/admin/ts/nuclen-admin-ui');
	document.dispatchEvent(new Event('DOMContentLoaded'));

	const tab1 = document.getElementById('tab1-link')!;
	const tab2 = document.getElementById('tab2-link')!;
	const content1 = document.getElementById('tab1')!;
	const content2 = document.getElementById('tab2')!;

	tab2.dispatchEvent(new MouseEvent('click', { bubbles: true }));

	expect(tab1.classList.contains('nav-tab-active')).toBe(false);
	expect(tab2.classList.contains('nav-tab-active')).toBe(true);
	expect(content1.style.display).toBe('none');
	expect(content2.style.display).toBe('block');
  });
});

describe('nuclen-admin-ui custom theme', () => {
  it('shows and hides custom theme section', async () => {
	await import('../../src/admin/ts/nuclen-admin-ui');
	document.dispatchEvent(new Event('DOMContentLoaded'));

	const customSection = document.getElementById('nuclen-custom-theme-section')!;
	const defaultRadio = document.getElementById('theme-default') as HTMLInputElement;
	const customRadio = document.getElementById('theme-custom') as HTMLInputElement;

	expect(customSection.classList.contains('nuclen-hidden')).toBe(true);

	customRadio.checked = true;
	customRadio.dispatchEvent(new Event('change', { bubbles: true }));
	expect(customSection.classList.contains('nuclen-hidden')).toBe(false);

	defaultRadio.checked = true;
	defaultRadio.dispatchEvent(new Event('change', { bubbles: true }));
	expect(customSection.classList.contains('nuclen-hidden')).toBe(true);
  });
});

