import { describe, it, expect, beforeEach } from 'vitest';
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
