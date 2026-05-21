<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()

// Core States
const variants = ref([])
const products = ref([])
const loading = ref(false)
const submitting = ref(false)
const searchQuery = ref('')

// Form State
const isEditMode = ref(false)
const editId = ref(null)
const form = ref({
  product_id: '',
  name: '',
  sku: '',
  barcode: '',
  cost_price: '',
  price: '',
  wholesale_price: '',
  is_active: true
})

// Error State for client-side validations
const errors = ref({
  product_id: '',
  name: '',
  sku: '',
  barcode: '',
  cost_price: '',
  price: '',
  wholesale_price: ''
})

// Selected Base Product computed property to detect live redundancy
const selectedProduct = computed(() => {
  if (!form.value.product_id) return null
  return products.value.find(p => p.id === parseInt(form.value.product_id)) || null
})

// Redundancy Analysis computed state
const redundancyWarnings = computed(() => {
  const warnings = {
    sku: '',
    barcode: '',
    cost_price: '',
    price: '',
    wholesale_price: ''
  }

  if (!selectedProduct.value) return warnings

  const base = selectedProduct.value

  // Check SKU
  if (form.value.sku && base.sku && form.value.sku.trim() === base.sku.trim()) {
    warnings.sku = 'Redundansi: SKU sama dengan produk dasar. Biarkan kosong atau ubah agar unik.'
  }

  // Check Barcode
  if (form.value.barcode && base.barcode && form.value.barcode.trim() === base.barcode.trim()) {
    warnings.barcode = 'Redundansi: Barcode sama dengan produk dasar.'
  }

  // Check Cost Price
  if (form.value.cost_price !== '' && form.value.cost_price !== null) {
    const val = parseFloat(form.value.cost_price)
    const baseVal = parseFloat(base.cost_price || 0)
    if (val === baseVal) {
      warnings.cost_price = 'Redundansi: Nilai sama dengan produk dasar. Biarkan kosong agar otomatis mewarisi.'
    }
  }

  // Check Price
  if (form.value.price !== '' && form.value.price !== null) {
    const val = parseFloat(form.value.price)
    const baseVal = parseFloat(base.price || 0)
    if (val === baseVal) {
      warnings.price = 'Redundansi: Nilai sama dengan produk dasar. Biarkan kosong agar otomatis mewarisi.'
    }
  }

  // Check Wholesale Price
  if (form.value.wholesale_price !== '' && form.value.wholesale_price !== null) {
    const val = parseFloat(form.value.wholesale_price)
    const baseVal = parseFloat(base.wholesale_price || 0)
    if (val === baseVal) {
      warnings.wholesale_price = 'Redundansi: Nilai sama dengan produk dasar. Biarkan kosong agar otomatis mewarisi.'
    }
  }

  return warnings
})

// Fetch all products for dropdown
const fetchProducts = async () => {
  try {
    const response = await api.get('/products')
    if (response.data && response.data.data) {
      products.value = response.data.data
    } else {
      products.value = response.data || []
    }
  } catch (error) {
    console.error('Error fetching products:', error)
    toast.error('Gagal mengambil data produk dasar.')
  }
}

// Fetch all variants
const fetchVariants = async () => {
  loading.value = true
  try {
    const response = await api.get('/product-variants')
    if (response.data && response.data.data) {
      variants.value = response.data.data
    } else {
      variants.value = response.data || []
    }
  } catch (error) {
    console.error('Error fetching variants:', error)
    toast.error('Gagal memuat daftar varian produk.')
  } finally {
    loading.value = false
  }
}

// Helper to format currency
const formatCurrency = (val) => {
  if (val === null || val === undefined || val === '') return '-'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val)
}

