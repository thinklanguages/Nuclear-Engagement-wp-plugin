export interface GeneratePageElements {
	step1: HTMLDivElement | null;
	step2: HTMLDivElement | null;
	updatesSection: HTMLDivElement | null;
	updatesContent: HTMLDivElement | null;
	restartBtn: HTMLButtonElement | null;
	getPostsBtn: HTMLButtonElement | null;
	goBackBtn: HTMLButtonElement | null;
	generateForm: HTMLFormElement | null;
	submitBtn: HTMLButtonElement | null;
	postsCountEl: HTMLSpanElement | null;
	stepBar1: HTMLElement | null;
	stepBar2: HTMLElement | null;
	// Removed stepBar3 and stepBar4 - no longer needed
	creditsInfoEl: HTMLParagraphElement | null;
}

export function getGeneratePageElements(): GeneratePageElements {
	return {
		step1: document.getElementById('nuclen-step-1') as HTMLDivElement | null,
		step2: document.getElementById('nuclen-step-2') as HTMLDivElement | null,
		updatesSection: document.getElementById('nuclen-updates-section') as HTMLDivElement | null,
		updatesContent: document.getElementById('nuclen-updates-content') as HTMLDivElement | null,
		restartBtn: document.getElementById('nuclen-restart-btn') as HTMLButtonElement | null,
		getPostsBtn: document.getElementById('nuclen-get-posts-btn') as HTMLButtonElement | null,
		goBackBtn: document.getElementById('nuclen-go-back-btn') as HTMLButtonElement | null,
		generateForm: document.getElementById('nuclen-generate-form') as HTMLFormElement | null,
		submitBtn: document.getElementById('nuclen-submit-btn') as HTMLButtonElement | null,
		postsCountEl: document.getElementById('nuclen-posts-count') as HTMLSpanElement | null,
		stepBar1: document.getElementById('nuclen-step-bar-1'),
		stepBar2: document.getElementById('nuclen-step-bar-2'),
		// Removed stepBar3 and stepBar4 - no longer needed
		creditsInfoEl: document.getElementById('nuclen-credits-info') as HTMLParagraphElement | null,
	};
}
