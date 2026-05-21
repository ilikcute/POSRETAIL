<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()

// States
const customers = ref([])
const loading = ref(false)
const submitting = ref(false)
const searchQuery = ref('')

// Form State
const isEditMode = ref(false)
const editId = ref(null)
const form = ref({
  name: '',
  email: '',
  phone: '',
  address: '',
  member_code: '',
  is_active: true
})

// Error State
const errors = ref({
  name: '',
  email: '',
  phone: '',
  address: '',
  member_code: ''
})

// Fetch all customers from backend
const fetchCustomers = async () => {
  loading.value = true
  try {
    const response = await api.get('/customers')
    if (response.data && response.data.data) {
      customers.value = response.data.data
    } else {
      customers.value = response.data || []
    }
  } catch (error) {
    console.error('Error fetching customers:', error)
    toast.error('Failed to load customers from server.')
  } finally {
    loading.value = false
  }
}

// Compute the next auto-incremented unsigned member_code
const nextMemberCode = computed(() => {
  if (customers.value.length === 0) return '100001'
  
  // Find maximum numeric value of member_code in existing list
  const numericCodes = customers.value
    .map(c => {
      const parsed = parseInt(c.member_code)
      return isNaN(parsed) ? 0 : parsed
    })
    .filter(val => val > 0)
    
  if (numericCodes.length === 0) return '100001'
  const maxVal = Math.max(...numericCodes)
  return String(maxVal + 1)
})

// Set the member_code when starting to add a new customer
const updateNextMemberCode = () => {
  if (!isEditMode.value) {
    form.value.member_code = nextMemberCode.value
  }
}

// Watch customers changes to automatically update member_code when in add mode
watch([customers, isEditMode], () => {
  updateNextMemberCode()
}, { immediate: true })

// Client-side validations
const validateForm = () => {
  let isValid = true
  errors.value = { name: '', email: '', phone: '', address: '', member_code: '' }

  // Name Validation
  if (!form.value.name || form.value.name.trim() === '') {
    errors.value.name = 'Customer name is required.'
    isValid = false
  } else if (form.value.name.length < 3) {
    errors.value.name = 'Customer name must be at least 3 characters.'
    isValid = false
  }

  // Email Validation
  if (form.value.email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!emailRegex.test(form.value.email)) {
      errors.value.email = 'Please enter a valid email address.'
      isValid = false
    }
  }

  // Phone Validation
  if (form.value.phone) {
    const phoneRegex = /^[0-9+\-\s()]{5,30}$/
    if (!phoneRegex.test(form.value.phone)) {
      errors.value.phone = 'Please enter a valid phone number (min 5 digits).'
      isValid = false
    }
  }

  // Member Code Validation
  if (!form.value.member_code || form.value.member_code.trim() === '') {
    errors.value.member_code = 'Member code is required.'
    isValid = false
  } else {
    const parsed = parseInt(form.value.member_code)
    if (isNaN(parsed) || parsed <= 0) {
      errors.value.member_code = 'Member code must be a positive integer.'
      isValid = false
    }
  }

  return isValid
}

// Reset form to initial state
const resetForm = () => {
  isEditMode.value = false
  editId.value = null
  form.value = {
    name: '',
    email: '',
    phone: '',
    address: '',
    member_code: '',
    is_active: true
  }
  errors.value = { name: '', email: '', phone: '', address: '', member_code: '' }
  updateNextMemberCode()
}

// Submit via Axios POST / PUT
const handleSubmit = async () => {
  if (!validateForm()) return

  submitting.value = true
  
  const payload = {
    name: form.value.name,
    email: form.value.email || null,
    phone: form.value.phone || null,
    address: form.value.address || null,
    member_code: form.value.member_code,
    is_active: form.value.is_active ? 1 : 0
  }

  try {
    if (isEditMode.value) {
      await api.put(`/customers/${editId.value}`, payload)
      toast.success('Customer updated successfully!')
    } else {
      await api.post('/customers', payload)
      toast.success('Customer created successfully!')
    }
    resetForm()
    fetchCustomers()
  } catch (error) {
    console.error('Error saving customer:', error)
    if (error.response && error.response.status === 422) {
      const serverErrors = error.response.data.errors || {}
      Object.keys(serverErrors).forEach(key => {
        if (errors.value[key] !== undefined) {
          errors.value[key] = serverErrors[key][0]
        }
      })
      toast.error('Please correct the highlighted errors.')
    } else {
      toast.error(error.response?.data?.message || 'Something went wrong while saving the customer.')
    }
  } finally {
    submitting.value = false
  }
}

