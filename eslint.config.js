import tsPlugin from '@typescript-eslint/eslint-plugin';
import tsParser from '@typescript-eslint/parser';
import js from '@eslint/js';

export default [
  js.configs.recommended,
  {
    ignores: [
      '**/node_modules/**', 
      '**/vendor/**', 
      '**/logger-*.js', 
      '**/dist/**', 
      '**/build/**',
      'nuclear-engagement/admin/js/*.js',
      'nuclear-engagement/front/js/*.js',
      'nuclear-engagement/inc/Modules/TOC/assets/js/*.js',
      'nuclear-engagement/modules/toc/assets/js/*.js',
      '*.min.js',
      '*.min.css',
      'tests/wp-stubs.php',
      'tests/wp-admin/',
      'postcss.config.cjs',
      'vite.config.ts',
      'vite.config.iife.ts',
      'tests/accessibility/*.cjs',
      'tests/e2e/*.js'
    ]
  },
  {
    files: ['**/*.ts'],
    languageOptions: {
      parser: tsParser,
      parserOptions: {
        ecmaVersion: 2022,
        sourceType: 'module'
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
        gtag: 'readonly',
        HTMLElement: 'readonly',
        HTMLDivElement: 'readonly',
        HTMLButtonElement: 'readonly',
        HTMLFormElement: 'readonly',
        HTMLSpanElement: 'readonly',
        HTMLParagraphElement: 'readonly',
        HTMLSelectElement: 'readonly',
        HTMLInputElement: 'readonly',
        HTMLTextAreaElement: 'readonly',
        HTMLAnchorElement: 'readonly',
        Element: 'readonly',
        FormData: 'readonly',
        RequestInit: 'readonly',
        Response: 'readonly',
        URLSearchParams: 'readonly',
        URL: 'readonly',
        IntersectionObserver: 'readonly',
        IntersectionObserverInit: 'readonly',
        MutationObserver: 'readonly',
        Event: 'readonly',
        KeyboardEvent: 'readonly',
        MouseEvent: 'readonly',
        global: 'readonly',
        process: 'readonly',
        __dirname: 'readonly',
        __filename: 'readonly',
        performance: 'readonly',
        jQuery: 'readonly'
      }
    },
    plugins: {
      '@typescript-eslint': tsPlugin
    },
    rules: {
      ...tsPlugin.configs.recommended.rules,
      '@typescript-eslint/no-explicit-any': 'off',
      'no-unused-vars': 'off',
      '@typescript-eslint/no-unused-vars': ['warn', { 'argsIgnorePattern': '^_' }]
    }
  },
  {
    files: ['**/*.js'],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'module',
      globals: {
        window: 'readonly',
        document: 'readonly',
        console: 'readonly',
        fetch: 'readonly',
        setTimeout: 'readonly',
        clearTimeout: 'readonly',
        setInterval: 'readonly',
        clearInterval: 'readonly',
        FormData: 'readonly',
        URLSearchParams: 'readonly',
        HTMLElement: 'readonly',
        IntersectionObserver: 'readonly',
        MutationObserver: 'readonly',
        jQuery: 'readonly',
        $: 'readonly',
        gtag: 'readonly',
        alert: 'readonly',
        jest: 'readonly',
        global: 'readonly',
        process: 'readonly',
        require: 'readonly',
        module: 'readonly',
        __dirname: 'readonly',
        __filename: 'readonly',
        exports: 'readonly',
        navigator: 'readonly',
        NuclenStrings: 'readonly',
        NuclenSettings: 'readonly',
        NuclenOptinAjax: 'readonly',
        postQuizData: 'readonly'
      }
    }
  },
  {
    files: ['**/*.test.ts', '**/*.spec.ts', '**/*.test.js', '**/*.spec.js', 'tests/**/*.ts', 'tests/**/*.js'],
    languageOptions: {
      globals: {
        vi: 'readonly',
        describe: 'readonly',
        it: 'readonly',
        expect: 'readonly',
        beforeEach: 'readonly',
        afterEach: 'readonly',
        beforeAll: 'readonly',
        afterAll: 'readonly',
        test: 'readonly'
      }
    }
  }
];