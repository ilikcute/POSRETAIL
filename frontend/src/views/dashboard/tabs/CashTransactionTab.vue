<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()

// Core States
const transactions = ref([])
const stores = ref([])
const shifts = ref([])
const loading = ref(false)
const submitting = ref(false)

// Query & Filter States
const searchQuery = ref('')
const filterType = ref('')
const filterStore = ref('')
const filterPaymentMethod = ref('')

// Pagination States
const currentPage = ref(1)
const lastPage = ref(1)
const totalItems = ref(0)
const perPage = ref(10)

// Form State
const isEditMode = ref(false)
const editId = ref(null)
const form = ref({
  store_id: '',
  shift_id: '',
  type: 'out',
  amount: '',
  category: 'operasional',
  custom_category: '',
  payment_method: 'cash',
  description: ''
})

// Error State
const errors = ref({
  store_id: '',
  shift_id: '',
  type: '',
  amount: '',
  category: '',
  payment_method: '',
  description: ''
})

// Options
const categories = [
  { value: 'operasional', label: 'Operasional Toko' },
  { value: 'listrik', label: 'Biaya Listrik / Air' },
  { value: 'atk', label: 'Alat Tulis Kantor (ATK)' },
  { value: 'penjualan_kardus', label: 'Penjualan Kardus / Barang Bekas (Uang Masuk)' },
  { value: 'lainnya', label: 'Lainnya (Kategori Kustom)' }
]

// Fetch resources
const fetchStores = async () => {
  try {
    const res = await api.get('/stores')
    stores.value = res.data?.data || res.data || []
  } catch (err) {
    console.error('Error fetching stores:', err)
  }
}

const fetchShifts = async () => {
  try {
    const res = await api.get('/shifts')
    const allShifts = res.data?.data || res.data || []
    // Filter shifts that are open or recently closed for better selection list
    shifts.value = allShifts
  } catch (err) {
    console.error('Error fetching shifts:', err)
  }
}

const fetchTransactions = async (page = 1) => {
  loading.value = true
  try {
    const params = {
      page,
      per_page: perPage.value,
      search: searchQuery.value,
      type: filterType.value,
      store_id: filterStore.value,
      payment_method: filterPaymentMethod.value
    }
    const res = await api.get('/cash-transactions', { params })
    const payload = res.data?.data || res.data
    
    transactions.value = payload.data || []
    currentPage.value = payload.current_page || 1
    lastPage.value = payload.last_page || 1
    totalItems.value = payload.total || 0
  } catch (err) {
    console.error('Error fetching transactions:', err)
    toast.error('Gagal mengambil data transaksi kas.')
  } finally {
    loading.value = false
  }
}

// Watch filters to trigger fetch
watch([searchQuery, filterType, filterStore, filterPaymentMethod], () => {
  fetchTransactions(1)
})

// Validation logic
const validateForm = () => {
  let isValid = true
  errors.value = {
    store_id: '',
    shift_id: '',
    type: '',
    amount: '',
    category: '',
    payment_method: '',
    description: ''
  }

  if (!form.value.store_id) {
    errors.value.store_id = 'Toko (Store) wajib dipilih.'
    isValid = false
  }

  if (!form.value.type) {
    errors.value.type = 'Tipe transaksi wajib dipilih.'
    isValid = false
  }

  if (!form.value.amount || Number(form.value.amount) <= 0) {
    errors.value.amount = 'Nominal harus berupa angka lebih besar dari 0.'
    isValid = false
  }

  const finalCategory = form.value.category === 'lainnya' ? form.value.custom_category : form.value.category
  if (!finalCategory || finalCategory.trim() === '') {
    errors.value.category = 'Kategori transaksi wajib dipilih atau diisi.'
    isValid = false
  }

  if (!form.value.payment_method) {
    errors.value.payment_method = 'Metode pembayaran wajib dipilih.'
    isValid = false
  }

  return isValid
}

const resetForm = () => {
  isEditMode.value = false
  editId.value = null
  form.value = {
    store_id: stores.value[0]?.id || '',
    shift_id: '',
    type: 'out',
    amount: '',
    category: 'operasional',
    custom_category: '',
    payment_method: 'cash',
    description: ''
  }
  errors.value = {
    store_id: '',
    shift_id: '',
    type: '',
    amount: '',
    category: '',
    payment_method: '',
    description: ''
  }
}

