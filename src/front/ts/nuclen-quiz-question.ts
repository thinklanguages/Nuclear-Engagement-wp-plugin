// src/front/ts/nuclen-quiz-question.ts

import type { QuizQuestion } from './nuclen-quiz-types';
import { shuffle, escapeHtml } from './nuclen-quiz-utils';
import type { QuizUIRefs, QuizState } from './nuclen-quiz-results';
import { updateProgress } from './nuclen-quiz-progress';

export function renderQuestion(
	questions: QuizQuestion[],
	state: QuizState,
	ui: QuizUIRefs,
	checkAnswer: (origIdx: number, shuffledIdx: number, correctIdx: number) => void,
): void {
	const { qContainer, aContainer, explContainer, nextBtn, progBar } = ui;
	const q = questions[state.currIdx];

	qContainer.innerHTML = `
	<div id="nuclen-quiz-question-number" aria-live="polite" aria-atomic="true">
		${state.currIdx + 1}/${questions.length}
	</div>
	<div class="nuclen-quiz-title" role="heading" aria-level="2">${escapeHtml(q.question)}</div>`;

	const shuffled = shuffle(
	q.answers.map((ans, idx) => ({ ans, idx })).filter((a) => a.ans.trim()),
	);

	aContainer.innerHTML = shuffled
	.map(
		(a, i) => `
		<button
			class="nuclen-quiz-answer-button nuclen-quiz-possible-answer"
			data-orig-idx="${a.idx}"
			tabindex="0"
			aria-label="Answer ${i + 1}: ${escapeHtml(a.ans)}"
		>${escapeHtml(a.ans)}</button>`,
	)
	.join('');

	const correctIdx = shuffled.findIndex((a) => a.idx === 0);

	/* reset */
	explContainer.innerHTML = '';
	explContainer.classList.add('nuclen-quiz-hidden');
	nextBtn.classList.add('nuclen-quiz-hidden');
	updateProgress(progBar, state.currIdx, questions.length);

	/* one-shot answer handler */
	const handler = (el: HTMLElement) => {
	const origIdx = parseInt(el.getAttribute('data-orig-idx') || '0', 10);
	checkAnswer(origIdx, shuffled.findIndex((a) => a.idx === origIdx), correctIdx);
	aContainer.removeEventListener('click', clickListener);
	aContainer.removeEventListener('keydown', keyListener);
	};

	const clickListener = (e: Event) => {
	const el = e.target as HTMLElement;
	if (!el.matches('button.nuclen-quiz-answer-button')) return;
	handler(el);
	};
	const keyListener = (e: KeyboardEvent) => {
	const el = e.target as HTMLElement;
	if (!el.matches('button.nuclen-quiz-answer-button')) return;
	if (e.key === 'Enter' || e.key === ' ') {
		e.preventDefault();
		handler(el);
	}
	};

	aContainer.addEventListener('click', clickListener);
	aContainer.addEventListener('keydown', keyListener);
}
