<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()

// States
const racks = ref([])
const warehouses = ref([])
const productsList = ref([])
const loading = ref(false)
const submitting = ref(false)
const searchQuery = ref('')
const activeFormTab = ref('general') // Sub-sections: 'general', 'planogram'

// Planogram state
const planogramShelves = ref([])
const planogramLoading = ref(false)
const planogramForm = ref({
  product_id: '',
  shelf_level: 1,
  position_order: 1,
  facing: 1,
  max_capacity: 10
})

// Form State
const isEditMode = ref(false)
const editId = ref(null)
const form = ref({
  warehouse_id: '',
  code: '',
  name: '',
  description: '',
  sort_order: 0,
  is_active: true
})

// Error State
const errors = ref({
  warehouse_id: '',
  code: '',
  name: '',
  description: '',
  sort_order: ''
})

// Error for planogram adding
const planogramError = ref('')
const planogramErrors = ref({
  product_id: '',
  shelf_level: '',
  position_order: '',
  facing: '',
  max_capacity: ''
})

// Fetch all warehouses
const fetchWarehouses = async () => {
  try {
    const response = await api.get('/warehouses')
    if (response.data && response.data.data) {
      warehouses.value = response.data.data
    } else {
      warehouses.value = response.data || []
    }
  } catch (error) {
    console.error('Error fetching warehouses:', error)
  }
}

// Fetch all products
const fetchProducts = async () => {
  try {
    const response = await api.get('/products')
    if (response.data && response.data.data) {
      productsList.value = response.data.data
    } else {
      productsList.value = response.data || []
    }
  } catch (error) {
    console.error('Error fetching products:', error)
  }
}

// Fetch all racks
const fetchRacks = async () => {
  loading.value = true
  try {
    const response = await api.get('/racks')
    if (response.data && response.data.data) {
      racks.value = response.data.data
    } else {
      racks.value = response.data || []
    }
  } catch (error) {
    console.error('Error fetching racks:', error)
    toast.error('Failed to load racks from server.')
  } finally {
    loading.value = false
  }
}

// Fetch single rack planogram layout
const fetchPlanogram = async (rackId) => {
  if (!rackId) return
  planogramLoading.value = true
  try {
    const response = await api.get(`/racks/${rackId}/planogram`)
    if (response.data && response.data.data) {
      planogramShelves.value = response.data.data.shelves || []
    }
  } catch (error) {
    console.error('Error fetching planogram:', error)
    toast.error('Failed to retrieve planogram layout.')
  } finally {
    planogramLoading.value = false
  }
}

// Auto unique rack code generator
const nextRackCode = computed(() => {
  if (racks.value.length === 0) return 'RCK-001'
  
  const suffixes = racks.value
    .map(r => {
      if (!r.code) return 0
      const match = r.code.match(/RCK-(\d+)/i)
      return match ? parseInt(match[1]) : 0
    })
    .filter(val => val > 0)
    
  if (suffixes.length === 0) return 'RCK-001'
  const maxVal = Math.max(...suffixes)
  return 'RCK-' + String(maxVal + 1).padStart(3, '0')
})

const updateNextRackCode = () => {
  if (!isEditMode.value) {
    form.value.code = nextRackCode.value
  }
}

watch([racks, isEditMode], () => {
  updateNextRackCode()
}, { immediate: true })

// Client-side validations
const validateForm = () => {
  let isValid = true
  errors.value = {
    warehouse_id: '',
    code: '',
    name: '',
    description: '',
    sort_order: ''
  }

  // Warehouse validation
  if (!form.value.warehouse_id) {
    errors.value.warehouse_id = 'Warehouse selection is required.'
    isValid = false
  }

  // Code validation
  if (!form.value.code || form.value.code.trim() === '') {
    errors.value.code = 'Rack code is required.'
    isValid = false
  } else if (form.value.code.length > 50) {
    errors.value.code = 'Rack code must not exceed 50 characters.'
    isValid = false
  }

  // Name validation
  if (!form.value.name || form.value.name.trim() === '') {
    errors.value.name = 'Rack name is required.'
    isValid = false
  } else if (form.value.name.length < 3) {
    errors.value.name = 'Rack name must be at least 3 characters.'
    isValid = false
  } else if (form.value.name.length > 255) {
    errors.value.name = 'Rack name must not exceed 255 characters.'
    isValid = false
  }

  // Sort Order validation
  if (form.value.sort_order !== null && form.value.sort_order !== '') {
    if (!Number.isInteger(Number(form.value.sort_order))) {
      errors.value.sort_order = 'Sort order must be an integer.'
      isValid = false
    }
  }

  return isValid
}

