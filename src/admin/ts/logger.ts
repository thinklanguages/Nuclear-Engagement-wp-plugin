export function nuclenLog(message: string, level: 'log' | 'warn' | 'error' = 'log'): void {
  if (typeof console === 'undefined') return;
  const prefix = '[NE]';
  if (level === 'warn') {
    console.warn(`${prefix} ${message}`);
  } else if (level === 'error') {
    console.error(`${prefix} ${message}`);
  } else {
    console.log(`${prefix} ${message}`);
  }
}
