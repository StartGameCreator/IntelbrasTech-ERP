const { defineConfig } = require('vite')
const vue = require('@vitejs/plugin-vue')
const path = require('node:path')

module.exports = defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'src'),
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8033',
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: 'dist',
    rollupOptions: {
      output: {
        manualChunks: {
          vendor: ['vue', 'vue-router', 'pinia'],
          charts: ['apexcharts', 'vue3-apexcharts'],
          maps: ['leaflet'],
        },
      },
    },
  },
})