// Client-side validation function
const validateForm = () => {
  let isValid = true
  errors.value = {
    product_id: '',
    name: '',
    sku: '',
    barcode: '',
    cost_price: '',
    price: '',
    wholesale_price: ''
  }

  if (!form.value.product_id) {
    errors.value.product_id = 'Pilihan Produk Induk wajib dipilih.'
    isValid = false
  }

  if (!form.value.name || form.value.name.trim() === '') {
    errors.value.name = 'Nama varian wajib diisi (contoh: "Size XL" atau "Warna Merah").'
    isValid = false
  } else if (form.value.name.length < 3) {
    errors.value.name = 'Nama varian harus memiliki minimal 3 karakter.'
    isValid = false
  }

  if (form.value.sku && form.value.sku.length > 100) {
    errors.value.sku = 'SKU tidak boleh melebihi 100 karakter.'
    isValid = false
  }

  if (form.value.barcode && form.value.barcode.length > 100) {
    errors.value.barcode = 'Barcode tidak boleh melebihi 100 karakter.'
    isValid = false
  }

  // Cost Price check
  if (form.value.cost_price !== '' && form.value.cost_price !== null) {
    const val = parseFloat(form.value.cost_price)
    if (isNaN(val) || val < 0) {
      errors.value.cost_price = 'Cost price harus bernilai positif.'
      isValid = false
    }
  }

  // Price check
  if (form.value.price !== '' && form.value.price !== null) {
    const val = parseFloat(form.value.price)
    if (isNaN(val) || val < 0) {
      errors.value.price = 'Selling price harus bernilai positif.'
      isValid = false
    }

    // Check pricing safeguards (Price vs Cost)
    if (form.value.cost_price !== '' && form.value.cost_price !== null) {
      const costVal = parseFloat(form.value.cost_price)
      if (val < costVal) {
        errors.value.price = 'Peringatan: Harga jual lebih rendah dari Harga Pokok Pembelian (HPP).'
      }
    }
  }

  // Wholesale Price check
  if (form.value.wholesale_price !== '' && form.value.wholesale_price !== null) {
    const val = parseFloat(form.value.wholesale_price)
    if (isNaN(val) || val < 0) {
      errors.value.wholesale_price = 'Harga grosir harus bernilai positif.'
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
    product_id: '',
    name: '',
    sku: '',
    barcode: '',
    cost_price: '',
    price: '',
    wholesale_price: '',
    is_active: true
  }
  errors.value = {
    product_id: '',
    name: '',
    sku: '',
    barcode: '',
    cost_price: '',
    price: '',
    wholesale_price: ''
  }
}

// Submit via Axios POST/PUT
const handleSubmit = async () => {
  if (!validateForm()) return

  submitting.value = true

  const payload = {
    product_id: parseInt(form.value.product_id),
    name: form.value.name,
    sku: form.value.sku ? form.value.sku.trim() : null,
    barcode: form.value.barcode ? form.value.barcode.trim() : null,
    cost_price: form.value.cost_price !== '' && form.value.cost_price !== null ? parseFloat(form.value.cost_price) : null,
    price: form.value.price !== '' && form.value.price !== null ? parseFloat(form.value.price) : null,
    wholesale_price: form.value.wholesale_price !== '' && form.value.wholesale_price !== null ? parseFloat(form.value.wholesale_price) : null,
    is_active: form.value.is_active ? 1 : 0
  }

  try {
    if (isEditMode.value) {
      await api.put(`/product-variants/${editId.value}`, payload)
      toast.success('Varian produk berhasil diperbarui!')
    } else {
      await api.post('/product-variants', payload)
      toast.success('Varian produk baru berhasil ditambahkan!')
    }
    resetForm()
    fetchVariants()
  } catch (error) {
    console.error('Error saving variant:', error)
    if (error.response && error.response.status === 422) {
      const serverErrors = error.response.data.errors || {}
      Object.keys(serverErrors).forEach(key => {
        // Map wholesale_price server validation back to our form field
        const formKey = key === 'wholesale_price' ? 'wholesale_price' : key
        if (errors.value[formKey] !== undefined) {
          errors.value[formKey] = serverErrors[key][0]
        }
      })
      toast.error('Harap perbaiki kesalahan input yang ditandai.')
    } else {
      toast.error(error.response?.data?.message || 'Terjadi kesalahan sistem saat menyimpan data varian.')
    }
  } finally {
    submitting.value = false
  }
}

// Edit Variant
const handleEdit = (variant) => {
  errors.value = {
    product_id: '',
    name: '',
    sku: '',
    barcode: '',
    cost_price: '',
    price: '',
    wholesale_price: ''
  }
  isEditMode.value = true
  editId.value = variant.id
  form.value = {
    product_id: variant.product_id,
    name: variant.name,
    sku: variant.sku || '',
    barcode: variant.barcode || '',
    cost_price: variant.cost_price !== null ? variant.cost_price : '',
    price: variant.price !== null ? variant.price : '',
    wholesale_price: variant.wholesale_price !== null ? variant.wholesale_price : '',
    is_active: variant.is_active === 1 || variant.is_active === true
  }
}

// Delete Variant
const handleDelete = async (id) => {
  if (!confirm('Apakah Anda yakin ingin menghapus varian produk ini secara permanen?')) return

  try {
    await api.delete(`/product-variants/${id}`)
    toast.success('Varian produk berhasil dihapus!')
    if (editId.value === id) {
      resetForm()
    }
    fetchVariants()
  } catch (error) {
    console.error('Error deleting variant:', error)
    toast.error('Gagal menghapus varian produk.')
  }
}

// Pagination & Search States
const currentPage = ref(1)
const pageSize = ref(10)

// Reset to page 1 when search query changes
watch(searchQuery, () => {
  currentPage.value = 1
})

// Filtered and searched variants
const filteredVariants = computed(() => {
  let list = variants.value

  if (searchQuery.value && searchQuery.value.trim() !== '') {
    const q = searchQuery.value.toLowerCase().trim()
    list = list.filter(v => 
      v.name.toLowerCase().includes(q) ||
      (v.sku && v.sku.toLowerCase().includes(q)) ||
      (v.barcode && v.barcode.toLowerCase().includes(q)) ||
      (v.product && v.product.name.toLowerCase().includes(q))
    )
  }

  return list
})

const totalPages = computed(() => {
  return Math.ceil(filteredVariants.value.length / pageSize.value) || 1
})

// Slice for client-side pagination
const paginatedVariants = computed(() => {
  const startIndex = (currentPage.value - 1) * pageSize.value
  return filteredVariants.value.slice(startIndex, startIndex + pageSize.value)
})

const goToPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

const paginationInfo = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value + 1
  const end = Math.min(currentPage.value * pageSize.value, filteredVariants.value.length)
  return {
    start: filteredVariants.value.length === 0 ? 0 : start,
    end,
    total: filteredVariants.value.length
  }
})

