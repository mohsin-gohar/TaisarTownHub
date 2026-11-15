import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import path from 'path';
import {defineConfig} from 'vite';

export default defineConfig(() => {
  const isProduction = process.env.VITE_APP_MODE === 'production' || process.env.VITE_APP_MODE === 'public';
  const alias: Record<string, string> = {
    '@': path.resolve(import.meta.dirname, '.'),
  };
  if (isProduction) {
    // Alias erpSeed to an empty module so no demo credentials/CNICs ship.
    alias['../data/erpSeed'] = path.resolve(import.meta.dirname, 'src/data/erpSeed.empty.ts');
  }
  return {
    plugins: [react(), tailwindcss()],
    resolve: { alias },
    build: {
      cssMinify: 'esbuild' as const,
    },
    server: {
      hmr: process.env.DISABLE_HMR !== 'true',
      watch: process.env.DISABLE_HMR === 'true' ? null : {},
    },
  };
});
