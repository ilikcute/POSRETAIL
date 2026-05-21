<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: ''
  },
  type: {
    type: String,
    default: 'text'
  },
  label: {
    type: String,
    required: true
  },
  placeholder: {
    type: String,
    default: ' ' // Default spasi diperlukan untuk peer-placeholder-shown floating label
  },
  error: {
    type: String,
    default: ''
  },
  required: {
    type: Boolean,
    default: false
  },
  disabled: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue'])

const showPassword = ref(false)

const inputType = computed(() => {
  if (props.type === 'password') {
    return showPassword.value ? 'text' : 'password'
  }
  return props.type
})

const onInput = (e) => {
  emit('update:modelValue', e.target.value)
}
</script>

<template>
  <div class="relative w-full mb-5 group">
    <div class="relative">
      <input
        :type="inputType"
        :value="modelValue"
        @input="onInput"
        :placeholder="placeholder"
        :required="required"
        :disabled="disabled"
        class="block w-full px-4 py-3.5 text-sm text-slate-100 bg-slate-900/50 rounded-xl border border-slate-700/60 appearance-none focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition-all duration-300 peer"
        :class="[
          error ? 'border-red-500 focus:ring-red-500/20 focus:border-red-500' : 'hover:border-slate-600/80',
          type === 'password' ? 'pr-12' : ''
        ]"
      />
      
      <!-- Floating Label -->
      <label
        class="absolute text-sm text-slate-400 duration-300 transform -translate-y-4 scale-75 top-2 z-10 origin-[0] bg-[#0F1322] px-2 peer-focus:px-2 peer-placeholder-shown:scale-100 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:top-1/2 peer-focus:top-2 peer-focus:scale-75 peer-focus:-translate-y-4 left-3 transition-all cursor-text peer-focus:text-emerald-400"
        :class="error ? 'text-red-400 peer-focus:text-red-400' : ''"
      >
        {{ label }} <span v-if="required" class="text-emerald-500">*</span>
      </label>

      <!-- Toggle Password Visibility (Eye Icon) -->
      <button
        v-if="type === 'password'"
        type="button"
        @click="showPassword = !showPassword"
        class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-emerald-400 transition-colors duration-200"
      >
        <!-- Icon Eye (Open) -->
        <svg
          v-if="showPassword"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
          stroke-width="1.8"
          stroke="currentColor"
          class="w-5 h-5"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.815 7.815L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
        </svg>
        <!-- Icon Eye (Closed) -->
        <svg
          v-else
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
          stroke-width="1.8"
          stroke="currentColor"
          class="w-5 h-5"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.644C3.483 8.343 7.242 5 12 5c4.757 0 8.517 3.343 9.964 6.974c.069.172.069.37 0 .542C20.517 15.657 16.757 19 12 19c-4.757 0-8.517-3.343-9.965-6.974z" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
      </button>
    </div>

    <!-- Error Message -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="transform -translate-y-2 opacity-0"
      enter-to-class="transform translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="transform translate-y-0 opacity-100"
      leave-to-class="transform -translate-y-2 opacity-0"
    >
      <p v-if="error" class="mt-1.5 text-xs text-red-500 pl-1">
        {{ error }}
      </p>
    </Transition>
  </div>
</template>
