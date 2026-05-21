<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()

// States
const stores = ref([])
const loading = ref(false)
const submitting = ref(false)
const searchQuery = ref('')
const activeFormTab = ref('general') // Sub-sections of the form: 'general', 'contact', 'print'

// Form State
const isEditMode = ref(false)
const editId = ref(null)
const form = ref({
  name: '',
  address: '',
  phone: '',
  email: '',
  tax_number: '',
  header_text: '',
  footer_text: '',
  default_printer_id: null,
  default_receipt_template_id: null,
  is_active: true,
  print_settings: {
    paper_width: '80mm',
    margin_top: 0,
    margin_bottom: 0,
    show_logo: true,
    show_datetime: true
  }
})
const logoFile = ref(null)
const logoPreviewUrl = ref(null)
const fileInput = ref(null)

// Error State
const errors = ref({
  name: '',
  email: '',
  phone: '',
  tax_number: '',
  logo: '',
  header_text: '',
  footer_text: '',
  default_printer_id: '',
  default_receipt_template_id: ''
})

// Fetch all stores from backend
const fetchStores = async () => {
  loading.value = true
  try {
    const response = await api.get('/stores')
    if (response.data && response.data.data) {
      stores.value = response.data.data
    } else {
      stores.value = response.data || []
    }
  } catch (error) {
    console.error('Error fetching stores:', error)
    toast.error('Failed to load stores from server.')
  } finally {
    loading.value = false
  }
}

// Get full storage URL for logo
const getLogoUrl = (path) => {
  if (!path) return null
  const base = import.meta.env.DEV ? 'http://localhost:8000' : ''
  return `${base}/storage/${path}`
}

// Client-side validations
const validateForm = () => {
  let isValid = true
  errors.value = {
    name: '',
    email: '',
    phone: '',
    tax_number: '',
    logo: '',
    header_text: '',
    footer_text: '',
    default_printer_id: '',
    default_receipt_template_id: ''
  }

  // Name Validation
  if (!form.value.name || form.value.name.trim() === '') {
    errors.value.name = 'Store name is required.'
    isValid = false
  } else if (form.value.name.length > 255) {
    errors.value.name = 'Store name must not exceed 255 characters.'
    isValid = false
  }

  // Email Validation (format check if filled)
  if (form.value.email && form.value.email.trim() !== '') {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!emailRegex.test(form.value.email.trim())) {
      errors.value.email = 'Please enter a valid email address.'
      isValid = false
    } else if (form.value.email.length > 255) {
      errors.value.email = 'Email address must not exceed 255 characters.'
      isValid = false
    }
  }

  // Phone Validation
  if (form.value.phone && form.value.phone.length > 50) {
    errors.value.phone = 'Phone number must not exceed 50 characters.'
    isValid = false
  }

  // Tax Number Validation
  if (form.value.tax_number && form.value.tax_number.length > 100) {
    errors.value.tax_number = 'Tax number must not exceed 100 characters.'
    isValid = false
  }

  // Header Text Validation
  if (form.value.header_text && form.value.header_text.length > 255) {
    errors.value.header_text = 'Receipt header text must not exceed 255 characters.'
    isValid = false
  }

  // Footer Text Validation
  if (form.value.footer_text && form.value.footer_text.length > 255) {
    errors.value.footer_text = 'Receipt footer text must not exceed 255 characters.'
    isValid = false
  }

  return isValid
}

// Handle file input changes with client-side validation
const handleFileChange = (e) => {
  const file = e.target.files[0]
  if (!file) return

  // Size limit: 2MB
  if (file.size > 2 * 1024 * 1024) {
    errors.value.logo = 'Logo image size must not exceed 2MB.'
    logoFile.value = null
    logoPreviewUrl.value = null
    if (fileInput.value) fileInput.value.value = ''
    return
  }

  // Type limit: Image only
  if (!file.type.match('image.*')) {
    errors.value.logo = 'Selected file must be a valid image (PNG, JPG, JPEG, GIF, SVG).'
    logoFile.value = null
    logoPreviewUrl.value = null
    if (fileInput.value) fileInput.value.value = ''
    return
  }

  errors.value.logo = ''
  logoFile.value = file
  logoPreviewUrl.value = URL.createObjectURL(file)
}

