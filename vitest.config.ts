import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
	environment: 'jsdom',
	include: ['tests/**/*.test.ts'],
	testTimeout: 30000,
	hookTimeout: 30000,
	coverage: {
	  provider: 'v8',
	  reporter: ['text', 'json', 'html', 'lcov'],
	  exclude: [
		'node_modules/',
		'tests/',
		'dist/',
		'build/',
		'coverage/',
		'**/*.config.{js,ts}',
		'**/*.d.ts'
	  ],
	  thresholds: {
		global: {
		  branches: 80,
		  functions: 80,
		  lines: 80,
		  statements: 80
		}
	  }
	}
  }
});
