<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()

// States
const promotions = ref([])
const loading = ref(false)
const submitting = ref(false)
const searchQuery = ref('')

// Pagination
const currentPage = ref(1)
const pageSize = ref(10)
const pageSizeOptions = [5, 10, 25, 50]

// Form State
const isEditMode = ref(false)
const editId = ref(null)
const form = ref({
  code: '',
  name: '',
  description: '',
  type: 'percentage',
  value: '',
  min_purchase_amount: '0',
  max_discount_amount: '',
  start_date: '',
  end_date: '',
  is_active: true
})

// Error State
const errors = ref({
  code: '',
  name: '',
  description: '',
  type: '',
  value: '',
  min_purchase_amount: '',
  max_discount_amount: '',
  start_date: '',
  end_date: ''
})

// Fetch all promotions from backend
const fetchPromotions = async () => {
  loading.value = true
  try {
    const response = await api.get('/promotions')
    if (response.data && response.data.data) {
      promotions.value = response.data.data
    } else {
      promotions.value = response.data || []
    }
  } catch (error) {
    console.error('Error fetching promotions:', error)
    toast.error('Failed to load promotions catalog.')
  } finally {
    loading.value = false
  }
}

// Generate random unique promotion code coupon
const generatePromoCode = () => {
  errors.value.code = ''
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'
  let result = 'PROMO-'
  for (let i = 0; i < 6; i++) {
    result += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  form.value.code = result
  toast.info(`Generated code: ${result}`)
}

// Client-side validations
const validateForm = () => {
  let isValid = true
  errors.value = {
    code: '',
    name: '',
    description: '',
    type: '',
    value: '',
    min_purchase_amount: '',
    max_discount_amount: '',
    start_date: '',
    end_date: ''
  }

  // Code validation
  if (!form.value.code || form.value.code.trim() === '') {
    errors.value.code = 'Promotion code is required.'
    isValid = false
  } else if (form.value.code.length > 50) {
    errors.value.code = 'Promotion code must not exceed 50 characters.'
    isValid = false
  }

  // Name validation
  if (!form.value.name || form.value.name.trim() === '') {
    errors.value.name = 'Promotion name is required.'
    isValid = false
  } else if (form.value.name.length < 3) {
    errors.value.name = 'Promotion name must be at least 3 characters.'
    isValid = false
  } else if (form.value.name.length > 255) {
    errors.value.name = 'Promotion name must not exceed 255 characters.'
    isValid = false
  }

  // Type validation
  if (!form.value.type) {
    errors.value.type = 'Promotion type is required.'
    isValid = false
  }

  // Value validation
  const numValue = parseFloat(form.value.value)
  if (form.value.value === '' || isNaN(numValue)) {
    errors.value.value = 'Promotion value is required and must be numeric.'
    isValid = false
  } else if (numValue < 0) {
    errors.value.value = 'Value must be a positive number.'
    isValid = false
  } else if (form.value.type === 'percentage' && numValue > 100) {
    errors.value.value = 'Percentage value cannot exceed 100%.'
    isValid = false
  }

  // Min purchase amount validation
  if (form.value.min_purchase_amount !== '') {
    const numMin = parseFloat(form.value.min_purchase_amount)
    if (isNaN(numMin) || numMin < 0) {
      errors.value.min_purchase_amount = 'Minimum purchase amount must be a positive number.'
      isValid = false
    }
  }

  // Max discount amount validation
  if (form.value.type === 'percentage' && form.value.max_discount_amount !== '') {
    const numMax = parseFloat(form.value.max_discount_amount)
    if (isNaN(numMax) || numMax < 0) {
      errors.value.max_discount_amount = 'Maximum discount amount must be a positive number.'
      isValid = false
    }
  }

  // Date range validation
  if (form.value.start_date && form.value.end_date) {
    const start = new Date(form.value.start_date)
    const end = new Date(form.value.end_date)
    if (end < start) {
      errors.value.end_date = 'End date must be after or equal to the start date.'
      isValid = false
    }
  }

  return isValid
}

// Reset form
const resetForm = () => {
  isEditMode.value = false
  editId.value = null
  form.value = {
    code: '',
    name: '',
    description: '',
    type: 'percentage',
    value: '',
    min_purchase_amount: '0',
    max_discount_amount: '',
    start_date: '',
    end_date: '',
    is_active: true
  }
  errors.value = {
    code: '',
    name: '',
    description: '',
    type: '',
    value: '',
    min_purchase_amount: '',
    max_discount_amount: '',
    start_date: '',
    end_date: ''
  }
}

// Format Date for datetime-local input (YYYY-MM-DDThh:mm)
const formatForInput = (dateStr) => {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  if (isNaN(date.getTime())) return ''
  
  const tzOffset = date.getTimezoneOffset() * 60000
  const localISOTime = (new Date(date.getTime() - tzOffset)).toISOString().slice(0, 16)
  return localISOTime
}

// Format Date for Display (DD MMM YYYY HH:mm)
const formatForDisplay = (dateStr) => {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  if (isNaN(date.getTime())) return '-'
  return date.toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Currency Formatter
const formatCurrency = (val) => {
  if (val === null || val === undefined || val === '') return '-'
  const parsed = parseFloat(val)
  if (isNaN(parsed)) return '-'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0
  }).format(parsed)
}

