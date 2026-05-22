<script setup>
import { ref, onMounted, watch } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const props = defineProps({
  activeTab: {
    type: String,
    required: true
  }
})

const toast = useToast()

// UI state
const loading = ref(false)
const submitting = ref(false)
const activeSection = ref('company') // company, region, theme, loyalty, security

// Available timezone and language options for selection
const timezoneOptions = [
  'Asia/Jakarta', 'Asia/Singapore', 'Asia/Kuala_Lumpur', 'Asia/Tokyo', 
  'Europe/London', 'America/New_York', 'UTC'
]

const languageOptions = [
  { code: 'id', name: 'Bahasa Indonesia' },
  { code: 'en', name: 'English' },
  { code: 'ms', name: 'Melayu' }
]

const currencyOptions = [
  { code: 'IDR', name: 'Rupiah (IDR)' },
  { code: 'USD', name: 'US Dollar ($)' },
  { code: 'SGD', name: 'Singapore Dollar (S$)' },
  { code: 'MYR', name: 'Malaysian Ringgit (RM)' }
]

// Setting Form State
const settings = ref({
  // Company details
  company_name: '',
  company_address: '',
  company_phone: '',
  company_email: '',
  company_tax_rate: 0,
  
  // Region & currency
  default_currency: 'IDR',
  currency_symbol: 'Rp',
  thousand_separator: '.',
  decimal_separator: ',',
  default_language: 'id',
  timezone: 'Asia/Jakarta',
  date_format: 'Y-m-d',
  
  // Display & theme
  theme_mode: 'dark',
  primary_color: '#6366F1',
  sidebar_color: '#1E1E2F',
  
  // Loyalty Points
  loyalty_spend_per_point: 10000,
  loyalty_point_value: 100,
  
  // Security
  drawer_safety_limit: 5000000,
  password_min_length: 8,
  
  // Receipts
  receipt_header: '',
  receipt_footer: ''
})

// Validation Errors
const errors = ref({
  company_name: '',
  company_address: '',
  company_phone: '',
  company_email: '',
  company_tax_rate: '',
  default_currency: '',
  currency_symbol: '',
  thousand_separator: '',
  decimal_separator: '',
  default_language: '',
  timezone: '',
  date_format: '',
  theme_mode: '',
  primary_color: '',
  sidebar_color: '',
  loyalty_spend_per_point: '',
  loyalty_point_value: '',
  drawer_safety_limit: '',
  password_min_length: '',
  receipt_header: '',
  receipt_footer: ''
})

// Watch sidebar activeTab prop to automatically switch sub-sections
watch(() => props.activeTab, (newTab) => {
  if ([
    'Company Settings', 'Company Profile', 'Outlet Settings', 'Outlet Profile', 
    'Department Settings', 'Department Profile', 'Employee Settings', 
    'Employee Profile', 'Branch Settings'
  ].includes(newTab)) {
    activeSection.value = 'company'
  } else if (['Language Settings', 'Currency Settings'].includes(newTab)) {
    activeSection.value = 'region'
  } else if (['Theme Settings'].includes(newTab)) {
    activeSection.value = 'theme'
  } else if (['User Management', 'Role Management', 'Privilege Management', 'Menu Management', 'Form Management', 'Button Management'].includes(newTab)) {
    activeSection.value = 'security'
  }
}, { immediate: true })

// Fetch settings from API
const fetchSettings = async () => {
  loading.value = true
  try {
    const response = await api.get('/settings')
    const data = response.data?.data || {}
    
    // Flatten grouped settings into flat form state keys
    Object.keys(data).forEach(group => {
      data[group].forEach(item => {
        if (settings.value[item.key] !== undefined) {
          settings.value[item.key] = item.value
        }
      })
    })
  } catch (error) {
    console.error('Error fetching settings:', error)
    toast.error('Failed to load system configurations.')
  } finally {
    loading.value = false
  }
}

// Reset error values
const clearErrors = () => {
  Object.keys(errors.value).forEach(key => {
    errors.value[key] = ''
  })
}

