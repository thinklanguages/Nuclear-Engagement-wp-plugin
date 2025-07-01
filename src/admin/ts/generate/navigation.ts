import {
	nuclenShowElement,
	nuclenHideElement,
	nuclenUpdateProgressBarStep,
	nuclenToggleSummaryFields,
} from './generate-page-utils';
import type { GeneratePageElements } from './elements';

export function initGoBack(elements: GeneratePageElements): void {
	elements.goBackBtn?.addEventListener('click', () => {
		nuclenHideElement(elements.step2);
		nuclenShowElement(elements.step1);
		if (elements.postsCountEl) {
			elements.postsCountEl.innerText = '';
		}
		if (elements.creditsInfoEl) {
			elements.creditsInfoEl.textContent = '';
		}
		nuclenUpdateProgressBarStep(elements.stepBar1, 'current');
		nuclenUpdateProgressBarStep(elements.stepBar2, 'todo');
	});
}

export function initRestart(elements: GeneratePageElements): void {
	elements.restartBtn?.addEventListener('click', () => {
		nuclenHideElement(elements.updatesSection);
		nuclenHideElement(elements.restartBtn);
		nuclenHideElement(elements.step2);
		if (elements.postsCountEl) {
			elements.postsCountEl.innerText = '';
		}
		if (elements.creditsInfoEl) {
			elements.creditsInfoEl.textContent = '';
		}
		nuclenShowElement(elements.step1);
		nuclenUpdateProgressBarStep(elements.stepBar1, 'current');
		nuclenUpdateProgressBarStep(elements.stepBar2, 'todo');
		nuclenUpdateProgressBarStep(elements.stepBar3, 'todo');
		nuclenUpdateProgressBarStep(elements.stepBar4, 'todo');
	});
}

export function initSummaryToggle(): void {
	nuclenToggleSummaryFields();
	const generateTypeEl = document.getElementById('nuclen_generate_workflow') as HTMLSelectElement | null;
	const summaryFormatEl = document.getElementById('nuclen_summary_format') as HTMLSelectElement | null;
	generateTypeEl?.addEventListener('change', nuclenToggleSummaryFields);
	summaryFormatEl?.addEventListener('change', nuclenToggleSummaryFields);
}
