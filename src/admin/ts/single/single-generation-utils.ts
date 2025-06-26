export {
  nuclenAlertApiError as alertApiError,
  nuclenStoreGenerationResults as storeGenerationResults,
} from '../generation/results';

interface QuizQuestionResult {
  question?: string;
  answers?: string[];
  explanation?: string;
}

interface QuizPostResult {
  date?: string;
  questions?: QuizQuestionResult[];
}

export function populateQuizMetaBox(postResult: QuizPostResult, finalDate?: string): void {
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

interface SummaryPostResult {
  date?: string;
  summary?: string;
}

export function populateSummaryMetaBox(postResult: SummaryPostResult, finalDate?: string): void {
  const { date, summary } = postResult;
  const newDate = finalDate || date;
  const dateField = document.querySelector<HTMLInputElement>('input[name="nuclen_summary_data[date]"]');
  if (dateField) {
    dateField.readOnly = false;
    dateField.value = newDate || '';
    dateField.readOnly = true;
  }

  if (typeof window.tinymce !== 'undefined') {
    const editor = window.tinymce?.get('nuclen_summary_data_summary');
    if (editor && typeof editor.setContent === 'function') {
      editor.setContent(summary || '');
      editor.save();
    } else {
      const summaryField = document.querySelector<HTMLTextAreaElement>('textarea[name="nuclen_summary_data[summary]"]');
      if (summaryField) {
        summaryField.value = summary || '';
      }
    }
  } else {
    const summaryField = document.querySelector<HTMLTextAreaElement>('textarea[name="nuclen_summary_data[summary]"]');
    if (summaryField) {
      summaryField.value = summary || '';
    }
  }
}

