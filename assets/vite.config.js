import { defineConfig } from 'vite';
import { cpSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);

export default defineConfig({
  base: './',
  plugins: [{ name: 'highlight-theme-assets', closeBundle() {
    const styles = resolve(dirname(require.resolve('highlight.js/package.json')), 'styles');
    mkdirSync(resolve(import.meta.dirname, 'dist/highlight'), { recursive: true });
    cpSync(styles, resolve(import.meta.dirname, 'dist/highlight'), { recursive: true, filter: file => !file.endsWith('.min.css') });
  } }],
  build: {
    target: 'es2022',
    manifest: 'manifest.json',
    sourcemap: false,
    rolldownOptions: {
      input: { theme: 'src/modern/index.js', admin: 'src/modern/admin.js' },
      output: { entryFileNames: '[name]-[hash].js', chunkFileNames: '[name]-[hash].js', assetFileNames: '[name]-[hash][extname]' }
    }
  }
});
