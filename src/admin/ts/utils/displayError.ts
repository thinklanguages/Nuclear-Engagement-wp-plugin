import { error } from '../../../shared/logger';

export function displayError(message: string): void {
	const toast = document.createElement('div');
	toast.className = 'nuclen-error-toast';
	toast.textContent = message;
	document.body.appendChild(toast);
	setTimeout(() => toast.remove(), 5000);
	error(message);
}
