const PREFIX = '[Nuclear Engagement]';
const isDevelopment = process.env.NODE_ENV === 'development';

export function log(...args: unknown[]): void {
	if (isDevelopment) {
		console.log(PREFIX, ...args);
	}
}

export function warn(...args: unknown[]): void {
	if (isDevelopment) {
		console.warn(PREFIX, ...args);
	}
}

export function error(...args: unknown[]): void {
	console.error(PREFIX, ...args);
}

export function debug(...args: unknown[]): void {
	if (isDevelopment && window.nuclenDebug) {
		console.debug(PREFIX, '[DEBUG]', ...args);
	}
}