// Trigger input click
const triggerFileInput = () => {
  if (fileInput.value) fileInput.value.click()
}

// Remove selected logo file
const removeLogoFile = () => {
  logoFile.value = null
  logoPreviewUrl.value = null
  if (fileInput.value) fileInput.value.value = ''
}

// Reset form to initial state
const resetForm = () => {
  isEditMode.value = false
  editId.value = null
  activeFormTab.value = 'general'
  form.value = {
    name: '',
    address: '',
    phone: '',
    email: '',
    tax_number: '',
    header_text: '',
    footer_text: '',
    default_printer_id: null,
    default_receipt_template_id: null,
    is_active: true,
    print_settings: {
      paper_width: '80mm',
      margin_top: 0,
      margin_bottom: 0,
      show_logo: true,
      show_datetime: true
    }
  }
  removeLogoFile()
  errors.value = {
    name: '',
    email: '',
    phone: '',
    tax_number: '',
    logo: '',
    header_text: '',
    footer_text: '',
    default_printer_id: '',
    default_receipt_template_id: ''
  }
}

// Submit via Axios POST / FormData
const handleSubmit = async () => {
  if (!validateForm()) return

  submitting.value = true
  const formData = new FormData()
  formData.append('name', form.value.name.trim())
  formData.append('address', form.value.address ? form.value.address.trim() : '')
  formData.append('phone', form.value.phone ? form.value.phone.trim() : '')
  formData.append('email', form.value.email ? form.value.email.trim() : '')
  formData.append('tax_number', form.value.tax_number ? form.value.tax_number.trim() : '')
  formData.append('header_text', form.value.header_text ? form.value.header_text.trim() : '')
  formData.append('footer_text', form.value.footer_text ? form.value.footer_text.trim() : '')
  
  if (form.value.default_printer_id !== null && form.value.default_printer_id !== '') {
    formData.append('default_printer_id', form.value.default_printer_id)
  }
  if (form.value.default_receipt_template_id !== null && form.value.default_receipt_template_id !== '') {
    formData.append('default_receipt_template_id', form.value.default_receipt_template_id)
  }

  formData.append('is_active', form.value.is_active ? '1' : '0')

  // Print settings nesting support for PHP FormData parsing
  formData.append('print_settings[paper_width]', form.value.print_settings.paper_width)
  formData.append('print_settings[margin_top]', form.value.print_settings.margin_top ?? 0)
  formData.append('print_settings[margin_bottom]', form.value.print_settings.margin_bottom ?? 0)
  formData.append('print_settings[show_logo]', form.value.print_settings.show_logo ? '1' : '0')
  formData.append('print_settings[show_datetime]', form.value.print_settings.show_datetime ? '1' : '0')

  if (logoFile.value) {
    formData.append('logo', logoFile.value)
  }

  try {
    if (isEditMode.value) {
      // Laravel multipart PUT workaround
      formData.append('_method', 'PUT')
      await api.post(`/stores/${editId.value}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      toast.success('Store updated successfully!')
    } else {
      await api.post('/stores', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      })
      toast.success('Store created successfully!')
    }
    resetForm()
    fetchStores()
  } catch (error) {
    console.error('Error saving store:', error)
    if (error.response && error.response.status === 422) {
      const serverErrors = error.response.data.errors || {}
      Object.keys(serverErrors).forEach(key => {
        if (errors.value[key] !== undefined) {
          errors.value[key] = serverErrors[key][0]
        }
      })
      toast.error('Please correct the highlighted errors.')
    } else {
      toast.error(error.response?.data?.message || 'Something went wrong while saving the store.')
    }
  } finally {
    submitting.value = false
  }
}

// Set form into edit mode
const handleEdit = (store) => {
  resetForm()
  errors.value = {
    name: '',
    email: '',
    phone: '',
    tax_number: '',
    logo: '',
    header_text: '',
    footer_text: '',
    default_printer_id: '',
    default_receipt_template_id: ''
  }
  isEditMode.value = true
  editId.value = store.id

  // Load existing values safely
  const parsedSettings = store.print_settings || {}
  
  form.value = {
    name: store.name,
    address: store.address || '',
    phone: store.phone || '',
    email: store.email || '',
    tax_number: store.tax_number || '',
    header_text: store.header_text || '',
    footer_text: store.footer_text || '',
    default_printer_id: store.default_printer_id,
    default_receipt_template_id: store.default_receipt_template_id,
    is_active: store.is_active === 1 || store.is_active === true,
    print_settings: {
      paper_width: parsedSettings.paper_width || '80mm',
      margin_top: parsedSettings.margin_top !== undefined ? parseInt(parsedSettings.margin_top) : 0,
      margin_bottom: parsedSettings.margin_bottom !== undefined ? parseInt(parsedSettings.margin_bottom) : 0,
      show_logo: parsedSettings.show_logo === '1' || parsedSettings.show_logo === 1 || parsedSettings.show_logo === true,
      show_datetime: parsedSettings.show_datetime === '1' || parsedSettings.show_datetime === 1 || parsedSettings.show_datetime === true
    }
  }

  logoFile.value = null
  logoPreviewUrl.value = store.logo_path ? getLogoUrl(store.logo_path) : null
}

// Delete a store with confirmation
const handleDelete = async (id) => {
  if (!confirm('Are you sure you want to delete this store? This action cannot be undone.')) return

  try {
    await api.delete(`/stores/${id}`)
    toast.success('Store deleted successfully!')
    if (editId.value === id) {
      resetForm()
    }
    fetchStores()
  } catch (error) {
    console.error('Error deleting store:', error)
    toast.error('Failed to delete the store. Please try again.')
  }
}

// Filtered stores computed property
const filteredStores = computed(() => {
  if (!searchQuery.value) return stores.value
  const query = searchQuery.value.toLowerCase().trim()
  return stores.value.filter(store => 
    store.name.toLowerCase().includes(query) || 
    (store.email && store.email.toLowerCase().includes(query)) ||
    (store.phone && store.phone.includes(query)) ||
    (store.tax_number && store.tax_number.toLowerCase().includes(query)) ||
    (store.address && store.address.toLowerCase().includes(query))
  )
})

// Pagination States
const currentPage = ref(1)
const pageSize = ref(10)

// Reset to first page when search query changes
watch(searchQuery, () => {
  currentPage.value = 1
})

// Paginated stores computed property
const paginatedStores = computed(() => {
  const startIndex = (currentPage.value - 1) * pageSize.value
  const endIndex = startIndex + pageSize.value
  return filteredStores.value.slice(startIndex, endIndex)
})

// Total pages computed property
const totalPages = computed(() => {
  return Math.ceil(filteredStores.value.length / pageSize.value) || 1
})

// Keep currentPage within bounds
watch(totalPages, (newVal) => {
  if (currentPage.value > newVal) {
    currentPage.value = newVal
  }
})

// Go to page helper
const goToPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

// Display ranges
const startRange = computed(() => {
  if (filteredStores.value.length === 0) return 0
  return (currentPage.value - 1) * pageSize.value + 1
})

const endRange = computed(() => {
  const calculatedEnd = currentPage.value * pageSize.value
  return Math.min(calculatedEnd, filteredStores.value.length)
})

onMounted(() => {
  fetchStores()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
      <div>
        <h2 class="text-xl font-bold text-gray-800">Stores Settings</h2>
        <p class="text-xs text-gray-500">Configure your business outlets, billing taxes, receipts branding, and hardware printing profiles.</p>
      </div>
      <div class="w-full sm:w-64 relative">
        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.637 10.637Z" />
          </svg>
        </span>
        <input 
          v-model="searchQuery"
          type="text" 
          placeholder="Search outlets..." 
          class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
        />
      </div>
    </div>

    <!-- Main Content Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      
      <!-- Left Column: Form (5 Cols) -->
      <div class="lg:col-span-5 bg-white border border-gray-100 p-6 rounded-2xl shadow-sm h-fit">
        <h3 class="text-md font-bold text-gray-800 mb-4 flex items-center gap-2">
          <span class="p-1.5 rounded-lg bg-emerald-50 text-emerald-600">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
          </span>
          {{ isEditMode ? 'Edit Store Outlet' : 'Add New Outlet' }}
        </h3>

        <!-- Form Section Tabs -->
        <div class="flex border-b border-gray-100 mb-5 text-xs font-bold text-gray-400">
          <button 
            type="button" 
            @click="activeFormTab = 'general'"
            class="flex-1 pb-3 text-center transition-colors border-b-2"
            :class="activeFormTab === 'general' ? 'border-emerald-500 text-emerald-600' : 'border-transparent hover:text-gray-600'"
          >
            General Profile
          </button>
          <button 
            type="button" 
            @click="activeFormTab = 'contact'"
            class="flex-1 pb-3 text-center transition-colors border-b-2"
            :class="activeFormTab === 'contact' ? 'border-emerald-500 text-emerald-600' : 'border-transparent hover:text-gray-600'"
          >
            Location & Contacts
          </button>
          <button 
            type="button" 
            @click="activeFormTab = 'print'"
            class="flex-1 pb-3 text-center transition-colors border-b-2"
            :class="activeFormTab === 'print' ? 'border-emerald-500 text-emerald-600' : 'border-transparent hover:text-gray-600'"
          >
            Receipts & Print
          </button>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-4">
          
          <!-- TAB 1: General Info -->
          <div v-show="activeFormTab === 'general'" class="space-y-4">
            <!-- Outlet Name Field -->
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Outlet Name *</label>
              <input 
                v-model="form.name"
                type="text" 
                placeholder="e.g. POSRETAIL Central MegaMall" 
                class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
                :class="errors.name ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
              />
              <p v-if="errors.name" class="text-xs text-red-500 font-medium mt-1">{{ errors.name }}</p>
            </div>

            <!-- Tax Registration Number Field -->
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">NPWP / Tax Identification Number</label>
              <input 
                v-model="form.tax_number"
                type="text" 
                placeholder="e.g. 01.234.567.8-999.000" 
                class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
                :class="errors.tax_number ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
              />
              <p v-if="errors.tax_number" class="text-xs text-red-500 font-medium mt-1">{{ errors.tax_number }}</p>
            </div>

            <!-- Logo Path (Image Upload Field) -->
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Outlet Logo Branding</label>
              
              <!-- Hidden input file -->
              <input 
                ref="fileInput"
                type="file" 
                accept="image/*"
                class="hidden" 
                @change="handleFileChange"
              />

              <div class="flex items-center gap-4 p-3 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                <!-- Preview area -->
                <div class="w-16 h-16 rounded-xl bg-white border border-gray-100 flex items-center justify-center overflow-hidden flex-shrink-0 relative group">
                  <img 
                    v-if="logoPreviewUrl" 
                    :src="logoPreviewUrl" 
                    class="w-full h-full object-contain"
                    alt="Logo Preview"
                  />
                  <span v-else class="text-[10px] text-gray-400 font-bold uppercase">No Logo</span>
                </div>

                <div class="space-y-1.5">
                  <div class="flex gap-2">
                    <button 
                      type="button"
                      @click="triggerFileInput"
                      class="px-3 py-1 bg-white border border-gray-200 rounded-lg text-xs font-bold text-gray-700 hover:bg-gray-100 transition-colors cursor-pointer"
                    >
                      Browse
                    </button>
                    <button 
                      v-if="logoPreviewUrl"
                      type="button"
                      @click="removeLogoFile"
                      class="px-3 py-1 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg text-xs font-bold transition-colors cursor-pointer"
                    >
                      Remove
                    </button>
                  </div>
                  <p class="text-[10px] text-gray-400">Max size 2MB (PNG, JPG, JPEG, SVG).</p>
                </div>
              </div>
              <p v-if="errors.logo" class="text-xs text-red-500 font-medium mt-1">{{ errors.logo }}</p>
            </div>

            <!-- Is Active Toggle Field -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100">
              <div class="flex flex-col">
                <span class="text-xs font-bold text-gray-700">Operational Status</span>
                <span class="text-[10px] text-gray-400">Turn on/off sales routing and terminal closures.</span>
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
          </div>

          <!-- TAB 2: Location & Contacts -->
          <div v-show="activeFormTab === 'contact'" class="space-y-4">
            <!-- Email Field -->
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Business Email Address</label>
              <input 
                v-model="form.email"
                type="text" 
                placeholder="e.g. outlet.central@posretail.com" 
                class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
                :class="errors.email ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
              />
              <p v-if="errors.email" class="text-xs text-red-500 font-medium mt-1">{{ errors.email }}</p>
            </div>

            <!-- Phone Field -->
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Contact Hotline / Phone</label>
              <input 
                v-model="form.phone"
                type="text" 
                placeholder="e.g. +62 21-555-1234" 
                class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
                :class="errors.phone ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
              />
              <p v-if="errors.phone" class="text-xs text-red-500 font-medium mt-1">{{ errors.phone }}</p>
            </div>

            <!-- Address Field -->
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Outlet Street Address</label>
              <textarea 
                v-model="form.address"
                rows="4"
                placeholder="Enter complete outlet building, floor, street details..." 
                class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors resize-none"
                :class="errors.address ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
              ></textarea>
              <p v-if="errors.address" class="text-xs text-red-500 font-medium mt-1">{{ errors.address }}</p>
            </div>
          </div>

          <!-- TAB 3: Receipts & Print Settings -->
          <div v-show="activeFormTab === 'print'" class="space-y-4">
            
            <!-- Receipt Header Text -->
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Receipt Header Text</label>
              <input 
                v-model="form.header_text"
                type="text" 
                placeholder="e.g. Welcome to POSRETAIL MegaMall!" 
                class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
                :class="errors.header_text ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
              />
              <p v-if="errors.header_text" class="text-xs text-red-500 font-medium mt-1">{{ errors.header_text }}</p>
            </div>

            <!-- Receipt Footer Text -->
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Receipt Footer Text</label>
              <input 
                v-model="form.footer_text"
                type="text" 
                placeholder="e.g. Thank you for shopping with us!" 
                class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
                :class="errors.footer_text ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
              />
              <p v-if="errors.footer_text" class="text-xs text-red-500 font-medium mt-1">{{ errors.footer_text }}</p>
            </div>

            <!-- Default Hardware Setup Grid -->
            <div class="grid grid-cols-2 gap-3">
              <!-- Printer Profile Select -->
              <div class="space-y-1">
                <label class="block text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Receipt Printer Profile</label>
                <select 
                  v-model="form.default_printer_id"
                  class="w-full px-3 py-2 border border-gray-200 bg-white rounded-xl text-xs focus:outline-none focus:border-emerald-500 transition-colors cursor-pointer"
                >
                  <option :value="null">-- Select --</option>
                  <option :value="1">Thermal Receipt 80mm (ID: 1)</option>
                  <option :value="2">EPSON Kitchen POS (ID: 2)</option>
                  <option :value="3">Warehouse Laser Jet (ID: 3)</option>
                </select>
              </div>

              <!-- Receipt Template Select -->
              <div class="space-y-1">
                <label class="block text-[10px] font-extrabold text-gray-400 uppercase tracking-wider">Receipt Template Format</label>
                <select 
                  v-model="form.default_receipt_template_id"
                  class="w-full px-3 py-2 border border-gray-200 bg-white rounded-xl text-xs focus:outline-none focus:border-emerald-500 transition-colors cursor-pointer"
                >
                  <option :value="null">-- Select --</option>
                  <option :value="1">Standard Compact Layout (ID: 1)</option>
                  <option :value="2">Itemized Logo Layout (ID: 2)</option>
                  <option :value="3">VAT / Tax Explicit Invoice (ID: 3)</option>
                </select>
              </div>
            </div>

            <!-- Print Settings Object Subform -->
            <div class="p-4 bg-emerald-50/30 border border-emerald-100 rounded-xl space-y-3">
              <h4 class="text-xs font-extrabold text-emerald-800 flex items-center gap-1.5 uppercase tracking-wider">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5 text-emerald-600">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096a42.42 42.42 0 0 0-10.56 0m10.56 0L17.66 18m0 0a2.25 2.25 0 0 1-2.244 2.077H8.584A2.25 2.25 0 0 1 6.34 18m11.32 0h2.083c.982 0 1.764-.787 1.764-1.75V11.5a3.5 3.5 0 0 0-3.5-3.5H4.82A3.5 3.5 0 0 0 1.32 11.5v4.75c0 .963.782 1.75 1.764 1.75h2.083m14.167-2.333c-1.79 0-3.25-1.46-3.25-3.25s1.46-3.25 3.25-3.25 3.25 1.46 3.25 3.25-1.46 3.25-3.25 3.25Zm-14.167 0C3.38 15.667 1.92 14.207 1.92 12.417s1.46-3.25 3.25-3.25 3.25 1.46 3.25 3.25-1.46 3.25-3.25 3.25Z" />
                </svg>
                Hardware Formatting Settings
              </h4>

              <!-- Width Selector -->
              <div class="space-y-1">
                <label class="block text-[10px] font-bold text-emerald-800/80">RECEIPT PAPER WIDTH</label>
                <div class="flex gap-4 text-xs font-bold text-gray-700">
                  <label class="flex items-center gap-1.5 cursor-pointer">
                    <input type="radio" value="80mm" v-model="form.print_settings.paper_width" class="text-emerald-500 focus:ring-emerald-500" />
                    <span>Standard 80mm</span>
                  </label>
                  <label class="flex items-center gap-1.5 cursor-pointer">
                    <input type="radio" value="58mm" v-model="form.print_settings.paper_width" class="text-emerald-500 focus:ring-emerald-500" />
                    <span>Compact 58mm</span>
                  </label>
                </div>
              </div>

              <!-- Margins Grid -->
              <div class="grid grid-cols-2 gap-2">
                <div class="space-y-1">
                  <label class="block text-[10px] font-bold text-emerald-800/80">TOP MARGIN (px)</label>
                  <input 
                    type="number" 
                    min="0"
                    max="100"
                    v-model.number="form.print_settings.margin_top"
                    class="w-full px-2.5 py-1 border border-emerald-100 bg-white rounded-lg text-xs text-gray-800 font-bold focus:outline-none focus:border-emerald-500"
                  />
                </div>
                <div class="space-y-1">
                  <label class="block text-[10px] font-bold text-emerald-800/80">BOTTOM MARGIN (px)</label>
                  <input 
                    type="number" 
                    min="0"
                    max="100"
                    v-model.number="form.print_settings.margin_bottom"
                    class="w-full px-2.5 py-1 border border-emerald-100 bg-white rounded-lg text-xs text-gray-800 font-bold focus:outline-none focus:border-emerald-500"
                  />
                </div>
              </div>

              <!-- Boolean Toggles -->
              <div class="flex flex-col gap-2 pt-1 border-t border-emerald-100/50">
                <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 cursor-pointer">
                  <input type="checkbox" v-model="form.print_settings.show_logo" class="rounded text-emerald-600 focus:ring-emerald-500 border-gray-300" />
                  <span>Render outlet logo on top of receipt</span>
                </label>
                <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 cursor-pointer">
                  <input type="checkbox" v-model="form.print_settings.show_datetime" class="rounded text-emerald-600 focus:ring-emerald-500 border-gray-300" />
                  <span>Print footer timestamp metadata</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex gap-2 pt-2">
            <button 
              type="submit" 
              :disabled="submitting"
              class="flex-1 py-2 px-4 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-bold flex items-center justify-center gap-2 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed shadow-md hover:shadow-lg active:scale-98"
            >
              <svg v-if="submitting" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>{{ isEditMode ? 'Update Outlet' : 'Save Outlet' }}</span>
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

      <!-- Right Column: Table Directory (7 Cols) -->
      <div class="lg:col-span-7 bg-white border border-gray-100 p-6 rounded-2xl shadow-sm flex flex-col min-w-0">
        <h3 class="text-md font-bold text-gray-800 mb-5">Registered Outlets</h3>

        <!-- Loading State -->
        <div v-if="loading" class="flex-1 flex flex-col items-center justify-center py-20 space-y-3">
          <svg class="animate-spin h-8 w-8 text-emerald-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span class="text-sm font-semibold text-gray-500">Loading outlets data...</span>
        </div>

        <!-- Empty State -->
        <div v-else-if="filteredStores.length === 0" class="flex-1 flex flex-col items-center justify-center py-20 text-center space-y-4">
          <span class="w-16 h-16 rounded-2xl bg-gray-50 flex items-center justify-center text-gray-300">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1.75-.75h3a.75.75 0 0 1.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 0 0.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.15c0 .415.336.75.75.75z" />
            </svg>
          </span>
          <div>
            <h4 class="text-sm font-bold text-gray-700">No Stores Found</h4>
            <p class="text-xs text-gray-400 max-w-[280px] mx-auto mt-1">
              {{ searchQuery ? 'No outlets match your search keyword.' : 'Your outlets database is currently empty.' }}
            </p>
          </div>
        </div>

        <!-- Stores Directory Table -->
        <div v-else class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs font-semibold text-gray-600">
            <thead>
              <tr class="border-b border-gray-100 text-gray-400 uppercase tracking-wider text-[10px]">
                <th class="pb-3 w-14">Logo</th>
                <th class="pb-3">Outlet Profile</th>
                <th class="pb-3 hidden md:table-cell">Contact Details</th>
                <th class="pb-3 hidden lg:table-cell">NPWP / Tax</th>
                <th class="pb-3 w-20 text-center">Status</th>
                <th class="pb-3 w-24 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="store in paginatedStores" :key="store.id" class="hover:bg-gray-50/50 transition-colors">
                <!-- Logo Column -->
                <td class="py-3.5 pr-2">
                  <div class="w-10 h-10 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-center overflow-hidden shadow-sm">
                    <img 
                      v-if="store.logo_path" 
                      :src="getLogoUrl(store.logo_path)" 
                      class="w-full h-full object-contain"
                      alt="Store Logo"
                    />
                    <!-- Fallback: Initial name -->
                    <span v-else class="text-xs font-extrabold text-emerald-600 uppercase">
                      {{ store.name.substring(0, 2) }}
                    </span>
                  </div>
                </td>

                <!-- Outlet Profile Column -->
                <td class="py-3.5 pr-4">
                  <span class="text-gray-800 font-bold block">{{ store.name }}</span>
                  <span class="text-[10px] text-gray-400 font-medium max-w-[180px] block truncate" :title="store.address">
                    {{ store.address || 'No street address registered' }}
                  </span>
                </td>

                <!-- Contact Details Column (hidden on mobile) -->
                <td class="py-3.5 pr-4 hidden md:table-cell">
                  <div class="flex flex-col gap-0.5">
                    <span class="text-gray-700 font-bold block">{{ store.phone || '-' }}</span>
                    <span class="text-[10px] text-gray-400 block max-w-[150px] truncate" :title="store.email">
                      {{ store.email || '-' }}
                    </span>
                  </div>
                </td>

                <!-- Tax Number Column -->
                <td class="py-3.5 pr-4 hidden lg:table-cell text-gray-500 font-bold">
                  {{ store.tax_number || 'Not Registered' }}
                </td>

                <!-- Status column -->
                <td class="py-3.5 text-center">
                  <span 
                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold"
                    :class="store.is_active === 1 || store.is_active === true
                      ? 'bg-emerald-50 text-emerald-600'
                      : 'bg-gray-100 text-gray-500'"
                  >
                    <span 
                      class="w-1.5 h-1.5 rounded-full"
                      :class="store.is_active === 1 || store.is_active === true
                        ? 'bg-emerald-500 animate-pulse'
                        : 'bg-gray-400'"
                    ></span>
                    {{ store.is_active === 1 || store.is_active === true ? 'Active' : 'Inactive' }}
                  </span>
                </td>

                <!-- Actions column -->
                <td class="py-3.5 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <!-- Edit Button -->
                    <button 
                      @click="handleEdit(store)"
                      class="w-8 h-8 rounded-lg bg-gray-50 text-gray-500 hover:text-emerald-600 hover:bg-emerald-50 transition-colors flex items-center justify-center cursor-pointer"
                      title="Edit Outlet"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                      </svg>
                    </button>

                    <!-- Delete Button -->
                    <button 
                      @click="handleDelete(store.id)"
                      class="w-8 h-8 rounded-lg bg-gray-50 text-gray-500 hover:text-red-600 hover:bg-red-50 transition-colors flex items-center justify-center cursor-pointer"
                      title="Delete Outlet"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Controls -->
        <div v-if="filteredStores.length > 0" class="mt-6 pt-4 border-t border-gray-100 flex flex-col sm:flex-row justify-between items-center gap-4">
          <!-- Left side -->
          <div class="flex items-center gap-3 text-xs text-gray-500">
            <span>Showing {{ startRange }} to {{ endRange }} of {{ filteredStores.length }} entries</span>
            <div class="flex items-center gap-1.5 ml-2 border-l border-gray-200 pl-3">
              <label class="font-medium text-gray-400">Show:</label>
              <select 
                v-model="pageSize" 
                @change="currentPage = 1"
                class="bg-gray-50 border border-gray-200 rounded-lg px-2 py-1 focus:outline-none focus:border-emerald-500 text-xs font-bold text-gray-700 transition-colors cursor-pointer"
              >
                <option :value="5">5</option>
                <option :value="10">10</option>
                <option :value="25">25</option>
                <option :value="50">50</option>
              </select>
            </div>
          </div>

          <!-- Right side -->
          <div class="flex items-center gap-1">
            <!-- First Page -->
            <button 
              @click="goToPage(1)" 
              :disabled="currentPage === 1"
              type="button"
              class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 hover:text-emerald-600 disabled:opacity-40 disabled:hover:bg-white disabled:hover:text-gray-500 transition-all cursor-pointer disabled:cursor-not-allowed"
              title="First Page"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m18.75 5.25-7.5 7.5 7.5 7.5m-6-15L5.25 12l7.5 7.5" />
              </svg>
            </button>

            <!-- Previous Page -->
            <button 
              @click="goToPage(currentPage - 1)" 
              :disabled="currentPage === 1"
              type="button"
              class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 hover:text-emerald-600 disabled:opacity-40 disabled:hover:bg-white disabled:hover:text-gray-500 transition-all cursor-pointer disabled:cursor-not-allowed"
              title="Previous Page"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
              </svg>
            </button>

            <!-- Page numbers -->
            <template v-for="page in totalPages" :key="page">
              <button 
                v-if="Math.abs(page - currentPage) <= 1 || page === 1 || page === totalPages"
                @click="goToPage(page)"
                type="button"
                class="w-8 h-8 rounded-lg border text-xs font-bold transition-all cursor-pointer"
                :class="currentPage === page 
                  ? 'bg-emerald-500 border-emerald-500 text-white shadow-md shadow-emerald-100 hover:bg-emerald-600' 
                  : 'border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-emerald-600'"
              >
                {{ page }}
              </button>
              <span 
                v-else-if="(page === 2 && currentPage > 3) || (page === totalPages - 1 && currentPage < totalPages - 2)"
                class="w-8 h-8 flex items-center justify-center text-gray-400 text-xs font-bold"
              >
                ...
              </span>
            </template>

            <!-- Next Page -->
            <button 
              @click="goToPage(currentPage + 1)" 
              :disabled="currentPage === totalPages"
              type="button"
              class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 hover:text-emerald-600 disabled:opacity-40 disabled:hover:bg-white disabled:hover:text-gray-500 transition-all cursor-pointer disabled:cursor-not-allowed"
              title="Next Page"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
              </svg>
            </button>

            <!-- Last Page -->
            <button 
              @click="goToPage(totalPages)" 
              :disabled="currentPage === totalPages"
              type="button"
              class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 hover:text-emerald-600 disabled:opacity-40 disabled:hover:bg-white disabled:hover:text-gray-500 transition-all cursor-pointer disabled:cursor-not-allowed"
              title="Last Page"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m5.25 5.25 7.5 7.5-7.5 7.5m6-15 7.5 7.5-7.5 7.5" />
              </svg>
            </button>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>
