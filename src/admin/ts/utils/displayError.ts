import * as logger from './logger';

export function displayError(message: string, _context?: Record<string, any>): void {
	// Log error with context
	logger.error(`[ERROR] UI Error | ${message}`);
	
	// Create toast notification
	const toast = document.createElement('div');
	toast.className = 'nuclen-error-toast';
	toast.innerHTML = message;
	document.body.appendChild(toast);
	
	// Remove toast after 5 seconds
	setTimeout(() => {
		if (toast.parentNode) {
			toast.remove();
		}
	}, 5000);
}
