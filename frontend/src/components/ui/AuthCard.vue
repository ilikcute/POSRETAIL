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
  <div class="relative w-full max-w-md p-8 md:p-10 bg-slate-900/40 backdrop-blur-xl border border-slate-800/80 rounded-3xl shadow-2xl shadow-black/40 transition-all duration-300">
    <!-- Glowing background accent behind the card -->
    <div class="absolute -top-10 -left-10 w-40 h-40 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-10 -right-10 w-40 h-40 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>
    
    <!-- Card Header -->
    <div class="relative flex flex-col items-center mb-8">
      <!-- Premium POS Logo Placeholder -->
      <div class="w-16 h-16 bg-gradient-to-tr from-emerald-500 to-teal-400 rounded-2xl flex items-center justify-center shadow-lg shadow-emerald-500/20 mb-5 transform hover:rotate-6 transition-transform duration-300">
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
      
      <h2 class="text-2xl font-bold bg-gradient-to-r from-white to-slate-200 bg-clip-text text-transparent">
        {{ title }}
      </h2>
      <p v-if="subtitle" class="mt-2 text-sm text-slate-400 text-center">
        {{ subtitle }}
      </p>
    </div>

    <!-- Card Content -->
    <div class="relative z-10">
      <slot></slot>
    </div>
  </div>
</template>
