<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()

// View state
const viewMode = ref('list') // 'list', 'form', 'detail'
const loadingList = ref(false)
const loadingMaster = ref(false)
const loadingDetail = ref(false)
const submitting = ref(false)
const deletingId = ref(null)

// Master data
const warehouses = ref([])
const products = ref([])
const productVariants = ref([])
const productStocks = ref([]) // key mapping for system quantities

// List data
const opnamesData = ref({
  data: [],
  current_page: 1,
  last_page: 1,
  total: 0,
  per_page: 10
})
const currentPage = ref(1)
const searchQuery = ref('')
const warehouseFilter = ref('')
const statusFilter = ref('')

// Form state
const isEditMode = ref(false)
const editingId = ref(null)

const form = ref({
  warehouse_id: '',
  opname_date: new Date().toISOString().split('T')[0],
  notes: '',
  items: []
})

const errors = ref({
  warehouse_id: '',
  opname_date: '',
  notes: '',
  items: ''
})

// Item selector state
const productSearchInput = ref('')
const selectedProduct = ref(null)
const selectedVariantId = ref('')
const itemPhysicalQty = ref(1)
const itemNotes = ref('')
const productSearchDropdownOpen = ref(false)

// Detail view state
const activeOpname = ref(null)

// Pagination lists
const months = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
]

// Computed product variant options for selected product
const activeVariants = computed(() => {
  if (!selectedProduct.value) return []
  return productVariants.value.filter(v => v.product_id === selectedProduct.value.id)
})

// Autocomplete product results based on search input
const productSearchResults = computed(() => {
  const query = productSearchInput.value.trim().toLowerCase()
  if (!query) return []
  
  return products.value.filter(p => {
    return (
      (p.name && p.name.toLowerCase().includes(query)) ||
      (p.code && p.code.toLowerCase().includes(query)) ||
      (p.barcode && p.barcode.toLowerCase().includes(query))
    )
  }).slice(0, 10)
})

// Calculate current system quantity of a product + variant inside the selected warehouse
const getSystemQty = (productId, variantId = null) => {
  if (!form.value.warehouse_id) return 0
  
  const stock = productStocks.value.find(s => 
    s.warehouse_id === Number(form.value.warehouse_id) &&
    s.product_id === Number(productId) &&
    (variantId ? s.product_variant_id === Number(variantId) : !s.product_variant_id)
  )
  
  return stock ? Number(stock.qty) : 0
}

// Format numbers as Rupiah Currency
const formatCurrency = (value) => {
  const parsed = Number(value)
  if (Number.isNaN(parsed)) return 'Rp 0'
  
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(parsed)
}