// Submit via Axios POST/PUT
const handleSubmit = async () => {
  if (!validateForm()) {
    toast.error('Please fix the highlighted errors before saving.')
    return
  }

  submitting.value = true
  
  // Format payload values properly
  const payload = {
    code: form.value.code.toUpperCase(),
    name: form.value.name,
    description: form.value.description || null,
    type: form.value.type,
    value: parseFloat(form.value.value),
    min_purchase_amount: form.value.min_purchase_amount !== '' ? parseFloat(form.value.min_purchase_amount) : 0,
    max_discount_amount: form.value.type === 'percentage' && form.value.max_discount_amount !== '' ? parseFloat(form.value.max_discount_amount) : null,
    start_date: form.value.start_date ? form.value.start_date.replace('T', ' ') + ':00' : null,
    end_date: form.value.end_date ? form.value.end_date.replace('T', ' ') + ':00' : null,
    is_active: form.value.is_active ? 1 : 0
  }

  try {
    if (isEditMode.value) {
      await api.put(`/promotions/${editId.value}`, payload)
      toast.success('Promotion updated successfully!')
    } else {
      await api.post('/promotions', payload)
      toast.success('Promotion created successfully!')
    }
    resetForm()
    fetchPromotions()
  } catch (error) {
    console.error('Error saving promotion:', error)
    if (error.response && error.response.status === 422) {
      const serverErrors = error.response.data.errors || {}
      Object.keys(serverErrors).forEach(key => {
        if (errors.value[key] !== undefined) {
          errors.value[key] = serverErrors[key][0]
        }
      })
      toast.error('Validation failed. Please correct the highlighted errors.')
    } else {
      toast.error(error.response?.data?.message || 'Failed to save promotion.')
    }
  } finally {
    submitting.value = false
  }
}

// Load Promotion Details into form for editing
const handleEdit = (promo) => {
  errors.value = {
    code: '',
    name: '',
    description: '',
    type: '',
    value: '',
    min_purchase_amount: '',
    max_discount_amount: '',
    start_date: '',
    end_date: ''
  }
  isEditMode.value = true
  editId.value = promo.id
  form.value = {
    code: promo.code,
    name: promo.name,
    description: promo.description || '',
    type: promo.type,
    value: promo.value.toString(),
    min_purchase_amount: promo.min_purchase_amount ? promo.min_purchase_amount.toString() : '0',
    max_discount_amount: promo.max_discount_amount ? promo.max_discount_amount.toString() : '',
    start_date: formatForInput(promo.start_date),
    end_date: formatForInput(promo.end_date),
    is_active: promo.is_active === 1 || promo.is_active === true
  }
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

// Delete promotion
const handleDelete = async (id) => {
  if (!confirm('Are you sure you want to delete this promotion? This action cannot be undone.')) return

  try {
    await api.delete(`/promotions/${id}`)
    toast.success('Promotion deleted successfully!')
    if (editId.value === id) {
      resetForm()
    }
    fetchPromotions()
  } catch (error) {
    console.error('Error deleting promotion:', error)
    toast.error(error.response?.data?.message || 'Failed to delete promotion.')
  }
}

// Filter and search
const filteredPromotions = computed(() => {
  if (!searchQuery.value) return promotions.value
  const query = searchQuery.value.toLowerCase().trim()
  return promotions.value.filter(promo => 
    promo.code.toLowerCase().includes(query) || 
    promo.name.toLowerCase().includes(query) ||
    (promo.description && promo.description.toLowerCase().includes(query))
  )
})

// Pagination Computations
const totalPages = computed(() => {
  return Math.ceil(filteredPromotions.value.length / pageSize.value) || 1
})

const paginatedPromotions = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value
  const end = start + pageSize.value
  return filteredPromotions.value.slice(start, end)
})

