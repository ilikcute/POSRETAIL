<script setup>
import { computed } from 'vue'

const props = defineProps({
  label: {
    type: String,
    required: true
  },
  value: {
    type: [String, Number],
    required: true
  },
  type: {
    type: String,
    default: 'sales' // receipt, sales, quantity, cost, profit, special, amount, transactions
  }
})

const styles = computed(() => {
  switch (props.type) {
    case 'receipt':
      return {
        bg: 'bg-[#fce4ec]',
        iconBg: 'bg-[#f48fb1]',
        iconText: 'text-white',
        valueColor: 'text-gray-800'
      }
    case 'sales':
    case 'amount':
      return {
        bg: 'bg-[#e8f5e9]',
        iconBg: 'bg-[#66bb6a]',
        iconText: 'text-white',
        valueColor: 'text-gray-800'
      }
    case 'quantity':
      return {
        bg: 'bg-[#ede7f6]',
        iconBg: 'bg-[#ab47bc]',
        iconText: 'text-white',
        valueColor: 'text-gray-800'
      }
    case 'cost':
      return {
        bg: 'bg-[#fff8e1]',
        iconBg: 'bg-[#ffca28]',
        iconText: 'text-white',
        valueColor: 'text-gray-800'
      }
    case 'profit':
    case 'transactions':
      return {
        bg: 'bg-[#e0f2f1]',
        iconBg: 'bg-[#26a69a]',
        iconText: 'text-white',
        valueColor: 'text-gray-800'
      }
    case 'special':
    default:
      return {
        bg: 'bg-[#e0f7fa]',
        iconBg: 'bg-[#00acc1]',
        iconText: 'text-white',
        valueColor: 'text-gray-800'
      }
  }
})
</script>

<template>
  <div
    class="flex-1 min-w-[120px] p-4 rounded-2xl flex flex-col items-center justify-center transition-all duration-200 hover:shadow-md relative overflow-hidden border border-gray-100"
    :class="styles.bg"
  >
    <!-- Icon -->
    <div
      class="w-9 h-9 rounded-lg flex items-center justify-center mb-2.5 shadow-sm"
      :class="[styles.iconBg, styles.iconText]"
    >
      <!-- Icon: Receipt -->
      <svg v-if="type === 'receipt'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4.5 h-4.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
      </svg>

      <!-- Icon: Sales / Amount -->
      <svg v-else-if="type === 'sales' || type === 'amount'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4.5 h-4.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>

      <!-- Icon: Quantity -->
      <svg v-else-if="type === 'quantity'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4.5 h-4.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z" />
      </svg>

      <!-- Icon: Cost -->
      <svg v-else-if="type === 'cost'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4.5 h-4.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581a1.125 1.125 0 001.591 0l4.318-4.318a1.125 1.125 0 000-1.591l-9.581-9.581A2.25 2.25 0 009.568 3z" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" />
      </svg>

      <!-- Icon: Profit / Transactions -->
      <svg v-else-if="type === 'profit' || type === 'transactions'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4.5 h-4.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.746 3.746 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.746 3.746 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" />
      </svg>

      <!-- Icon: Special (%) -->
      <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4.5 h-4.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z" />
      </svg>
    </div>

    <!-- Value & Label -->
    <span class="text-xl md:text-2xl font-black tracking-tight" :class="styles.valueColor">
      {{ value }}
    </span>
    <span class="text-[10px] text-gray-500 font-semibold uppercase tracking-wider mt-1 text-center">
      {{ label }}
    </span>
  </div>
</template>
