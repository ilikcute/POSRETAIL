<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()

// ─── States ──────────────────────────────────────────────────────────────────
const products    = ref([])
const categories  = ref([])
const brands      = ref([])
const units       = ref([])
const loading     = ref(false)
const submitting  = ref(false)
const searchQuery = ref('')
const activeFormTab = ref('profile') // 'profile' | 'pricing' | 'stock'

// ─── Image Upload ─────────────────────────────────────────────────────────────
const imageFile       = ref(null)
const imagePreviewUrl = ref(null)
const fileInput       = ref(null)

// ─── Form State ───────────────────────────────────────────────────────────────
const isEditMode = ref(false)
const editId     = ref(null)
const form = ref({
  category_id: '',
  brand_id: '',
  unit_id: '',
  code: '',
  name: '',
  sku: '',
  barcode: '',
  description: '',
  cost_price: '',
  price: '',
  wholesale_price: '',
  min_margin_percentage: 10,
  safety_stock: 0,
  reorder_point: 0,
  lead_time: 0,
  purchase_type: 'outright',
  consignment_commission_fee: 0,
  is_taxable: true,
  is_consignment: false,
  is_active: true,
})

// ─── Error State ──────────────────────────────────────────────────────────────
const errors = ref({
  category_id: '',
  unit_id: '',
  code: '',
  name: '',
  sku: '',
  barcode: '',
  cost_price: '',
  price: '',
  wholesale_price: '',
  min_margin_percentage: '',
  safety_stock: '',
  reorder_point: '',
  lead_time: '',
  consignment_commission_fee: '',
  image: '',
})

// ─── Computed: Live Margin Check ─────────────────────────────────────────────
const liveMargin = computed(() => {
  const cost  = parseFloat(form.value.cost_price)
  const price = parseFloat(form.value.price)
  if (!cost || !price || price === 0) return null
  return ((price - cost) / price) * 100
})

const marginStatus = computed(() => {
  if (liveMargin.value === null) return null
  const minMargin = parseFloat(form.value.min_margin_percentage) || 0
  return {
    margin: liveMargin.value.toFixed(2),
    ok: liveMargin.value >= minMargin,
  }
})

// ─── Fetch Lookups ────────────────────────────────────────────────────────────
const fetchLookups = async () => {
  try {
    const [catRes, brandRes, unitRes] = await Promise.all([
      api.get('/categories'),
      api.get('/brands'),
      api.get('/units'),
    ])
    categories.value = catRes.data?.data ?? catRes.data ?? []
    brands.value     = brandRes.data?.data ?? brandRes.data ?? []
    units.value      = unitRes.data?.data ?? unitRes.data ?? []
  } catch (err) {
    console.error('Error fetching lookups:', err)
    toast.error('Failed to load dropdown data.')
  }
}

// ─── Fetch Products ───────────────────────────────────────────────────────────
const fetchProducts = async () => {
  loading.value = true
  try {
    const response = await api.get('/products')
    products.value = response.data?.data ?? response.data ?? []
    // Eagerly load relations where available
    products.value = products.value.map(p => ({
      ...p,
      _category: categories.value.find(c => c.id === p.category_id) || null,
      _brand:    brands.value.find(b => b.id === p.brand_id)         || null,
      _unit:     units.value.find(u => u.id === p.unit_id)           || null,
    }))
  } catch (error) {
    console.error('Error fetching products:', error)
    toast.error('Failed to load products from server.')
  } finally {
    loading.value = false
  }
}

// ─── Auto Product Code Generator ─────────────────────────────────────────────
const nextProductCode = computed(() => {
  if (products.value.length === 0) return 'PRD-001'
  const suffixes = products.value
    .map(p => {
      const m = (p.code || '').match(/PRD-(\d+)/i)
      return m ? parseInt(m[1]) : 0
    })
    .filter(v => v > 0)
  if (suffixes.length === 0) return 'PRD-001'
  return 'PRD-' + String(Math.max(...suffixes) + 1).padStart(3, '0')
})

watch([products, isEditMode], () => {
  if (!isEditMode.value) {
    form.value.code = nextProductCode.value
  }
}, { immediate: true })

// ─── Image Handling ───────────────────────────────────────────────────────────
const onImageChange = (e) => {
  const file = e.target.files[0]
  if (!file) return

  if (file.size > 2 * 1024 * 1024) {
    errors.value.image = 'Image size must not exceed 2MB.'
    imageFile.value = null
    imagePreviewUrl.value = null
    if (fileInput.value) fileInput.value.value = ''
    return
  }

  if (!file.type.match('image.*')) {
    errors.value.image = 'Selected file must be a valid image (PNG, JPG, JPEG, GIF, SVG).'
    imageFile.value = null
    imagePreviewUrl.value = null
    if (fileInput.value) fileInput.value.value = ''
    return
  }

  errors.value.image = ''
  imageFile.value = file
  imagePreviewUrl.value = URL.createObjectURL(file)
}

const triggerFileInput = () => {
  if (fileInput.value) fileInput.value.click()
}

const removeImage = () => {
  imageFile.value = null
  imagePreviewUrl.value = null
  if (fileInput.value) fileInput.value.value = ''
}