// Submit via Axios
const handleSubmit = async () => {
  if (!validateForm()) return

  submitting.value = true
  const finalCategory = form.value.category === 'lainnya' ? form.value.custom_category : form.value.category

  const payload = {
    store_id: form.value.store_id,
    shift_id: form.value.shift_id || null,
    type: form.value.type,
    amount: Number(form.value.amount),
    category: finalCategory,
    payment_method: form.value.payment_method,
    description: form.value.description
  }

  try {
    if (isEditMode.value) {
      await api.put(`/cash-transactions/${editId.value}`, payload)
      toast.success('Transaksi kas berhasil diperbarui!')
    } else {
      await api.post('/cash-transactions', payload)
      toast.success('Transaksi kas berhasil dicatat!')
    }
    resetForm()
    fetchTransactions(currentPage.value)
  } catch (err) {
    console.error('Error submitting transaction:', err)
    if (err.response && err.response.status === 422) {
      const serverErrors = err.response.data.errors || {}
      Object.keys(serverErrors).forEach(key => {
        if (errors.value[key] !== undefined) {
          errors.value[key] = Array.isArray(serverErrors[key]) ? serverErrors[key][0] : serverErrors[key]
        }
      })
      // If there's a general context error from our custom exception
      if (err.response.data.message) {
        toast.error(err.response.data.message)
      } else {
        toast.error('Periksa kembali input Anda.')
      }
    } else {
      toast.error(err.response?.data?.message || 'Terjadi kesalahan sistem saat memproses transaksi.')
    }
  } finally {
    submitting.value = false
  }
}

const handleEdit = (tx) => {
  isEditMode.value = true
  editId.value = tx.id

  // Check if category is standard or custom
  const isStandard = categories.some(cat => cat.value === tx.category)

  form.value = {
    store_id: tx.store_id,
    shift_id: tx.shift_id || '',
    type: tx.type,
    amount: tx.amount,
    category: isStandard ? tx.category : 'lainnya',
    custom_category: isStandard ? '' : tx.category,
    payment_method: tx.payment_method || 'cash',
    description: tx.description || ''
  }
}

const handleDelete = async (tx) => {
  if (!confirm('Apakah Anda yakin ingin menghapus transaksi kas ini? Tindakan ini akan mengembalikan saldo akun dan menghapus jurnal penyesuaian terkait.')) {
    return
  }

  try {
    await api.delete(`/cash-transactions/${tx.id}`)
    toast.success('Transaksi kas berhasil dihapus.')
    if (editId.value === tx.id) {
      resetForm()
    }
    fetchTransactions(currentPage.value)
  } catch (err) {
    console.error('Error deleting transaction:', err)
    toast.error(err.response?.data?.message || 'Gagal menghapus transaksi kas.')
  }
}

