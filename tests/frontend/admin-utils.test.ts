import { describe, it, expect, beforeEach, vi } from 'vitest';
import { nuclenShowElement, nuclenHideElement, nuclenUpdateProgressBarStep, nuclenToggleSummaryFields } from '../../src/admin/ts/generate/generate-page-utils';

describe('nuclenShowElement & nuclenHideElement', () => {
  it('toggles nuclen-hidden class', () => {
	document.body.innerHTML = '<div id="el" class="nuclen-hidden"></div>';
	const el = document.getElementById('el') as HTMLElement;
	nuclenShowElement(el);
	expect(el.classList.contains('nuclen-hidden')).toBe(false);
	nuclenHideElement(el);
	expect(el.classList.contains('nuclen-hidden')).toBe(true);
  });

  it('scrolls to progress bar when showing step 2', () => {
	// Setup DOM with progress bar and step 2
	document.body.innerHTML = `
	  <div id="nuclen-progress-bar" style="position: absolute; top: 100px;"></div>
	  <div id="nuclen-step-2" class="nuclen-hidden"></div>
	`;
	
	const step2 = document.getElementById('nuclen-step-2') as HTMLElement;
	const progressBar = document.getElementById('nuclen-progress-bar') as HTMLElement;
	
	// Mock window.scrollTo
	const scrollToSpy = vi.spyOn(window, 'scrollTo').mockImplementation(() => {});
	
	// Mock getBoundingClientRect
	progressBar.getBoundingClientRect = vi.fn().mockReturnValue({
	  top: 100,
	  left: 0,
	  right: 0,
	  bottom: 0,
	  width: 0,
	  height: 0,
	  x: 0,
	  y: 0,
	  toJSON: () => ({})
	});
	
	// Show step 2
	nuclenShowElement(step2);
	
	// Verify scrollTo was called with correct parameters
	expect(scrollToSpy).toHaveBeenCalledWith({
	  top: 68, // 100 - 32 (offset for admin bar)
	  behavior: 'smooth'
	});
	
	// Cleanup
	scrollToSpy.mockRestore();
  });

  it('scrolls to top when showing step 2 without progress bar', () => {
	// Setup DOM without progress bar
	document.body.innerHTML = '<div id="nuclen-step-2" class="nuclen-hidden"></div>';
	
	const step2 = document.getElementById('nuclen-step-2') as HTMLElement;
	
	// Mock window.scrollTo
	const scrollToSpy = vi.spyOn(window, 'scrollTo').mockImplementation(() => {});
	
	// Show step 2
	nuclenShowElement(step2);
	
	// Verify scrollTo was called with fallback parameters
	expect(scrollToSpy).toHaveBeenCalledWith({
	  top: 0,
	  behavior: 'smooth'
	});
	
	// Cleanup
	scrollToSpy.mockRestore();
  });

  it('does not scroll when showing other elements', () => {
	document.body.innerHTML = '<div id="other-element" class="nuclen-hidden"></div>';
	
	const otherEl = document.getElementById('other-element') as HTMLElement;
	
	// Mock window.scrollTo
	const scrollToSpy = vi.spyOn(window, 'scrollTo').mockImplementation(() => {});
	
	// Show other element
	nuclenShowElement(otherEl);
	
	// Verify scrollTo was not called
	expect(scrollToSpy).not.toHaveBeenCalled();
	
	// Cleanup
	scrollToSpy.mockRestore();
  });
});

describe('nuclenUpdateProgressBarStep', () => {
  it('applies state class', () => {
	document.body.innerHTML = '<div id="step" class="ne-step-bar__step--todo"></div>';
	const el = document.getElementById('step') as HTMLElement;
	nuclenUpdateProgressBarStep(el, 'done');
	expect(el.className).toBe('ne-step-bar__step--done');
  });
});

describe('nuclenToggleSummaryFields', () => {
  beforeEach(() => {
	document.body.innerHTML = `
	  <select id="nuclen_generate_workflow">
		<option value="posts">Posts</option>
		<option value="summary">Summary</option>
	  </select>
	  <div id="nuclen-summary-settings" class="nuclen-hidden"></div>
	  <div id="nuclen-summary-paragraph-options" class="nuclen-hidden"></div>
	  <div id="nuclen-summary-bullet-options" class="nuclen-hidden"></div>
	  <select id="nuclen_summary_format">
		<option value="paragraph">Paragraph</option>
		<option value="bullet">Bullet</option>
	  </select>
	`;
  });

  it('shows paragraph options when workflow is summary and format paragraph', () => {
	(document.getElementById('nuclen_generate_workflow') as HTMLSelectElement).value = 'summary';
	(document.getElementById('nuclen_summary_format') as HTMLSelectElement).value = 'paragraph';
	nuclenToggleSummaryFields();
	expect(document.getElementById('nuclen-summary-settings')!.classList.contains('nuclen-hidden')).toBe(false);
	expect(document.getElementById('nuclen-summary-paragraph-options')!.classList.contains('nuclen-hidden')).toBe(false);
	expect(document.getElementById('nuclen-summary-bullet-options')!.classList.contains('nuclen-hidden')).toBe(true);
  });

  it('hides settings when workflow is posts', () => {
	(document.getElementById('nuclen_generate_workflow') as HTMLSelectElement).value = 'posts';
	nuclenToggleSummaryFields();
	expect(document.getElementById('nuclen-summary-settings')!.classList.contains('nuclen-hidden')).toBe(true);
  });
});
