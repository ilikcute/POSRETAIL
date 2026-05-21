<script setup>
import { computed } from 'vue'

const props = defineProps({
  activeTab: {
    type: String,
    required: true
  }
})

const title = computed(() => {
  switch (props.activeTab) {
    case 'overview':
      return { main: 'RETAIL SALES', sub: 'overview' }
    case 'store':
      return { main: 'STORE SALES', sub: 'analysis' }
    case 'item':
      return { main: 'ITEM SALES', sub: 'analysis' }
    case 'void':
      return { main: 'VOIDED', sub: 'transactions' }
    default:
      return { main: 'RETAIL SALES', sub: 'overview' }
  }
})
</script>

<template>
  <header class="h-16 border-b border-gray-200 px-6 md:px-8 flex items-center justify-between bg-white relative z-20">
    <!-- Title Section -->
    <div class="flex items-baseline space-x-2">
      <h1 class="text-lg md:text-xl font-extrabold tracking-wide text-[#0d3b66] uppercase">
        {{ title.main }}
      </h1>
      <span class="text-base md:text-lg font-medium text-[#1a9e8f] lowercase italic">
        {{ title.sub }}
      </span>
    </div>

    <!-- Branding Section (Global Data 365) -->
    <div class="flex items-center space-x-2.5">
      <!-- Logo -->
      <div class="w-8 h-8 bg-gradient-to-tr from-[#0d3b66] to-[#1a9e8f] rounded-lg flex items-center justify-center shadow-sm">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" class="w-4 h-4">
          <path d="M18.375 2.25c-1.035 0-1.875.84-1.875 1.875v15.75c0 1.035.84 1.875 1.875 1.875h.75c1.035 0 1.875-.84 1.875-1.875V4.125c0-1.036-.84-1.875-1.875-1.875h-.75zM9.75 8.625c0-1.036.84-1.875 1.875-1.875h.75c1.036 0 1.875.84 1.875 1.875v9.375c0 1.036-.84 1.875-1.875 1.875h-.75a1.875 1.875 0 01-1.875-1.875V8.625zM3 15c0-1.035.84-1.875 1.875-1.875h.75c1.036 0 1.875.84 1.875 1.875v3c0 1.035-.84 1.875-1.875 1.875h-.75A1.875 1.875 0 013 18v-3z" />
        </svg>
      </div>

      <div class="hidden sm:block text-right">
        <div class="flex items-center space-x-1 justify-end">
          <span class="text-sm font-black text-gray-700 tracking-tight">Global Data</span>
          <span class="text-sm font-bold text-[#1a9e8f]">365</span>
        </div>
        <p class="text-[9px] text-gray-400 font-medium tracking-wider uppercase">Your Partner in Digital Future</p>
      </div>
    </div>
  </header>
</template>
