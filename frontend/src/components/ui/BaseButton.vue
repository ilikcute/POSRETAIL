<script setup>
defineProps({
  loading: {
    type: Boolean,
    default: false
  },
  disabled: {
    type: Boolean,
    default: false
  },
  type: {
    type: String,
    default: 'button'
  }
})
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    class="relative w-full py-3.5 px-5 text-sm font-semibold text-white rounded-xl focus:outline-none focus:ring-2 focus:ring-[#1a9e8f]/50 shadow-lg shadow-[#0d3b66]/20 active:scale-[0.98] transition-all duration-200 cursor-pointer flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100"
    style="background: linear-gradient(135deg, #0d3b66 0%, #0e4d6e 40%, #147a83 70%, #1a9e8f 100%);"
  >
    <!-- Loading Spinner -->
    <svg
      v-if="loading"
      class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle
        class="opacity-25"
        cx="12"
        cy="12"
        r="10"
        stroke="currentColor"
        stroke-width="4"
      ></circle>
      <path
        class="opacity-75"
        fill="currentColor"
        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
      ></path>
    </svg>
    
    <!-- Button Content -->
    <span>
      <slot></slot>
    </span>
  </button>
</template>
