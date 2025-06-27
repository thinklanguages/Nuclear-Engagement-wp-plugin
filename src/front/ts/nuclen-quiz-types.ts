// ─────────────────────────────────────────────────────────────
// File: src/front/ts/nuclen-quiz-types.ts
// -----------------------------------------------------------------------------
export interface QuizQuestion {
	question: string;
	answers: string[];
	explanation: string;
	}

	export interface NuclenSettings {
	questions_per_quiz: number;
	answers_per_question: number;
	}

	export interface OptinContext {
	position: 'with_results' | 'before_results';
	mandatory: boolean;
	promptText: string;
	submitLabel: string;
	enabled: boolean;
	webhook: string;
	ajaxUrl: string;
	ajaxNonce: string;
	}
