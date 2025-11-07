import { initSingleGenerationButtons } from './single/single-generation-handlers';

// Initialize summary format field toggling for metabox
function initSummaryFormatToggle(): void {
	const formatSelect = document.getElementById('nuclen_summary_data_format') as HTMLSelectElement;
	if (!formatSelect) return;

	const paragraphOptions = document.querySelector('.nuclen-summary-paragraph-options') as HTMLElement;
	const bulletOptions = document.querySelector('.nuclen-summary-bullet-options') as HTMLElement;

	function toggleFormatFields(): void {
		if (!paragraphOptions || !bulletOptions) return;

		if (formatSelect.value === 'paragraph') {
			paragraphOptions.style.display = 'block';
			bulletOptions.style.display = 'none';
		} else {
			paragraphOptions.style.display = 'none';
			bulletOptions.style.display = 'block';
		}
	}

	// Initial toggle
	toggleFormatFields();

	// Listen for changes
	formatSelect.addEventListener('change', toggleFormatFields);
}

// Initialize everything when DOM is ready
function initAll(): void {
	initSummaryFormatToggle();
	initSingleGenerationButtons();
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initAll);
} else {
	// Defer initialization to next tick to ensure WordPress has finished its setup
	setTimeout(initAll, 0);
}
