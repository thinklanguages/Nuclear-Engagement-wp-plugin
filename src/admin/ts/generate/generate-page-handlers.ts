import { getGeneratePageElements } from './elements';
import { initStep1 } from './step1';
import { initStep2 } from './step2';
import { initGoBack, initRestart, initSummaryToggle } from './navigation';

export function initGeneratePage(): void {
	const elements = getGeneratePageElements();

	// Initial UI setup - removed step 3 & 4
	if (elements.step1) elements.step1.classList.remove('nuclen-hidden');
	if (elements.step2) elements.step2.classList.add('nuclen-hidden');
	if (elements.updatesSection) elements.updatesSection.classList.add('nuclen-hidden');
	if (elements.restartBtn) elements.restartBtn.classList.add('nuclen-hidden');
	if (elements.stepBar1) elements.stepBar1.classList.add('ne-step-bar__step--current');
	if (elements.stepBar2) elements.stepBar2.classList.add('ne-step-bar__step--todo');

	initStep1(elements);
	initStep2(elements);
	initGoBack(elements);
	initRestart(elements);
	initSummaryToggle();
}
