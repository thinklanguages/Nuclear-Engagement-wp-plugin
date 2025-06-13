export const REST_ENDPOINT =
  (window as any).nuclenAdminVars?.rest_receive_content ||
  '/wp-json/nuclear-engagement/v1/receive-content';

export const REST_NONCE = (window as any).nuclenAdminVars?.rest_nonce || '';

export function alertApiError(errMsg: string): void {
  if (errMsg.includes('Invalid API key')) {
    alert('Your API key is invalid. Please go to the Setup page and enter a new one.');
  } else if (errMsg.includes('Invalid WP App Password')) {
    alert('Your WP App Password is invalid. Please go to the Setup page and re-generate it.');
  } else if (errMsg.includes('Not enough credits')) {
    alert('Not enough credits for single-post generation.');
  } else {
    alert('Error: ' + errMsg);
  }
}

export function populateQuizMetaBox(postResult: any, finalDate?: string): void {
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

export function populateSummaryMetaBox(postResult: any, finalDate?: string): void {
  const { date, summary } = postResult;
  const newDate = finalDate || date;
  const dateField = document.querySelector<HTMLInputElement>('input[name="nuclen_summary_data[date]"]');
  if (dateField) {
    dateField.readOnly = false;
    dateField.value = newDate || '';
    dateField.readOnly = true;
  }

  if (typeof (window as any).tinymce !== 'undefined') {
    const editor = (window as any).tinymce.get('nuclen_summary_data_summary');
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

export async function storeGenerationResults(workflow: string, results: any) {
  const payload = { workflow, results };
  const resp = await fetch(REST_ENDPOINT, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': REST_NONCE,
    },
    credentials: 'include',
    body: JSON.stringify(payload),
  });
  const data = await resp.json();
  return { ok: resp.ok, data };
}
