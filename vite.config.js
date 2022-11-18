import { fileURLToPath, URL } from 'node:url'

import { resolve } from 'path'
import { defineConfig, splitVendorChunkPlugin } from 'vite'
import vue from '@vitejs/plugin-vue'
import liveReload from 'vite-plugin-live-reload'
import path from 'path'

export default defineConfig({
    plugins: [
        vue(),
        liveReload([
            // edit live reload paths according to your source code
            // for example:
            __dirname + '/(app|config|views)/**/*.php',
            // using this for our example:
            __dirname + '/../public/*.php',
        ]),
        splitVendorChunkPlugin(),
    ],
    resolve: {
        alias: {
            vue: 'vue/dist/vue.esm-bundler.js'
        }
    },
    root: 'src',
    base: process.env.APP_ENV === 'development'
        ? '/'
        : '/dist/',
    build: {
        outDir: './public/dist',
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            input: path.resolve(__dirname, 'src/js/main.js'),
        }
    },
    server: {
        // we need a strict port to match on PHP side
        // change freely, but update on PHP to match the same port
        // tip: choose a different port per project to run them at the same time
        strictPort: true,
        port: 5133
    },
})