// Reset form
const resetForm = () => {
  isEditMode.value = false
  editId.value = null
  activeFormTab.value = 'general'
  form.value = {
    warehouse_id: '',
    code: '',
    name: '',
    description: '',
    sort_order: 0,
    is_active: true
  }
  errors.value = {
    warehouse_id: '',
    code: '',
    name: '',
    description: '',
    sort_order: ''
  }
  planogramShelves.value = []
  resetPlanogramForm()
  updateNextRackCode()
}

const resetPlanogramForm = () => {
  planogramForm.value = {
    product_id: '',
    shelf_level: 1,
    position_order: 1,
    facing: 1,
    max_capacity: 10
  }
  planogramError.value = ''
  planogramErrors.value = {
    product_id: '',
    shelf_level: '',
    position_order: '',
    facing: '',
    max_capacity: ''
  }
}

// Submit Rack Details
const handleSubmit = async () => {
  if (!validateForm()) return

  submitting.value = true
  const payload = {
    warehouse_id: form.value.warehouse_id,
    code: form.value.code.trim(),
    name: form.value.name.trim(),
    description: form.value.description && form.value.description.trim() !== '' ? form.value.description.trim() : null,
    sort_order: form.value.sort_order !== null ? parseInt(form.value.sort_order) : 0,
    is_active: form.value.is_active ? 1 : 0
  }

  try {
    if (isEditMode.value) {
      await api.put(`/racks/${editId.value}`, payload)
      toast.success('Rack details updated successfully!')
      fetchRacks()
    } else {
      const response = await api.post('/racks', payload)
      toast.success('Rack created successfully!')
      
      // Auto switch to edit mode so they can populate the planogram immediately
      const newRack = response.data.data || response.data
      if (newRack && newRack.id) {
        handleEdit(newRack)
        activeFormTab.value = 'planogram'
      } else {
        resetForm()
      }
      fetchRacks()
    }
  } catch (error) {
    console.error('Error saving rack:', error)
    if (error.response && error.response.status === 422) {
      const serverErrors = error.response.data.errors || {}
      Object.keys(serverErrors).forEach(key => {
        if (errors.value[key] !== undefined) {
          errors.value[key] = serverErrors[key][0]
        }
      })
      toast.error('Please correct the highlighted errors.')
    } else {
      toast.error(error.response?.data?.message || 'Something went wrong while saving the rack.')
    }
  } finally {
    submitting.value = false
  }
}

// Set form into edit mode
const handleEdit = (rack) => {
  resetForm()
  isEditMode.value = true
  editId.value = rack.id
  form.value = {
    warehouse_id: rack.warehouse_id,
    code: rack.code,
    name: rack.name,
    description: rack.description || '',
    sort_order: rack.sort_order !== undefined ? rack.sort_order : 0,
    is_active: rack.is_active === 1 || rack.is_active === true
  }
  
  // Load planogram data
  fetchPlanogram(rack.id)
}

// Delete rack
const handleDelete = async (id) => {
  if (!confirm('Are you sure you want to delete this rack? All product planogram mappings will be removed.')) return

  try {
    await api.delete(`/racks/${id}`)
    toast.success('Rack deleted successfully!')
    if (editId.value === id) {
      resetForm()
    }
    fetchRacks()
  } catch (error) {
    console.error('Error deleting rack:', error)
    toast.error('Failed to delete the rack. Please try again.')
  }
}

// Validate planogram input fields client-side
const validatePlanogramForm = () => {
  let isValid = true
  planogramErrors.value = {
    product_id: '',
    shelf_level: '',
    position_order: '',
    facing: '',
    max_capacity: ''
  }

  // Product Selection
  if (!planogramForm.value.product_id) {
    planogramErrors.value.product_id = 'Product SKU is required.'
    isValid = false
  }

  // Shelf Level
  const shelfLevel = Number(planogramForm.value.shelf_level)
  if (!planogramForm.value.shelf_level && planogramForm.value.shelf_level !== 0) {
    planogramErrors.value.shelf_level = 'Shelf level is required.'
    isValid = false
  } else if (!Number.isInteger(shelfLevel) || shelfLevel < 1 || shelfLevel > 10) {
    planogramErrors.value.shelf_level = 'Must be an integer between 1 and 10.'
    isValid = false
  }

  // Position Order
  const positionOrder = Number(planogramForm.value.position_order)
  if (!planogramForm.value.position_order && planogramForm.value.position_order !== 0) {
    planogramErrors.value.position_order = 'Position order is required.'
    isValid = false
  } else if (!Number.isInteger(positionOrder) || positionOrder < 1) {
    planogramErrors.value.position_order = 'Must be a positive integer.'
    isValid = false
  }

  // Facing Count
  const facing = Number(planogramForm.value.facing)
  if (!planogramForm.value.facing && planogramForm.value.facing !== 0) {
    planogramErrors.value.facing = 'Facing count is required.'
    isValid = false
  } else if (!Number.isInteger(facing) || facing < 1) {
    planogramErrors.value.facing = 'Must be a positive integer.'
    isValid = false
  }

  // Max Capacity
  const maxCapacity = Number(planogramForm.value.max_capacity)
  if (!planogramForm.value.max_capacity && planogramForm.value.max_capacity !== 0) {
    planogramErrors.value.max_capacity = 'Max capacity is required.'
    isValid = false
  } else if (!Number.isInteger(maxCapacity) || maxCapacity < 1) {
    planogramErrors.value.max_capacity = 'Must be a positive integer.'
    isValid = false
  }

  return isValid
}

