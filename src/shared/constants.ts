// API Configuration
export const API_CONFIG = {
	RETRY_COUNT: 3,
	INITIAL_DELAY_MS: 500,
	POLLING_INTERVAL_MS: 5000,
	MAX_BACKOFF_MS: 30000,
} as const;

// Action Names
export const ACTIONS = {
	TRIGGER_GENERATION: 'nuclen_trigger_generation',
	GET_POSTS_COUNT: 'nuclen_get_posts_count',
	UPDATE_POINTER: 'nuclen_update_pointer',
	EXPORT_OPTIN: 'nuclen_export_optin',
} as const;

// CSS Class Names
export const CSS_CLASSES = {
	ADMIN: {
		CONTAINER: 'nuclen-admin-container',
		LOADING: 'nuclen-loading',
		ERROR: 'nuclen-error',
		SUCCESS: 'nuclen-success',
	},
	QUIZ: {
		CONTAINER: 'nuclen-quiz-container',
		QUESTION: 'nuclen-quiz-question',
		ANSWER: 'nuclen-quiz-answer',
		PROGRESS: 'nuclen-quiz-progress',
	},
	TOC: {
		CONTAINER: 'nuclen-toc-container',
		STICKY: 'nuclen-toc-sticky',
		ACTIVE: 'nuclen-toc-active',
	},
} as const;

// Scroll Configuration
export const SCROLL_CONFIG = {
	TOC_ROOT_MARGIN: '0px 0px -60%',
	SMOOTH_SCROLL_DURATION: 300,
} as const;

// Local Storage Keys
export const STORAGE_KEYS = {
	QUIZ_PROGRESS: 'nuclen_quiz_progress',
	USER_PREFERENCES: 'nuclen_user_prefs',
} as const;