// src/front/ts/nuclen-quiz-progress.ts

export function updateProgress(
	progBar: HTMLElement,
	currIdx: number,
	total: number,
): void {
	progBar.style.width = `${((currIdx + 1) / total) * 100}%`;
}
