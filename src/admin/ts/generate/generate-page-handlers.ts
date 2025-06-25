import { getGeneratePageElements } from './elements';
import { initStep1 } from './step1';
import { initStep2 } from './step2';
import { initGoBack, initRestart, initSummaryToggle } from './navigation';

export function initGeneratePage(): void {
  const elements = getGeneratePageElements();

  // Initial UI setup
  elements.step1 && elements.step1.classList.remove('nuclen-hidden');
  elements.step2 && elements.step2.classList.add('nuclen-hidden');
  elements.updatesSection && elements.updatesSection.classList.add('nuclen-hidden');
  elements.restartBtn && elements.restartBtn.classList.add('nuclen-hidden');
  elements.stepBar1 && elements.stepBar1.classList.add('ne-step-bar__step--current');
  elements.stepBar2 && elements.stepBar2.classList.add('ne-step-bar__step--todo');
  elements.stepBar3 && elements.stepBar3.classList.add('ne-step-bar__step--todo');
  elements.stepBar4 && elements.stepBar4.classList.add('ne-step-bar__step--todo');

  initStep1(elements);
  initStep2(elements);
  initGoBack(elements);
  initRestart(elements);
  initSummaryToggle();
}
