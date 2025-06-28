import tsPlugin from '@typescript-eslint/eslint-plugin';
import tsParser from '@typescript-eslint/parser';
import js from '@eslint/js';
import fs from 'fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const tsconfigPath = path.join(__dirname, 'tsconfig.json');
if (!fs.existsSync(tsconfigPath)) {
  console.error('tsconfig.json not found at', tsconfigPath);
}

// Explicitly apply the recommended TypeScript rules so future
// maintainers know exactly which set is used.

export default [
  js.configs.recommended,
  {
    files: [
      'eslint.config.ts',
      'vite.config.ts',
      'vitest.config.ts',
      'jest.setup.ts'
    ],
    languageOptions: {
      globals: {
        module: 'readonly',
        require: 'readonly',
        process: 'readonly',
        __dirname: 'readonly',
        __filename: 'readonly'
      }
    }
  },
  {
    files: ['**/*.ts'],
    languageOptions: {
      parser: tsParser,
      parserOptions: {
        project: tsconfigPath,
        tsconfigRootDir: __dirname
      },
      globals: {
        window: 'readonly',
        document: 'readonly',
        fetch: 'readonly',
        console: 'readonly',
        setTimeout: 'readonly',
        clearTimeout: 'readonly',
        setInterval: 'readonly',
        clearInterval: 'readonly',
        navigator: 'readonly',
        alert: 'readonly',
        gtag: 'readonly'
      }
    },
    plugins: {
      '@typescript-eslint': tsPlugin
    },
    // Clone the recommended rules so updates to the preset
    // won't silently change lint behavior.
    rules: {
      ...tsPlugin.configs.recommended.rules,
      '@typescript-eslint/no-explicit-any': 'off',
      indent: ['error', 'tab']
    }
  }
];
