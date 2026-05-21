import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'
import path from 'path'


// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    tailwindcss(),
    vueDevTools(),
    VitePWA({
      registerType: "autoUpdate",
      includeAssets: [
        "favicon.ico",
        "apple-touch-icon.png",
        "mask-icon.svg",
      ],
      manifest: {
        name: "Restoku POS",
        short_name: "Restoku",
        description: "Point of Sale System by Restoku",
        theme_color: "#10b981",
        icons: [
          {
            src: "pwa-192x192.png",
            sizes: "192x192",
            type: "image/png",
          },
          {
            src: "pwa-512x512.png",
            sizes: "512x512",
            type: "image/png",
          },
        ],
      },
    }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
  build: {
    // Arahkan hasil build keluar dari folder frontend, menuju public/app Laravel
    outDir: "../public/app",
    // Kosongkan folder public/app setiap kali melakukan build ulang
    emptyOutDir: true,
  },
  // Instruksikan file index.html untuk mencari aset CSS/JS di path /app/
  base: "/app/",
})