// Set form into edit mode
const handleEdit = (customer) => {
  errors.value = { name: '', email: '', phone: '', address: '', member_code: '' }
  isEditMode.value = true
  editId.value = customer.id
  form.value = {
    name: customer.name,
    email: customer.email || '',
    phone: customer.phone || '',
    address: customer.address || '',
    member_code: customer.member_code || '',
    is_active: customer.is_active === 1 || customer.is_active === true
  }
}

// Delete a customer with confirmation
const handleDelete = async (id) => {
  if (!confirm('Are you sure you want to delete this customer? This action cannot be undone.')) return

  try {
    await api.delete(`/customers/${id}`)
    toast.success('Customer deleted successfully!')
    if (editId.value === id) {
      resetForm()
    }
    fetchCustomers()
  } catch (error) {
    console.error('Error deleting customer:', error)
    toast.error('Failed to delete the customer. Please try again.')
  }
}

// Filtered customers computed property
const filteredCustomers = computed(() => {
  if (!searchQuery.value) return customers.value
  const query = searchQuery.value.toLowerCase().trim()
  return customers.value.filter(customer => 
    customer.name.toLowerCase().includes(query) || 
    (customer.email && customer.email.toLowerCase().includes(query)) ||
    (customer.phone && customer.phone.toLowerCase().includes(query)) ||
    (customer.member_code && customer.member_code.toLowerCase().includes(query))
  )
})

// Pagination States
const currentPage = ref(1)
const pageSize = ref(10)

// Reset to first page when search query changes
watch(searchQuery, () => {
  currentPage.value = 1
})

// Paginated customers computed property
const paginatedCustomers = computed(() => {
  const startIndex = (currentPage.value - 1) * pageSize.value
  const endIndex = startIndex + pageSize.value
  return filteredCustomers.value.slice(startIndex, endIndex)
})

// Total pages computed property
const totalPages = computed(() => {
  return Math.ceil(filteredCustomers.value.length / pageSize.value) || 1
})

// Keep currentPage within bounds if list shrinks (e.g., after deletions)
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
  if (filteredCustomers.value.length === 0) return 0
  return (currentPage.value - 1) * pageSize.value + 1
})

const endRange = computed(() => {
  const calculatedEnd = currentPage.value * pageSize.value
  return Math.min(calculatedEnd, filteredCustomers.value.length)
})