// Client Side Validation
const validateSettings = () => {
  clearErrors()
  let isValid = true

  // Company Profile validations
  if (activeSection.value === 'company') {
    if (!settings.value.company_name?.trim()) {
      errors.value.company_name = 'Company name is required.'
      isValid = false
    }
    if (!settings.value.company_phone?.trim()) {
      errors.value.company_phone = 'Company contact phone is required.'
      isValid = false
    }
    if (!settings.value.company_email?.trim()) {
      errors.value.company_email = 'Company email address is required.'
      isValid = false
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(settings.value.company_email)) {
      errors.value.company_email = 'Invalid email address format.'
      isValid = false
    }
    if (settings.value.company_tax_rate === null || settings.value.company_tax_rate === '') {
      errors.value.company_tax_rate = 'Tax rate is required.'
      isValid = false
    } else if (parseFloat(settings.value.company_tax_rate) < 0 || parseFloat(settings.value.company_tax_rate) > 100) {
      errors.value.company_tax_rate = 'Tax rate must be between 0 and 100%.'
      isValid = false
    }
  }

  // Region & Format validations
  if (activeSection.value === 'region') {
    if (!settings.value.default_currency?.trim()) {
      errors.value.default_currency = 'Default currency code is required.'
      isValid = false
    }
    if (!settings.value.currency_symbol?.trim()) {
      errors.value.currency_symbol = 'Currency symbol is required.'
      isValid = false
    }
    if (!settings.value.thousand_separator?.trim()) {
      errors.value.thousand_separator = 'Thousand separator is required.'
      isValid = false
    }
    if (!settings.value.decimal_separator?.trim()) {
      errors.value.decimal_separator = 'Decimal separator is required.'
      isValid = false
    } else if (settings.value.decimal_separator === settings.value.thousand_separator) {
      errors.value.decimal_separator = 'Thousand and decimal separators must differ.'
      isValid = false
    }
  }

  // Theme Appearance validations
  if (activeSection.value === 'theme') {
    const hexPattern = /^#[0-9A-F]{6}$/i
    if (!settings.value.primary_color?.trim()) {
      errors.value.primary_color = 'Primary theme color is required.'
      isValid = false
    } else if (!hexPattern.test(settings.value.primary_color)) {
      errors.value.primary_color = 'Must be a valid HEX color code (e.g. #6366F1).'
      isValid = false
    }
    if (!settings.value.sidebar_color?.trim()) {
      errors.value.sidebar_color = 'Sidebar color is required.'
      isValid = false
    } else if (!hexPattern.test(settings.value.sidebar_color)) {
      errors.value.sidebar_color = 'Must be a valid HEX color code (e.g. #1E1E2F).'
      isValid = false
    }
  }

  // CRM Loyalty validations
  if (activeSection.value === 'loyalty') {
    if (settings.value.loyalty_spend_per_point === null || settings.value.loyalty_spend_per_point === '') {
      errors.value.loyalty_spend_per_point = 'Loyalty spend per point is required.'
      isValid = false
    } else if (parseFloat(settings.value.loyalty_spend_per_point) < 0) {
      errors.value.loyalty_spend_per_point = 'Spend per point must be greater than or equal to 0.'
      isValid = false
    }
    if (settings.value.loyalty_point_value === null || settings.value.loyalty_point_value === '') {
      errors.value.loyalty_point_value = 'Point conversion value is required.'
      isValid = false
    } else if (parseFloat(settings.value.loyalty_point_value) < 0) {
      errors.value.loyalty_point_value = 'Point value must be greater than or equal to 0.'
      isValid = false
    }
  }

  // Security & drawers validations
  if (activeSection.value === 'security') {
    if (settings.value.drawer_safety_limit === null || settings.value.drawer_safety_limit === '') {
      errors.value.drawer_safety_limit = 'Drawer safety limit is required.'
      isValid = false
    } else if (parseFloat(settings.value.drawer_safety_limit) < 0) {
      errors.value.drawer_safety_limit = 'Safety limit must be greater than or equal to 0.'
      isValid = false
    }
    if (settings.value.password_min_length === null || settings.value.password_min_length === '') {
      errors.value.password_min_length = 'Minimum password length is required.'
      isValid = false
    } else {
      const len = parseInt(settings.value.password_min_length)
      if (isNaN(len) || len < 4 || len > 32) {
        errors.value.password_min_length = 'Password length must be between 4 and 32.'
        isValid = false
      }
    }
  }

  return isValid
}