// Lifecycle
onMounted(() => {
  fetchProducts()
  fetchVariants()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header Summary Card -->
    <div class="bg-white/80 backdrop-blur-md rounded-2xl p-6 shadow-sm border border-slate-100 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h2 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-indigo-500">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
          </svg>
          Master Varian Produk (Variance)
        </h2>
        <p class="text-sm text-slate-500 mt-1">
          Kelola variasi produk dasar (seperti ukuran, warna, kemasan) beserta penyesuaian khusus harga dan kode SKU.
        </p>
      </div>

      <!-- Quick stats indicators -->
      <div class="flex gap-4">
        <div class="bg-indigo-50 border border-indigo-100/50 rounded-xl px-4 py-2 text-center min-w-[100px]">
          <div class="text-xs text-indigo-600 font-medium uppercase tracking-wider">Total Varian</div>
          <div class="text-lg font-bold text-indigo-800">{{ variants.length }}</div>
        </div>
        <div class="bg-emerald-50 border border-emerald-100/50 rounded-xl px-4 py-2 text-center min-w-[100px]">
          <div class="text-xs text-emerald-600 font-medium uppercase tracking-wider">Aktif</div>
          <div class="text-lg font-bold text-emerald-800">{{ variants.filter(v => v.is_active).length }}</div>
        </div>
      </div>
    </div>

    <!-- Live Redundancy Analytics Badge Alert -->
    <Transition name="fade">
      <div v-if="selectedProduct" class="bg-amber-50 border border-amber-200/60 rounded-2xl p-5 shadow-sm space-y-3">
        <h3 class="text-sm font-semibold text-amber-800 flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-amber-600">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
          </svg>
          Informasi Redundansi & Pewarisan Nilai (Inheritance Analysis)
        </h3>
        <p class="text-xs text-amber-700 leading-relaxed">
          Anda memilih produk induk <span class="font-semibold">{{ selectedProduct.name }}</span>. 
          Modul ini mendukung struktur relasi dinamis untuk mencegah redundansi database. 
          Jika kolom harga varian dikosongkan, POS akan <strong>mewarisi secara otomatis</strong> harga produk induk di bawah ini.
        </p>

        <!-- Product Base Reference Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 pt-1">
          <div class="bg-white/60 rounded-lg p-2.5 border border-amber-200/40 text-center">
            <div class="text-[10px] text-amber-600 font-bold uppercase">Base SKU</div>
            <div class="text-xs font-semibold text-slate-700 truncate mt-0.5">{{ selectedProduct.sku || 'None' }}</div>
          </div>
          <div class="bg-white/60 rounded-lg p-2.5 border border-amber-200/40 text-center">
            <div class="text-[10px] text-amber-600 font-bold uppercase">Base Barcode</div>
            <div class="text-xs font-semibold text-slate-700 truncate mt-0.5">{{ selectedProduct.barcode || 'None' }}</div>
          </div>
          <div class="bg-white/60 rounded-lg p-2.5 border border-amber-200/40 text-center">
            <div class="text-[10px] text-amber-600 font-bold uppercase">Base HPP</div>
            <div class="text-xs font-semibold text-slate-700 truncate mt-0.5">{{ formatCurrency(selectedProduct.cost_price) }}</div>
          </div>
          <div class="bg-white/60 rounded-lg p-2.5 border border-amber-200/40 text-center">
            <div class="text-[10px] text-amber-600 font-bold uppercase">Base Jual</div>
            <div class="text-xs font-semibold text-slate-700 truncate mt-0.5">{{ formatCurrency(selectedProduct.price) }}</div>
          </div>
          <div class="bg-white/60 rounded-lg p-2.5 border border-amber-200/40 text-center col-span-2 sm:col-span-1">
            <div class="text-[10px] text-amber-600 font-bold uppercase">Base Grosir</div>
            <div class="text-xs font-semibold text-slate-700 truncate mt-0.5">{{ formatCurrency(selectedProduct.wholesale_price) }}</div>
          </div>
        </div>
      </div>
    </Transition>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
      <!-- Left: Input Form Card -->
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 lg:col-span-1">
        <h3 class="text-base font-semibold text-slate-800 mb-5 flex items-center gap-2">
          <span>{{ isEditMode ? 'Edit Varian Produk' : 'Tambah Varian Baru' }}</span>
          <span v-if="isEditMode" class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full font-medium">Mode Ubah</span>
        </h3>

        <form @submit.prevent="handleSubmit" class="space-y-4">
          <!-- Product ID Dropdown -->
          <div class="space-y-1">
            <label class="block text-xs font-semibold text-slate-600">Produk Induk <span class="text-red-500">*</span></label>
            <select
              v-model="form.product_id"
              class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all cursor-pointer"
              :class="{ 'border-red-400 focus:ring-red-500/10 focus:border-red-400': errors.product_id }"
              :disabled="isEditMode"
            >
              <option value="" disabled>-- Pilih Produk Dasar --</option>
              <option v-for="prod in products" :key="prod.id" :value="prod.id">
                {{ prod.code }} - {{ prod.name }}
              </option>
            </select>
            <p v-if="errors.product_id" class="text-[11px] text-red-500 font-medium">{{ errors.product_id }}</p>
          </div>

          <!-- Variant Name -->
          <div class="space-y-1">
            <label class="block text-xs font-semibold text-slate-600">Nama Varian <span class="text-red-500">*</span></label>
            <input
              v-model="form.name"
              type="text"
              placeholder="Contoh: Merah - XL, 500ml"
              class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
              :class="{ 'border-red-400 focus:ring-red-500/10 focus:border-red-400': errors.name }"
            />
            <p v-if="errors.name" class="text-[11px] text-red-500 font-medium">{{ errors.name }}</p>
          </div>

          <!-- SKU Field -->
          <div class="space-y-1">
            <label class="block text-xs font-semibold text-slate-600">Kode SKU Varian <span class="text-slate-400">(Opsional)</span></label>
            <input
              v-model="form.sku"
              type="text"
              placeholder="Biarkan kosong untuk mengikuti induk"
              class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
              :class="{ 'border-red-400': errors.sku, 'border-amber-300 focus:ring-amber-500/10': redundancyWarnings.sku }"
            />
            <p v-if="errors.sku" class="text-[11px] text-red-500 font-medium">{{ errors.sku }}</p>
            <p v-if="redundancyWarnings.sku" class="text-[10px] text-amber-600 font-medium bg-amber-50 rounded-lg p-1.5 border border-amber-200/40 mt-1">{{ redundancyWarnings.sku }}</p>
          </div>

          <!-- Barcode Field -->
          <div class="space-y-1">
            <label class="block text-xs font-semibold text-slate-600">Barcode Varian <span class="text-slate-400">(Opsional)</span></label>
            <input
              v-model="form.barcode"
              type="text"
              placeholder="Contoh: 8991234567"
              class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
              :class="{ 'border-red-400': errors.barcode, 'border-amber-300 focus:ring-amber-500/10': redundancyWarnings.barcode }"
            />
            <p v-if="errors.barcode" class="text-[11px] text-red-500 font-medium">{{ errors.barcode }}</p>
            <p v-if="redundancyWarnings.barcode" class="text-[10px] text-amber-600 font-medium bg-amber-50 rounded-lg p-1.5 border border-amber-200/40 mt-1">{{ redundancyWarnings.barcode }}</p>
          </div>

          <!-- Pricing Split Row -->
          <div class="grid grid-cols-2 gap-3">
            <!-- Cost Price Field -->
            <div class="space-y-1 col-span-2">
              <label class="block text-xs font-semibold text-slate-600">Harga Pokok Pembelian (HPP) <span class="text-slate-400">(Opsional)</span></label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">Rp</span>
                <input
                  v-model="form.cost_price"
                  type="number"
                  placeholder="Ikuti harga induk"
                  class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3.5 py-2.5 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                  :class="{ 'border-red-400': errors.cost_price, 'border-amber-300 focus:ring-amber-500/10': redundancyWarnings.cost_price }"
                />
              </div>
              <p v-if="errors.cost_price" class="text-[11px] text-red-500 font-medium">{{ errors.cost_price }}</p>
              <p v-if="redundancyWarnings.cost_price" class="text-[10px] text-amber-600 font-medium bg-amber-50 rounded-lg p-1.5 border border-amber-200/40 mt-1">{{ redundancyWarnings.cost_price }}</p>
            </div>

            <!-- Price Field -->
            <div class="space-y-1">
              <label class="block text-xs font-semibold text-slate-600">Harga Jual <span class="text-slate-400">(Opsional)</span></label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">Rp</span>
                <input
                  v-model="form.price"
                  type="number"
                  placeholder="Sama"
                  class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3.5 py-2.5 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                  :class="{ 'border-red-400': errors.price, 'border-amber-300 focus:ring-amber-500/10': redundancyWarnings.price }"
                />
              </div>
              <p v-if="errors.price" class="text-[11px] text-red-500 font-medium leading-tight">{{ errors.price }}</p>
              <p v-if="redundancyWarnings.price" class="text-[10px] text-amber-600 font-medium bg-amber-50 rounded-lg p-1.5 border border-amber-200/40 mt-1">{{ redundancyWarnings.price }}</p>
            </div>

            <!-- Wholesale Price Field -->
            <div class="space-y-1">
              <label class="block text-xs font-semibold text-slate-600">Harga Grosir <span class="text-slate-400">(Opsional)</span></label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">Rp</span>
                <input
                  v-model="form.wholesale_price"
                  type="number"
                  placeholder="Sama"
                  class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3.5 py-2.5 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
                  :class="{ 'border-red-400': errors.wholesale_price, 'border-amber-300 focus:ring-amber-500/10': redundancyWarnings.wholesale_price }"
                />
              </div>
              <p v-if="errors.wholesale_price" class="text-[11px] text-red-500 font-medium">{{ errors.wholesale_price }}</p>
              <p v-if="redundancyWarnings.wholesale_price" class="text-[10px] text-amber-600 font-medium bg-amber-50 rounded-lg p-1.5 border border-amber-200/40 mt-1">{{ redundancyWarnings.wholesale_price }}</p>
            </div>
          </div>

          <!-- iOS Toggle for Active Status -->
          <div class="flex items-center justify-between p-3 bg-slate-50 border border-slate-100 rounded-xl">
            <span class="text-xs font-semibold text-slate-600">Status Varian Aktif</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" v-model="form.is_active" class="sr-only peer" />
              <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
            </label>
          </div>

          <!-- Form Action Buttons -->
          <div class="flex gap-2 pt-2">
            <button
              type="button"
              @click="resetForm"
              class="flex-1 text-slate-500 hover:text-slate-700 hover:bg-slate-100 border border-slate-200 py-2.5 rounded-xl text-xs font-semibold transition-all cursor-pointer text-center"
            >
              Reset
            </button>
            <button
              type="submit"
              :disabled="submitting"
              class="flex-1 bg-gradient-to-r from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 text-white py-2.5 rounded-xl text-xs font-semibold shadow-md shadow-indigo-200 hover:shadow-lg hover:shadow-indigo-300 transition-all cursor-pointer flex items-center justify-center gap-1.5"
            >
              <svg v-if="submitting" class="animate-spin h-3 w-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>{{ isEditMode ? 'Simpan Perubahan' : 'Tambah Varian' }}</span>
            </button>
          </div>
        </form>
      </div>

      <!-- Right: List / Directory View Card -->
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 lg:col-span-2 space-y-4">
        <!-- Search and Filter Bar -->
        <div class="flex flex-col sm:flex-row gap-3 items-center justify-between">
          <h3 class="text-base font-semibold text-slate-800 self-start sm:self-auto">
            Direktori Varian Produk
          </h3>
          <!-- Search input -->
          <div class="relative w-full sm:w-64">
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.602 10.602Z" />
              </svg>
            </span>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari nama, SKU, induk..."
              class="w-full text-xs bg-slate-50 border border-slate-200 pl-10 pr-3.5 py-2.5 rounded-xl text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all"
            />
          </div>
        </div>

        <!-- Directory Table -->
        <div class="overflow-x-auto border border-slate-100 rounded-xl">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50/50 text-slate-600 border-b border-slate-100 text-[10px] uppercase font-bold tracking-wider">
                <th class="p-4">Varian & Produk Induk</th>
                <th class="p-4">Kode SKU / Barcode</th>
                <th class="p-4">Harga Pokok (HPP)</th>
                <th class="p-4">Harga Jual</th>
                <th class="p-4">Status</th>
                <th class="p-4 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-700 text-xs">
              <tr v-if="loading" class="hover:bg-slate-50/20">
                <td colspan="6" class="p-10 text-center text-slate-400">
                  <div class="flex flex-col items-center justify-center gap-2">
                    <svg class="animate-spin h-6 w-6 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Memuat daftar varian produk...</span>
                  </div>
                </td>
              </tr>
              <tr v-else-if="paginatedVariants.length === 0" class="hover:bg-slate-50/20">
                <td colspan="6" class="p-10 text-center text-slate-400">
                  Tidak ada varian produk ditemukan.
                </td>
              </tr>
              <tr v-else v-for="variant in paginatedVariants" :key="variant.id" class="hover:bg-slate-50/25 transition-colors">
                <!-- Variant & Base Product Info -->
                <td class="p-4">
                  <div class="font-semibold text-slate-800 text-sm">{{ variant.name }}</div>
                  <div class="text-[10px] text-indigo-600 bg-indigo-50 rounded px-1.5 py-0.5 inline-block font-medium mt-1 max-w-[200px] truncate">
                    Induk: {{ variant.product?.name || 'Unknown' }}
                  </div>
                </td>

                <!-- SKU & Barcode -->
                <td class="p-4 space-y-1">
                  <div class="flex items-center gap-1.5">
                    <span class="text-[10px] text-slate-400 font-bold uppercase w-8">SKU</span>
                    <span :class="variant.sku ? 'font-mono text-slate-700' : 'text-slate-400 italic text-[11px]'">
                      {{ variant.sku || 'Waris Induk' }}
                    </span>
                  </div>
                  <div class="flex items-center gap-1.5">
                    <span class="text-[10px] text-slate-400 font-bold uppercase w-8">BAR</span>
                    <span :class="variant.barcode ? 'font-mono text-slate-700' : 'text-slate-400 italic text-[11px]'">
                      {{ variant.barcode || 'Waris Induk' }}
                    </span>
                  </div>
                </td>

                <!-- Cost Price -->
                <td class="p-4">
                  <div class="font-medium text-slate-700">
                    {{ variant.cost_price !== null ? formatCurrency(variant.cost_price) : formatCurrency(variant.product?.cost_price) }}
                  </div>
                  <div class="text-[9px] mt-1 font-bold">
                    <span v-if="variant.cost_price !== null" class="text-indigo-600 bg-indigo-50 px-1 py-0.5 rounded uppercase">Custom HPP</span>
                    <span v-else class="text-slate-400 bg-slate-100 px-1 py-0.5 rounded uppercase">Mewarisi</span>
                  </div>
                </td>

                <!-- Price & Inheritance Indicator -->
                <td class="p-4">
                  <div class="font-semibold text-slate-800 text-sm">
                    {{ variant.price !== null ? formatCurrency(variant.price) : formatCurrency(variant.product?.price) }}
                  </div>
                  <div class="text-[9px] mt-1 font-bold">
                    <span v-if="variant.price !== null" class="text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded uppercase">Kustom Varian</span>
                    <span v-else class="text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded uppercase">Waris Induk</span>
                  </div>
                </td>

                <!-- Status -->
                <td class="p-4">
                  <span
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider"
                    :class="variant.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'"
                  >
                    <span class="w-1.5 h-1.5 rounded-full" :class="variant.is_active ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                    {{ variant.is_active ? 'Aktif' : 'Non-Aktif' }}
                  </span>
                </td>

                <!-- Actions -->
                <td class="p-4">
                  <div class="flex items-center justify-center gap-1.5">
                    <button
                      @click="handleEdit(variant)"
                      class="p-1.5 rounded-lg border border-slate-200 text-slate-600 hover:text-indigo-600 hover:border-indigo-200 hover:bg-indigo-50/50 transition-all cursor-pointer"
                      title="Ubah Varian"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.83 17.162a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                      </svg>
                    </button>
                    <button
                      @click="handleDelete(variant.id)"
                      class="p-1.5 rounded-lg border border-slate-200 text-slate-600 hover:text-red-600 hover:border-red-200 hover:bg-red-50/50 transition-all cursor-pointer"
                      title="Hapus Varian"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination UI -->
        <div v-if="filteredVariants.length > 0" class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-3 border-t border-slate-100">
          <!-- Page size selector -->
          <div class="flex items-center gap-2 text-xs text-slate-500">
            <span>Tampilkan</span>
            <select
              v-model="pageSize"
              @change="currentPage = 1"
              class="bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-slate-700 cursor-pointer focus:outline-none focus:border-indigo-500 text-xs font-semibold"
            >
              <option :value="5">5 entri</option>
              <option :value="10">10 entri</option>
              <option :value="25">25 entri</option>
              <option :value="50">50 entri</option>
            </select>
            <span>dari {{ paginationInfo.total }} varian</span>
          </div>

          <!-- Page navigation buttons -->
          <div class="flex items-center gap-1">
            <button
              @click="goToPage(1)"
              :disabled="currentPage === 1"
              class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-100 hover:text-slate-800 disabled:opacity-40 disabled:hover:bg-transparent transition-all cursor-pointer"
              title="First Page"
            >
              &laquo;
            </button>
            <button
              @click="goToPage(currentPage - 1)"
              :disabled="currentPage === 1"
              class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-100 hover:text-slate-800 disabled:opacity-40 disabled:hover:bg-transparent transition-all cursor-pointer"
              title="Previous Page"
            >
              &lsaquo;
            </button>

            <!-- Numeric Page Buttons -->
            <template v-for="page in totalPages" :key="page">
              <button
                v-if="Math.abs(page - currentPage) <= 1 || page === 1 || page === totalPages"
                @click="goToPage(page)"
                class="w-8 h-8 rounded-lg text-xs font-semibold flex items-center justify-center transition-all cursor-pointer"
                :class="currentPage === page
                  ? 'bg-indigo-500 text-white shadow-sm shadow-indigo-200'
                  : 'border border-slate-200 text-slate-500 hover:bg-slate-100 hover:text-slate-800'"
              >
                {{ page }}
              </button>
              <span v-else-if="(page === 2 && currentPage > 3) || (page === totalPages - 1 && currentPage < totalPages - 2)" class="px-1 text-slate-400 text-xs">...</span>
            </template>

            <button
              @click="goToPage(currentPage + 1)"
              :disabled="currentPage === totalPages"
              class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-100 hover:text-slate-800 disabled:opacity-40 disabled:hover:bg-transparent transition-all cursor-pointer"
              title="Next Page"
            >
              &rsaquo;
            </button>
            <button
              @click="goToPage(totalPages)"
              :disabled="currentPage === totalPages"
              class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-100 hover:text-slate-800 disabled:opacity-40 disabled:hover:bg-transparent transition-all cursor-pointer"
              title="Last Page"
            >
              &raquo;
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