// Reset current page when filters or search change
watch([searchQuery, pageSize], () => {
  currentPage.value = 1
})

onMounted(() => {
  fetchPromotions()
})
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
    
    <!-- Left Column: Form Panel -->
    <div class="lg:col-span-1 bg-white border border-gray-100 p-6 rounded-2xl shadow-sm space-y-6">
      <div class="flex items-center justify-between border-b border-gray-100 pb-4">
        <div>
          <h3 class="text-md font-bold text-gray-800">{{ isEditMode ? 'Edit Promotion' : 'Add New Promotion' }}</h3>
          <p class="text-xs text-gray-400 mt-0.5">Manage details and limits for discounts</p>
        </div>
        <span 
          v-if="isEditMode" 
          class="px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase bg-amber-50 text-amber-600 rounded-full border border-amber-200"
        >
          Edit Mode
        </span>
      </div>

      <form @submit.prevent="handleSubmit" class="space-y-4">
        
        <!-- Code Field -->
        <div class="space-y-1">
          <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Promotion Code *</label>
          <div class="flex gap-2">
            <input 
              v-model="form.code"
              type="text" 
              placeholder="e.g. FLASH25, RAMADAN" 
              class="flex-1 min-w-0 px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors uppercase"
              :class="errors.code ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
            />
            <button 
              type="button"
              @click="generatePromoCode"
              class="px-3 py-2 bg-emerald-50 text-emerald-600 border border-emerald-200 hover:bg-emerald-100 rounded-xl text-xs font-bold transition-colors cursor-pointer active:scale-95"
            >
              Generate
            </button>
          </div>
          <p v-if="errors.code" class="text-xs text-red-500 font-medium mt-1">{{ errors.code }}</p>
        </div>

        <!-- Name Field -->
        <div class="space-y-1">
          <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Promotion Name *</label>
          <input 
            v-model="form.name"
            type="text" 
            placeholder="e.g. Mid-Year Flash Sale" 
            class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
            :class="errors.name ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
          />
          <p v-if="errors.name" class="text-xs text-red-500 font-medium mt-1">{{ errors.name }}</p>
        </div>

        <!-- Description Field -->
        <div class="space-y-1">
          <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Description</label>
          <textarea 
            v-model="form.description"
            rows="2"
            placeholder="Promo description, terms and conditions..." 
            class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors resize-none"
            :class="errors.description ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
          ></textarea>
          <p v-if="errors.description" class="text-xs text-red-500 font-medium mt-1">{{ errors.description }}</p>
        </div>

        <!-- Type Selector -->
        <div class="space-y-1">
          <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Discount Type *</label>
          <select 
            v-model="form.type"
            class="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 bg-white"
          >
            <option value="percentage">Percentage (%)</option>
            <option value="fixed_amount">Fixed Amount (IDR)</option>
          </select>
          <p v-if="errors.type" class="text-xs text-red-500 font-medium mt-1">{{ errors.type }}</p>
        </div>

        <!-- Value Field -->
        <div class="space-y-1">
          <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">
            Discount Value * <span class="text-[10px] text-gray-400 font-normal">({{ form.type === 'percentage' ? '%' : 'IDR' }})</span>
          </label>
          <div class="relative">
            <input 
              v-model="form.value"
              type="number" 
              step="any"
              :placeholder="form.type === 'percentage' ? 'e.g. 10' : 'e.g. 50000'" 
              class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
              :class="errors.value ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
            />
            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-gray-400">
              {{ form.type === 'percentage' ? '%' : 'Rp' }}
            </span>
          </div>
          <p v-if="errors.value" class="text-xs text-red-500 font-medium mt-1">{{ errors.value }}</p>
        </div>

        <!-- Minimum Purchase Field -->
        <div class="space-y-1">
          <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Minimum Spending (IDR)</label>
          <div class="relative">
            <input 
              v-model="form.min_purchase_amount"
              type="number" 
              step="any"
              placeholder="e.g. 0" 
              class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
              :class="errors.min_purchase_amount ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
            />
            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-gray-400">Rp</span>
          </div>
          <p v-if="errors.min_purchase_amount" class="text-xs text-red-500 font-medium mt-1">{{ errors.min_purchase_amount }}</p>
        </div>

        <!-- Maximum Discount Field (Percentage only) -->
        <Transition name="fade-height">
          <div v-if="form.type === 'percentage'" class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Max Discount Limit (IDR)</label>
            <div class="relative">
              <input 
                v-model="form.max_discount_amount"
                type="number" 
                step="any"
                placeholder="Leave blank for unlimited" 
                class="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
                :class="errors.max_discount_amount ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
              />
              <span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-gray-400">Rp</span>
            </div>
            <p v-if="errors.max_discount_amount" class="text-xs text-red-500 font-medium mt-1">{{ errors.max_discount_amount }}</p>
          </div>
        </Transition>

        <!-- Date Range Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Start Date</label>
            <input 
              v-model="form.start_date"
              type="datetime-local" 
              class="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 bg-white"
              :class="errors.start_date ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
            />
            <p v-if="errors.start_date" class="text-xs text-red-500 font-medium mt-1">{{ errors.start_date }}</p>
          </div>

          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">End Date</label>
            <input 
              v-model="form.end_date"
              type="datetime-local" 
              class="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 bg-white"
              :class="errors.end_date ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
            />
            <p v-if="errors.end_date" class="text-xs text-red-500 font-medium mt-1">{{ errors.end_date }}</p>
          </div>
        </div>

        <!-- Is Active Toggle -->
        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100">
          <div class="flex flex-col">
            <span class="text-xs font-bold text-gray-700">Status Active</span>
            <span class="text-[10px] text-gray-400">Toggle whether this coupon is redeemable.</span>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input 
              v-model="form.is_active"
              type="checkbox" 
              class="sr-only peer"
            />
            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
          </label>
        </div>

        <!-- Actions -->
        <div class="flex gap-2 pt-2 border-t border-gray-100">
          <button 
            type="submit" 
            :disabled="submitting"
            class="flex-1 py-2 px-4 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-bold flex items-center justify-center gap-2 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed shadow-md hover:shadow-lg active:scale-98"
          >
            <svg v-if="submitting" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>{{ isEditMode ? 'Update Promotion' : 'Save Promotion' }}</span>
          </button>

          <button 
            v-if="isEditMode"
            type="button" 
            @click="resetForm"
            class="py-2 px-4 rounded-xl border border-gray-200 hover:bg-gray-50 text-gray-500 hover:text-gray-700 text-sm font-bold transition-all cursor-pointer active:scale-98"
          >
            Cancel
          </button>
        </div>
      </form>
    </div>

    <!-- Right Column: List Table Directory -->
    <div class="lg:col-span-2 bg-white border border-gray-100 p-6 rounded-2xl shadow-sm flex flex-col min-w-0">
      
      <!-- List Title and Search Controls -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
          <h3 class="text-md font-bold text-gray-800">Promotions Catalog</h3>
          <p class="text-xs text-gray-400 mt-0.5">Browse active codes, discount scopes, and parameters</p>
        </div>
        
        <!-- Search Input -->
        <div class="relative w-full md:w-72">
          <input 
            v-model="searchQuery"
            type="text" 
            placeholder="Search code or name..." 
            class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
          />
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4 text-gray-400 absolute left-3.5 top-1/2 -translate-y-1/2">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.602 10.602Z" />
          </svg>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex flex-col items-center justify-center py-20 space-y-3">
        <svg class="animate-spin h-8 w-8 text-emerald-500" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="text-sm font-semibold text-gray-500">Loading promotions...</span>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredPromotions.length === 0" class="flex flex-col items-center justify-center py-20 text-center space-y-4">
        <span class="w-16 h-16 rounded-2xl bg-gray-50 flex items-center justify-center text-gray-300">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.357.205a.75.75 0 0 1-1.006-.203 19.048 19.048 0 0 1-3.07-5.027m5.01-1.73a11.97 11.97 0 0 0-.105-1.97m0 0A19.21 19.21 0 0 0 6 7.476m10.826 9.878A11.962 11.962 0 0 0 18 15V9c0-.895-.098-1.767-.285-2.607m-1.122 10.428a19.21 19.21 0 0 1-3.07 5.027.75.75 0 0 1-1.006.203l-.357-.205a.75.75 0 0 1-.463-1.511c.4-.89.73-1.82 1.03-2.783m-1.115-9.18a19.048 19.048 0 0 1 3.07-5.027.75.75 0 0 1 1.006-.203l.357.205a.75.75 0 0 1 .463 1.511 20.13 20.13 0 0 1-1.03 2.783m-3.46 3.41a4.5 4.5 0 0 0 0 6h1.5a4.5 4.5 0 0 0 0-6h-1.5Z" />
          </svg>
        </span>
        <div>
          <h4 class="text-sm font-bold text-gray-700">No Promotions Found</h4>
          <p class="text-xs text-gray-400 mt-1 max-w-md">Could not find any promotions matching your search query, or no promotions have been registered yet.</p>
        </div>
      </div>

      <!-- Directory Table -->
      <div v-else class="flex-1 flex flex-col justify-between min-h-0">
        <div class="overflow-x-auto border border-gray-100 rounded-2xl min-w-full">
          <table class="min-w-full divide-y divide-gray-100 text-left text-sm">
            <thead class="bg-gray-50 text-xs font-bold text-gray-500 uppercase tracking-wider">
              <tr>
                <th class="px-6 py-4">Promo Coupon</th>
                <th class="px-6 py-4">Scope</th>
                <th class="px-6 py-4 text-right">Value</th>
                <th class="px-6 py-4 text-right">Min Purchase</th>
                <th class="px-6 py-4">Active Date Frame</th>
                <th class="px-6 py-4 text-center">Status</th>
                <th class="px-6 py-4 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
              <tr 
                v-for="promo in paginatedPromotions" 
                :key="promo.id"
                class="hover:bg-gray-50 transition-colors"
              >
                <!-- Promo Code & Name -->
                <td class="px-6 py-4">
                  <div class="flex flex-col">
                    <span class="text-sm font-bold text-gray-800 tracking-wide uppercase">{{ promo.code }}</span>
                    <span class="text-xs text-gray-400 truncate max-w-[160px]">{{ promo.name }}</span>
                  </div>
                </td>

                <!-- Scope / Description -->
                <td class="px-6 py-4">
                  <div class="flex flex-col">
                    <span 
                      class="self-start px-2 py-0.5 text-[10px] font-bold uppercase rounded-full"
                      :class="promo.type === 'percentage' ? 'bg-indigo-50 text-indigo-600 border border-indigo-200' : 'bg-teal-50 text-teal-600 border border-teal-200'"
                    >
                      {{ promo.type === 'percentage' ? 'Percentage' : 'Fixed Amount' }}
                    </span>
                    <span class="text-[11px] text-gray-400 mt-1 max-w-[150px] truncate" :title="promo.description">
                      {{ promo.description || 'No description provided' }}
                    </span>
                  </div>
                </td>

                <!-- Value -->
                <td class="px-6 py-4 text-right">
                  <div class="flex flex-col items-end">
                    <span class="text-sm font-bold text-gray-800">
                      {{ promo.type === 'percentage' ? `${parseFloat(promo.value)}%` : formatCurrency(promo.value) }}
                    </span>
                    <span v-if="promo.type === 'percentage' && promo.max_discount_amount" class="text-[10px] text-gray-400">
                      Max: {{ formatCurrency(promo.max_discount_amount) }}
                    </span>
                  </div>
                </td>

                <!-- Min Purchase -->
                <td class="px-6 py-4 text-right">
                  <span class="text-xs text-gray-600">{{ formatCurrency(promo.min_purchase_amount) }}</span>
                </td>

                <!-- Active Dates -->
                <td class="px-6 py-4">
                  <div class="flex flex-col text-[11px] text-gray-500 space-y-0.5">
                    <div class="flex items-center gap-1.5">
                      <span class="text-[9px] font-bold text-gray-400 uppercase w-7">Start:</span>
                      <span>{{ formatForDisplay(promo.start_date) }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                      <span class="text-[9px] font-bold text-gray-400 uppercase w-7">End:</span>
                      <span>{{ formatForDisplay(promo.end_date) }}</span>
                    </div>
                  </div>
                </td>

                <!-- Status -->
                <td class="px-6 py-4 text-center">
                  <span 
                    class="px-2.5 py-0.5 text-[10px] font-bold rounded-full inline-block"
                    :class="promo.is_active === 1 || promo.is_active === true ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-red-50 text-red-600 border border-red-200'"
                  >
                    {{ promo.is_active === 1 || promo.is_active === true ? 'Active' : 'Inactive' }}
                  </span>
                </td>

                <!-- Actions -->
                <td class="px-6 py-4 text-center">
                  <div class="flex items-center justify-center gap-2">
                    <button 
                      @click="handleEdit(promo)"
                      class="p-1.5 bg-gray-50 hover:bg-emerald-50 text-gray-400 hover:text-emerald-600 rounded-lg transition-colors cursor-pointer"
                      title="Edit promotion"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                      </svg>
                    </button>
                    <button 
                      @click="handleDelete(promo.id)"
                      class="p-1.5 bg-gray-50 hover:bg-red-50 text-gray-400 hover:text-red-600 rounded-lg transition-colors cursor-pointer"
                      title="Delete promotion"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Table Footer / Pagination -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mt-6 border-t border-gray-100 pt-4">
          <!-- Page Sizer -->
          <div class="flex items-center gap-2 text-xs text-gray-500">
            <span>Show</span>
            <select 
              v-model="pageSize"
              class="px-2 py-1 border border-gray-200 rounded-lg bg-white focus:outline-none focus:border-emerald-500"
            >
              <option v-for="opt in pageSizeOptions" :key="opt" :value="opt">{{ opt }}</option>
            </select>
            <span>entries</span>
            <span class="ml-2 text-gray-400">
              (Showing {{ paginatedPromotions.length }} of {{ filteredPromotions.length }} total entries)
            </span>
          </div>

          <!-- Page Navigation -->
          <div class="flex items-center gap-1.5 self-end">
            <button 
              @click="currentPage--"
              :disabled="currentPage === 1"
              class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
              </svg>
            </button>
            <span class="text-xs font-bold text-gray-700 px-3">
              Page {{ currentPage }} of {{ totalPages }}
            </span>
            <button 
              @click="currentPage++"
              :disabled="currentPage === totalPages"
              class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors cursor-pointer"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
              </svg>
            </button>
          </div>
        </div>

      </div>
    </div>

  </div>
</template>

<style scoped>
.fade-height-enter-active,
.fade-height-leave-active {
  transition: all 0.25s ease-out;
  max-height: 100px;
  opacity: 1;
  overflow: hidden;
}
.fade-height-enter-from,
.fade-height-leave-to {
  max-height: 0;
  opacity: 0;
  overflow: hidden;
  margin-top: 0 !important;
  margin-bottom: 0 !important;
  padding-top: 0 !important;
  padding-bottom: 0 !important;
}
</style>
