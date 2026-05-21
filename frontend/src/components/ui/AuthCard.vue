<script setup>
defineProps({
  title: {
    type: String,
    required: true
  },
  subtitle: {
    type: String,
    default: ''
  }
})
</script>

<template>
  <div class="relative w-full max-w-md p-8 md:p-10 bg-white/95 backdrop-blur-xl border border-white/40 rounded-3xl shadow-2xl shadow-black/15 transition-all duration-300">
    <!-- Soft glow accents -->
    <div class="absolute -top-10 -left-10 w-40 h-40 bg-[#1a9e8f]/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-10 -right-10 w-40 h-40 bg-[#0d3b66]/10 rounded-full blur-3xl pointer-events-none"></div>
    
    <!-- Card Header -->
    <div class="relative flex flex-col items-center mb-8">
      <!-- Logo -->
      <div class="w-16 h-16 rounded-2xl flex items-center justify-center shadow-lg shadow-[#0d3b66]/20 mb-5 transform hover:rotate-6 transition-transform duration-300" style="background: linear-gradient(135deg, #0d3b66 0%, #147a83 50%, #1a9e8f 100%);">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
          stroke-width="2"
          stroke="white"
          class="w-8 h-8"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5h.007v.008H3.75V4.5zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 7.5h.007v.008H3.75V7.5zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 10.5h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 13.5h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM21 12c0 1.657-3.134 3-7 3s-7-1.343-7-3 3.134-3 7-3 7 1.343 7 3zm0 3c0 1.657-3.134 3-7 3s-7-1.343-7-3 3.134-3 7-3 7 1.343 7 3zm0 3c0 1.657-3.134 3-7 3s-7-1.343-7-3 3.134-3 7-3 7 1.343 7 3z" />
        </svg>
      </div>
      
      <h2 class="text-2xl font-bold text-[#0d3b66]">
        {{ title }}
      </h2>
      <p v-if="subtitle" class="mt-2 text-sm text-gray-500 text-center">
        {{ subtitle }}
      </p>
    </div>

    <!-- Card Content -->
    <div class="relative z-10">
      <slot></slot>
    </div>
  </div>
</template>
