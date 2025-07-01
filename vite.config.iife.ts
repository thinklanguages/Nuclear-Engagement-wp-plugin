// vite.config.iife.ts
import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
  root: 'src',
  
  build: {
    outDir: path.resolve(__dirname, 'nuclear-engagement'),
    emptyOutDir: false,
    
    rollupOptions: {
      input: {
        onboarding: path.resolve(__dirname, 'src/admin/ts/onboarding-pointers.ts')
      },
      output: {
        format: 'iife',
        entryFileNames: 'admin/js/onboarding-pointers.js'
      }
    }
  }
});