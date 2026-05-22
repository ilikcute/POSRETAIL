<script setup lang="ts">
import { defineProps, defineEmits } from 'vue'

const props = defineProps<{ activeTab: string }>()
const emit = defineEmits<{ (e: 'update:activeTab', value: string): void }>()

import { ChartBarIcon, BuildingOfficeIcon, CubeIcon, TrashIcon } from '@heroicons/vue/24/solid'

const tabs = [
  { key: 'overview', label: 'Sales Overview', icon: ChartBarIcon },
  { key: 'store', label: 'Store Analysis', icon: BuildingOfficeIcon },
  { key: 'item', label: 'Item Analysis', icon: CubeIcon },
  { key: 'void', label: 'Void Transactions', icon: TrashIcon },
]

function selectTab(key: string) {
  emit('update:activeTab', key)
}
</script>

<template>
  <nav class="flex space-x-2 overflow-x-auto p-2 bg-white/10 backdrop-blur-lg rounded-xl" role="tablist">
    <button
      v-for="tab in tabs"
      :key="tab.key"
      @click="selectTab(tab.key)"
      :class="[
        'relative flex items-center px-5 py-2.5 rounded-lg text-sm font-semibold transition-all duration-300',
        props.activeTab === tab.key 
          ? 'text-indigo-400 bg-white/10 shadow-sm' 
          : 'text-gray-400 hover:text-white hover:bg-white/5'
      ]"
      role="tab"
      :aria-selected="props.activeTab === tab.key"
    >
      <component :is="tab.icon" class="w-4 h-4 mr-2" />
      {{ tab.label }}
      <span 
        v-if="props.activeTab === tab.key" 
        class="absolute bottom-0 left-0 right-0 h-0.5 bg-indigo-500 rounded-full animate-in fade-in zoom-in duration-300"
      ></span>
    </button>
  </nav>
</template>

<style scoped>
/* Optional custom scrollbar styling */
nav::-webkit-scrollbar {
  height: 6px;
}
nav::-webkit-scrollbar-thumb {
  background-color: rgba(255, 255, 255, 0.3);
  border-radius: 3px;
}
</style>