// Select a product from the visual planogram to edit its details
const handleSelectProductForEditing = (product, shelfLevel) => {
  planogramForm.value = {
    product_id: product.id,
    shelf_level: shelfLevel,
    position_order: product.planogram.position_order,
    facing: product.planogram.facing,
    max_capacity: product.planogram.max_capacity
  }
  planogramError.value = ''
  planogramErrors.value = {
    product_id: '',
    shelf_level: '',
    position_order: '',
    facing: '',
    max_capacity: ''
  }
}

// Add product to planogram shelf with upsert capability
const handleAddProductToShelf = async () => {
  if (!validatePlanogramForm()) {
    return
  }
  
  planogramError.value = ''
  
  // Format all items mapped so far, plus the new one
  const currentItems = []
  
  // Extract from existing layout
  planogramShelves.value.forEach(shelf => {
    shelf.products.forEach(prod => {
      currentItems.push({
        product_id: prod.id,
        shelf_level: shelf.shelf_level,
        position_order: prod.planogram.position_order,
        facing: prod.planogram.facing,
        max_capacity: prod.planogram.max_capacity
      })
    })
  })

  // Upsert the mapped item
  const existingIndex = currentItems.findIndex(item => item.product_id === planogramForm.value.product_id)
  if (existingIndex > -1) {
    currentItems[existingIndex] = {
      product_id: planogramForm.value.product_id,
      shelf_level: parseInt(planogramForm.value.shelf_level),
      position_order: parseInt(planogramForm.value.position_order),
      facing: parseInt(planogramForm.value.facing),
      max_capacity: parseInt(planogramForm.value.max_capacity)
    }
  } else {
    currentItems.push({
      product_id: planogramForm.value.product_id,
      shelf_level: parseInt(planogramForm.value.shelf_level),
      position_order: parseInt(planogramForm.value.position_order),
      facing: parseInt(planogramForm.value.facing),
      max_capacity: parseInt(planogramForm.value.max_capacity)
    })
  }

  try {
    await api.post(`/racks/${editId.value}/planogram`, { items: currentItems })
    toast.success('Product successfully mapped to planogram!')
    resetPlanogramForm()
    fetchPlanogram(editId.value)
  } catch (error) {
    console.error('Error adding product to planogram:', error)
    if (error.response && error.response.status === 422) {
      const serverErrors = error.response.data.errors || {}
      planogramError.value = error.response.data.message || 'Validation error from server.'
      
      // Reset individual errors
      planogramErrors.value = {
        product_id: '',
        shelf_level: '',
        position_order: '',
        facing: '',
        max_capacity: ''
      }

      // Map backend validation errors back to specific fields if index matches
      const formProductId = planogramForm.value.product_id
      const itemIndex = currentItems.findIndex(item => item.product_id === formProductId)
      
      if (itemIndex > -1) {
        const fields = ['product_id', 'shelf_level', 'position_order', 'facing', 'max_capacity']
        fields.forEach(field => {
          const serverKey = `items.${itemIndex}.${field}`
          if (serverErrors[serverKey]) {
            planogramErrors.value[field] = serverErrors[serverKey][0]
          }
        })
      }
      
      toast.error('Validation error when updating planogram.')
    } else {
      toast.error(error.response?.data?.message || 'Failed to update planogram layout.')
    }
  }
}

// Remove product from planogram shelf
const handleRemoveProductFromShelf = async (productId) => {
  if (!confirm('Are you sure you want to remove this product from the rack?')) return

  const currentItems = []
  planogramShelves.value.forEach(shelf => {
    shelf.products.forEach(prod => {
      if (prod.id !== productId) {
        currentItems.push({
          product_id: prod.id,
          shelf_level: shelf.shelf_level,
          position_order: prod.planogram.position_order,
          facing: prod.planogram.facing,
          max_capacity: prod.planogram.max_capacity
        })
      }
    })
  })

  try {
    await api.post(`/racks/${editId.value}/planogram`, { items: currentItems })
    toast.success('Product removed from planogram.')
    fetchPlanogram(editId.value)
  } catch (error) {
    console.error('Error removing product from planogram:', error)
    toast.error('Failed to update planogram layout.')
  }
}