// Helpers
const formatCurrency = (val) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 2
  }).format(val)
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Initialize
onMounted(async () => {
  await fetchStores()
  await fetchShifts()
  if (stores.value.length > 0) {
    form.value.store_id = stores.value[0].id
  }
  fetchTransactions()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header Area -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
      <div>
        <h2 class="text-xl font-bold text-gray-800">Petty Cash & Cash Transactions</h2>
        <p class="text-xs text-gray-500">Mencatat pengeluaran operasional (cash-out) dan penerimaan lain (cash-in) toko dengan sinkronisasi jurnal akuntansi.</p>
      </div>
      <div class="flex flex-wrap gap-2 w-full md:w-auto">
        <!-- Search bar -->
        <div class="relative flex-1 md:w-64">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.637 10.637Z" />
            </svg>
          </span>
          <input 
            v-model="searchQuery"
            type="text" 
            placeholder="Cari kategori atau deskripsi..." 
            class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
          />
        </div>
      </div>
    </div>

    <!-- Filters Section -->
    <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-sm flex flex-wrap gap-3 items-center text-xs">
      <div class="flex items-center gap-1.5">
        <span class="font-bold text-gray-400 uppercase tracking-wider">Filter:</span>
      </div>
      <!-- Type Filter -->
      <select v-model="filterType" class="px-3 py-1.5 border border-gray-200 rounded-lg focus:outline-none focus:border-emerald-500">
        <option value="">Semua Tipe</option>
        <option value="in">Cash In (Uang Masuk)</option>
        <option value="out">Cash Out (Uang Keluar)</option>
      </select>
      <!-- Store Filter -->
      <select v-model="filterStore" class="px-3 py-1.5 border border-gray-200 rounded-lg focus:outline-none focus:border-emerald-500">
        <option value="">Semua Toko</option>
        <option v-for="store in stores" :key="store.id" :value="store.id">
          {{ store.name }}
        </option>
      </select>
      <!-- Payment Method Filter -->
      <select v-model="filterPaymentMethod" class="px-3 py-1.5 border border-gray-200 rounded-lg focus:outline-none focus:border-emerald-500">
        <option value="">Semua Metode</option>
        <option value="cash">Cash (Kas Laci)</option>
        <option value="bank">Bank</option>
      </select>
      <!-- Clear Filter Button -->
      <button 
        v-if="filterType || filterStore || filterPaymentMethod || searchQuery"
        @click="filterType = ''; filterStore = ''; filterPaymentMethod = ''; searchQuery = ''"
        class="text-red-500 hover:text-red-700 font-bold ml-auto transition-colors cursor-pointer"
      >
        Reset Filter
      </button>
    </div>

    <!-- Main Layout Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left side: Form -->
      <div class="lg:col-span-1 bg-white border border-gray-100 p-6 rounded-2xl shadow-sm h-fit">
        <h3 class="text-md font-bold text-gray-800 mb-5 flex items-center gap-2">
          <span class="p-1.5 rounded-lg bg-emerald-50 text-emerald-600">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
          </span>
          {{ isEditMode ? 'Edit Transaksi Kas' : 'Catat Transaksi Baru' }}
        </h3>

        <form @submit.prevent="handleSubmit" class="space-y-4">
          <!-- Store Selection -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Toko / Cabang *</label>
            <select 
              v-model="form.store_id"
              class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors bg-white"
              :class="errors.store_id ? 'border-red-400' : 'border-gray-200'"
            >
              <option value="" disabled>Pilih Toko</option>
              <option v-for="store in stores" :key="store.id" :value="store.id">
                {{ store.name }}
              </option>
            </select>
            <p v-if="errors.store_id" class="text-xs text-red-500 font-medium mt-0.5">{{ errors.store_id }}</p>
          </div>

          <!-- Shift Selection (Optional) -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Shift Kasir (Opsional)</label>
            <select 
              v-model="form.shift_id"
              class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors bg-white text-xs"
              :class="errors.shift_id ? 'border-red-400' : 'border-gray-200'"
            >
              <option value="">Tanpa Shift (Gudang/Kantor)</option>
              <option v-for="shift in shifts" :key="shift.id" :value="shift.id">
                Shift #{{ shift.id }} - {{ shift.user?.name }} ({{ shift.status.toUpperCase() }} - {{ formatDate(shift.start_time) }})
              </option>
            </select>
            <p v-if="errors.shift_id" class="text-xs text-red-500 font-medium mt-0.5">{{ errors.shift_id }}</p>
          </div>

          <!-- Transaction Type -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Tipe Transaksi *</label>
            <div class="grid grid-cols-2 gap-2">
              <button 
                type="button"
                @click="form.type = 'out'"
                class="py-2 border rounded-xl font-bold text-xs transition-all cursor-pointer flex justify-center items-center gap-1.5"
                :class="form.type === 'out' ? 'bg-red-50 border-red-500 text-red-600 shadow-sm' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>
                Keluar (Cash Out)
              </button>
              <button 
                type="button"
                @click="form.type = 'in'"
                class="py-2 border rounded-xl font-bold text-xs transition-all cursor-pointer flex justify-center items-center gap-1.5"
                :class="form.type === 'in' ? 'bg-emerald-50 border-emerald-500 text-emerald-600 shadow-sm' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                Masuk (Cash In)
              </button>
            </div>
            <p v-if="errors.type" class="text-xs text-red-500 font-medium mt-0.5">{{ errors.type }}</p>
          </div>

          <!-- Payment Method / Account -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Sumber/Tujuan Dana *</label>
            <select 
              v-model="form.payment_method"
              class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors bg-white"
              :class="errors.payment_method ? 'border-red-400' : 'border-gray-200'"
            >
              <option value="cash">Cash / Kas Laci Toko (1101)</option>
              <option value="bank">Bank Mandiri Toko (1102)</option>
            </select>
            <p v-if="errors.payment_method" class="text-xs text-red-500 font-medium mt-0.5">{{ errors.payment_method }}</p>
          </div>

          <!-- Amount -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Nominal (Rupiah) *</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 text-sm">Rp</span>
              <input 
                v-model="form.amount"
                type="number"
                step="0.01" 
                placeholder="0.00" 
                class="w-full pl-9 pr-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors font-mono font-bold"
                :class="errors.amount ? 'border-red-400 focus:ring-red-400' : 'border-gray-200'"
              />
            </div>
            <p v-if="errors.amount" class="text-xs text-red-500 font-medium mt-0.5">{{ errors.amount }}</p>
          </div>

          <!-- Category -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Kategori *</label>
            <select 
              v-model="form.category"
              class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors bg-white"
              :class="errors.category ? 'border-red-400' : 'border-gray-200'"
            >
              <option v-for="cat in categories" :key="cat.value" :value="cat.value">
                {{ cat.label }}
              </option>
            </select>

            <!-- Custom Category Text input -->
            <input 
              v-if="form.category === 'lainnya'"
              v-model="form.custom_category"
              type="text"
              placeholder="Masukkan kategori kustom Anda..."
              class="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 mt-2"
              :class="errors.category ? 'border-red-400' : ''"
            />
            <p v-if="errors.category" class="text-xs text-red-500 font-medium mt-0.5">{{ errors.category }}</p>
          </div>

          <!-- Description -->
          <div class="space-y-1">
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Keterangan / Catatan</label>
            <textarea 
              v-model="form.description"
              rows="3"
              placeholder="Detail penggunaan atau penerimaan dana..." 
              class="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors resize-none text-xs"
            ></textarea>
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
              {{ submitting ? 'Menyimpan...' : (isEditMode ? 'Perbarui Transaksi' : 'Catat Transaksi') }}
            </button>
            <button 
              v-if="isEditMode || form.amount || form.description"
              type="button" 
              @click="resetForm"
              class="py-2 px-4 rounded-xl border border-gray-200 hover:bg-gray-50 text-gray-600 text-sm font-bold transition-all cursor-pointer"
            >
              Batal
            </button>
          </div>
        </form>
      </div>

      <!-- Right side: History Table -->
      <div class="lg:col-span-2 flex flex-col gap-6">
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden flex-1 flex flex-col justify-between">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-gray-50 border-b border-gray-100">
                  <th class="p-4 text-xs font-bold text-gray-500 uppercase tracking-wider w-24">ID</th>
                  <th class="p-4 text-xs font-bold text-gray-500 uppercase tracking-wider w-28">Tipe</th>
                  <th class="p-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Kategori / Keterangan</th>
                  <th class="p-4 text-xs font-bold text-gray-500 uppercase tracking-wider w-36">Toko & Cabang</th>
                  <th class="p-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right w-36">Nominal</th>
                  <th class="p-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-center w-28">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 text-sm">
                <!-- Loading -->
                <tr v-if="loading">
                  <td colspan="6" class="p-8 text-center text-gray-400 font-medium">
                    <div class="flex items-center justify-center gap-2">
                      <svg class="animate-spin h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      Memuat transaksi kas...
                    </div>
                  </td>
                </tr>

                <!-- Empty State -->
                <tr v-else-if="transactions.length === 0">
                  <td colspan="6" class="p-8 text-center text-gray-400 font-medium">
                    Tidak ada transaksi kas yang ditemukan.
                  </td>
                </tr>

                <!-- Rows -->
                <tr 
                  v-else 
                  v-for="tx in transactions" 
                  :key="tx.id"
                  class="hover:bg-gray-50 transition-colors"
                >
                  <td class="p-4 font-mono text-xs text-gray-700 font-bold">
                    #{{ tx.id }}
                  </td>
                  <td class="p-4">
                    <span 
                      class="px-2.5 py-1 text-[10px] font-bold rounded-full uppercase tracking-wider flex items-center justify-center gap-1 w-24"
                      :class="tx.type === 'in' ? 'bg-emerald-50 text-emerald-600 border border-emerald-100' : 'bg-red-50 text-red-600 border border-red-100'"
                    >
                      <span class="w-1.5 h-1.5 rounded-full" :class="tx.type === 'in' ? 'bg-emerald-500' : 'bg-red-500'"></span>
                      {{ tx.type === 'in' ? 'Masuk' : 'Keluar' }}
                    </span>
                  </td>
                  <td class="p-4">
                    <div class="font-medium text-gray-800 capitalize">{{ tx.category }}</div>
                    <div class="text-[11px] text-gray-400 mt-0.5">{{ tx.description || 'Tidak ada catatan' }}</div>
                    <!-- Payment method and Shift badge -->
                    <div class="flex items-center gap-1.5 mt-1.5">
                      <span class="px-1.5 py-0.2 bg-gray-100 text-gray-500 text-[9px] font-bold rounded border border-gray-200">
                        {{ tx.payment_method === 'cash' ? 'Cash (Laci)' : 'Bank' }}
                      </span>
                      <span v-if="tx.shift_id" class="px-1.5 py-0.2 bg-blue-50 text-blue-500 text-[9px] font-bold rounded border border-blue-100">
                        Shift #{{ tx.shift_id }}
                      </span>
                    </div>
                  </td>
                  <td class="p-4 text-xs text-gray-500">
                    <div class="font-semibold text-gray-700">{{ tx.store?.name || 'Store' }}</div>
                    <div class="text-[10px] mt-0.5">Oleh: {{ tx.creator?.name || 'Kasir' }}</div>
                    <div class="text-[9px] text-gray-400 mt-0.5">{{ formatDate(tx.created_at) }}</div>
                  </td>
                  <td class="p-4 text-right font-mono text-xs font-bold" :class="tx.type === 'in' ? 'text-emerald-600' : 'text-red-600'">
                    {{ tx.type === 'in' ? '+' : '-' }}{{ formatCurrency(tx.amount) }}
                  </td>
                  <td class="p-4 text-center">
                    <div class="flex items-center justify-center gap-2">
                      <!-- Edit -->
                      <button 
                        @click="handleEdit(tx)"
                        class="p-1 text-gray-500 hover:text-emerald-600 hover:bg-emerald-50 rounded transition-colors cursor-pointer"
                        title="Edit Transaksi"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                          <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                        </svg>
                      </button>
                      <!-- Delete -->
                      <button 
                        @click="handleDelete(tx)"
                        class="p-1 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded transition-colors cursor-pointer"
                        title="Hapus Transaksi"
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

          <!-- Pagination Area -->
          <div v-if="totalItems > 0" class="flex flex-col sm:flex-row justify-between items-center gap-4 p-4 border-t border-gray-100 bg-gray-50 text-xs text-gray-500">
            <div>
              Menampilkan <span class="font-bold text-gray-700">{{ (currentPage - 1) * perPage + 1 }}</span> sampai 
              <span class="font-bold text-gray-700">{{ Math.min(currentPage * perPage, totalItems) }}</span> dari 
              <span class="font-bold text-gray-700">{{ totalItems }}</span> transaksi
            </div>

            <div class="flex items-center gap-1">
              <button 
                @click="fetchTransactions(currentPage - 1)" 
                :disabled="currentPage === 1"
                class="p-2 border border-gray-200 rounded-lg hover:bg-white transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
              </button>

              <button 
                v-for="page in lastPage" 
                :key="page"
                @click="fetchTransactions(page)"
                class="px-3 py-1.5 border rounded-lg font-bold transition-all cursor-pointer"
                :class="currentPage === page ? 'bg-emerald-500 border-emerald-500 text-white shadow-sm' : 'border-gray-200 hover:bg-white text-gray-600'"
              >
                {{ page }}
              </button>

              <button 
                @click="fetchTransactions(currentPage + 1)" 
                :disabled="currentPage === lastPage"
                class="p-2 border border-gray-200 rounded-lg hover:bg-white transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
