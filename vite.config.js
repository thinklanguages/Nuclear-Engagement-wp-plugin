// vite.config.js
import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig({
  // Optional root: if your source is in ./src, you can set root to 'src' or remove it.
  root: 'src',

  build: {
    // Output to the "nuclear-engagement" folder (the plugin root).
    outDir: path.resolve(__dirname, 'nuclear-engagement'),
    emptyOutDir: false,

    rollupOptions: {
      // Entry points for admin, front and the TOC module
      input: {
        admin: path.resolve(__dirname, 'src/admin/ts/nuclen-admin.ts'),
        front: path.resolve(__dirname, 'src/front/ts/nuclen-front.ts'),
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
          if (chunkInfo.name === 'admin') {
            return 'admin/js/nuclen-admin.js';
          }
          if (chunkInfo.name === 'front') {
            return 'front/js/nuclen-front.js';
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
          // You can customize further if you want separate folders for each entry's chunks.
          // For simplicity, just put them next to the main files with a hash:
          return '[name]-[hash].js';
        }
      }
    }
  }
});