// Filtering
const filteredRacks = computed(() => {
  if (!searchQuery.value) return racks.value
  const query = searchQuery.value.toLowerCase().trim()
  return racks.value.filter(rack => 
    rack.name.toLowerCase().includes(query) || 
    (rack.code && rack.code.toLowerCase().includes(query)) ||
    (rack.description && rack.description.toLowerCase().includes(query)) ||
    (rack.warehouse && rack.warehouse.name.toLowerCase().includes(query))
  )
})

// Pagination
const currentPage = ref(1)
const pageSize = ref(10)

watch(searchQuery, () => {
  currentPage.value = 1
})

const paginatedRacks = computed(() => {
  const startIndex = (currentPage.value - 1) * pageSize.value
  const endIndex = startIndex + pageSize.value
  return filteredRacks.value.slice(startIndex, endIndex)
})

const totalPages = computed(() => {
  return Math.ceil(filteredRacks.value.length / pageSize.value) || 1
})

watch(totalPages, (newVal) => {
  if (currentPage.value > newVal) {
    currentPage.value = newVal
  }
})

const goToPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

const startRange = computed(() => {
  if (filteredRacks.value.length === 0) return 0
  return (currentPage.value - 1) * pageSize.value + 1
})

const endRange = computed(() => {
  const calculatedEnd = currentPage.value * pageSize.value
  return Math.min(calculatedEnd, filteredRacks.value.length)
})

