<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  data: {
    type: Array,
    required: true // Format: [{ label: 'Mon', value: 12000 }]
  },
  height: {
    type: Number,
    default: 120
  },
  mode: {
    type: String,
    default: 'area' // line, area, step
  },
  color: {
    type: String,
    default: '#10b981' // Emerald
  },
  gradientId: {
    type: String,
    default: 'emerald-gradient'
  }
})

const hoverIndex = ref(-1)

const width = 400
const paddingX = 20
const paddingY = 20

const maxValue = computed(() => {
  const values = props.data.map(item => item.value)
  return Math.max(...values) || 100
})

const points = computed(() => {
  if (!props.data.length) return []
  const count = props.data.length - 1
  const chartHeight = props.height - paddingY * 2
  const chartWidth = width - paddingX * 2

  return props.data.map((item, index) => {
    const x = paddingX + (index / count) * chartWidth
    const ratio = maxValue.value ? item.value / maxValue.value : 0
    const y = props.height - paddingY - ratio * chartHeight
    return { x, y, label: item.label, value: item.value }
  })
})

const linePath = computed(() => {
  if (!points.value.length) return ''
  
  if (props.mode === 'step') {
    let path = `M ${points.value[0].x} ${points.value[0].y}`
    for (let i = 1; i < points.value.length; i++) {
      const curr = points.value[i]
      path += ` H ${curr.x} V ${curr.y}`
    }
    return path
  }

  return points.value.reduce((path, pt, idx) => {
    return path + `${idx === 0 ? 'M' : 'L'} ${pt.x} ${pt.y} `
  }, '')
})

const areaPath = computed(() => {
  if (!points.value.length) return ''
  const startX = points.value[0].x
  const endX = points.value[points.value.length - 1].x
  const bottomY = props.height - paddingY

  let path = linePath.value

  if (props.mode === 'step') {
    path += ` H ${endX} V ${bottomY} H ${startX} Z`
  } else {
    path += ` L ${endX} ${bottomY} L ${startX} ${bottomY} Z`
  }
  return path
})
</script>

<template>
  <div class="w-full flex flex-col justify-end select-none">
    <div class="relative w-full" :style="{ height: height + 'px' }">
      <svg
        :viewBox="`0 0 ${width} ${height}`"
        class="w-full h-full overflow-visible"
      >
        <!-- Gradients -->
        <defs>
          <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" :stop-color="color" stop-opacity="0.25" />
            <stop offset="100%" :stop-color="color" stop-opacity="0.02" />
          </linearGradient>
        </defs>

        <!-- Horizontal grid lines -->
        <line
          :x1="paddingX"
          :y1="height - paddingY"
          :x2="width - paddingX"
          :y2="height - paddingY"
          stroke="#e5e7eb"
          stroke-width="1"
        />
        <line
          :x1="paddingX"
          :y1="paddingY"
          :x2="width - paddingX"
          :y2="paddingY"
          stroke="#e5e7eb"
          stroke-width="1"
          stroke-dasharray="3 3"
        />

        <!-- Area -->
        <path
          v-if="mode === 'area' || mode === 'step'"
          :d="areaPath"
          :fill="`url(#${gradientId})`"
          class="transition-all duration-300"
        />

        <!-- Line -->
        <path
          :d="linePath"
          fill="none"
          :stroke="color"
          stroke-width="2.5"
          stroke-linecap="round"
          stroke-linejoin="round"
          class="transition-all duration-300"
        />

        <!-- Dots -->
        <circle
          v-for="(pt, idx) in points"
          :key="idx"
          :cx="pt.x"
          :cy="pt.y"
          r="4"
          fill="white"
          :stroke="color"
          stroke-width="2.5"
          class="cursor-pointer transition-all duration-200"
          @mouseenter="hoverIndex = idx"
          @mouseleave="hoverIndex = -1"
        />

        <!-- Hover guide line -->
        <line
          v-if="hoverIndex !== -1"
          :x1="points[hoverIndex].x"
          :y1="paddingY"
          :x2="points[hoverIndex].x"
          :y2="height - paddingY"
          stroke="#9ca3af"
          stroke-opacity="0.3"
          stroke-width="1"
          stroke-dasharray="2 2"
        />
      </svg>

      <!-- Tooltip -->
      <div
        v-if="hoverIndex !== -1"
        class="absolute z-30 bg-white border border-gray-200 px-2.5 py-1.5 rounded-lg text-[10px] text-gray-700 shadow-lg pointer-events-none transition-all duration-100"
        :style="{
          left: (points[hoverIndex].x / width) * 100 + '%',
          top: points[hoverIndex].y - 35 + 'px',
          transform: 'translateX(-50%)'
        }"
      >
        <span class="font-bold block text-gray-400">{{ points[hoverIndex].label }}</span>
        <span class="font-extrabold block text-emerald-600 mt-0.5">Rp {{ points[hoverIndex].value.toLocaleString() }}</span>
      </div>
    </div>

    <!-- X Labels -->
    <div class="flex justify-between px-4 text-[9px] font-semibold text-gray-400 mt-1">
      <span v-for="item in data" :key="item.label">{{ item.label }}</span>
    </div>
  </div>
</template>
