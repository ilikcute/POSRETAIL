<script setup>
// Minimal Footer Component
</script>

<template>
  <footer class="h-9 border-t border-gray-200 px-6 md:px-8 flex items-center justify-between bg-gray-50 text-xs text-gray-400 flex-shrink-0 relative z-20">
    <div class="flex items-center space-x-2">
      <!-- Active Online Status Indicator -->
      <span class="relative flex h-2 w-2">
        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
        <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
      </span>
      <span>Koneksi Laragon Apache: Aktif</span>
    </div>
    
    <div>
      &copy; 2026 POSRETAIL System. All rights reserved.
    </div>
  </footer>
</template>
