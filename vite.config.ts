// vite.config.ts
import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
  // Optional root: if your source is in ./src, you can set root to 'src' or remove it.
  root: 'src',

  build: {
    // Output to the "nuclear-engagement" folder (the plugin root).
    outDir: path.resolve(__dirname, 'nuclear-engagement'),
    emptyOutDir: false,
    
    // Generate source maps for debugging
    sourcemap: true,
    
    // Set a higher threshold to inline small modules like logger
    modulePreload: {
      polyfill: false
    },
    
    // Inline modules smaller than 1KB
    assetsInlineLimit: 1024,
    
    // Configure chunk size warnings
    chunkSizeWarningLimit: 500,
    
    // Inline small chunks to avoid module loading issues
    rollupOptions: {
      treeshake: {
        moduleSideEffects: true
      },
      // Entry points for admin, front and the TOC module
      input: {
        logger: path.resolve(__dirname, 'src/shared/logger.ts'),
        admin: path.resolve(__dirname, 'src/admin/ts/nuclen-admin.ts'),
        front: path.resolve(__dirname, 'src/front/ts/nuclen-front.ts'),
        tasks: path.resolve(__dirname, 'src/admin/ts/tasks.ts'),
        tocAdmin: path.resolve(
          __dirname,
          'src/modules/toc/ts/nuclen-toc-admin.ts',
        ),
        tocFront: path.resolve(
          __dirname,
          'src/modules/toc/ts/nuclen-toc-front.ts',
        )
      },
      output: {
        // Place each entry in its own subfolder
        entryFileNames: (chunkInfo) => {
          if (chunkInfo.name === 'logger') {
            return 'logger-[hash].js';
          }
          if (chunkInfo.name === 'admin') {
            return 'admin/js/nuclen-admin.js';
          }
          if (chunkInfo.name === 'front') {
            return 'front/js/nuclen-front.js';
          }
          if (chunkInfo.name === 'tasks') {
            return 'admin/js/nuclen-tasks.js';
          }
          if (chunkInfo.name === 'tocAdmin') {
            return 'modules/toc/assets/js/nuclen-toc-admin.js';
          }
          if (chunkInfo.name === 'tocFront') {
            return 'modules/toc/assets/js/nuclen-toc-front.js';
          }
          // fallback for any other entry (if you had more)
          return '[name].js';
        },

        // If you have dynamic imports or code-splitting, place those chunks here:
        chunkFileNames: (chunkInfo) => {
          // Place quiz chunks in the front/js directory for consistency
          if (chunkInfo.name.includes('quiz')) {
            return 'front/js/nuclen-[name]-[hash].js';
          }
          // Place other chunks at the root level for easier resolution
          return '[name]-[hash].js';
        },
        
        // Control chunk creation
        manualChunks(id) {
          // Keep logger as a separate module
          if (id.includes('shared/logger')) {
            return 'logger';
          }
        }
      }
    }
  }
});
