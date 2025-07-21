// API Configuration
export const API_CONFIG = {
	RETRY_COUNT: 3,
	INITIAL_DELAY_MS: 500,
	POLLING_INTERVAL_MS: 10000,
	MAX_BACKOFF_MS: 30000,
	// Progressive polling configuration
	POLLING_INTERVALS: [
		// First 1 minute: 10 second intervals (6 polls)
		{ duration: 60000, interval: 10000 },
		// Next 4 minutes: 30 second intervals (8 polls)
		{ duration: 240000, interval: 30000 },
		// Remaining time: 60 second intervals
		{ duration: Infinity, interval: 60000 }
	],
	MAX_POLLING_ATTEMPTS: 240, // Increased from 120
	MAX_POLLING_TIMEOUT_MS: 1800000, // 30 minutes
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