import * as logger from './logger';

export function displaySuccess(message: string, _context?: Record<string, any>): void {
	// Log success with context
	logger.log(`[SUCCESS] UI Success | ${message}`);
	
	// Create toast notification
	const toast = document.createElement('div');
	toast.className = 'nuclen-success-toast';
	toast.innerHTML = message;
	document.body.appendChild(toast);
	
	// Remove toast after 5 seconds
	setTimeout(() => {
		if (toast.parentNode) {
			toast.remove();
		}
	}, 5000);
}

export function displayPageMessage(message: string, type: 'success' | 'info' | 'warning' = 'success'): void {
	// Log message
	logger.log(`[PAGE MESSAGE] ${type.toUpperCase()} | ${message}`);
	
	// Find or create message container
	let messageContainer = document.getElementById('nuclen-page-message');
	if (!messageContainer) {
		// Create it after the heading
		const heading = document.querySelector('.nuclen-heading');
		if (heading) {
			messageContainer = document.createElement('div');
			messageContainer.id = 'nuclen-page-message';
			heading.insertAdjacentElement('afterend', messageContainer);
		}
	}
	
	if (messageContainer) {
		// Clear any existing message
		messageContainer.innerHTML = '';
		
		// Create message element
		const messageEl = document.createElement('div');
		messageEl.className = `nuclen-page-message nuclen-page-message--${type}`;
		messageEl.innerHTML = `
			<div class="nuclen-page-message__icon">
				${type === 'success' ? '✓' : type === 'warning' ? '⚠' : 'ℹ'}
			</div>
			<div class="nuclen-page-message__text">${message}</div>
		`;
		
		messageContainer.appendChild(messageEl);
		
		// Auto-hide after 10 seconds for non-warning messages
		if (type !== 'warning') {
			setTimeout(() => {
				messageEl.style.opacity = '0';
				setTimeout(() => messageEl.remove(), 300);
			}, 10000);
		}
	}
}