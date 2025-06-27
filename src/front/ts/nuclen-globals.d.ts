export {};

declare global {
	interface Window {
	NuclenOptinEnabled?: boolean;
	NuclenOptinWebhook?: string;
	NuclenOptinSuccessMessage?: string;
	NuclenLazyLoadComponent?: (
		containerId: string,
		initFunctionName?: string | null
	) => void;
	nuclearEngagementInitQuiz?: () => void;
	nuclearEngagementShowQuizQuestionDetails?: (index: number) => void;
	nuclearEngagementRetakeQuiz?: () => void;
	}
}
