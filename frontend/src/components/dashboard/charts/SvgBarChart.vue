<script setup>
import { computed } from 'vue'

const props = defineProps({
  data: {
    type: Array,
    required: true // Format: [{ label: 'Jan', value: 12000, secondaryValue: 8000 }]
  },
  type: {
    type: String,
    default: 'vertical' // vertical, horizontal
  },
  height: {
    type: Number,
    default: 180
  },
  showGrid: {
    type: Boolean,
    default: true
  },
  hasSecondary: {
    type: Boolean,
    default: false
  }
})

const maxValue = computed(() => {
  let max = 0
  props.data.forEach(item => {
    if (item.value > max) max = item.value
    if (item.secondaryValue > max) max = item.secondaryValue
  })
  return max || 100
})

const gridYValues = computed(() => {
  const max = maxValue.value
  return [0, max * 0.5, max]
})

const formatGridValue = (val) => {
  if (val >= 1000) {
    return (val / 1000).toFixed(0) + 'Rb'
  }
  return val.toString()
}
</script>

<template>
  <div class="w-full flex flex-col justify-end select-none">
    <!-- 1. VERTICAL CHART -->
    <div v-if="type === 'vertical'" class="relative w-full flex flex-col justify-end" :style="{ height: height + 'px' }">
      <!-- Gridlines -->
      <div v-if="showGrid" class="absolute inset-0 flex flex-col justify-between pointer-events-none">
        <div v-for="gridVal in gridYValues.slice().reverse()" :key="gridVal" class="w-full flex items-center border-b border-dashed border-gray-200 h-0 relative">
          <span class="absolute -left-1 -translate-x-full text-[9px] font-semibold text-gray-400 pr-1.5">{{ formatGridValue(gridVal) }}</span>
        </div>
      </div>

      <!-- Bars -->
      <div class="relative z-10 w-full h-full flex items-end justify-between px-1">
        <div
          v-for="item in data"
          :key="item.label"
          class="flex-1 flex flex-col items-center group relative cursor-pointer"
        >
          <!-- Tooltip -->
          <div class="absolute bottom-full mb-2 bg-white border border-gray-200 px-2.5 py-1.5 rounded-lg text-[10px] text-gray-700 opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none shadow-lg z-30 whitespace-nowrap">
            <p class="font-bold text-gray-500">{{ item.label }}</p>
            <p class="text-emerald-600 font-extrabold mt-0.5">Sales: Rp {{ item.value.toLocaleString() }}</p>
            <p v-if="hasSecondary" class="text-rose-500 font-extrabold">Void: Rp {{ item.secondaryValue.toLocaleString() }}</p>
          </div>

          <!-- Bar columns -->
          <div class="w-full flex justify-center items-end space-x-1">
            <!-- Main Bar (Green) -->
            <div
              class="w-5 bg-gradient-to-t from-[#2e7d32] to-[#66bb6a] group-hover:from-[#43a047] group-hover:to-[#81c784] rounded-t-md transition-all duration-500"
              :style="{ height: (item.value / maxValue) * (height - 30) + 'px' }"
            ></div>

            <!-- Secondary Bar (Rose/Pink for Void) -->
            <div
              v-if="hasSecondary"
              class="w-5 bg-gradient-to-t from-[#c62828] to-[#ef5350] group-hover:from-[#e53935] group-hover:to-[#ef9a9a] rounded-t-md transition-all duration-500"
              :style="{ height: (item.secondaryValue / maxValue) * (height - 30) + 'px' }"
            ></div>
          </div>

          <!-- Label -->
          <span class="text-[9px] md:text-[10px] font-semibold text-gray-500 mt-2 group-hover:text-gray-800 transition-colors">{{ item.label }}</span>
        </div>
      </div>
    </div>

    <!-- 2. HORIZONTAL CHART -->
    <div v-else class="w-full flex flex-col space-y-3">
      <div v-for="item in data" :key="item.label" class="flex flex-col space-y-1.5 group cursor-pointer">
        <!-- Label and value -->
        <div class="flex items-center justify-between text-[11px] font-semibold text-gray-600 group-hover:text-gray-800 transition-colors">
          <span class="truncate pr-4 max-w-[70%]">{{ item.label }}</span>
          <span class="text-emerald-600 font-extrabold">{{ formatGridValue(item.value) }}</span>
        </div>
        
        <!-- Bar -->
        <div class="w-full h-3 bg-gray-100 border border-gray-200 rounded-full overflow-hidden">
          <div
            class="h-full bg-gradient-to-r from-[#2e7d32] to-[#66bb6a] group-hover:from-[#43a047] group-hover:to-[#81c784] rounded-full transition-all duration-500"
            :style="{ width: (item.value / maxValue) * 100 + '%' }"
          ></div>
        </div>
      </div>
    </div>
  </div>
</template>
