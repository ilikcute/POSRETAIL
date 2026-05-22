<script setup lang="ts">
import { defineProps, defineEmits } from 'vue'

const props = defineProps<{ activeTab: string }>()
const emit = defineEmits<{ (e: 'update:activeTab', value: string): void }>()

const tabs = [
  { key: 'overview', label: 'Sales Overview' },
  { key: 'store', label: 'Store Analysis' },
  { key: 'item', label: 'Item Analysis' },
  { key: 'void', label: 'Void Transactions' },
]

function selectTab(key: string) {
  emit('update:activeTab', key)
}
</script>

<template>
  <nav class="flex flex-col w-48 bg-gray-800/70 backdrop-blur-lg p-4 space-y-2">
    <button
      v-for="tab in tabs"
      :key="tab.key"
      @click="selectTab(tab.key)"
      :class="[
        'w-full text-left px-3 py-2 rounded-md text-sm font-medium transition-colors',
        props.activeTab === tab.key ? 'bg-white text-gray-800' : 'text-gray-200 hover:bg-white/10',
      ]"
    >
      {{ tab.label }}
    </button>
  </nav>
</template>

<style scoped>
/* optional custom scrollbar styling */
nav::-webkit-scrollbar {
  width: 6px;
}
nav::-webkit-scrollbar-thumb {
  background-color: rgba(255, 255, 255, 0.3);
  border-radius: 3px;
}
</style>