// Format date time strings
const formatDate = (value) => {
  if (!value) return '-'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '-'
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const formatDateTime = (value) => {
  if (!value) return '-'
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return '-'
  return d.toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Reset form validations
const resetErrors = () => {
  errors.value = {
    warehouse_id: '',
    opname_date: '',
    notes: '',
    items: ''
  }
}

// Apply validation errors from server
const applyServerErrors = (serverErrors) => {
  Object.keys(serverErrors || {}).forEach(key => {
    if (errors.value[key] !== undefined) {
      errors.value[key] = Array.isArray(serverErrors[key]) ? serverErrors[key][0] : serverErrors[key]
    }
  })
}

// Load master data
const fetchMasterData = async () => {
  loadingMaster.value = true
  try {
    const [warehousesRes, productsRes, variantsRes, stocksRes] = await Promise.all([
      api.get('/warehouses'),
      api.get('/products'),
      api.get('/product-variants'),
      api.get('/product-stocks')
    ])
    
    warehouses.value = warehousesRes.data?.data || warehousesRes.data || []
    products.value = productsRes.data?.data || productsRes.data || []
    productVariants.value = variantsRes.data?.data || variantsRes.data || []
    productStocks.value = stocksRes.data?.data || stocksRes.data || []
  } catch (error) {
    console.error('Error fetching master data:', error)
    toast.error('Gagal memuat data master.')
  } finally {
    loadingMaster.value = false
  }
}

// Load stock opnames list
const fetchOpnames = async (page = 1) => {
  loadingList.value = true
  currentPage.value = page
  try {
    const response = await api.get('/stock-opnames', {
      params: {
        page: page,
        warehouse_id: warehouseFilter.value || undefined,
        status: statusFilter.value || undefined,
        search: searchQuery.value || undefined
      }
    })
    
    const resData = response.data?.data || response.data
    if (resData && Array.isArray(resData.data)) {
      opnamesData.value = resData
    } else if (Array.isArray(resData)) {
      opnamesData.value = {
        data: resData,
        current_page: 1,
        last_page: 1,
        total: resData.length,
        per_page: 10
      }
    }
  } catch (error) {
    console.error('Error fetching stock opnames:', error)
    toast.error('Gagal memuat daftar Stock Opname.')
  } finally {
    loadingList.value = false
  }
}

// Load detail opname
const fetchDetail = async (id) => {
  loadingDetail.value = true
  try {
    const response = await api.get(`/stock-opnames/${id}`)
    activeOpname.value = response.data?.data || response.data
    viewMode.value = 'detail'
  } catch (error) {
    console.error('Error fetching details:', error)
    toast.error('Gagal memuat detail Stock Opname.')
  } finally {
    loadingDetail.value = false
  }
}

// Client-side form validation
const validateForm = () => {
  resetErrors()
  let valid = true
  
  if (!form.value.warehouse_id) {
    errors.value.warehouse_id = 'Warehouse harus dipilih.'
    valid = false
  }
  
  if (!form.value.opname_date) {
    errors.value.opname_date = 'Tanggal opname harus diisi.'
    valid = false
  }
  
  if (form.value.items.length === 0) {
    errors.value.items = 'Minimal harus menginput 1 produk untuk stock opname.'
    valid = false
  }
  
  if (form.value.notes && form.value.notes.length > 1000) {
    errors.value.notes = 'Catatan tidak boleh melebihi 1000 karakter.'
    valid = false
  }
  
  return valid
}

// Handle adding a product item to the counted list
const selectProductSearchResult = (product) => {
  selectedProduct.value = product
  productSearchInput.value = `${product.code} — ${product.name}`
  productSearchDropdownOpen.value = false
  
  // Auto select default variant if any
  selectedVariantId.value = ''
  const variants = productVariants.value.filter(v => v.product_id === product.id)
  if (variants.length > 0) {
    selectedVariantId.value = variants[0].id
  }
}

const addProductItem = () => {
  if (!selectedProduct.value) {
    toast.warning('Pilih produk terlebih dahulu.')
    return
  }
  
  if (!form.value.warehouse_id) {
    toast.warning('Pilih warehouse sebelum menambahkan produk.')
    errors.value.warehouse_id = 'Pilih warehouse terlebih dahulu.'
    return
  }
  
  const productId = selectedProduct.value.id
  const variantId = selectedVariantId.value ? Number(selectedVariantId.value) : null
  const variant = variantId ? productVariants.value.find(v => v.id === variantId) : null
  
  // Check if item already exists in the list
  const existingIndex = form.value.items.findIndex(item => 
    item.product_id === productId && 
    item.product_variant_id === variantId
  )
  
  if (existingIndex !== -1) {
    toast.warning('Produk/varian ini sudah ada di daftar opname.')
    return
  }
  
  const systemQty = getSystemQty(productId, variantId)
  const physicalQty = Number(itemPhysicalQty.value) || 0
  const discrepancy = physicalQty - systemQty
  const unitCost = Number(selectedProduct.value.cost_price) || 0
  const discrepancyValue = discrepancy * unitCost
  
  form.value.items.push({
    product_id: productId,
    product: selectedProduct.value,
    product_variant_id: variantId,
    variant_name: variant ? variant.name : '',
    system_qty: systemQty,
    physical_qty: physicalQty,
    discrepancy: discrepancy,
    unit_cost: unitCost,
    discrepancy_value: discrepancyValue,
    notes: itemNotes.value || ''
  })
  
  // Reset item selector fields
  selectedProduct.value = null
  selectedVariantId.value = ''
  productSearchInput.value = ''
  itemPhysicalQty.value = 1
  itemNotes.value = ''
}

// Remove item from count list
const removeItem = (index) => {
  form.value.items.splice(index, 1)
}

// Update calculated fields on physical quantity changes
const updateItemCalculations = (item) => {
  item.physical_qty = Math.max(0, Number(item.physical_qty) || 0)
  item.discrepancy = item.physical_qty - item.system_qty
  item.discrepancy_value = item.discrepancy * item.unit_cost
}

const incrementQty = (item) => {
  item.physical_qty = Number(item.physical_qty) + 1
  updateItemCalculations(item)
}

const decrementQty = (item) => {
  if (item.physical_qty > 0) {
    item.physical_qty = Number(item.physical_qty) - 1
    updateItemCalculations(item)
  }
}

// Form totals
const totalItemsCounted = computed(() => {
  return form.value.items.length
})

const totalDiscrepancyValuation = computed(() => {
  return form.value.items.reduce((sum, item) => sum + item.discrepancy_value, 0)
})

// Initialize create form
const initCreate = () => {
  isEditMode.value = false
  editingId.value = null
  form.value = {
    warehouse_id: warehouses.value.length > 0 ? warehouses.value[0].id : '',
    opname_date: new Date().toISOString().split('T')[0],
    notes: '',
    items: []
  }
  resetErrors()
  viewMode.value = 'form'
}

// Initialize edit form from draft
const initEdit = (opname) => {
  isEditMode.value = true
  editingId.value = opname.id
  resetErrors()
  
  // Map API items schema to form items
  const mappedItems = opname.items.map(item => {
    return {
      product_id: item.product_id,
      product: item.product,
      product_variant_id: item.product_variant_id,
      variant_name: item.product_variant ? item.product_variant.name : '',
      system_qty: Number(item.system_qty),
      physical_qty: Number(item.physical_qty),
      discrepancy: Number(item.discrepancy),
      unit_cost: Number(item.unit_cost),
      discrepancy_value: Number(item.discrepancy_value),
      notes: item.notes || ''
    }
  })
  
  form.value = {
    warehouse_id: opname.warehouse_id,
    opname_date: opname.opname_date ? opname.opname_date.split('T')[0] : new Date().toISOString().split('T')[0],
    notes: opname.notes || '',
    items: mappedItems
  }
  
  viewMode.value = 'form'
}

// Submit stock opname
const submitForm = async (targetStatus = 'draft') => {
  if (!validateForm()) {
    toast.warning('Harap lengkapi semua field yang valid.')
    return
  }
  
  if (targetStatus === 'approved') {
    if (!confirm('Apakah Anda yakin ingin menyetujui Stock Opname ini? Persediaan fisik di warehouse akan diperbarui dan penyesuaian nilai buku akuntansi akan langsung diposting ke Buku Besar secara otomatis.')) {
      return
    }
  }
  
  submitting.value = true
  try {
    const payload = {
      warehouse_id: Number(form.value.warehouse_id),
      opname_date: form.value.opname_date,
      notes: form.value.notes,
      status: targetStatus,
      items: form.value.items.map(item => ({
        product_id: item.product_id,
        product_variant_id: item.product_variant_id || null,
        physical_qty: Number(item.physical_qty),
        notes: item.notes || null
      }))
    }
    
    if (isEditMode.value) {
      await api.put(`/stock-opnames/${editingId.value}`, payload)
      toast.success(targetStatus === 'approved' ? 'Stock opname berhasil disetujui & dibukukan!' : 'Draft stock opname berhasil diperbarui.')
    } else {
      await api.post('/stock-opnames', payload)
      toast.success(targetStatus === 'approved' ? 'Stock opname berhasil disetujui & dibukukan!' : 'Stock opname berhasil disimpan sebagai draft.')
    }
    
    viewMode.value = 'list'
    await fetchOpnames(1)
  } catch (error) {
    console.error('Error submitting stock opname:', error)
    if (error.response?.status === 422) {
      applyServerErrors(error.response.data?.errors || {})
      toast.error(error.response.data?.message || 'Validasi gagal.')
    } else {
      toast.error(error.response?.data?.message || 'Gagal memproses stock opname.')
    }
  } finally {
    submitting.value = false
  }
}

// Quick action to approve an existing draft
const approveDraft = async (opname) => {
  if (!confirm(`Apakah Anda yakin ingin menyetujui Stock Opname #${opname.reference_no}? Tindakan ini akan mengunci stok fisik dan memposting penyesuaian GL.`)) {
    return
  }
  
  submitting.value = true
  try {
    await api.put(`/stock-opnames/${opname.id}`, {
      status: 'approved'
    })
    toast.success('Stock opname berhasil disetujui!')
    await fetchOpnames(currentPage.value)
  } catch (error) {
    console.error('Error approving draft:', error)
    toast.error(error.response?.data?.message || 'Gagal menyetujui stock opname.')
  } finally {
    submitting.value = false
  }
}

// Void or cancel draft
const cancelDraft = async (opname) => {
  if (!confirm(`Apakah Anda yakin ingin membatalkan Stock Opname #${opname.reference_no}?`)) {
    return
  }
  
  submitting.value = true
  try {
    await api.put(`/stock-opnames/${opname.id}`, {
      status: 'cancelled'
    })
    toast.success('Stock opname berhasil dibatalkan.')
    await fetchOpnames(currentPage.value)
  } catch (error) {
    console.error('Error cancelling draft:', error)
    toast.error(error.response?.data?.message || 'Gagal membatalkan stock opname.')
  } finally {
    submitting.value = false
  }
}

// Delete draft
const deleteDraft = async (opname) => {
  if (!confirm(`Apakah Anda yakin ingin menghapus draft Stock Opname #${opname.reference_no}?`)) {
    return
  }
  
  deletingId.value = opname.id
  try {
    await api.delete(`/stock-opnames/${opname.id}`)
    toast.success('Draft Stock Opname berhasil dihapus.')
    await fetchOpnames(1)
  } catch (error) {
    console.error('Error deleting draft:', error)
    toast.error(error.response?.data?.message || 'Gagal menghapus draft.')
  } finally {
    deletingId.value = null
  }
}

// Watch filters to trigger search
watch(
  () => [warehouseFilter.value, statusFilter.value, searchQuery.value],
  () => {
    fetchOpnames(1)
  }
)

// Watch warehouse selection in form to recalculate/refresh system stock values
watch(
  () => form.value.warehouse_id,
  () => {
    if (form.value.warehouse_id && form.value.items.length > 0) {
      form.value.items.forEach(item => {
        item.system_qty = getSystemQty(item.product_id, item.product_variant_id)
        updateItemCalculations(item)
      })
    }
  }
)

onMounted(async () => {
  await fetchMasterData()
  await fetchOpnames(1)
})
</script>

<template>
  <div class="space-y-6">
    
    <!-- HEADER BAR -->
    <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div class="flex items-center gap-3">
        <span class="w-12 h-12 rounded-xl bg-teal-800 text-white flex items-center justify-center shadow-md">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-6 h-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0.75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />
          </svg>
        </span>
        <div>
          <h1 class="text-xl font-extrabold text-slate-850">Stock Opname</h1>
          <p class="text-xs text-slate-500 font-medium">Lakukan pencocokan stok fisik produk di gudang secara real-time.</p>
        </div>
      </div>
      
      <div class="flex items-center gap-2">
        <button
          v-if="viewMode === 'list'"
          @click="initCreate"
          class="w-full md:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-teal-800 hover:bg-teal-900 text-white font-bold px-5 py-3 transition text-sm shadow-sm cursor-pointer"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          Mulai Stock Opname
        </button>
        
        <button
          v-else
          @click="viewMode = 'list'"
          class="w-full md:w-auto inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-bold px-4 py-3 transition text-sm shadow-sm cursor-pointer"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
          </svg>
          Kembali Ke Daftar
        </button>
      </div>
    </div>

    <!-- VIEW: OPNAME LIST -->
    <div v-if="viewMode === 'list'" class="space-y-4">
      
      <!-- Filters and Search Bar -->
      <div class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="grid grid-cols-2 md:flex items-center gap-2 w-full md:w-auto">
          <!-- Warehouse Filter -->
          <select
            v-model="warehouseFilter"
            class="bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500"
          >
            <option value="">Semua Gudang</option>
            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
          </select>
          
          <!-- Status Filter -->
          <select
            v-model="statusFilter"
            class="bg-white border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-700 outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500"
          >
            <option value="">Semua Status</option>
            <option value="draft">Draft</option>
            <option value="approved">Approved</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        
        <!-- Search bar -->
        <div class="w-full md:max-w-xs relative">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari Ref No, Catatan..."
            class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-4 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500"
          />
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4 text-slate-400 absolute left-3 top-3.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.602 10.602Z" />
          </svg>
        </div>
      </div>

      <!-- Mobile-First Stacked List (Optimized Cards) -->
      <div v-if="loadingList" class="bg-white border border-slate-100 rounded-2xl py-20 text-center text-slate-400 shadow-sm">
        <span class="inline-block w-8 h-8 border-4 border-teal-800 border-t-transparent rounded-full animate-spin mb-2"></span>
        <p class="text-sm font-medium">Memuat data Stock Opname...</p>
      </div>
      
      <div v-else-if="opnamesData.data.length === 0" class="bg-white border border-slate-100 rounded-2xl py-20 text-center text-slate-400 shadow-sm border-dashed">
        <p class="text-sm font-medium">Tidak ada histori Stock Opname yang ditemukan.</p>
      </div>
      
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        
        <!-- Responsive Card List -->
        <div
          v-for="opname in opnamesData.data"
          :key="opname.id"
          class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm hover:shadow transition flex flex-col justify-between gap-4"
        >
          <!-- Top Card info -->
          <div>
            <div class="flex items-center justify-between gap-2">
              <span class="text-sm font-black text-slate-800">{{ opname.reference_no }}</span>
              <span
                class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border"
                :class="{
                  'bg-amber-50 text-amber-700 border-amber-200': opname.status === 'draft',
                  'bg-emerald-50 text-emerald-700 border-emerald-200': opname.status === 'approved',
                  'bg-slate-50 text-slate-500 border-slate-200': opname.status === 'cancelled'
                }"
              >
                {{ opname.status }}
              </span>
            </div>
            
            <div class="mt-3 space-y-1 text-xs font-semibold text-slate-500">
              <div class="flex items-center gap-1.5">
                <span class="font-bold text-slate-400">Gudang:</span>
                <span class="text-slate-700">{{ opname.warehouse?.name || '-' }}</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="font-bold text-slate-400">Tanggal:</span>
                <span class="text-slate-700">{{ formatDate(opname.opname_date) }}</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="font-bold text-slate-400">Total Item:</span>
                <span class="text-slate-700 font-extrabold">{{ opname.items_count ?? 0 }} Produk</span>
              </div>
            </div>
            
            <p v-if="opname.notes" class="mt-3 text-xs text-slate-400 line-clamp-2 bg-slate-50/50 p-2 rounded-lg italic">
              "{{ opname.notes }}"
            </p>
          </div>
          
          <!-- Bottom Action Buttons inside Card -->
          <div class="border-t border-slate-50 pt-3 flex flex-wrap gap-2 justify-end">
            <!-- View details -->
            <button
              @click="fetchDetail(opname.id)"
              class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition cursor-pointer"
            >
              Lihat Detail
            </button>
            
            <!-- Draft action buttons -->
            <template v-if="opname.status === 'draft'">
              <button
                @click="initEdit(opname)"
                class="px-3 py-2 bg-teal-50 hover:bg-teal-100 text-teal-800 text-xs font-bold rounded-xl transition cursor-pointer"
              >
                Edit Draft
              </button>
              
              <button
                @click="approveDraft(opname)"
                :disabled="submitting"
                class="px-3 py-2 bg-teal-800 hover:bg-teal-900 text-white text-xs font-bold rounded-xl transition cursor-pointer disabled:opacity-50"
              >
                Approve
              </button>
              
              <button
                @click="deleteDraft(opname)"
                :disabled="deletingId === opname.id"
                class="px-2 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-xl transition cursor-pointer disabled:opacity-50"
                title="Hapus Draft"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
              </button>
            </template>
          </div>
        </div>
        
      </div>

      <!-- Pagination bar -->
      <div v-if="opnamesData.last_page > 1" class="flex items-center justify-between border-t border-slate-100 pt-4 text-xs font-semibold text-slate-500">
        <p>Menampilkan halaman {{ opnamesData.current_page }} dari {{ opnamesData.last_page }} (Total {{ opnamesData.total }} data)</p>
        <div class="flex items-center gap-1">
          <button
            @click="fetchOpnames(opnamesData.current_page - 1)"
            :disabled="opnamesData.current_page === 1"
            class="px-2.5 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50 disabled:hover:bg-transparent"
          >
            Sebelumnya
          </button>
          <button
            @click="fetchOpnames(opnamesData.current_page + 1)"
            :disabled="opnamesData.current_page === opnamesData.last_page"
            class="px-2.5 py-1.5 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-50 disabled:hover:bg-transparent"
          >
            Selanjutnya
          </button>
        </div>
      </div>
      
    </div>

    <!-- VIEW: OPNAME CREATE/EDIT FORM (MOBILE-FIRST) -->
    <div v-else-if="viewMode === 'form'" class="grid grid-cols-1 xl:grid-cols-12 gap-6">
      
      <!-- Left side: Form Settings & Item counting -->
      <div class="xl:col-span-8 space-y-6">
        
        <!-- Header status settings card -->
        <section class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm space-y-4">
          <h2 class="text-base font-extrabold text-slate-800">
            {{ isEditMode ? 'Edit Draft Stock Opname' : 'Mulai Hitung Stock Opname' }}
          </h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Warehouse Select -->
            <div class="space-y-1">
              <label for="form_warehouse_id" class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Warehouse *</label>
              <select
                id="form_warehouse_id"
                v-model="form.warehouse_id"
                :disabled="isEditMode || form.items.length > 0"
                class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm text-slate-850 outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 disabled:opacity-60"
              >
                <option value="" disabled>Pilih warehouse</option>
                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
              </select>
              <p v-if="errors.warehouse_id" class="text-xs text-rose-600 font-bold mt-0.5">{{ errors.warehouse_id }}</p>
              <p v-if="form.items.length > 0" class="text-[10px] text-amber-600 font-bold">Kunci warehouse aktif untuk menghindari ketidaksesuaian stok sistem.</p>
            </div>
            
            <!-- Date input -->
            <div class="space-y-1">
              <label for="form_opname_date" class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Tanggal Opname *</label>
              <input
                id="form_opname_date"
                v-model="form.opname_date"
                type="date"
                class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm text-slate-850 outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500"
              />
              <p v-if="errors.opname_date" class="text-xs text-rose-600 font-bold mt-0.5">{{ errors.opname_date }}</p>
            </div>
          </div>

          <!-- Notes Area -->
          <div class="space-y-1">
            <label for="form_notes" class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Catatan</label>
            <textarea
              id="form_notes"
              v-model="form.notes"
              rows="2"
              placeholder="Catatan penyesuaian stock opname..."
              class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2 text-sm text-slate-850 outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 resize-none"
            ></textarea>
            <p v-if="errors.notes" class="text-xs text-rose-600 font-bold mt-0.5">{{ errors.notes }}</p>
          </div>
        </section>

        <!-- Product selection container -->
        <section class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm space-y-4">
          <h3 class="text-sm font-extrabold text-slate-850">Cari & Tambah Produk</h3>
          
          <div class="space-y-3">
            <!-- Product Autocomplete Search -->
            <div class="relative">
              <label class="block text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">Cari Produk (Nama / Kode / Barcode)</label>
              <input
                v-model="productSearchInput"
                type="text"
                placeholder="Ketik nama produk, SKU, barcode..."
                @focus="productSearchDropdownOpen = true"
                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm text-slate-850 outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500"
              />
              
              <!-- Dropdown results -->
              <div
                v-if="productSearchDropdownOpen && productSearchResults.length > 0"
                class="absolute left-0 right-0 mt-1 bg-white border border-slate-100 rounded-xl shadow-lg z-55 max-h-60 overflow-y-auto divide-y divide-slate-100"
              >
                <div
                  v-for="p in productSearchResults"
                  :key="p.id"
                  @click="selectProductSearchResult(p)"
                  class="px-4 py-2.5 hover:bg-slate-50 cursor-pointer text-sm flex flex-col"
                >
                  <span class="font-extrabold text-slate-800">{{ p.name }}</span>
                  <span class="text-[10px] text-slate-400 font-black">SKU: {{ p.code }} | Barcode: {{ p.barcode || '-' }} | HPP: {{ formatCurrency(p.cost_price) }}</span>
                </div>
              </div>
              
              <!-- Close search overlay click handler -->
              <div
                v-if="productSearchDropdownOpen"
                class="fixed inset-0 z-40"
                @click="productSearchDropdownOpen = false"
              ></div>
            </div>
            
            <!-- Varian Selector (Visible if selected product has variants) -->
            <div v-if="selectedProduct && activeVariants.length > 0" class="space-y-1">
              <label for="form_variant" class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Varian Produk</label>
              <select
                id="form_variant"
                v-model="selectedVariantId"
                class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm text-slate-850 outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500"
              >
                <option v-for="v in activeVariants" :key="v.id" :value="v.id">{{ v.name }}</option>
              </select>
            </div>
            
            <!-- Quick qty and note to add -->
            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
              <!-- Physical qty input -->
              <div class="sm:col-span-3 space-y-1">
                <label for="item_physical" class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Jumlah Fisik Aktual</label>
                <input
                  id="item_physical"
                  v-model="itemPhysicalQty"
                  type="number"
                  min="0"
                  class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm text-slate-850 outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500"
                />
              </div>
              
              <!-- Item Notes -->
              <div class="sm:col-span-6 space-y-1">
                <label for="item_note" class="block text-[10px] font-black text-slate-400 uppercase tracking-wider">Catatan Item (Opsional)</label>
                <input
                  id="item_note"
                  v-model="itemNotes"
                  type="text"
                  placeholder="Contoh: Selisih pecah, rusak..."
                  class="w-full bg-white border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm text-slate-850 outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500"
                />
              </div>
              
              <!-- Add button -->
              <div class="sm:col-span-3">
                <button
                  type="button"
                  @click="addProductItem"
                  class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold py-3 transition text-sm cursor-pointer"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                  </svg>
                  Tambah Ke List
                </button>
              </div>
            </div>
          </div>
        </section>

        <!-- Counted Product List Cards (Mobile-first vertical stack) -->
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-extrabold text-slate-800">Daftar Hitung Fisik</h3>
            <span class="text-xs text-slate-400 font-bold">{{ form.items.length }} Item Ditambahkan</span>
          </div>
          
          <p v-if="errors.items" class="text-xs text-rose-600 font-black">{{ errors.items }}</p>
          
          <div v-if="form.items.length === 0" class="bg-white border border-slate-100 rounded-2xl py-14 text-center text-slate-400 shadow-sm border-dashed">
            Belum ada produk yang dimasukkan untuk diopname.
          </div>
          
          <!-- Stacked cards for mobile counts -->
          <div v-else class="space-y-3">
            <div
              v-for="(item, index) in form.items"
              :key="item.product_id + '_' + item.product_variant_id"
              class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm flex flex-col gap-3 relative"
            >
              <!-- Info Row -->
              <div class="flex justify-between items-start gap-4">
                <div>
                  <h4 class="font-extrabold text-sm text-slate-800 leading-tight">{{ item.product.name }}</h4>
                  <p class="text-[10px] text-slate-400 font-black mt-0.5">
                    SKU: {{ item.product.code }} 
                    <span v-if="item.variant_name" class="ml-1 text-teal-700 bg-teal-50 border border-teal-100/50 px-1.5 py-0.5 rounded-md font-bold">
                      Varian: {{ item.variant_name }}
                    </span>
                  </p>
                </div>
                
                <!-- Remove item button -->
                <button
                  type="button"
                  @click="removeItem(index)"
                  class="text-rose-500 hover:text-rose-600 p-1 rounded-lg hover:bg-rose-50/50 transition cursor-pointer"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                  </svg>
                </button>
              </div>
              
              <!-- Cost and stock info -->
              <div class="grid grid-cols-2 gap-3 text-xs bg-slate-50 p-2.5 rounded-xl font-bold text-slate-600">
                <div>
                  <span class="text-slate-400">Harga Pokok (HPP):</span>
                  <p class="text-slate-800">{{ formatCurrency(item.unit_cost) }}</p>
                </div>
                <div>
                  <span class="text-slate-400">Stok Sistem:</span>
                  <p class="text-slate-800 font-black">{{ item.system_qty }} pcs</p>
                </div>
              </div>
              
              <!-- Physical Entry Controls (Touch friendly buttons) -->
              <div class="flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
                <div class="flex items-center gap-2">
                  <span class="text-[11px] font-black text-slate-400 uppercase tracking-wider">Fisik Aktual:</span>
                  
                  <div class="flex items-center border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                    <button
                      type="button"
                      @click="decrementQty(item)"
                      class="px-3 py-2 bg-slate-50 hover:bg-slate-100 text-slate-700 font-extrabold cursor-pointer border-r border-slate-200 transition"
                    >
                      －
                    </button>
                    
                    <input
                      v-model="item.physical_qty"
                      type="number"
                      min="0"
                      @input="updateItemCalculations(item)"
                      class="w-14 text-center text-sm font-black text-slate-855 bg-white outline-none py-1 focus:ring-1 focus:ring-teal-500"
                    />
                    
                    <button
                      type="button"
                      @click="incrementQty(item)"
                      class="px-3 py-2 bg-slate-50 hover:bg-slate-100 text-slate-700 font-extrabold cursor-pointer border-l border-slate-200 transition"
                    >
                      ＋
                    </button>
                  </div>
                </div>
                
                <!-- Calculated calculations status -->
                <div class="flex items-center gap-2.5 justify-end">
                  <div class="text-right">
                    <span class="text-[10px] text-slate-400 font-bold block">Selisih Unit</span>
                    <span
                      class="text-xs font-black px-2 py-0.5 rounded border"
                      :class="{
                        'bg-emerald-50 text-emerald-700 border-emerald-100': item.discrepancy > 0,
                        'bg-rose-50 text-rose-700 border-rose-100': item.discrepancy < 0,
                        'bg-slate-50 text-slate-500 border-slate-200': item.discrepancy === 0
                      }"
                    >
                      {{ item.discrepancy > 0 ? '+' : '' }}{{ item.discrepancy }} pcs
                    </span>
                  </div>
                  
                  <div class="text-right border-l border-slate-100 pl-3">
                    <span class="text-[10px] text-slate-400 font-bold block">Valuasi Selisih</span>
                    <span
                      class="text-xs font-black"
                      :class="item.discrepancy_value >= 0 ? 'text-emerald-700' : 'text-rose-700'"
                    >
                      {{ item.discrepancy_value >= 0 ? '+' : '' }}{{ formatCurrency(item.discrepancy_value) }}
                    </span>
                  </div>
                </div>
              </div>
              
              <!-- Item level notes entry -->
              <input
                v-model="item.notes"
                type="text"
                placeholder="Catatan kecil item ini..."
                class="w-full bg-slate-50 border border-slate-100 rounded-xl px-3 py-1.5 text-xs text-slate-800 outline-none focus:bg-white focus:ring-2 focus:ring-teal-500/20"
              />
            </div>
          </div>
        </div>

      </div>

      <!-- Right side: Sticky action Summary Panel -->
      <div class="xl:col-span-4">
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm space-y-5 sticky top-6">
          <h2 class="text-base font-extrabold text-slate-850 pb-2 border-b border-slate-100">Kalkulasi Ringkasan</h2>
          
          <div class="space-y-3 font-semibold text-xs text-slate-500">
            <div class="flex justify-between">
              <span>Total Item Dihitung:</span>
              <span class="text-slate-800 font-extrabold">{{ totalItemsCounted }} Produk</span>
            </div>
            
            <div class="flex justify-between border-t border-slate-50 pt-3">
              <span>Estimasi Nilai Selisih:</span>
              <span
                class="text-sm font-black"
                :class="totalDiscrepancyValuation >= 0 ? 'text-emerald-700' : 'text-rose-700'"
              >
                {{ totalDiscrepancyValuation >= 0 ? '+' : '' }}{{ formatCurrency(totalDiscrepancyValuation) }}
              </span>
            </div>
            
            <div class="bg-slate-50/50 p-3.5 rounded-xl border border-slate-100 text-[10px] text-slate-400 italic">
              * Nilai plus (+) mengindikasikan surplus stok, dan minus (-) mengindikasikan defisit/kerugian barang susut. Penyesuaian persediaan akan terposting ke GL Akuntansi jika disetujui.
            </div>
          </div>
          
          <!-- Submit buttons -->
          <div class="space-y-2 pt-2">
            <button
              type="button"
              @click="submitForm('draft')"
              :disabled="submitting"
              class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-3 transition text-sm cursor-pointer disabled:opacity-50"
            >
              <span v-if="submitting" class="w-4 h-4 border-2 border-slate-700 border-t-transparent rounded-full animate-spin"></span>
              Simpan Sebagai Draft
            </button>
            
            <button
              type="button"
              @click="submitForm('approved')"
              :disabled="submitting || form.items.length === 0"
              class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-teal-800 hover:bg-teal-900 text-white font-bold py-3 transition text-sm cursor-pointer disabled:opacity-50"
            >
              <span v-if="submitting" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              Setujui & Post Jurnal
            </button>
          </div>
        </div>
      </div>
      
    </div>

    <!-- VIEW: OPNAME DETAIL VIEW -->
    <div v-else-if="viewMode === 'detail' && activeOpname" class="space-y-6">
      
      <!-- Top info details panel -->
      <section class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-150">
          <div>
            <h2 class="text-lg font-extrabold text-slate-800 flex items-center gap-2">
              <span>Stock Opname #{{ activeOpname.reference_no }}</span>
              <span
                class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border"
                :class="{
                  'bg-amber-50 text-amber-700 border-amber-200': activeOpname.status === 'draft',
                  'bg-emerald-50 text-emerald-700 border-emerald-200': activeOpname.status === 'approved',
                  'bg-slate-50 text-slate-500 border-slate-200': activeOpname.status === 'cancelled'
                }"
              >
                {{ activeOpname.status }}
              </span>
            </h2>
            <p class="text-xs text-slate-400 font-bold mt-1">Dicatat oleh: {{ activeOpname.creator?.name || '-' }} pada {{ formatDateTime(activeOpname.created_at) }}</p>
          </div>
          
          <div class="flex items-center gap-2">
            <!-- Action buttons inside details -->
            <template v-if="activeOpname.status === 'draft'">
              <button
                @click="initEdit(activeOpname)"
                class="px-4 py-2 bg-teal-50 hover:bg-teal-100 text-teal-800 text-xs font-bold rounded-xl transition cursor-pointer"
              >
                Edit Draft
              </button>
              <button
                @click="approveDraft(activeOpname)"
                class="px-4 py-2 bg-teal-800 hover:bg-teal-900 text-white text-xs font-bold rounded-xl transition cursor-pointer"
              >
                Approve
              </button>
              <button
                @click="cancelDraft(activeOpname)"
                class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold rounded-xl transition cursor-pointer"
              >
                Batalkan
              </button>
            </template>
          </div>
        </div>
        
        <!-- Details grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs font-bold text-slate-500">
          <div>
            <span class="text-slate-400">Warehouse Gudang:</span>
            <p class="text-slate-800 font-extrabold text-sm mt-0.5">{{ activeOpname.warehouse?.name || '-' }}</p>
          </div>
          <div>
            <span class="text-slate-400">Tanggal Pencatatan:</span>
            <p class="text-slate-800 font-extrabold text-sm mt-0.5">{{ formatDate(activeOpname.opname_date) }}</p>
          </div>
          <div>
            <span class="text-slate-400">Disetujui Oleh:</span>
            <p class="text-slate-800 font-extrabold text-sm mt-0.5">{{ activeOpname.approver?.name || '-' }}</p>
          </div>
          <div>
            <span class="text-slate-400">Waktu Persetujuan:</span>
            <p class="text-slate-800 font-extrabold text-sm mt-0.5">{{ formatDateTime(activeOpname.approved_at) }}</p>
          </div>
        </div>
        
        <div v-if="activeOpname.notes" class="bg-slate-50 p-4 rounded-xl text-xs text-slate-600">
          <span class="font-bold text-slate-400 uppercase tracking-wider block mb-1">Catatan Keterangan:</span>
          <p class="italic">"{{ activeOpname.notes }}"</p>
        </div>
      </section>

      <!-- Details Items Counted List (Mobile-first vertical stack) -->
      <section class="space-y-4">
        <h3 class="text-sm font-extrabold text-slate-850">Rincian Perbandingan Barang</h3>
        
        <div class="space-y-3">
          <div
            v-for="item in activeOpname.items"
            :key="item.id"
            class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm space-y-3"
          >
            <div>
              <h4 class="font-extrabold text-sm text-slate-800 leading-tight">{{ item.product?.name }}</h4>
              <p class="text-[10px] text-slate-400 font-black mt-0.5">
                SKU: {{ item.product?.code }}
                <span v-if="item.product_variant" class="ml-1 text-teal-700 bg-teal-50 border border-teal-100/50 px-1.5 py-0.5 rounded-md font-bold">
                  Varian: {{ item.product_variant?.name }}
                </span>
              </p>
            </div>
            
            <div class="grid grid-cols-3 gap-3 text-xs bg-slate-50 p-2.5 rounded-xl font-bold text-slate-600 text-center">
              <div>
                <span class="text-[10px] text-slate-400 block">Stok Sistem</span>
                <span class="text-slate-800 font-extrabold">{{ Number(item.system_qty) }} pcs</span>
              </div>
              
              <div class="border-x border-slate-200">
                <span class="text-[10px] text-slate-400 block">Fisik Aktual</span>
                <span class="text-slate-800 font-black text-sm">{{ Number(item.physical_qty) }} pcs</span>
              </div>
              
              <div>
                <span class="text-[10px] text-slate-400 block">Selisih Unit</span>
                <span
                  class="font-extrabold px-1.5 py-0.5 rounded text-[10px] border inline-block"
                  :class="{
                    'bg-emerald-50 text-emerald-700 border-emerald-100': Number(item.discrepancy) > 0,
                    'bg-rose-50 text-rose-700 border-rose-100': Number(item.discrepancy) < 0,
                    'bg-slate-50 text-slate-500 border-slate-200': Number(item.discrepancy) === 0
                  }"
                >
                  {{ Number(item.discrepancy) > 0 ? '+' : '' }}{{ Number(item.discrepancy) }} pcs
                </span>
              </div>
            </div>
            
            <!-- Value impact row -->
            <div class="flex items-center justify-between text-xs font-bold pt-1 border-t border-slate-50">
              <span class="text-slate-400">Penyesuaian Nilai Persediaan:</span>
              <span :class="Number(item.discrepancy_value) >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                {{ Number(item.discrepancy_value) >= 0 ? '+' : '' }}{{ formatCurrency(item.discrepancy_value) }}
              </span>
            </div>
            
            <p v-if="item.notes" class="text-[11px] text-slate-400 italic">
              <strong>Catatan:</strong> {{ item.notes }}
            </p>
          </div>
        </div>
      </section>
      
    </div>

  </div>
</template>

<style scoped>
/* Chrome, Safari, Edge, Opera: hide spin buttons on numeric physical count inputs */
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
  -webkit-appearance: none;
  margin: 0;
}

/* Firefox: hide spin buttons */
input[type=number] {
  -moz-appearance: textfield;
}
</style>
