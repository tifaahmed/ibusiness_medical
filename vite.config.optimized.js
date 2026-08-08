import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    build: {
        // Optimize bundle size
        rollupOptions: {
            output: {
                // Code splitting strategy
                manualChunks: {
                    // Separate vendor chunk for better caching
                    'vendor': [
                        'vue',
                        'pinia',
                        '@inertiajs/vue3'
                    ],
                    // Separate swiper into its own chunk
                    'swiper': ['swiper'],
                    // Separate large utilities
                    'utils': [
                        '@vueuse/core',
                        '@vueuse/integrations'
                    ]
                },
                // Optimize chunk file names for caching
                chunkFileNames: 'assets/[name]-[hash].js',
                entryFileNames: 'assets/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash].[ext]'
            }
        },
        // Increase chunk size warning limit
        chunkSizeWarningLimit: 600,
        // Use terser for better minification
        minify: 'terser',
        terserOptions: {
            compress: {
                // Remove console.logs in production
                drop_console: true,
                drop_debugger: true,
                // Additional optimizations
                pure_funcs: ['console.log', 'console.info', 'console.debug'],
                passes: 2
            },
            mangle: {
                safari10: true
            },
            format: {
                comments: false
            }
        },
        // Enable CSS code splitting
        cssCodeSplit: true,
        // Source maps for debugging (disable in production for smaller files)
        sourcemap: false,
        // Optimize asset handling
        assetsInlineLimit: 4096, // 4kb - inline smaller assets as base64
        // Target modern browsers for smaller bundle
        target: ['es2020', 'edge88', 'firefox78', 'chrome87', 'safari14']
    },
    // Optimize dependencies
    optimizeDeps: {
        include: [
            'vue',
            '@inertiajs/vue3',
            'pinia',
            'swiper'
        ],
        exclude: [
            // Exclude large dependencies that should be loaded on-demand
        ]
    },
    // Server configuration for development
    server: {
        hmr: {
            overlay: true
        },
        // Optimize dev server
        watch: {
            ignored: ['**/vendor/**', '**/node_modules/**', '**/storage/**']
        }
    },
    // Preview server configuration
    preview: {
        port: 5173
    }
});
