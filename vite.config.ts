import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import { resolve } from 'node:path';
import { defineConfig } from 'vite';

function stripUseClientDirective(): import('vite').Plugin {
  return {
    name: 'strip-use-client-directive',
    enforce: 'pre' as const,
    transform(code, id) {
      if (
        id.endsWith('.js') ||
        id.endsWith('.ts') ||
        id.endsWith('.tsx') ||
        id.endsWith('.mjs')
      ) {
        if (code.includes('"use client"') || code.includes("'use client'")) {
          return code.replace(/['"]use client['"];?\s*/g, '');
        }
      }
    },
  };
}

export default defineConfig({
    base: './',
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/dark-mode.css', 'resources/css/rtl.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
            // Kept out of public/ so a stale hot file can't ship in a deploy zip
            // and point production at 127.0.0.1:5173. Must match AppServiceProvider.
            hotFile: 'storage/framework/vite.hot',
        }),
        stripUseClientDirective(),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        // This host is also written to public/hot, so it must be reachable from
        // the browser. To test from other LAN devices, set VITE_HMR_HOST to this
        // machine's LAN IP (0.0.0.0 can't be resolved by browsers).
        hmr: {
            host: process.env.VITE_HMR_HOST || '127.0.0.1',
        },
        headers: {
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'GET,POST,PUT,DELETE,OPTIONS',
            'Access-Control-Allow-Headers': '*',
        },
        watch: {
            ignored: ['**/vendor/**', '**/node_modules/**', '**/storage/**', '**/.git/**']
        }
    },

    esbuild: {
        jsx: 'automatic',
        jsxImportSource: 'react',
    },
    resolve: {
        alias: {
            'ziggy-js': resolve(__dirname, 'vendor/tightenco/ziggy'),
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ['react', 'react-dom'],
                    ui: ['@radix-ui/react-dialog', '@radix-ui/react-dropdown-menu'],
                    utils: ['date-fns', 'clsx']
                }
            },
        },
        assetsDir: 'assets',
    }
});