// ─── Client-Side Validation ───────────────────────────────────────────────────
const validateForm = () => {
  let valid = true
  errors.value = {
    category_id: '', unit_id: '', code: '', name: '', sku: '',
    barcode: '', cost_price: '', price: '', wholesale_price: '',
    min_margin_percentage: '', safety_stock: '', reorder_point: '',
    lead_time: '', consignment_commission_fee: '', image: '',
  }

  if (!form.value.category_id) {
    errors.value.category_id = 'Category is required.'
    valid = false
  }

  if (!form.value.unit_id) {
    errors.value.unit_id = 'Unit of measure is required.'
    valid = false
  }

  if (!form.value.code || form.value.code.trim() === '') {
    errors.value.code = 'Product code is required.'
    valid = false
  } else if (form.value.code.length > 50) {
    errors.value.code = 'Product code must not exceed 50 characters.'
    valid = false
  }

  if (!form.value.name || form.value.name.trim() === '') {
    errors.value.name = 'Product name is required.'
    valid = false
  } else if (form.value.name.length < 3) {
    errors.value.name = 'Product name must be at least 3 characters.'
    valid = false
  } else if (form.value.name.length > 255) {
    errors.value.name = 'Product name must not exceed 255 characters.'
    valid = false
  }

  if (form.value.sku && form.value.sku.length > 100) {
    errors.value.sku = 'SKU must not exceed 100 characters.'
    valid = false
  }

  if (form.value.barcode && form.value.barcode.length > 100) {
    errors.value.barcode = 'Barcode must not exceed 100 characters.'
    valid = false
  }

  if (form.value.cost_price === '' || form.value.cost_price === null) {
    errors.value.cost_price = 'Cost price is required.'
    valid = false
  } else if (isNaN(parseFloat(form.value.cost_price)) || parseFloat(form.value.cost_price) < 0) {
    errors.value.cost_price = 'Cost price must be a number ≥ 0.'
    valid = false
  }

  if (form.value.price === '' || form.value.price === null) {
    errors.value.price = 'Selling price is required.'
    valid = false
  } else if (isNaN(parseFloat(form.value.price)) || parseFloat(form.value.price) < 0) {
    errors.value.price = 'Selling price must be a number ≥ 0.'
    valid = false
  }

  if (form.value.wholesale_price !== '' && form.value.wholesale_price !== null) {
    if (isNaN(parseFloat(form.value.wholesale_price)) || parseFloat(form.value.wholesale_price) < 0) {
      errors.value.wholesale_price = 'Wholesale price must be a number ≥ 0.'
      valid = false
    }
  }

  if (form.value.min_margin_percentage !== '' && form.value.min_margin_percentage !== null) {
    const mg = parseFloat(form.value.min_margin_percentage)
    if (isNaN(mg) || mg < 0 || mg > 100) {
      errors.value.min_margin_percentage = 'Min margin must be between 0 and 100.'
      valid = false
    }
  }

  if (form.value.purchase_type === 'consignment') {
    const fee = parseFloat(form.value.consignment_commission_fee)
    if (isNaN(fee) || fee < 0 || fee > 100) {
      errors.value.consignment_commission_fee = 'Commission fee must be between 0 and 100.'
      valid = false
    }
  }

  return valid
}

// ─── Reset Form ───────────────────────────────────────────────────────────────
const resetForm = () => {
  isEditMode.value = false
  editId.value = null
  activeFormTab.value = 'profile'
  form.value = {
    category_id: '', brand_id: '', unit_id: '', code: '',
    name: '', sku: '', barcode: '', description: '',
    cost_price: '', price: '', wholesale_price: '',
    min_margin_percentage: 10, safety_stock: 0, reorder_point: 0,
    lead_time: 0, purchase_type: 'outright', consignment_commission_fee: 0,
    is_taxable: true, is_consignment: false, is_active: true,
  }
  errors.value = {
    category_id: '', unit_id: '', code: '', name: '', sku: '',
    barcode: '', cost_price: '', price: '', wholesale_price: '',
    min_margin_percentage: '', safety_stock: '', reorder_point: '',
    lead_time: '', consignment_commission_fee: '', image: '',
  }
  removeImage()
}

