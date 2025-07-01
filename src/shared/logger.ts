export function log(..._args: unknown[]): void {
	// Production logging disabled
}

export function warn(..._args: unknown[]): void {
	// Production warning disabled
}

export function error(...args: unknown[]): void {
	console.error(...args);
}