onMounted(() => {
  fetchRacks()
  fetchWarehouses()
  fetchProducts()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
      <div>
        <h2 class="text-xl font-bold text-gray-800">Racks & Planograms</h2>
        <p class="text-xs text-gray-500">Configure warehouse physical racks, shelf levels, and map product planogram positions visually.</p>
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
          placeholder="Search racks..." 
          class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
        />
      </div>
    </div>

    <!-- Main Content Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      
      <!-- Left Column: Form Column (5 Cols) -->
      <div class="lg:col-span-5 bg-white border border-gray-100 p-6 rounded-2xl shadow-sm h-fit">
        <h3 class="text-md font-bold text-gray-800 mb-4 flex items-center gap-2">
          <span class="p-1.5 rounded-lg bg-emerald-50 text-emerald-600">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
          </span>
          {{ isEditMode ? 'Edit Rack Configuration' : 'Add New Storage Rack' }}
        </h3>

        <!-- Form Tab Headers -->
        <div class="flex border-b border-gray-100 mb-5 text-xs font-bold text-gray-400">
          <button 
            type="button" 
            @click="activeFormTab = 'general'"
            class="flex-1 pb-3 text-center transition-colors border-b-2 cursor-pointer"
            :class="activeFormTab === 'general' ? 'border-emerald-500 text-emerald-600' : 'border-transparent hover:text-gray-600'"
          >
            General Info
          </button>
          <button 
            type="button" 
            @click="isEditMode ? activeFormTab = 'planogram' : null"
            class="flex-1 pb-3 text-center transition-colors border-b-2"
            :class="[
              !isEditMode ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer',
              activeFormTab === 'planogram' ? 'border-emerald-500 text-emerald-600' : 'border-transparent hover:text-gray-600'
            ]"
            :title="!isEditMode ? 'Save Rack details first to unlock products layout' : 'Configure Products Layout'"
          >
            Planogram & Products
          </button>
        </div>

        <!-- Sub-Form Tab 1: General Info -->
        <form v-show="activeFormTab === 'general'" @submit.prevent="handleSubmit" class="space-y-4">
          <!-- Warehouse ID -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Storage Warehouse *</label>
            <select 
              v-model="form.warehouse_id"
              class="w-full px-4 py-2 border bg-white rounded-xl text-sm focus:outline-none focus:border-emerald-500 transition-colors cursor-pointer"
              :class="errors.warehouse_id ? 'border-red-400 focus:border-red-400' : 'border-gray-200'"
            >
              <option value="">-- Choose Warehouse --</option>
              <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">
                {{ wh.name }} ({{ wh.code }})
              </option>
            </select>
            <p v-if="errors.warehouse_id" class="text-xs text-red-500 font-medium mt-1">{{ errors.warehouse_id }}</p>
          </div>

          <!-- Rack Code -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Rack Code *</label>
            <input 
              v-model="form.code"
              type="text"
              placeholder="e.g. RCK-001"
              class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 transition-colors"
              :class="errors.code ? 'border-red-400 focus:border-red-400' : 'border-gray-200'"
            />
            <p v-if="errors.code" class="text-xs text-red-500 font-medium mt-1">{{ errors.code }}</p>
          </div>

          <!-- Rack Name -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Rack Name *</label>
            <input 
              v-model="form.name"
              type="text"
              placeholder="e.g. Fresh Dairy Cooler Rack A"
              class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 transition-colors"
              :class="errors.name ? 'border-red-400 focus:border-red-400' : 'border-gray-200'"
            />
            <p v-if="errors.name" class="text-xs text-red-500 font-medium mt-1">{{ errors.name }}</p>
          </div>

          <!-- Description -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Description</label>
            <textarea 
              v-model="form.description"
              rows="3"
              placeholder="Describe physical location or shelf characteristics..."
              class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 transition-colors resize-none border-gray-200"
            ></textarea>
          </div>

          <!-- Sort Order -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Display Sort Order</label>
            <input 
              v-model.number="form.sort_order"
              type="number"
              placeholder="0"
              class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 transition-colors"
              :class="errors.sort_order ? 'border-red-400 focus:border-red-400' : 'border-gray-200'"
            />
            <p v-if="errors.sort_order" class="text-xs text-red-500 font-medium mt-1">{{ errors.sort_order }}</p>
          </div>

          <!-- Status active toggle -->
          <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100">
            <div class="flex flex-col">
              <span class="text-xs font-bold text-gray-700">Operational Active Status</span>
              <span class="text-[10px] text-gray-400">Available for stock mapping and audits.</span>
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
              <span>{{ isEditMode ? 'Update Rack' : 'Save & Continue' }}</span>
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

        <!-- Sub-Form Tab 2: Associated Products & Planogram -->
        <div v-show="activeFormTab === 'planogram'" class="space-y-5">
          <div class="p-4 bg-emerald-50/30 border border-emerald-100 rounded-xl space-y-4">
            <h4 class="text-xs font-extrabold text-emerald-800 uppercase tracking-wider flex items-center gap-1.5">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5 text-emerald-600">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.5h18M5.25 13.5v7.5m13.5-7.5v7.5m-11.25-7.5H9.75M9.75 3v10.5m0-10.5h4.5m-4.5 0a2.25 2.25 0 0 0-2.25 2.25v2.25M14.25 3v10.5m0-10.5a2.25 2.25 0 0 1 2.25 2.25v2.25m-4.5-4.5H18M18 10.5h-4.5" />
              </svg>
              Map Product to Rack Shelf
            </h4>

            <div class="space-y-3">
              <!-- Select Product -->
              <div class="space-y-1">
                <label class="block text-[10px] font-bold text-emerald-800/80">PRODUCT SKU *</label>
                <select 
                  v-model="planogramForm.product_id"
                  class="w-full px-3 py-1.5 border bg-white rounded-lg text-xs focus:outline-none focus:border-emerald-500 transition-colors cursor-pointer"
                  :class="planogramErrors.product_id ? 'border-red-400 focus:border-red-400 animate-shake' : 'border-emerald-100'"
                >
                  <option value="">-- Choose Product --</option>
                  <option v-for="prod in productsList" :key="prod.id" :value="prod.id">
                    {{ prod.name }} ({{ prod.code }})
                  </option>
                </select>
                <p v-if="planogramErrors.product_id" class="text-[9px] text-red-500 font-semibold mt-0.5 leading-none">{{ planogramErrors.product_id }}</p>
              </div>

              <!-- Grid details: shelf level, position order, facing, capacity -->
              <div class="grid grid-cols-2 gap-2">
                <div class="space-y-1">
                  <label class="block text-[10px] font-bold text-emerald-800/80">SHELF LEVEL (1-10)</label>
                  <input 
                    type="number" 
                    min="1"
                    max="10"
                    v-model.number="planogramForm.shelf_level"
                    class="w-full px-3 py-1 border bg-white rounded-lg text-xs focus:outline-none focus:border-emerald-500"
                    :class="planogramErrors.shelf_level ? 'border-red-400 focus:border-red-400 animate-shake' : 'border-emerald-100'"
                  />
                  <p v-if="planogramErrors.shelf_level" class="text-[9px] text-red-500 font-semibold mt-0.5 leading-none">{{ planogramErrors.shelf_level }}</p>
                </div>
                <div class="space-y-1">
                  <label class="block text-[10px] font-bold text-emerald-800/80">POSITION ORDER (L->R)</label>
                  <input 
                    type="number" 
                    min="1"
                    max="50"
                    v-model.number="planogramForm.position_order"
                    class="w-full px-3 py-1 border bg-white rounded-lg text-xs focus:outline-none focus:border-emerald-500"
                    :class="planogramErrors.position_order ? 'border-red-400 focus:border-red-400 animate-shake' : 'border-emerald-100'"
                  />
                  <p v-if="planogramErrors.position_order" class="text-[9px] text-red-500 font-semibold mt-0.5 leading-none">{{ planogramErrors.position_order }}</p>
                </div>
                <div class="space-y-1">
                  <label class="block text-[10px] font-bold text-emerald-800/80">FACING COUNT</label>
                  <input 
                    type="number" 
                    min="1"
                    v-model.number="planogramForm.facing"
                    class="w-full px-3 py-1 border bg-white rounded-lg text-xs focus:outline-none focus:border-emerald-500"
                    :class="planogramErrors.facing ? 'border-red-400 focus:border-red-400 animate-shake' : 'border-emerald-100'"
                  />
                  <p v-if="planogramErrors.facing" class="text-[9px] text-red-500 font-semibold mt-0.5 leading-none">{{ planogramErrors.facing }}</p>
                </div>
                <div class="space-y-1">
                  <label class="block text-[10px] font-bold text-emerald-800/80">MAX CAPACITY</label>
                  <input 
                    type="number" 
                    min="1"
                    v-model.number="planogramForm.max_capacity"
                    class="w-full px-3 py-1 border bg-white rounded-lg text-xs focus:outline-none focus:border-emerald-500"
                    :class="planogramErrors.max_capacity ? 'border-red-400 focus:border-red-400 animate-shake' : 'border-emerald-100'"
                  />
                  <p v-if="planogramErrors.max_capacity" class="text-[9px] text-red-500 font-semibold mt-0.5 leading-none">{{ planogramErrors.max_capacity }}</p>
                </div>
              </div>

              <!-- Planogram error validation -->
              <p v-if="planogramError" class="text-xs text-red-500 font-semibold mt-1 p-2 bg-red-50 border border-red-100 rounded-lg">{{ planogramError }}</p>

              <div class="flex gap-2">
                <button 
                  type="button"
                  @click="handleAddProductToShelf"
                  class="flex-1 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition-all cursor-pointer shadow-sm active:scale-95"
                >
                  Map on Shelf
                </button>
                <button 
                  type="button"
                  @click="resetPlanogramForm"
                  class="px-3 py-1.5 rounded-lg border border-emerald-200 text-emerald-700 hover:bg-emerald-50 text-xs font-bold transition-all cursor-pointer active:scale-95"
                  title="Clear Form"
                >
                  Reset
                </button>
              </div>
            </div>
          </div>

          <!-- Go Back to Rack Details -->
          <button 
            type="button" 
            @click="activeFormTab = 'general'"
            class="w-full py-2 px-4 rounded-xl border border-dashed border-gray-300 hover:border-gray-400 text-gray-500 hover:text-gray-700 text-xs font-bold text-center transition-all cursor-pointer"
          >
            ← Back to Rack Details Settings
          </button>
        </div>

      </div>

      <!-- Right Column: Visual Planogram and Directory List (7 Cols) -->
      <div class="lg:col-span-7 bg-white border border-gray-100 p-6 rounded-2xl shadow-sm flex flex-col min-w-0">
        
        <!-- Tab Switching header for Right Column: Directory List vs Planogram Visualizer -->
        <div class="flex items-center justify-between border-b border-gray-100 pb-3 mb-5">
          <h3 class="text-md font-bold text-gray-800">
            {{ activeFormTab === 'planogram' ? 'Visual Planogram Viewer' : 'Racks Directory' }}
          </h3>
          <span 
            v-if="activeFormTab === 'planogram'"
            class="text-[10px] bg-indigo-50 border border-indigo-100 text-indigo-700 font-extrabold px-2.5 py-0.5 rounded-full uppercase tracking-wider"
          >
            Rack Code: {{ form.code }}
          </span>
        </div>

        <!-- 1. IF Tab is GENERAL: Show Racks directory listing -->
        <div v-if="activeFormTab === 'general'" class="flex-1 flex flex-col min-w-0">
          <!-- Loading State -->
          <div v-if="loading" class="flex-1 flex flex-col items-center justify-center py-24 space-y-3">
            <svg class="animate-spin h-8 w-8 text-emerald-500" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-sm font-semibold text-gray-500">Loading racks directory...</span>
          </div>

          <!-- Empty State -->
          <div v-else-if="filteredRacks.length === 0" class="flex-1 flex flex-col items-center justify-center py-24 text-center space-y-4">
            <span class="w-16 h-16 rounded-2xl bg-gray-50 flex items-center justify-center text-gray-300">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h12M3.75 12h12m-12 5.25h12m-13.5-12h15" />
              </svg>
            </span>
            <div>
              <h4 class="text-sm font-bold text-gray-700">No Racks Registered</h4>
              <p class="text-xs text-gray-400 max-w-[280px] mx-auto mt-1">
                {{ searchQuery ? 'No racks match your current search.' : 'Create your physical storage rack on the left to get started.' }}
              </p>
            </div>
          </div>

          <!-- Racks Table -->
          <div v-else class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs font-semibold text-gray-600">
              <thead>
                <tr class="border-b border-gray-100 text-gray-400 uppercase tracking-wider text-[10px]">
                  <th class="pb-3 w-12">Icon</th>
                  <th class="pb-3">Rack Profile</th>
                  <th class="pb-3">Warehouse Hub</th>
                  <th class="pb-3 text-center">Rack Code</th>
                  <th class="pb-3 text-center">Sort Order</th>
                  <th class="pb-3 w-20 text-center">Status</th>
                  <th class="pb-3 w-24 text-center">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                <tr v-for="rack in paginatedRacks" :key="rack.id" class="hover:bg-gray-50/50 transition-colors">
                  <td class="py-3">
                    <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-indigo-500 to-emerald-500 text-white flex items-center justify-center shadow-sm">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12" />
                      </svg>
                    </div>
                  </td>
                  <td class="py-3 pr-4">
                    <span class="text-gray-800 font-bold block">{{ rack.name }}</span>
                    <span class="text-[10px] text-gray-400 font-medium block truncate max-w-[140px]">{{ rack.description || 'No description' }}</span>
                  </td>
                  <td class="py-3 text-gray-700 font-bold pr-2">
                    {{ rack.warehouse ? rack.warehouse.name : '-' }}
                  </td>
                  <td class="py-3 text-center text-gray-800 font-bold">
                    <span class="px-2 py-0.5 bg-gray-50 border border-gray-100 rounded-md">
                      {{ rack.code }}
                    </span>
                  </td>
                  <td class="py-3 text-center text-gray-400 font-bold">
                    {{ rack.sort_order }}
                  </td>
                  <td class="py-3 text-center">
                    <span 
                      class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold"
                      :class="rack.is_active === 1 || rack.is_active === true
                        ? 'bg-emerald-50 text-emerald-600'
                        : 'bg-gray-100 text-gray-500'"
                    >
                      <span class="w-1.5 h-1.5 rounded-full" :class="rack.is_active === 1 || rack.is_active === true ? 'bg-emerald-500' : 'bg-gray-400'"></span>
                      {{ rack.is_active === 1 || rack.is_active === true ? 'Active' : 'Inactive' }}
                    </span>
                  </td>
                  <td class="py-3 text-center">
                    <div class="flex items-center justify-center gap-1">
                      <button 
                        @click="handleEdit(rack)"
                        class="w-8 h-8 rounded-lg bg-gray-50 text-gray-500 hover:text-emerald-600 hover:bg-emerald-50 transition-colors flex items-center justify-center cursor-pointer"
                        title="Edit Configuration & Planogram"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                          <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                        </svg>
                      </button>
                      <button 
                        @click="handleDelete(rack.id)"
                        class="w-8 h-8 rounded-lg bg-gray-50 text-gray-500 hover:text-red-600 hover:bg-red-50 transition-colors flex items-center justify-center cursor-pointer"
                        title="Delete Rack"
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
          <div v-if="filteredRacks.length > 0" class="mt-auto pt-6 border-t border-gray-100 flex flex-col sm:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-3 text-xs text-gray-500">
              <span>Showing {{ startRange }} to {{ endRange }} of {{ filteredRacks.length }} entries</span>
              <div class="flex items-center gap-1.5 ml-2 border-l border-gray-200 pl-3">
                <label class="font-medium text-gray-400">Show:</label>
                <select 
                  v-model="pageSize" 
                  @change="currentPage = 1"
                  class="bg-gray-50 border border-gray-200 rounded-lg px-2 py-1 focus:outline-none text-xs font-bold text-gray-700 transition-colors cursor-pointer"
                >
                  <option :value="5">5</option>
                  <option :value="10">10</option>
                  <option :value="25">25</option>
                  <option :value="50">50</option>
                </select>
              </div>
            </div>

            <!-- Page Selection Buttons -->
            <div class="flex items-center gap-1">
              <!-- First Page -->
              <button 
                @click="goToPage(1)" 
                :disabled="currentPage === 1"
                class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 disabled:opacity-40 transition-all cursor-pointer"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m18.75 5.25-7.5 7.5 7.5 7.5m-6-15L5.25 12l7.5 7.5" />
                </svg>
              </button>

              <!-- Previous -->
              <button 
                @click="goToPage(currentPage - 1)" 
                :disabled="currentPage === 1"
                class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 disabled:opacity-40 transition-all cursor-pointer"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
              </button>

              <template v-for="page in totalPages" :key="page">
                <button 
                  v-if="Math.abs(page - currentPage) <= 1 || page === 1 || page === totalPages"
                  @click="goToPage(page)"
                  class="w-8 h-8 rounded-lg border text-xs font-bold transition-all cursor-pointer"
                  :class="currentPage === page ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                >
                  {{ page }}
                </button>
                <span v-else-if="(page === 2 && currentPage > 3) || (page === totalPages - 1 && currentPage < totalPages - 2)" class="w-8 h-8 flex items-center justify-center text-gray-400 text-xs">...</span>
              </template>

              <!-- Next -->
              <button 
                @click="goToPage(currentPage + 1)" 
                :disabled="currentPage === totalPages"
                class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 disabled:opacity-40 transition-all cursor-pointer"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
              </button>

              <!-- Last Page -->
              <button 
                @click="goToPage(totalPages)" 
                :disabled="currentPage === totalPages"
                class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 disabled:opacity-40 transition-all cursor-pointer"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m5.25 5.25 7.5 7.5-7.5 7.5m6-15 7.5 7.5-7.5 7.5" />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- 2. IF Tab is PLANOGRAM: Show interactive visual planogram shelves -->
        <div v-else class="flex-1 flex flex-col min-w-0">
          <div v-if="planogramLoading" class="flex-1 flex flex-col items-center justify-center py-20 space-y-3">
            <svg class="animate-spin h-8 w-8 text-indigo-500" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-sm font-semibold text-gray-500">Loading planogram layout...</span>
          </div>

          <div v-else-if="planogramShelves.length === 0" class="flex-1 flex flex-col items-center justify-center py-20 text-center space-y-4">
            <span class="w-16 h-16 rounded-2xl bg-indigo-50 text-indigo-300 flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 0 1 2.008 1.24l.885 1.77a2.25 2.25 0 0 0 2.007 1.24h1.98a2.25 2.25 0 0 0 2.007-1.24l.885-1.77a2.25 2.25 0 0 1 2.007-1.24h3.86m-18 0h18" />
              </svg>
            </span>
            <div>
              <h4 class="text-sm font-bold text-gray-700">Shelf Layout is Empty</h4>
              <p class="text-xs text-gray-400 max-w-[280px] mx-auto mt-1">
                No products are currently mapped to this rack. Add products using the form on the left.
              </p>
            </div>
          </div>

          <!-- visual representation of wooden/glass shelves -->
          <div v-else class="space-y-8 flex-1 overflow-y-auto max-h-[500px] pr-2 pb-6">
            <div 
              v-for="shelf in planogramShelves" 
              :key="shelf.shelf_level" 
              class="relative"
            >
              <!-- Shelf Header / Level Indicator -->
              <div class="flex justify-between items-center mb-2 px-1">
                <span class="text-[10px] uppercase font-extrabold text-indigo-700 tracking-widest">
                  {{ shelf.level_name }}
                </span>
                <span class="text-[9px] text-gray-400 font-bold">
                  {{ shelf.products.length }} product(s) mapped
                </span>
              </div>

              <!-- Product Shelf Container Area -->
              <div class="bg-slate-100 rounded-xl p-4 min-h-[110px] flex flex-wrap gap-3 border border-slate-200/50 shadow-inner items-end">
                <div 
                  v-for="prod in shelf.products" 
                  :key="prod.id"
                  @click="handleSelectProductForEditing(prod, shelf.shelf_level)"
                  class="w-[120px] bg-white border border-slate-200 rounded-lg p-2 shadow-sm relative group flex flex-col justify-between hover:shadow-md transition-all cursor-pointer hover:border-indigo-400 hover:ring-2 hover:ring-indigo-100"
                  title="Click to edit mapping details"
                >
                  <!-- Delete button (visible on hover) -->
                  <button 
                    type="button" 
                    @click.stop="handleRemoveProductFromShelf(prod.id)"
                    class="absolute -top-1.5 -right-1.5 bg-red-500 hover:bg-red-600 text-white rounded-full w-5 h-5 flex items-center justify-center shadow cursor-pointer transition-all hover:scale-110 opacity-0 group-hover:opacity-100 transition-opacity duration-200"
                    title="Remove Product"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-2.5 h-2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                  </button>

                  <div class="text-[9px] font-extrabold text-gray-400 truncate mb-0.5">
                    {{ prod.code }}
                  </div>
                  <div class="text-[11px] font-bold text-gray-800 line-clamp-2 leading-tight min-h-[32px]">
                    {{ prod.name }}
                  </div>

                  <!-- Small metrics bar -->
                  <div class="mt-2 pt-1 border-t border-slate-100 grid grid-cols-3 gap-0.5 text-[8px] text-center font-extrabold">
                    <div class="bg-indigo-50 text-indigo-600 rounded px-0.5 py-0.2" title="Position Order">
                      P:{{ prod.planogram.position_order }}
                    </div>
                    <div class="bg-emerald-50 text-emerald-600 rounded px-0.5 py-0.2" title="Facing">
                      F:{{ prod.planogram.facing }}
                    </div>
                    <div class="bg-amber-50 text-amber-600 rounded px-0.5 py-0.2" title="Max Display Capacity">
                      C:{{ prod.planogram.max_capacity }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Visual Wooden bar effect representing physical shelf -->
              <div class="h-3.5 bg-gradient-to-r from-amber-700 via-amber-600 to-amber-800 rounded-b-lg shadow-md border-t border-amber-500/20"></div>
            </div>
          </div>
        </div>

      </div>

    </div>
  </div>
</template>

<style scoped>
/* Custom styled vertical scrollbars */
.max-h-\[500px\]::-webkit-scrollbar {
  width: 4px;
}
.max-h-\[500px\]::-webkit-scrollbar-track {
  background: transparent;
}
.max-h-\[500px\]::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 9999px;
}
.max-h-\[500px\]::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>