// Submit setting batch updates to server via Axios
const handleSave = async () => {
  if (!validateSettings()) {
    toast.error('Validation failed. Please correct the highlighted errors.')
    return
  }

  submitting.value = true
  
  // Format payload according to active section to prevent overwriting everything
  const selectedKeys = []
  if (activeSection.value === 'company') {
    selectedKeys.push('company_name', 'company_address', 'company_phone', 'company_email', 'company_tax_rate', 'receipt_header', 'receipt_footer')
  } else if (activeSection.value === 'region') {
    selectedKeys.push('default_currency', 'currency_symbol', 'thousand_separator', 'decimal_separator', 'default_language', 'timezone', 'date_format')
  } else if (activeSection.value === 'theme') {
    selectedKeys.push('theme_mode', 'primary_color', 'sidebar_color')
  } else if (activeSection.value === 'loyalty') {
    selectedKeys.push('loyalty_spend_per_point', 'loyalty_point_value')
  } else if (activeSection.value === 'security') {
    selectedKeys.push('drawer_safety_limit', 'password_min_length')
  }

  const payload = { settings: {} }
  selectedKeys.forEach(key => {
    payload.settings[key] = settings.value[key]
  });

  try {
    await api.post('/settings', payload)
    toast.success('Configuration saved successfully!')
    fetchSettings()
  } catch (error) {
    console.error('Error saving settings:', error)
    if (error.response && error.response.status === 422) {
      const serverErrors = error.response.data.errors || {}
      Object.keys(serverErrors).forEach(field => {
        // Handle nested settings error names, e.g. settings.company_name
        const key = field.replace('settings.', '')
        if (errors.value[key] !== undefined) {
          errors.value[key] = serverErrors[field][0]
        }
      })
      toast.error('Server validation failed.')
    } else {
      toast.error(error.response?.data?.message || 'Error occurred while saving configurations.')
    }
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Premium Header Area -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-gradient-to-r from-slate-900 to-slate-800 p-6 rounded-2xl border border-slate-700 shadow-xl">
      <div>
        <h2 class="text-xl font-extrabold text-white tracking-wide">System Settings & Profile Configuration</h2>
        <p class="text-xs text-slate-400 mt-1">Configure company profile, regional formats, loyalty point calculations, styles, and safety limits.</p>
      </div>
      <div class="px-3.5 py-1.5 rounded-full text-xs font-black uppercase tracking-wider bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
        Control Panel
      </div>
    </div>

    <!-- Layout Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      
      <!-- Side Navigation / Tabs -->
      <div class="lg:col-span-1 bg-white border border-gray-100 p-4 rounded-2xl shadow-sm space-y-1">
        <h3 class="text-xs font-black text-gray-400 uppercase tracking-widest px-3 mb-3">Settings Categories</h3>
        
        <button 
          @click="activeSection = 'company'"
          class="w-full text-left px-4 py-3 rounded-xl text-xs font-bold transition-all flex items-center gap-3 cursor-pointer"
          :class="activeSection === 'company' ? 'bg-indigo-50 text-indigo-600 shadow-sm border-l-4 border-indigo-600' : 'text-gray-600 hover:bg-gray-50'"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4.5 h-4.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
          </svg>
          Company & Profile
        </button>

        <button 
          @click="activeSection = 'region'"
          class="w-full text-left px-4 py-3 rounded-xl text-xs font-bold transition-all flex items-center gap-3 cursor-pointer"
          :class="activeSection === 'region' ? 'bg-indigo-50 text-indigo-600 shadow-sm border-l-4 border-indigo-600' : 'text-gray-600 hover:bg-gray-50'"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4.5 h-4.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-.778.099-1.533.284-2.253" />
          </svg>
          Region & Formats
        </button>

        <button 
          @click="activeSection = 'theme'"
          class="w-full text-left px-4 py-3 rounded-xl text-xs font-bold transition-all flex items-center gap-3 cursor-pointer"
          :class="activeSection === 'theme' ? 'bg-indigo-50 text-indigo-600 shadow-sm border-l-4 border-indigo-600' : 'text-gray-600 hover:bg-gray-50'"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4.5 h-4.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 01-1.622-3.395m3.02 0a15.999 15.999 0 01-1.622-3.39M17.804 5.733a9.746 9.746 0 11-15.607 10.45A4.5 4.5 0 008.4 20.25a9.74 9.74 0 009.404-14.517z" />
          </svg>
          Theme & Display
        </button>

        <button 
          @click="activeSection = 'loyalty'"
          class="w-full text-left px-4 py-3 rounded-xl text-xs font-bold transition-all flex items-center gap-3 cursor-pointer"
          :class="activeSection === 'loyalty' ? 'bg-indigo-50 text-indigo-600 shadow-sm border-l-4 border-indigo-600' : 'text-gray-600 hover:bg-gray-50'"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4.5 h-4.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625A2.625 2.625 0 1114.625 7.5H12m0 0V21m-8.625-9.75h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
          </svg>
          CRM & Loyalty
        </button>

        <button 
          @click="activeSection = 'security'"
          class="w-full text-left px-4 py-3 rounded-xl text-xs font-bold transition-all flex items-center gap-3 cursor-pointer"
          :class="activeSection === 'security' ? 'bg-indigo-50 text-indigo-600 shadow-sm border-l-4 border-indigo-600' : 'text-gray-600 hover:bg-gray-50'"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4.5 h-4.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
          </svg>
          Security & Drawer Limit
        </button>
      </div>

      <!-- Settings Configurations Card -->
      <div class="lg:col-span-3 bg-white border border-gray-100 p-6 rounded-2xl shadow-sm flex flex-col justify-between">
        
        <!-- Loading overlay -->
        <div v-if="loading" class="py-20 flex flex-col items-center justify-center space-y-3">
          <svg class="animate-spin h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span class="text-sm font-semibold text-gray-500">Loading configurations...</span>
        </div>

        <form v-else @submit.prevent="handleSave" class="space-y-6">
          
          <!-- Category 1: Company Profile -->
          <div v-if="activeSection === 'company'" class="space-y-5">
            <h3 class="text-md font-bold text-gray-800 border-b pb-2 flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
              Company & Store Profile
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Company Name -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Company Name *</label>
                <input 
                  v-model="settings.company_name"
                  type="text"
                  class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors"
                  :class="errors.company_name ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
                />
                <p v-if="errors.company_name" class="text-xs text-red-500 font-medium mt-1">{{ errors.company_name }}</p>
              </div>

              <!-- Company Email -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Contact Email *</label>
                <input 
                  v-model="settings.company_email"
                  type="text"
                  class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors"
                  :class="errors.company_email ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
                />
                <p v-if="errors.company_email" class="text-xs text-red-500 font-medium mt-1">{{ errors.company_email }}</p>
              </div>

              <!-- Company Phone -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Contact Phone *</label>
                <input 
                  v-model="settings.company_phone"
                  type="text"
                  class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors"
                  :class="errors.company_phone ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
                />
                <p v-if="errors.company_phone" class="text-xs text-red-500 font-medium mt-1">{{ errors.company_phone }}</p>
              </div>

              <!-- Tax Rate -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Default Tax Rate (%) *</label>
                <input 
                  v-model.number="settings.company_tax_rate"
                  type="number"
                  step="0.01"
                  class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors"
                  :class="errors.company_tax_rate ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
                />
                <p v-if="errors.company_tax_rate" class="text-xs text-red-500 font-medium mt-1">{{ errors.company_tax_rate }}</p>
              </div>
            </div>

            <!-- Address -->
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Office Address</label>
              <textarea 
                v-model="settings.company_address"
                rows="2"
                class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors resize-none border-gray-200"
              ></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
              <!-- Receipt Header -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Receipt Print Header</label>
                <input 
                  v-model="settings.receipt_header"
                  type="text"
                  placeholder="e.g. Thanks for shopping!"
                  class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors border-gray-200"
                />
              </div>

              <!-- Receipt Footer -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Receipt Print Footer</label>
                <input 
                  v-model="settings.receipt_footer"
                  type="text"
                  placeholder="e.g. Keep invoice for return within 7 days"
                  class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors border-gray-200"
                />
              </div>
            </div>
          </div>

          <!-- Category 2: Region & Formatting -->
          <div v-if="activeSection === 'region'" class="space-y-5">
            <h3 class="text-md font-bold text-gray-800 border-b pb-2 flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
              Regional Locale & Financial Formats
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Default Currency -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Default Currency *</label>
                <select 
                  v-model="settings.default_currency"
                  class="w-full px-4 py-2.5 border rounded-xl text-sm bg-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors"
                  :class="errors.default_currency ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
                >
                  <option v-for="c in currencyOptions" :key="c.code" :value="c.code">{{ c.name }}</option>
                </select>
                <p v-if="errors.default_currency" class="text-xs text-red-500 font-medium mt-1">{{ errors.default_currency }}</p>
              </div>

              <!-- Currency Symbol -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Currency Symbol *</label>
                <input 
                  v-model="settings.currency_symbol"
                  type="text"
                  class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors"
                  :class="errors.currency_symbol ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
                />
                <p v-if="errors.currency_symbol" class="text-xs text-red-500 font-medium mt-1">{{ errors.currency_symbol }}</p>
              </div>

              <!-- Thousand Separator -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Thousand Separator *</label>
                <input 
                  v-model="settings.thousand_separator"
                  type="text"
                  maxlength="1"
                  class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors"
                  :class="errors.thousand_separator ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
                />
                <p v-if="errors.thousand_separator" class="text-xs text-red-500 font-medium mt-1">{{ errors.thousand_separator }}</p>
              </div>

              <!-- Decimal Separator -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Decimal Separator *</label>
                <input 
                  v-model="settings.decimal_separator"
                  type="text"
                  maxlength="1"
                  class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors"
                  :class="errors.decimal_separator ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
                />
                <p v-if="errors.decimal_separator" class="text-xs text-red-500 font-medium mt-1">{{ errors.decimal_separator }}</p>
              </div>

              <!-- Language -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Default Language *</label>
                <select 
                  v-model="settings.default_language"
                  class="w-full px-4 py-2.5 border rounded-xl text-sm bg-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors border-gray-200"
                >
                  <option v-for="l in languageOptions" :key="l.code" :value="l.code">{{ l.name }}</option>
                </select>
              </div>

              <!-- Timezone -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Timezone *</label>
                <select 
                  v-model="settings.timezone"
                  class="w-full px-4 py-2.5 border rounded-xl text-sm bg-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors border-gray-200"
                >
                  <option v-for="t in timezoneOptions" :key="t" :value="t">{{ t }}</option>
                </select>
              </div>

              <!-- Date Format -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Date Format *</label>
                <input 
                  v-model="settings.date_format"
                  type="text"
                  placeholder="e.g. Y-m-d"
                  class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors border-gray-200"
                />
              </div>
            </div>
          </div>

          <!-- Category 3: Theme Appearance -->
          <div v-if="activeSection === 'theme'" class="space-y-5">
            <h3 class="text-md font-bold text-gray-800 border-b pb-2 flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
              Theme Mode & Appearance
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <!-- Theme mode -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Theme Mode</label>
                <select 
                  v-model="settings.theme_mode"
                  class="w-full px-4 py-2.5 border rounded-xl text-sm bg-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors border-gray-200"
                >
                  <option value="light">Light Mode</option>
                  <option value="dark">Dark Mode</option>
                </select>
              </div>

              <!-- Primary Color -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Primary UI Color</label>
                <div class="flex items-center gap-2">
                  <input 
                    v-model="settings.primary_color"
                    type="color"
                    class="w-10 h-10 border-0 p-0 rounded-lg cursor-pointer shadow-sm"
                  />
                  <input 
                    v-model="settings.primary_color"
                    type="text"
                    maxlength="7"
                    class="flex-1 px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors"
                    :class="errors.primary_color ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
                  />
                </div>
                <p v-if="errors.primary_color" class="text-xs text-red-500 font-medium mt-1">{{ errors.primary_color }}</p>
              </div>

              <!-- Sidebar Color -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Sidebar Color</label>
                <div class="flex items-center gap-2">
                  <input 
                    v-model="settings.sidebar_color"
                    type="color"
                    class="w-10 h-10 border-0 p-0 rounded-lg cursor-pointer shadow-sm"
                  />
                  <input 
                    v-model="settings.sidebar_color"
                    type="text"
                    maxlength="7"
                    class="flex-1 px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors"
                    :class="errors.sidebar_color ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
                  />
                </div>
                <p v-if="errors.sidebar_color" class="text-xs text-red-500 font-medium mt-1">{{ errors.sidebar_color }}</p>
              </div>
            </div>
          </div>

          <!-- Category 4: Loyalty Program -->
          <div v-if="activeSection === 'loyalty'" class="space-y-5">
            <h3 class="text-md font-bold text-gray-800 border-b pb-2 flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
              CRM & Loyalty Membership calculations
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Spend per point -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Purchase Spend Per Point *</label>
                <input 
                  v-model.number="settings.loyalty_spend_per_point"
                  type="number"
                  class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors"
                  :class="errors.loyalty_spend_per_point ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
                />
                <p v-if="errors.loyalty_spend_per_point" class="text-xs text-red-500 font-medium mt-1">{{ errors.loyalty_spend_per_point }}</p>
              </div>

              <!-- Point value -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Monetary Value of 1 Point *</label>
                <input 
                  v-model.number="settings.loyalty_point_value"
                  type="number"
                  class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors"
                  :class="errors.loyalty_point_value ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
                />
                <p v-if="errors.loyalty_point_value" class="text-xs text-red-500 font-medium mt-1">{{ errors.loyalty_point_value }}</p>
              </div>
            </div>
          </div>

          <!-- Category 5: Security & System Limits -->
          <div v-if="activeSection === 'security'" class="space-y-5">
            <h3 class="text-md font-bold text-gray-800 border-b pb-2 flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-indigo-500"></span>
              Security Policies & Drawer Safe Limits
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Drawer Safety Limit -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Drawer Cash Safety Limit *</label>
                <input 
                  v-model.number="settings.drawer_safety_limit"
                  type="number"
                  class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors"
                  :class="errors.drawer_safety_limit ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
                />
                <p v-if="errors.drawer_safety_limit" class="text-xs text-red-500 font-medium mt-1">{{ errors.drawer_safety_limit }}</p>
              </div>

              <!-- Password Minimum Length -->
              <div class="space-y-1">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Minimum Password Length *</label>
                <input 
                  v-model.number="settings.password_min_length"
                  type="number"
                  min="4"
                  max="32"
                  class="w-full px-4 py-2.5 border rounded-xl text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-colors"
                  :class="errors.password_min_length ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
                />
                <p v-if="errors.password_min_length" class="text-xs text-red-500 font-medium mt-1">{{ errors.password_min_length }}</p>
              </div>
            </div>
          </div>

          <!-- Submit Button -->
          <div class="flex justify-end pt-4 border-t border-gray-100">
            <button 
              type="submit" 
              :disabled="submitting"
              class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black uppercase tracking-wider flex items-center justify-center gap-2 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed shadow-md hover:shadow-lg active:scale-98"
            >
              <svg v-if="submitting" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>{{ submitting ? 'Saving Changes...' : 'Save Settings' }}</span>
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>
</template>
