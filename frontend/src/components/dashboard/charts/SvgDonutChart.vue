<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  data: {
    type: Array,
    required: true // Format: [{ label: 'Cash', value: 53.24, color: '#c084fc' }]
  },
  size: {
    type: Number,
    default: 130
  }
})

const activeIndex = ref(-1)

const totalValue = computed(() => {
  return props.data.reduce((sum, item) => sum + item.value, 0)
})

const donutSegments = computed(() => {
  let accumulatedPercent = 0
  
  return props.data.map((item) => {
    const percent = (item.value / totalValue.value) * 100
    const strokeDasharray = `${percent} ${100 - percent}`
    const strokeDashoffset = -accumulatedPercent
    
    accumulatedPercent += percent
    
    return {
      ...item,
      strokeDasharray,
      strokeDashoffset,
      percent: percent.toFixed(1)
    }
  })
})

const hoverSegment = (index) => {
  activeIndex.value = index
}

const formatValue = (val) => {
  if (val >= 1000) {
    return (val / 1000).toFixed(1) + 'Rb'
  }
  return val.toFixed(1)
}
</script>

<template>
  <div class="w-full flex items-center justify-between space-x-5 py-2 select-none">
    <!-- SVG Donut -->
    <div class="relative flex items-center justify-center flex-shrink-0" :style="{ width: size + 'px', height: size + 'px' }">
      <svg
        viewBox="0 0 36 36"
        class="w-full h-full transform -rotate-90 drop-shadow-sm"
      >
        <!-- Background circle -->
        <circle
          cx="18"
          cy="18"
          r="15.915"
          fill="none"
          stroke="#f3f4f6"
          stroke-width="3"
        />

        <!-- Donut segments -->
        <circle
          v-for="(seg, idx) in donutSegments"
          :key="seg.label"
          cx="18"
          cy="18"
          r="15.915"
          fill="none"
          :stroke="seg.color || '#10b981'"
          stroke-width="4.2"
          :stroke-dasharray="seg.strokeDasharray"
          :stroke-dashoffset="seg.strokeDashoffset"
          class="transition-all duration-300 cursor-pointer origin-center hover:scale-[1.05]"
          :class="[activeIndex === idx ? 'stroke-[5px]' : '']"
          @mouseenter="hoverSegment(idx)"
          @mouseleave="hoverSegment(-1)"
        />
      </svg>

      <!-- Center text -->
      <div class="absolute inset-0 flex flex-col items-center justify-center text-center pointer-events-none">
        <span class="text-[9px] text-gray-400 font-bold uppercase tracking-wider">
          {{ activeIndex === -1 ? 'Total' : donutSegments[activeIndex].label }}
        </span>
        <span class="text-sm font-black text-gray-700 mt-0.5">
          {{ activeIndex === -1 ? formatValue(totalValue) : formatValue(donutSegments[activeIndex].value) }}
        </span>
        <span class="text-[9px] text-gray-500 font-bold mt-0.5" v-if="activeIndex !== -1">
          ({{ donutSegments[activeIndex].percent }}%)
        </span>
      </div>
    </div>

    <!-- Legends -->
    <div class="flex-1 flex flex-col justify-center space-y-2">
      <div
        v-for="(seg, idx) in donutSegments"
        :key="seg.label"
        class="flex items-center justify-between text-[11px] font-semibold p-1 px-2 border border-transparent rounded-lg cursor-pointer transition-all duration-200"
        :class="[activeIndex === idx ? 'bg-gray-100 border-gray-200' : '']"
        @mouseenter="hoverSegment(idx)"
        @mouseleave="hoverSegment(-1)"
      >
        <div class="flex items-center space-x-2 truncate">
          <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="{ backgroundColor: seg.color }"></span>
          <span class="text-gray-600 truncate">{{ seg.label }}</span>
        </div>
        <span class="text-gray-700 font-bold pl-3">{{ seg.percent }}%</span>
      </div>
    </div>
  </div>
</template>