onMounted(() => {
  fetchCustomers()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
      <div>
        <h2 class="text-xl font-bold text-gray-800">Customers Management</h2>
        <p class="text-xs text-gray-500">Add, edit, view, and manage your retail store loyalty customers.</p>
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
          placeholder="Search customers..." 
          class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
        />
      </div>
    </div>

    <!-- Main Content Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      
      <!-- Left Column: Form -->
      <div class="lg:col-span-1 bg-white border border-gray-100 p-6 rounded-2xl shadow-sm h-fit">
        <h3 class="text-md font-bold text-gray-800 mb-5 flex items-center gap-2">
          <span class="p-1.5 rounded-lg bg-emerald-50 text-emerald-600">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
          </span>
          {{ isEditMode ? 'Edit Customer' : 'Add New Customer' }}
        </h3>

        <form @submit.prevent="handleSubmit" class="space-y-4">
          <!-- Member Code Field (Unsigned Integer Auto Increment display) -->
          <div class="space-y-1">
            <div class="flex justify-between items-center">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Member Code *</label>
              <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md">Auto Unsigned</span>
            </div>
            <input 
              v-model="form.member_code"
              type="text" 
              placeholder="e.g. 100001" 
              class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors bg-gray-50 text-gray-500 border-gray-200"
              :class="errors.member_code ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
              readonly
            />
            <p v-if="errors.member_code" class="text-xs text-red-500 font-medium mt-1">{{ errors.member_code }}</p>
          </div>

          <!-- Customer Name Field -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Customer Name *</label>
            <input 
              v-model="form.name"
              type="text" 
              placeholder="e.g. John Doe" 
              class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
              :class="errors.name ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
            />
            <p v-if="errors.name" class="text-xs text-red-500 font-medium mt-1">{{ errors.name }}</p>
          </div>

          <!-- Email Field -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Email Address</label>
            <input 
              v-model="form.email"
              type="email" 
              placeholder="e.g. john@example.com" 
              class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
              :class="errors.email ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
            />
            <p v-if="errors.email" class="text-xs text-red-500 font-medium mt-1">{{ errors.email }}</p>
          </div>

          <!-- Phone Field -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Phone Number</label>
            <input 
              v-model="form.phone"
              type="text" 
              placeholder="e.g. 081234567890" 
              class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
              :class="errors.phone ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
            />
            <p v-if="errors.phone" class="text-xs text-red-500 font-medium mt-1">{{ errors.phone }}</p>
          </div>

          <!-- Address Field -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Street Address</label>
            <textarea 
              v-model="form.address"
              rows="3"
              placeholder="Enter customer address details..." 
              class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors resize-none"
              :class="errors.address ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
            ></textarea>
            <p v-if="errors.address" class="text-xs text-red-500 font-medium mt-1">{{ errors.address }}</p>
          </div>

          <!-- Is Active Toggle Field -->
          <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
            <div class="flex flex-col">
              <span class="text-xs font-bold text-gray-700">Status Active</span>
              <span class="text-[10px] text-gray-400">Enable or disable this membership.</span>
            </div>
            
            <!-- Switch/Toggle (iOS Style) -->
            <label class="relative inline-flex items-center cursor-pointer">
              <input 
                v-model="form.is_active"
                type="checkbox" 
                class="sr-only peer"
              />
              <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
            </label>
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
              <span>{{ isEditMode ? 'Update Customer' : 'Save Customer' }}</span>
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

      <!-- Right Column: Table List -->
      <div class="lg:col-span-2 bg-white border border-gray-100 p-6 rounded-2xl shadow-sm flex flex-col min-w-0">
        <h3 class="text-md font-bold text-gray-800 mb-5">Customers Directory</h3>

        <!-- Loading State -->
        <div v-if="loading" class="flex-1 flex flex-col items-center justify-center py-20 space-y-3">
          <svg class="animate-spin h-8 w-8 text-emerald-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span class="text-sm font-semibold text-gray-500">Loading customers catalog...</span>
        </div>

        <!-- Empty State -->
        <div v-else-if="filteredCustomers.length === 0" class="flex-1 flex flex-col items-center justify-center py-20 text-center space-y-4">
          <span class="w-16 h-16 rounded-2xl bg-gray-50 flex items-center justify-center text-gray-300">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.008 1.24l.885 1.77a2.25 2.25 0 0 0 2.007 1.24h1.98a2.25 2.25 0 0 0 2.007-1.24l.885-1.77a2.25 2.25 0 0 1 2.007-1.24h3.86m-18 0h18" />
            </svg>
          </span>
          <div>
            <h4 class="text-sm font-bold text-gray-700">No Customers Found</h4>
            <p class="text-xs text-gray-400 max-w-[280px] mx-auto mt-1">
              {{ searchQuery ? 'No customers match your current search filters.' : 'Your customers library is empty. Create a customer on the left to start.' }}
            </p>
          </div>
        </div>

        <!-- Customers Table -->
        <div v-else class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-xs font-semibold text-gray-600">
            <thead>
              <tr class="border-b border-gray-100 text-gray-400 uppercase tracking-wider text-[10px]">
                <th class="pb-3 w-12">Avatar</th>
                <th class="pb-3">Customer Info</th>
                <th class="pb-3 hidden md:table-cell">Address</th>
                <th class="pb-3 text-center">Member Code</th>
                <!-- Point Balance tampil di table tetapi tidak di form -->
                <th class="pb-3 text-center">Point Balance</th>
                <th class="pb-3 w-20 text-center">Status</th>
                <th class="pb-3 w-24 text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="customer in paginatedCustomers" :key="customer.id" class="hover:bg-gray-50/50 transition-colors">
                <!-- Avatar column -->
                <td class="py-3">
                  <div class="w-8 h-8 rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 text-white font-bold text-xs uppercase flex items-center justify-center shadow-sm">
                    {{ customer.name.charAt(0) }}
                  </div>
                </td>

                <!-- Customer Info column -->
                <td class="py-3 pr-4">
                  <span class="text-gray-800 font-bold block">{{ customer.name }}</span>
                  <span class="text-[10px] text-gray-400 block mt-0.5 max-w-[150px] truncate">
                    {{ customer.email || 'No email' }} • {{ customer.phone || 'No phone' }}
                  </span>
                </td>

                <!-- Address column (hidden on mobile) -->
                <td class="py-3 pr-4 text-gray-400 font-medium hidden md:table-cell max-w-[150px] truncate">
                  {{ customer.address || '-' }}
                </td>

                <!-- Member Code column -->
                <td class="py-3 text-center text-gray-700 font-bold">
                  <span class="px-2 py-0.5 bg-gray-50 border border-gray-100 rounded-md">
                    {{ customer.member_code || '-' }}
                  </span>
                </td>

                <!-- Point Balance column (tampil di table tetapi tidak di form) -->
                <td class="py-3 text-center">
                  <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-100">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="solid" viewBox="0 0 24 24" stroke-width="0" class="w-3.5 h-3.5 text-amber-500 fill-amber-500">
                      <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
                    </svg>
                    {{ customer.point_balance ?? 0 }} pts
                  </span>
                </td>

                <!-- Status column -->
                <td class="py-3 text-center">
                  <span 
                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold"
                    :class="customer.is_active === 1 || customer.is_active === true
                      ? 'bg-emerald-50 text-emerald-600'
                      : 'bg-gray-100 text-gray-500'"
                  >
                    <span 
                      class="w-1.5 h-1.5 rounded-full"
                      :class="customer.is_active === 1 || customer.is_active === true
                        ? 'bg-emerald-500 animate-pulse'
                        : 'bg-gray-400'"
                    ></span>
                    {{ customer.is_active === 1 || customer.is_active === true ? 'Active' : 'Inactive' }}
                  </span>
                </td>

                <!-- Actions column -->
                <td class="py-3 text-center">
                  <div class="flex items-center justify-center gap-1">
                    <!-- Edit Button -->
                    <button 
                      @click="handleEdit(customer)"
                      class="w-8 h-8 rounded-lg bg-gray-50 text-gray-500 hover:text-emerald-600 hover:bg-emerald-50 transition-colors flex items-center justify-center cursor-pointer"
                      title="Edit Customer"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                      </svg>
                    </button>

                    <!-- Delete Button -->
                    <button 
                      @click="handleDelete(customer.id)"
                      class="w-8 h-8 rounded-lg bg-gray-50 text-gray-500 hover:text-red-600 hover:bg-red-50 transition-colors flex items-center justify-center cursor-pointer"
                      title="Delete Customer"
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
        <div v-if="filteredCustomers.length > 0" class="mt-6 pt-4 border-t border-gray-100 flex flex-col sm:flex-row justify-between items-center gap-4">
          <!-- Left side: Range info and page size selector -->
          <div class="flex items-center gap-3 text-xs text-gray-500">
            <span>Showing {{ startRange }} to {{ endRange }} of {{ filteredCustomers.length }} entries</span>
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

          <!-- Right side: Page buttons -->
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
              <!-- Show page button if it is close to currentPage to avoid long horizontal lists -->
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
