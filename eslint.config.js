import tsPlugin from '@typescript-eslint/eslint-plugin';
import tsParser from '@typescript-eslint/parser';
import js from '@eslint/js';

// Explicitly apply the recommended TypeScript rules so future
// maintainers know exactly which set is used.

export default [
  js.configs.recommended,
  {
    files: ['**/*.ts'],
    languageOptions: {
      parser: tsParser,
      parserOptions: {
        project: './tsconfig.json',
        tsconfigRootDir: __dirname
      }
    },
    plugins: {
      '@typescript-eslint': tsPlugin
    },
    // Clone the recommended rules so updates to the preset
    // won't silently change lint behavior.
    rules: { ...tsPlugin.configs.recommended.rules }
  }
];
