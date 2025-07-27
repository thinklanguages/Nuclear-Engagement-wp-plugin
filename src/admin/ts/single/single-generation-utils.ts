export {
	nuclenAlertApiError as alertApiError,
	nuclenStoreGenerationResults as storeGenerationResults,
} from '../generation/results';

export interface PostResult {
	date?: string;
	summary?: string;
	questions?: Array<{
	question?: string;
	answers?: string[];
	explanation?: string;
	}>;
	[key: string]: unknown;
}

export function populateQuizMetaBox(
	postResult: PostResult,
	finalDate?: string
): void {
	const { date, questions } = postResult;
	const newDate = finalDate || date;
	const dateField = document.querySelector<HTMLInputElement>('input[name="nuclen_quiz_data[date]"]');
	if (dateField) {
		dateField.readOnly = false;
		dateField.value = newDate || '';
		dateField.readOnly = true;
	}

	if (Array.isArray(questions)) {
		questions.forEach((q, qIndex) => {
			const questionSelector = `input[name="nuclen_quiz_data[questions][${qIndex}][question]"]`;
			const questionInput = document.querySelector<HTMLInputElement>(questionSelector);
			if (questionInput) {
				questionInput.value = q.question || '';
			}

			if (Array.isArray(q.answers)) {
				q.answers.forEach((ans: string, aIndex: number) => {
					const ansSelector = `input[name="nuclen_quiz_data[questions][${qIndex}][answers][${aIndex}]"]`;
					const ansInput = document.querySelector<HTMLInputElement>(ansSelector);
					if (ansInput) {
						ansInput.value = ans;
					}
				});
			}

			const explanationSelector = `textarea[name="nuclen_quiz_data[questions][${qIndex}][explanation]"]`;
			const explanationTextarea = document.querySelector<HTMLTextAreaElement>(explanationSelector);
			if (explanationTextarea) {
				explanationTextarea.value = q.explanation || '';
			}
		});
	}
}

export function populateSummaryMetaBox(
	postResult: PostResult,
	finalDate?: string
): void {
	const { date, summary } = postResult;
	const newDate = finalDate || date;
	const dateField = document.querySelector<HTMLInputElement>('input[name="nuclen_summary_data[date]"]');
	if (dateField) {
		dateField.readOnly = false;
		dateField.value = newDate || '';
		dateField.readOnly = true;
	}

	// Update the textarea value
	const summaryField = document.querySelector<HTMLTextAreaElement>('textarea[name="nuclen_summary_data[summary]"]');
	if (summaryField) {
		summaryField.value = summary || '';
		
		// WordPress TinyMCE will automatically sync from the textarea value
		// when switching between Visual/Text tabs, so we don't need to
		// trigger any events or directly update TinyMCE
	}
}

