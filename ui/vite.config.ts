import { defineConfig } from 'vite'
import preact from '@preact/preset-vite'

// "./../web/soubory/ui"

// https://vitejs.dev/config/
export default ({outDir}) => defineConfig({
  plugins: [preact()],
  build: {
    target: "es6",
    outDir,
    emptyOutDir: true,
    lib: {
      entry: 'src/index.ts',
      name: "script",
      fileName: () => "bundle.js",
      formats: ["iife"]
    },
    minify: true,
    sourcemap: true,
  },
  server: {
    proxy: {
      '/api': {
        target: `http://localhost:80/admin/api/`,
      },
    },
    host: true,
  },
})
