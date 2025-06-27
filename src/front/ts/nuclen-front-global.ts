// file: src/front/ts/nuclen-front-global.ts

/*************************************************
 * 1) Global Declarations
 *************************************************/
declare global {
	const postQuizData: Array<{
	question: string;
	answers: string[];
	explanation: string;
	}>;

	const NuclenOptinPosition: string;
	const NuclenOptinMandatory: boolean;
	const NuclenOptinPromptText: string;
	const NuclenOptinButtonText: string;

	const NuclenCustomQuizHtmlAfter: string;

	const NuclenOptinAjax: {
	url: string;
	nonce: string;
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
