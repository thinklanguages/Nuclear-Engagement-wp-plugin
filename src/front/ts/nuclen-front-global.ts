// file: src/front/ts/nuclen-front-global.ts

/*************************************************
 * 1) Global Declarations
 *************************************************/
declare global {
	interface Window {
		postQuizData: Array<{
			question: string;
			answers: string[];
			explanation: string;
		}>;

		NuclenOptinPosition?: string;
		NuclenOptinMandatory?: boolean;
		NuclenOptinPromptText?: string;
		NuclenOptinButtonText?: string;
		NuclenOptinEnabled?: boolean;
		NuclenOptinWebhook?: string;

		NuclenCustomQuizHtmlAfter: string;

		NuclenOptinAjax: {
			url: string;
			nonce: string;
		};
		
		NuclenSettings: {
			questions_per_quiz?: number;
			answers_per_question?: number;
		};

		NuclenStrings: {
			retake_test: string;
			your_score: string;
			perfect: string;
			well_done: string;
			retake_prompt: string;
			correct: string;
			your_answer: string;
		};
	}

	const postQuizData: Array<{
		question: string;
		answers: string[];
		explanation: string;
	}>;

	const NuclenOptinAjax: {
		url: string;
		nonce: string;
	};

	const NuclenSettings: {
		questions_per_quiz?: number;
		answers_per_question?: number;
	};

	const NuclenStrings: {
		retake_test: string;
		your_score: string;
		perfect: string;
		well_done: string;
		retake_prompt: string;
		correct: string;
		your_answer: string;
	};

	function gtag(...args: unknown[]): void;
}

export {};
