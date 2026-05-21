<script setup>
import { RouterView } from 'vue-router'
</script>

<template>
  <RouterView />
</template>

<style>
/* CSS Global Tambahan */
body {
  margin: 0;
  padding: 0;
  background-color: #0B0F19;
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  overflow-x: hidden;
}

/* Transisi Halaman Router */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