// ─── Submit ───────────────────────────────────────────────────────────────────
const handleSubmit = async () => {
  if (!validateForm()) {
    // If error is on another tab, switch to it to show error
    if (errors.value.category_id || errors.value.unit_id || errors.value.code || errors.value.name || errors.value.sku || errors.value.barcode || errors.value.image) {
      activeFormTab.value = 'profile'
    } else if (errors.value.cost_price || errors.value.price || errors.value.wholesale_price || errors.value.min_margin_percentage) {
      activeFormTab.value = 'pricing'
    } else {
      activeFormTab.value = 'stock'
    }
    return
  }

  submitting.value = true

  const formData = new FormData()
  formData.append('category_id', form.value.category_id)
  if (form.value.brand_id) formData.append('brand_id', form.value.brand_id)
  formData.append('unit_id', form.value.unit_id)
  formData.append('code', form.value.code.trim())
  formData.append('name', form.value.name.trim())
  if (form.value.sku) formData.append('sku', form.value.sku.trim())
  if (form.value.barcode) formData.append('barcode', form.value.barcode.trim())
  if (form.value.description) formData.append('description', form.value.description.trim())
  formData.append('cost_price', form.value.cost_price)
  formData.append('price', form.value.price)
  if (form.value.wholesale_price !== '' && form.value.wholesale_price !== null) {
    formData.append('wholesale_price', form.value.wholesale_price)
  }
  if (form.value.min_margin_percentage !== '' && form.value.min_margin_percentage !== null) {
    formData.append('min_margin_percentage', form.value.min_margin_percentage)
  }
  formData.append('safety_stock', form.value.safety_stock || 0)
  formData.append('reorder_point', form.value.reorder_point || 0)
  formData.append('lead_time', form.value.lead_time || 0)
  formData.append('purchase_type', form.value.purchase_type)
  if (form.value.purchase_type === 'consignment') {
    formData.append('consignment_commission_fee', form.value.consignment_commission_fee)
  }
  formData.append('is_taxable', form.value.is_taxable ? '1' : '0')
  formData.append('is_consignment', form.value.is_consignment ? '1' : '0')
  formData.append('is_active', form.value.is_active ? '1' : '0')
  if (imageFile.value) {
    formData.append('image', imageFile.value)
  }

  try {
    if (isEditMode.value) {
      formData.append('_method', 'PUT')
      await api.post(`/products/${editId.value}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      toast.success('Product updated successfully!')
    } else {
      await api.post('/products', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      toast.success('Product created successfully!')
    }
    resetForm()
    fetchProducts()
  } catch (error) {
    console.error('Error saving product:', error)
    if (error.response?.status === 422) {
      const serverErrors = error.response.data.errors || {}
      Object.keys(serverErrors).forEach(key => {
        if (errors.value[key] !== undefined) {
          errors.value[key] = serverErrors[key][0]
        }
      })
      toast.error('Please correct the highlighted errors.')
    } else {
      toast.error(error.response?.data?.message || 'Something went wrong while saving the product.')
    }
  } finally {
    submitting.value = false
  }
}

// ─── Edit ─────────────────────────────────────────────────────────────────────
const handleEdit = (product) => {
  resetForm()
  isEditMode.value = true
  editId.value = product.id
  form.value = {
    category_id: product.category_id || '',
    brand_id: product.brand_id || '',
    unit_id: product.unit_id || '',
    code: product.code || '',
    name: product.name || '',
    sku: product.sku || '',
    barcode: product.barcode || '',
    description: product.description || '',
    cost_price: product.cost_price || '',
    price: product.price || '',
    wholesale_price: product.wholesale_price || '',
    min_margin_percentage: product.min_margin_percentage ?? 10,
    safety_stock: product.safety_stock || 0,
    reorder_point: product.reorder_point || 0,
    lead_time: product.lead_time || 0,
    purchase_type: product.purchase_type || 'outright',
    consignment_commission_fee: product.consignment_commission_fee || 0,
    is_taxable: product.is_taxable === true || product.is_taxable === 1,
    is_consignment: product.is_consignment === true || product.is_consignment === 1,
    is_active: product.is_active === true || product.is_active === 1,
  }
  // Set image preview if existing
  if (product.image_path) {
    imagePreviewUrl.value = `/storage/${product.image_path}`
  }
  activeFormTab.value = 'profile'
}

// ─── Delete ───────────────────────────────────────────────────────────────────
const handleDelete = async (id) => {
  if (!confirm('Are you sure you want to delete this product? This cannot be undone.')) return
  try {
    await api.delete(`/products/${id}`)
    toast.success('Product deleted successfully!')
    if (editId.value === id) resetForm()
    fetchProducts()
  } catch (error) {
    console.error('Error deleting product:', error)
    toast.error('Failed to delete product. Please try again.')
  }
}

// ─── Filtering ────────────────────────────────────────────────────────────────
const filteredProducts = computed(() => {
  if (!searchQuery.value) return products.value
  const q = searchQuery.value.toLowerCase().trim()
  return products.value.filter(p =>
    p.name?.toLowerCase().includes(q) ||
    p.code?.toLowerCase().includes(q) ||
    p.sku?.toLowerCase().includes(q) ||
    p.barcode?.toLowerCase().includes(q) ||
    p._category?.name?.toLowerCase().includes(q) ||
    p._brand?.name?.toLowerCase().includes(q)
  )
})

// ─── Pagination ───────────────────────────────────────────────────────────────
const currentPage = ref(1)
const pageSize    = ref(10)

watch(searchQuery, () => { currentPage.value = 1 })

const totalPages = computed(() => Math.ceil(filteredProducts.value.length / pageSize.value) || 1)

watch(totalPages, (val) => {
  if (currentPage.value > val) currentPage.value = val
})

const paginatedProducts = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value
  return filteredProducts.value.slice(start, start + pageSize.value)
})

const startRange = computed(() => {
  if (filteredProducts.value.length === 0) return 0
  return (currentPage.value - 1) * pageSize.value + 1
})

const endRange = computed(() => {
  return Math.min(currentPage.value * pageSize.value, filteredProducts.value.length)
})

const goToPage = (page) => {
  if (page >= 1 && page <= totalPages.value) currentPage.value = page
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
const formatCurrency = (val) => {
  if (val === null || val === undefined || val === '') return '-'
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0 })
}

const getImageUrl = (path) => {
  if (!path) return null
  if (path.startsWith('http') || path.startsWith('blob')) return path
  return `/storage/${path}`
}

// ─── Excel Export ─────────────────────────────────────────────────────────────
const isExporting = ref(false)

const handleExport = async () => {
  isExporting.value = true
  try {
    const token = localStorage.getItem('auth_token') ||
      document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''
    const response = await api.get('/products/export', {
      responseType: 'blob',
    })
    const url  = URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `products_export_${new Date().toISOString().slice(0,10)}.xlsx`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
    toast.success('Products exported successfully!')
  } catch (error) {
    console.error('Export failed:', error)
    toast.error('Failed to export products. Please try again.')
  } finally {
    isExporting.value = false
  }
}

const handleDownloadTemplate = async () => {
  try {
    const response = await api.get('/products/import-template', { responseType: 'blob' })
    const url  = URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', 'products_import_template.xlsx')
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(url)
    toast.success('Import template downloaded!')
  } catch (error) {
    console.error('Template download failed:', error)
    toast.error('Failed to download template.')
  }
}

// ─── Excel Import Modal ───────────────────────────────────────────────────────
const showImportModal  = ref(false)
const importFile       = ref(null)
const importFileInput  = ref(null)
const importLoading    = ref(false)
const importDragOver   = ref(false)
const importResult     = ref(null) // { success_count, failure_count, failures[] }
const showFailures     = ref(false)

const openImportModal = () => {
  showImportModal.value = true
  importFile.value   = null
  importResult.value = null
  showFailures.value = false
  if (importFileInput.value) importFileInput.value.value = ''
}

const closeImportModal = () => {
  showImportModal.value = false
  if (importResult.value?.success_count > 0) fetchProducts()
}

const triggerImportFileInput = () => {
  if (importFileInput.value) importFileInput.value.click()
}

const onImportFileChange = (e) => {
  const file = e.target.files?.[0]
  if (file) {
    importFile.value   = file
    importResult.value = null
    showFailures.value = false
  }
}

const onImportDrop = (e) => {
  importDragOver.value = false
  const file = e.dataTransfer.files?.[0]
  if (file) {
    importFile.value   = file
    importResult.value = null
    showFailures.value = false
  }
}

const handleImport = async () => {
  if (!importFile.value) {
    toast.error('Please select an Excel file first.')
    return
  }

  const allowedExtensions = ['.xlsx', '.xls', '.csv']
  const fileName = importFile.value.name.toLowerCase()
  if (!allowedExtensions.some(ext => fileName.endsWith(ext))) {
    toast.error('Only .xlsx, .xls, or .csv files are accepted.')
    return
  }

  importLoading.value = true
  importResult.value  = null

  const formData = new FormData()
  formData.append('file', importFile.value)

  try {
    const response = await api.post('/products/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    importResult.value = response.data?.data ?? response.data
    if (importResult.value.success_count > 0) {
      toast.success(`${importResult.value.success_count} product(s) imported successfully!`)
    }
    if (importResult.value.failure_count > 0) {
      toast.warning(`${importResult.value.failure_count} row(s) had validation errors.`)
    }
  } catch (error) {
    console.error('Import failed:', error)
    if (error.response?.status === 422) {
      toast.error(error.response.data.message || 'Invalid file format.')
    } else {
      toast.error('Import failed. Please check the file and try again.')
    }
  } finally {
    importLoading.value = false
  }
}

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
  await fetchLookups()
  fetchProducts()
})
</script>

<template>
  <div class="space-y-6">
    <!-- ── Header ──────────────────────────────────────────────────────── -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
      <div>
        <h2 class="text-xl font-bold text-gray-800">Products Management</h2>
        <p class="text-xs text-gray-500">Manage your product catalog, pricing, stock settings, and consignment configuration.</p>
      </div>
      <!-- Action buttons + search -->
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
        <!-- Download Template -->
        <button @click="handleDownloadTemplate"
          class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 hover:bg-gray-50 text-gray-600 text-xs font-bold transition-colors cursor-pointer whitespace-nowrap">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
          </svg>
          Template
        </button>
        <!-- Import Excel -->
        <button @click="openImportModal"
          class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl border border-violet-200 hover:bg-violet-50 text-violet-600 text-xs font-bold transition-colors cursor-pointer whitespace-nowrap">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
          </svg>
          Import Excel
        </button>
        <!-- Export Excel -->
        <button @click="handleExport" :disabled="isExporting"
          class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-violet-500 hover:bg-violet-600 text-white text-xs font-bold transition-colors cursor-pointer whitespace-nowrap disabled:opacity-60 disabled:cursor-not-allowed shadow-sm">
          <svg v-if="isExporting" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
          </svg>
          {{ isExporting ? 'Exporting...' : 'Export Excel' }}
        </button>
        <!-- Search Box -->
        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.637 10.637Z" />
            </svg>
          </span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search products..."
            class="w-full sm:w-52 pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500 transition-colors"
          />
        </div>
      </div>
    </div>

    <!-- ── Main 2-Col Layout ───────────────────────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

      <!-- ── Left Column: Form (5 cols) ──────────────────────────────── -->
      <div class="lg:col-span-5 bg-white border border-gray-100 p-6 rounded-2xl shadow-sm h-fit">
        <h3 class="text-md font-bold text-gray-800 mb-4 flex items-center gap-2">
          <span class="p-1.5 rounded-lg bg-violet-50 text-violet-600">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
          </span>
          {{ isEditMode ? 'Edit Product' : 'Add New Product' }}
        </h3>

        <!-- ── Sub-Tab Navigation ──────────────────────────────────── -->
        <div class="flex border-b border-gray-100 mb-5 text-xs font-bold text-gray-400">
          <button type="button" @click="activeFormTab = 'profile'"
            class="flex-1 pb-3 text-center transition-colors border-b-2 cursor-pointer"
            :class="activeFormTab === 'profile' ? 'border-violet-500 text-violet-600' : 'border-transparent hover:text-gray-600'">
            Profile & Info
          </button>
          <button type="button" @click="activeFormTab = 'pricing'"
            class="flex-1 pb-3 text-center transition-colors border-b-2 cursor-pointer"
            :class="activeFormTab === 'pricing' ? 'border-violet-500 text-violet-600' : 'border-transparent hover:text-gray-600'">
            Pricing
          </button>
          <button type="button" @click="activeFormTab = 'stock'"
            class="flex-1 pb-3 text-center transition-colors border-b-2 cursor-pointer"
            :class="activeFormTab === 'stock' ? 'border-violet-500 text-violet-600' : 'border-transparent hover:text-gray-600'">
            Stock & Settings
          </button>
        </div>

        <!-- ═══════════ TAB 1: Profile & Info ════════════════════════ -->
        <form v-show="activeFormTab === 'profile'" @submit.prevent="handleSubmit" class="space-y-4">

          <!-- Category -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Category *</label>
            <select v-model="form.category_id"
              class="w-full px-4 py-2 border bg-white rounded-xl text-sm focus:outline-none focus:border-violet-500 transition-colors cursor-pointer"
              :class="errors.category_id ? 'border-red-400' : 'border-gray-200'">
              <option value="">-- Select Category --</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
            <p v-if="errors.category_id" class="text-xs text-red-500 font-medium mt-1">{{ errors.category_id }}</p>
          </div>

          <!-- Brand + Unit (side-by-side) -->
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Brand</label>
              <select v-model="form.brand_id"
                class="w-full px-3 py-2 border border-gray-200 bg-white rounded-xl text-sm focus:outline-none focus:border-violet-500 transition-colors cursor-pointer">
                <option value="">-- Optional --</option>
                <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
              </select>
            </div>
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Unit *</label>
              <select v-model="form.unit_id"
                class="w-full px-3 py-2 border bg-white rounded-xl text-sm focus:outline-none focus:border-violet-500 transition-colors cursor-pointer"
                :class="errors.unit_id ? 'border-red-400' : 'border-gray-200'">
                <option value="">-- Select --</option>
                <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
              </select>
              <p v-if="errors.unit_id" class="text-xs text-red-500 font-medium mt-1">{{ errors.unit_id }}</p>
            </div>
          </div>

          <!-- Product Code -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Product Code *</label>
            <input v-model="form.code" type="text" placeholder="e.g. PRD-001"
              class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-violet-500 transition-colors"
              :class="errors.code ? 'border-red-400' : 'border-gray-200'" />
            <p v-if="errors.code" class="text-xs text-red-500 font-medium mt-1">{{ errors.code }}</p>
          </div>

          <!-- Product Name -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Product Name *</label>
            <input v-model="form.name" type="text" placeholder="e.g. Indomie Goreng Original"
              class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-violet-500 transition-colors"
              :class="errors.name ? 'border-red-400' : 'border-gray-200'" />
            <p v-if="errors.name" class="text-xs text-red-500 font-medium mt-1">{{ errors.name }}</p>
          </div>

          <!-- SKU + Barcode (side-by-side) -->
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">SKU</label>
              <input v-model="form.sku" type="text" placeholder="Optional"
                class="w-full px-3 py-2 border rounded-xl text-sm focus:outline-none focus:border-violet-500 transition-colors"
                :class="errors.sku ? 'border-red-400' : 'border-gray-200'" />
              <p v-if="errors.sku" class="text-xs text-red-500 font-medium mt-1">{{ errors.sku }}</p>
            </div>
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Barcode</label>
              <input v-model="form.barcode" type="text" placeholder="Optional"
                class="w-full px-3 py-2 border rounded-xl text-sm focus:outline-none focus:border-violet-500 transition-colors"
                :class="errors.barcode ? 'border-red-400' : 'border-gray-200'" />
              <p v-if="errors.barcode" class="text-xs text-red-500 font-medium mt-1">{{ errors.barcode }}</p>
            </div>
          </div>

          <!-- Description -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Description</label>
            <textarea v-model="form.description" rows="2" placeholder="Product description..."
              class="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-violet-500 transition-colors resize-none">
            </textarea>
          </div>

          <!-- Image Upload -->
          <div class="space-y-2">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Product Image</label>
            <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onImageChange" />

            <!-- Preview / Upload Area -->
            <div v-if="imagePreviewUrl" class="relative rounded-xl overflow-hidden border border-violet-100 h-36 group">
              <img :src="imagePreviewUrl" alt="Product Preview" class="w-full h-full object-cover" />
              <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                <button type="button" @click="triggerFileInput"
                  class="py-1 px-3 rounded-lg bg-white text-gray-800 text-xs font-bold cursor-pointer hover:bg-gray-100 transition-colors">
                  Change
                </button>
                <button type="button" @click="removeImage"
                  class="py-1 px-3 rounded-lg bg-red-500 text-white text-xs font-bold cursor-pointer hover:bg-red-600 transition-colors">
                  Remove
                </button>
              </div>
            </div>
            <button v-else type="button" @click="triggerFileInput"
              class="w-full h-24 border-2 border-dashed border-gray-200 rounded-xl flex flex-col items-center justify-center gap-1.5 text-gray-400 hover:border-violet-400 hover:text-violet-500 transition-colors cursor-pointer">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
              </svg>
              <span class="text-xs font-semibold">Click to upload image</span>
              <span class="text-[10px]">PNG, JPG, GIF up to 2MB</span>
            </button>
            <p v-if="errors.image" class="text-xs text-red-500 font-medium">{{ errors.image }}</p>
          </div>

          <!-- Tab Actions -->
          <div class="flex gap-2 pt-2">
            <button type="button" @click="activeFormTab = 'pricing'"
              class="flex-1 py-2 px-4 rounded-xl bg-violet-500 hover:bg-violet-600 text-white text-sm font-bold flex items-center justify-center gap-1 transition-all cursor-pointer shadow-md hover:shadow-lg">
              Next: Pricing →
            </button>
            <button v-if="isEditMode" type="button" @click="resetForm"
              class="py-2 px-4 rounded-xl border border-gray-200 hover:bg-gray-50 text-gray-500 text-sm font-bold transition-all cursor-pointer">
              Cancel
            </button>
          </div>
        </form>

        <!-- ═══════════ TAB 2: Pricing & Safeguards ═══════════════════ -->
        <div v-show="activeFormTab === 'pricing'" class="space-y-4">

          <!-- Cost Price + Retail Price -->
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Cost Price (HPP) *</label>
              <div class="relative">
                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-xs font-bold">Rp</span>
                <input v-model="form.cost_price" type="number" min="0" step="1" placeholder="0"
                  class="w-full pl-8 pr-3 py-2 border rounded-xl text-sm focus:outline-none focus:border-violet-500 transition-colors"
                  :class="errors.cost_price ? 'border-red-400' : 'border-gray-200'" />
              </div>
              <p v-if="errors.cost_price" class="text-xs text-red-500 font-medium mt-1">{{ errors.cost_price }}</p>
            </div>
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Selling Price *</label>
              <div class="relative">
                <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-xs font-bold">Rp</span>
                <input v-model="form.price" type="number" min="0" step="1" placeholder="0"
                  class="w-full pl-8 pr-3 py-2 border rounded-xl text-sm focus:outline-none focus:border-violet-500 transition-colors"
                  :class="errors.price ? 'border-red-400' : 'border-gray-200'" />
              </div>
              <p v-if="errors.price" class="text-xs text-red-500 font-medium mt-1">{{ errors.price }}</p>
            </div>
          </div>

          <!-- Wholesale Price -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Wholesale Price</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-xs font-bold">Rp</span>
              <input v-model="form.wholesale_price" type="number" min="0" step="1" placeholder="Optional"
                class="w-full pl-8 pr-3 py-2 border rounded-xl text-sm focus:outline-none focus:border-violet-500 transition-colors"
                :class="errors.wholesale_price ? 'border-red-400' : 'border-gray-200'" />
            </div>
            <p v-if="errors.wholesale_price" class="text-xs text-red-500 font-medium mt-1">{{ errors.wholesale_price }}</p>
          </div>

          <!-- Min Margin Percentage -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Min Margin Safeguard (%)</label>
            <div class="relative">
              <input v-model="form.min_margin_percentage" type="number" min="0" max="100" step="0.01" placeholder="10"
                class="w-full pl-4 pr-8 py-2 border rounded-xl text-sm focus:outline-none focus:border-violet-500 transition-colors"
                :class="errors.min_margin_percentage ? 'border-red-400' : 'border-gray-200'" />
              <span class="absolute inset-y-0 right-3 flex items-center text-gray-400 text-xs font-bold">%</span>
            </div>
            <p v-if="errors.min_margin_percentage" class="text-xs text-red-500 font-medium mt-1">{{ errors.min_margin_percentage }}</p>
          </div>

          <!-- Live Margin Display -->
          <div v-if="marginStatus" class="p-3 rounded-xl border" :class="marginStatus.ok ? 'bg-emerald-50 border-emerald-100' : 'bg-rose-50 border-rose-100'">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold" :class="marginStatus.ok ? 'text-emerald-700' : 'text-rose-700'">
                {{ marginStatus.ok ? '✓ Margin OK' : '✗ Margin Below Safeguard' }}
              </span>
              <span class="text-sm font-extrabold" :class="marginStatus.ok ? 'text-emerald-600' : 'text-rose-600'">
                {{ marginStatus.margin }}%
              </span>
            </div>
            <p class="text-[10px] mt-0.5" :class="marginStatus.ok ? 'text-emerald-500' : 'text-rose-400'">
              Min required: {{ form.min_margin_percentage || 0 }}% &nbsp;|&nbsp; Calculated: (Price − Cost) / Price × 100
            </p>
          </div>

          <!-- Tab Actions -->
          <div class="flex gap-2 pt-2">
            <button type="button" @click="activeFormTab = 'profile'"
              class="py-2 px-4 rounded-xl border border-gray-200 hover:bg-gray-50 text-gray-500 text-sm font-bold transition-all cursor-pointer">
              ← Profile
            </button>
            <button type="button" @click="activeFormTab = 'stock'"
              class="flex-1 py-2 px-4 rounded-xl bg-violet-500 hover:bg-violet-600 text-white text-sm font-bold flex items-center justify-center gap-1 transition-all cursor-pointer shadow-md hover:shadow-lg">
              Next: Stock & Settings →
            </button>
          </div>
        </div>

        <!-- ═══════════ TAB 3: Stock & Settings ══════════════════════ -->
        <div v-show="activeFormTab === 'stock'" class="space-y-4">

          <!-- Safety Stock + Reorder Point -->
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Safety Stock</label>
              <input v-model.number="form.safety_stock" type="number" min="0" step="1" placeholder="0"
                class="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-violet-500 transition-colors" />
              <p v-if="errors.safety_stock" class="text-xs text-red-500 font-medium mt-1">{{ errors.safety_stock }}</p>
            </div>
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Reorder Point</label>
              <input v-model.number="form.reorder_point" type="number" min="0" step="1" placeholder="0"
                class="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-violet-500 transition-colors" />
              <p v-if="errors.reorder_point" class="text-xs text-red-500 font-medium mt-1">{{ errors.reorder_point }}</p>
            </div>
          </div>

          <!-- Lead Time -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Lead Time (Days)</label>
            <input v-model.number="form.lead_time" type="number" min="0" step="1" placeholder="0"
              class="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-violet-500 transition-colors" />
            <p v-if="errors.lead_time" class="text-xs text-red-500 font-medium mt-1">{{ errors.lead_time }}</p>
          </div>

          <!-- Purchase Type -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Purchase Type</label>
            <select v-model="form.purchase_type"
              class="w-full px-4 py-2 border border-gray-200 bg-white rounded-xl text-sm focus:outline-none focus:border-violet-500 transition-colors cursor-pointer">
              <option value="outright">Outright (Beli Putus)</option>
              <option value="consignment">Consignment (Titip Jual)</option>
            </select>
          </div>

          <!-- Consignment Commission (only if consignment) -->
          <div v-if="form.purchase_type === 'consignment'" class="space-y-1 p-3 bg-amber-50 border border-amber-100 rounded-xl">
            <label class="block text-xs font-bold text-amber-700 uppercase tracking-wider">Consignment Commission Fee (%)</label>
            <div class="relative">
              <input v-model.number="form.consignment_commission_fee" type="number" min="0" max="100" step="0.01" placeholder="20"
                class="w-full pl-4 pr-8 py-2 border rounded-xl text-sm focus:outline-none focus:border-amber-500 transition-colors"
                :class="errors.consignment_commission_fee ? 'border-red-400' : 'border-amber-200'" />
              <span class="absolute inset-y-0 right-3 flex items-center text-amber-500 text-xs font-bold">%</span>
            </div>
            <p class="text-[10px] text-amber-600">Store's share of consignment revenue (e.g. 20% means store keeps 20%, 80% goes to supplier).</p>
            <p v-if="errors.consignment_commission_fee" class="text-xs text-red-500 font-medium mt-1">{{ errors.consignment_commission_fee }}</p>
          </div>

          <!-- Boolean Toggles -->
          <div class="space-y-2 pt-1">
            <!-- Is Taxable -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100">
              <div>
                <span class="text-xs font-bold text-gray-700">Taxable (PPN)</span>
                <p class="text-[10px] text-gray-400">Subject to tax on sales transactions.</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input v-model="form.is_taxable" type="checkbox" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-violet-500"></div>
              </label>
            </div>

            <!-- Is Consignment -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100">
              <div>
                <span class="text-xs font-bold text-gray-700">Consignment Product</span>
                <p class="text-[10px] text-gray-400">Mark if this product is sold on consignment basis.</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input v-model="form.is_consignment" type="checkbox" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
              </label>
            </div>

            <!-- Is Active -->
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100">
              <div>
                <span class="text-xs font-bold text-gray-700">Active Status</span>
                <p class="text-[10px] text-gray-400">Available for sale and purchase orders.</p>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input v-model="form.is_active" type="checkbox" class="sr-only peer" />
                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
              </label>
            </div>
          </div>

          <!-- Final Action Buttons -->
          <div class="flex gap-2 pt-2">
            <button type="button" @click="activeFormTab = 'pricing'"
              class="py-2 px-4 rounded-xl border border-gray-200 hover:bg-gray-50 text-gray-500 text-sm font-bold transition-all cursor-pointer">
              ← Pricing
            </button>
            <button type="button" @click="handleSubmit" :disabled="submitting"
              class="flex-1 py-2 px-4 rounded-xl bg-violet-500 hover:bg-violet-600 text-white text-sm font-bold flex items-center justify-center gap-2 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed shadow-md hover:shadow-lg active:scale-98">
              <svg v-if="submitting" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>{{ isEditMode ? 'Update Product' : 'Save Product' }}</span>
            </button>
            <button v-if="isEditMode" type="button" @click="resetForm"
              class="py-2 px-4 rounded-xl border border-gray-200 hover:bg-gray-50 text-gray-500 text-sm font-bold transition-all cursor-pointer">
              Cancel
            </button>
          </div>
        </div>
      </div>

      <!-- ── Right Column: Products Directory (7 cols) ───────────────── -->
      <div class="lg:col-span-7 bg-white border border-gray-100 p-6 rounded-2xl shadow-sm flex flex-col min-w-0">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-md font-bold text-gray-800">Products Directory</h3>
          <!-- Page Size Selector -->
          <div class="flex items-center gap-2">
            <span class="text-xs text-gray-400">Show</span>
            <select v-model.number="pageSize" @change="currentPage = 1"
              class="text-xs border border-gray-200 rounded-lg px-2 py-1 focus:outline-none focus:border-violet-400 bg-white cursor-pointer">
              <option :value="5">5</option>
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
            </select>
            <span class="text-xs text-gray-400">entries</span>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex-1 flex flex-col items-center justify-center py-24 space-y-3">
          <svg class="animate-spin h-8 w-8 text-violet-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span class="text-sm font-semibold text-gray-500">Loading products...</span>
        </div>

        <!-- Empty State -->
        <div v-else-if="filteredProducts.length === 0" class="flex-1 flex flex-col items-center justify-center py-24 text-center space-y-4">
          <span class="w-16 h-16 rounded-2xl bg-gray-50 flex items-center justify-center text-gray-300">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
              <path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
            </svg>
          </span>
          <div>
            <h4 class="text-sm font-bold text-gray-700">No Products Found</h4>
            <p class="text-xs text-gray-400 max-w-[280px] mx-auto mt-1">
              {{ searchQuery ? 'No products match your search.' : 'Start by adding your first product using the form on the left.' }}
            </p>
          </div>
        </div>

        <!-- Products Table -->
        <div v-else class="overflow-x-auto flex-1">
          <table class="w-full text-left border-collapse text-xs font-semibold text-gray-600">
            <thead>
              <tr class="border-b border-gray-100 text-gray-400 uppercase tracking-wider text-[10px]">
                <th class="pb-3 w-12">Image</th>
                <th class="pb-3">Product</th>
                <th class="pb-3">Category / Unit</th>
                <th class="pb-3 text-right">Price</th>
                <th class="pb-3 text-center w-24">Type</th>
                <th class="pb-3 text-center w-16">Status</th>
                <th class="pb-3 text-center w-20">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="product in paginatedProducts" :key="product.id" class="hover:bg-gray-50/50 transition-colors">
                <!-- Image Thumbnail -->
                <td class="py-3">
                  <div class="w-10 h-10 rounded-xl overflow-hidden border border-gray-100 bg-gray-50 flex items-center justify-center flex-shrink-0">
                    <img v-if="product.image_path" :src="getImageUrl(product.image_path)" :alt="product.name"
                      class="w-full h-full object-cover" />
                    <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-gray-300">
                      <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                    </svg>
                  </div>
                </td>
                <!-- Product Profile -->
                <td class="py-3 pr-4">
                  <span class="text-gray-800 font-bold block leading-tight truncate max-w-[160px]">{{ product.name }}</span>
                  <div class="flex items-center gap-1.5 mt-0.5">
                    <span class="text-[10px] text-gray-400 font-medium">{{ product.code }}</span>
                    <span v-if="product.sku" class="text-[10px] text-gray-300">|</span>
                    <span v-if="product.sku" class="text-[10px] text-gray-400">{{ product.sku }}</span>
                  </div>
                  <span v-if="product._brand" class="text-[10px] text-violet-500 font-semibold">{{ product._brand.name }}</span>
                </td>
                <!-- Category / Unit -->
                <td class="py-3 pr-3">
                  <span class="text-gray-700 font-semibold block text-[11px]">{{ product._category?.name || '-' }}</span>
                  <span class="text-[10px] text-gray-400">{{ product._unit?.name || '-' }}</span>
                </td>
                <!-- Price -->
                <td class="py-3 text-right">
                  <span class="text-gray-800 font-bold block text-[11px]">{{ formatCurrency(product.price) }}</span>
                  <span class="text-[10px] text-gray-400">HPP: {{ formatCurrency(product.cost_price) }}</span>
                </td>
                <!-- Purchase Type -->
                <td class="py-3 text-center">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold"
                    :class="product.purchase_type === 'consignment' ? 'bg-amber-50 text-amber-600' : 'bg-blue-50 text-blue-600'">
                    {{ product.purchase_type === 'consignment' ? 'Consign' : 'Outright' }}
                  </span>
                </td>
                <!-- Status -->
                <td class="py-3 text-center">
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold"
                    :class="product.is_active ? 'bg-emerald-50 text-emerald-600' : 'bg-gray-100 text-gray-400'">
                    <span class="w-1.5 h-1.5 rounded-full"
                      :class="product.is_active ? 'bg-emerald-400' : 'bg-gray-400'"></span>
                    {{ product.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <!-- Actions -->
                <td class="py-3 text-center">
                  <div class="flex items-center justify-center gap-1.5">
                    <button @click="handleEdit(product)" title="Edit"
                      class="w-7 h-7 rounded-lg bg-violet-50 hover:bg-violet-100 text-violet-600 flex items-center justify-center transition-colors cursor-pointer">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                      </svg>
                    </button>
                    <button @click="handleDelete(product.id)" title="Delete"
                      class="w-7 h-7 rounded-lg bg-red-50 hover:bg-red-100 text-red-500 flex items-center justify-center transition-colors cursor-pointer">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination Controls -->
        <div v-if="filteredProducts.length > 0" class="flex items-center justify-between pt-4 mt-4 border-t border-gray-50">
          <span class="text-xs text-gray-400">
            Showing <strong>{{ startRange }}–{{ endRange }}</strong> of <strong>{{ filteredProducts.length }}</strong> products
          </span>
          <div class="flex items-center gap-1">
            <button @click="goToPage(1)" :disabled="currentPage === 1"
              class="w-7 h-7 rounded-lg text-xs flex items-center justify-center transition-colors disabled:opacity-30 disabled:cursor-not-allowed hover:bg-violet-50 text-gray-500 cursor-pointer">
              «
            </button>
            <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1"
              class="w-7 h-7 rounded-lg text-xs flex items-center justify-center transition-colors disabled:opacity-30 disabled:cursor-not-allowed hover:bg-violet-50 text-gray-500 cursor-pointer">
              ‹
            </button>
            <template v-for="page in totalPages" :key="page">
              <button v-if="Math.abs(page - currentPage) <= 2 || page === 1 || page === totalPages"
                @click="goToPage(page)"
                class="w-7 h-7 rounded-lg text-xs font-bold flex items-center justify-center transition-colors cursor-pointer"
                :class="page === currentPage ? 'bg-violet-500 text-white shadow-sm' : 'hover:bg-violet-50 text-gray-500'">
                {{ page }}
              </button>
              <span v-else-if="Math.abs(page - currentPage) === 3" class="text-gray-300 text-xs px-0.5">…</span>
            </template>
            <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages"
              class="w-7 h-7 rounded-lg text-xs flex items-center justify-center transition-colors disabled:opacity-30 disabled:cursor-not-allowed hover:bg-violet-50 text-gray-500 cursor-pointer">
              ›
            </button>
            <button @click="goToPage(totalPages)" :disabled="currentPage === totalPages"
              class="w-7 h-7 rounded-lg text-xs flex items-center justify-center transition-colors disabled:opacity-30 disabled:cursor-not-allowed hover:bg-violet-50 text-gray-500 cursor-pointer">
              »
            </button>
          </div>
        </div>
      </div>
    </div>
    <!-- ── Import Excel Modal ─────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="showImportModal"
          class="fixed inset-0 z-50 flex items-center justify-center p-4"
          style="background: rgba(0,0,0,0.45); backdrop-filter: blur(4px)">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] flex flex-col overflow-hidden">

            <!-- Modal Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
              <div class="flex items-center gap-2">
                <span class="p-2 rounded-xl bg-violet-50 text-violet-600">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                  </svg>
                </span>
                <div>
                  <h3 class="text-sm font-extrabold text-gray-800">Import Products from Excel</h3>
                  <p class="text-[10px] text-gray-400">Supports .xlsx, .xls, .csv — max 10MB</p>
                </div>
              </div>
              <button @click="closeImportModal"
                class="w-7 h-7 rounded-lg hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Modal Body -->
            <div class="flex-1 overflow-y-auto p-6 space-y-4">

              <!-- Download template hint -->
              <div class="flex items-center justify-between p-3 bg-violet-50 rounded-xl border border-violet-100">
                <div>
                  <p class="text-xs font-bold text-violet-700">Need the column format?</p>
                  <p class="text-[10px] text-violet-500">Download our template with example rows already filled.</p>
                </div>
                <button @click="handleDownloadTemplate"
                  class="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-violet-100 hover:bg-violet-200 text-violet-700 text-xs font-bold transition-colors cursor-pointer whitespace-nowrap">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                  </svg>
                  Download
                </button>
              </div>

              <!-- File Drop Zone -->
              <input ref="importFileInput" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="onImportFileChange" />
              <div
                @click="triggerImportFileInput"
                @dragover.prevent="importDragOver = true"
                @dragleave.prevent="importDragOver = false"
                @drop.prevent="onImportDrop"
                class="border-2 border-dashed rounded-xl p-8 text-center cursor-pointer transition-colors select-none"
                :class="importDragOver
                  ? 'border-violet-400 bg-violet-50'
                  : importFile
                    ? 'border-emerald-300 bg-emerald-50'
                    : 'border-gray-200 hover:border-violet-300 hover:bg-violet-50/40'">

                <!-- File selected state -->
                <div v-if="importFile" class="space-y-1">
                  <div class="flex items-center justify-center">
                    <span class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-emerald-600">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                      </svg>
                    </span>
                  </div>
                  <p class="text-xs font-bold text-emerald-700 truncate max-w-xs mx-auto">{{ importFile.name }}</p>
                  <p class="text-[10px] text-emerald-500">{{ (importFile.size / 1024).toFixed(1) }} KB · Click to change file</p>
                </div>

                <!-- Empty state -->
                <div v-else class="space-y-2">
                  <div class="flex items-center justify-center">
                    <span class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center text-gray-400">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                      </svg>
                    </span>
                  </div>
                  <p class="text-xs font-bold text-gray-600">Drag & drop your file here</p>
                  <p class="text-[10px] text-gray-400">or <span class="text-violet-600 underline">click to browse</span> · .xlsx / .xls / .csv</p>
                </div>
              </div>

              <!-- Import Result -->
              <div v-if="importResult" class="space-y-3">
                <!-- Summary Row -->
                <div class="grid grid-cols-2 gap-3">
                  <div class="p-3 bg-emerald-50 border border-emerald-100 rounded-xl flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-600">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                      </svg>
                    </span>
                    <div>
                      <p class="text-[10px] text-emerald-500 font-bold uppercase">Imported</p>
                      <p class="text-lg font-extrabold text-emerald-700">{{ importResult.success_count }}</p>
                    </div>
                  </div>
                  <div class="p-3 bg-rose-50 border border-rose-100 rounded-xl flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-rose-100 flex items-center justify-center text-rose-600">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                      </svg>
                    </span>
                    <div>
                      <p class="text-[10px] text-rose-500 font-bold uppercase">Failed Rows</p>
                      <p class="text-lg font-extrabold text-rose-700">{{ importResult.failure_count }}</p>
                    </div>
                  </div>
                </div>

                <!-- Failure Detail Table -->
                <div v-if="importResult.failure_count > 0">
                  <button @click="showFailures = !showFailures"
                    class="flex items-center gap-1.5 text-xs font-bold text-rose-600 hover:text-rose-700 transition-colors cursor-pointer">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 transition-transform" :class="showFailures ? 'rotate-180' : ''">
                      <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                    {{ showFailures ? 'Hide' : 'Show' }} {{ importResult.failure_count }} failed row(s)
                  </button>

                  <div v-if="showFailures" class="mt-2 rounded-xl border border-rose-100 overflow-hidden">
                    <table class="w-full text-[10px]">
                      <thead>
                        <tr class="bg-rose-50 text-rose-500 font-bold uppercase text-[9px] tracking-wider">
                          <th class="px-3 py-2 text-left">Row</th>
                          <th class="px-3 py-2 text-left">Column</th>
                          <th class="px-3 py-2 text-left">Error</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-rose-50">
                        <tr v-for="(failure, idx) in importResult.failures" :key="idx" class="hover:bg-rose-50/60">
                          <td class="px-3 py-2 font-bold text-rose-700">#{{ failure.row }}</td>
                          <td class="px-3 py-2 text-gray-600 font-medium">{{ failure.column }}</td>
                          <td class="px-3 py-2 text-rose-600">{{ failure.errors }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex gap-2 px-6 py-4 border-t border-gray-100 bg-gray-50/50">
              <button @click="closeImportModal"
                class="flex-1 py-2 px-4 rounded-xl border border-gray-200 hover:bg-gray-100 text-gray-600 text-sm font-bold transition-colors cursor-pointer">
                {{ importResult ? 'Close' : 'Cancel' }}
              </button>
              <button @click="handleImport" :disabled="!importFile || importLoading"
                class="flex-1 py-2 px-4 rounded-xl bg-violet-500 hover:bg-violet-600 text-white text-sm font-bold flex items-center justify-center gap-2 transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed shadow-md">
                <svg v-if="importLoading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                </svg>
                {{ importLoading ? 'Importing...' : 'Start Import' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s ease;
}
.modal-fade-enter-active .bg-white,
.modal-fade-leave-active .bg-white {
  transition: transform 0.2s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
.modal-fade-enter-from .bg-white {
  transform: scale(0.95) translateY(-10px);
}
</style>
