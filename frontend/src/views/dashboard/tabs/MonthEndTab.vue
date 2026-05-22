<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()

const stores = ref([])
const closesData = ref({
  data: [],
  current_page: 1,
  last_page: 1,
  total: 0,
  per_page: 15
})
const preview = ref(null)
const loadingStores = ref(false)
const loadingPreview = ref(false)
const loadingCloses = ref(false)
const submitting = ref(false)
const deletingId = ref(null)

const searchQuery = ref('')
const historyStoreFilter = ref('')
const currentPage = ref(1)

const currentDate = new Date()
const defaultMonth = currentDate.getMonth() + 1
const defaultYear = currentDate.getFullYear()

const months = [
  { value: 1, name: 'Januari' },
  { value: 2, name: 'Februari' },
  { value: 3, name: 'Maret' },
  { value: 4, name: 'April' },
  { value: 5, name: 'Mei' },
  { value: 6, name: 'Juni' },
  { value: 7, name: 'Juli' },
  { value: 8, name: 'Agustus' },
  { value: 9, name: 'September' },
  { value: 10, name: 'Oktobers' },
  { value: 11, name: 'November' },
  { value: 12, name: 'Desember' }
]

// Generate years from current year down to 2020
const years = computed(() => {
  const currentY = new Date().getFullYear()
  const list = []
  for (let y = currentY; y >= 2020; y--) {
    list.push(y)
  }
  return list
})

const form = ref({
  store_id: '',
  month: defaultMonth,
  year: defaultYear,
  notes: ''
})

const errors = ref({
  store_id: '',
  month: '',
  year: '',
  notes: ''
})

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

const getMonthName = (monthNumber) => {
  const m = months.find(item => item.value === Number(monthNumber))
  return m ? m.name : monthNumber
}

const formatDateTime = (value) => {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'

  return parsed.toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const resetErrors = () => {
  errors.value = {
    store_id: '',
    month: '',
    year: '',
    notes: ''
  }
}

const applyServerErrors = (serverErrors) => {
  Object.keys(serverErrors || {}).forEach(key => {
    if (errors.value[key] !== undefined) {
      errors.value[key] = Array.isArray(serverErrors[key])
        ? serverErrors[key][0]
        : serverErrors[key]
    }
  })
}

const fetchStores = async () => {
  loadingStores.value = true
  try {
    const response = await api.get('/stores')
    const data = response.data?.data || response.data || []
    stores.value = data.filter(store => store.is_active === true || store.is_active === 1)
    if (!form.value.store_id && stores.value.length > 0) {
      form.value.store_id = stores.value[0].id
    }
  } catch (error) {
    console.error('Error fetching stores:', error)
    toast.error(error.response?.data?.message || 'Gagal memuat daftar store.')
  } finally {
    loadingStores.value = false
  }
}

const fetchCloses = async (page = 1) => {
  loadingCloses.value = true
  currentPage.value = page
  try {
    const response = await api.get('/month-ends', {
      params: {
        page: page,
        store_id: historyStoreFilter.value || undefined,
        search: searchQuery.value || undefined
      }
    })
    
    const resData = response.data?.data || response.data
    if (resData && Array.isArray(resData.data)) {
      closesData.value = resData
    } else if (Array.isArray(resData)) {
      closesData.value = {
        data: resData,
        current_page: 1,
        last_page: 1,
        total: resData.length,
        per_page: 15
      }
    }
  } catch (error) {
    console.error('Error fetching EOM closes:', error)
    toast.error(error.response?.data?.message || 'Gagal memuat riwayat Month End.')
  } finally {
    loadingCloses.value = false
  }
}

const validateForm = () => {
  resetErrors()
  let valid = true

  if (!form.value.store_id) {
    errors.value.store_id = 'Store wajib dipilih.'
    valid = false
  }

  if (!form.value.month) {
    errors.value.month = 'Bulan wajib dipilih.'
    valid = false
  }

  if (!form.value.year) {
    errors.value.year = 'Tahun wajib dipilih.'
    valid = false
  }

  if (form.value.notes && form.value.notes.length > 1000) {
    errors.value.notes = 'Catatan tidak boleh lebih dari 1000 karakter.'
    valid = false
  }

  return valid
}

const fetchPreview = async () => {
  if (!validateForm()) {
    preview.value = null
    return
  }

  loadingPreview.value = true
  try {
    const response = await api.get('/month-ends/preview', {
      params: {
        store_id: form.value.store_id,
        month: form.value.month,
        year: form.value.year
      }
    })
    preview.value = response.data?.data || null
  } catch (error) {
    console.error('Error previewing month end:', error)
    preview.value = null
    if (error.response?.status === 422) {
      applyServerErrors(error.response.data?.errors || {})
    }
    toast.error(error.response?.data?.message || 'Gagal membuat preview Month End.')
  } finally {
    loadingPreview.value = false
  }
}

const submitMonthEnd = async () => {
  if (!validateForm()) {
    toast.warning('Perbaiki error pada form terlebih dahulu.')
    return
  }

  if (preview.value && !preview.value.can_close) {
    let warnMsg = 'Tidak dapat memproses Month End.'
    if (preview.value.is_future) {
      warnMsg = 'Periode EOM di masa mendatang tidak dapat ditutup.'
      errors.value.month = warnMsg
    } else if (preview.value.already_closed) {
      warnMsg = 'Periode EOM ini sudah pernah ditutup untuk store ini.'
      errors.value.month = warnMsg
    } else if (preview.value.open_shift_count > 0) {
      warnMsg = `Terdapat ${preview.value.open_shift_count} shift kasir yang masih terbuka.`
      errors.value.month = warnMsg
    }
    toast.warning(warnMsg)
    return
  }

  submitting.value = true
  try {
    const payload = {
      store_id: Number(form.value.store_id),
      month: Number(form.value.month),
      year: Number(form.value.year),
      notes: form.value.notes || `Month End Closing ${getMonthName(form.value.month)} ${form.value.year}`
    }

    await api.post('/month-ends', payload)
    toast.success('Month End closing berhasil disubmit!')
    form.value.notes = ''
    await Promise.all([fetchCloses(1), fetchPreview()])
  } catch (error) {
    console.error('Error submitting month end:', error)
    if (error.response?.status === 422) {
      applyServerErrors(error.response.data?.errors || {})
      toast.error(error.response.data?.message || 'Validasi EOM gagal.')
    } else {
      toast.error(error.response?.data?.message || 'Gagal memproses Month End.')
    }
  } finally {
    submitting.value = false
  }
}

const deleteMonthEnd = async (close) => {
  if (!confirm(`Apakah Anda yakin ingin menghapus EOM Closing untuk ${getMonthName(close.month)} ${close.year}? Tindakan ini akan menghapus data audit snapshot.`)) {
    return
  }

  deletingId.value = close.id
  try {
    await api.delete(`/month-ends/${close.id}`)
    toast.success('Month End snapshot berhasil dihapus.')
    await Promise.all([fetchCloses(currentPage.value), fetchPreview()])
  } catch (error) {
    console.error('Error deleting month end:', error)
    toast.error(error.response?.data?.message || 'Gagal menghapus Month End.')
  } finally {
    deletingId.value = null
  }
}

const selectedStore = computed(() => {
  return stores.value.find(store => store.id === Number(form.value.store_id)) || null
})

// Trigger preview updates on configuration changes
watch(
  () => [form.value.store_id, form.value.month, form.value.year],
  () => {
    if (form.value.store_id && form.value.month && form.value.year) {
      fetchPreview()
    }
  }
)

// Trigger history filters
watch(
  () => [historyStoreFilter.value, searchQuery.value],
  () => {
    fetchCloses(1)
  }
)

onMounted(async () => {
  await fetchStores()
  await Promise.all([fetchCloses(1), fetchPreview()])
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header Card -->
    <div class="bg-white border border-slate-100 rounded-lg p-5 shadow-sm flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
      <div class="flex items-center gap-3">
        <span class="w-11 h-11 rounded-lg bg-indigo-900 text-white flex items-center justify-center shadow-sm">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-6 h-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" />
          </svg>
        </span>
        <div>
          <h1 class="text-xl font-bold text-slate-800">Month End</h1>
          <p class="text-sm text-slate-500">Penutupan buku bulanan per outlet, validasi transaksi, laba kotor, dan data audit snapshot.</p>
        </div>
      </div>

      <div v-if="selectedStore" class="rounded-lg bg-indigo-50/50 border border-indigo-100/50 px-4 py-3">
        <p class="text-[11px] uppercase font-bold text-indigo-500">Store Aktif</p>
        <p class="text-sm font-black text-indigo-900">{{ selectedStore.name }}</p>
      </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
      
      <!-- Left Column: Form EOM -->
      <section class="xl:col-span-4 bg-white border border-slate-100 rounded-lg p-5 shadow-sm space-y-5">
        <div class="border-b border-slate-100 pb-4">
          <h2 class="text-base font-bold text-slate-800">Closing Bulanan</h2>
          <p class="text-xs text-slate-400 mt-1">Closing bulanan mengunci data penjualan, COGS, dan pembelian pada periode bulan bersangkutan.</p>
        </div>

        <form @submit.prevent="submitMonthEnd" class="space-y-4">
          
          <!-- Store Selector -->
          <div class="space-y-1">
            <label for="store_id" class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Store *</label>
            <select
              id="store_id"
              v-model="form.store_id"
              :disabled="loadingStores"
              class="w-full bg-white border rounded-lg px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-slate-500/20"
              :class="errors.store_id ? 'border-rose-500' : 'border-slate-200 focus:border-slate-500'"
            >
              <option value="">{{ loadingStores ? 'Memuat store...' : 'Pilih store' }}</option>
              <option v-for="store in stores" :key="store.id" :value="store.id">
                {{ store.name }}
              </option>
            </select>
            <p v-if="errors.store_id" class="text-xs text-rose-600 font-medium">{{ errors.store_id }}</p>
          </div>

          <!-- Month & Year Selectors -->
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label for="month" class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Bulan *</label>
              <select
                id="month"
                v-model="form.month"
                class="w-full bg-white border rounded-lg px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-slate-500/20 border-slate-200 focus:border-slate-500"
                :class="errors.month ? 'border-rose-500' : ''"
              >
                <option v-for="m in months" :key="m.value" :value="m.value">
                  {{ m.name }}
                </option>
              </select>
              <p v-if="errors.month" class="text-xs text-rose-600 font-medium">{{ errors.month }}</p>
            </div>

            <div class="space-y-1">
              <label for="year" class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Tahun *</label>
              <select
                id="year"
                v-model="form.year"
                class="w-full bg-white border rounded-lg px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-slate-500/20 border-slate-200 focus:border-slate-500"
                :class="errors.year ? 'border-rose-500' : ''"
              >
                <option v-for="y in years" :key="y" :value="y">
                  {{ y }}
                </option>
              </select>
              <p v-if="errors.year" class="text-xs text-rose-600 font-medium">{{ errors.year }}</p>
            </div>
          </div>

          <!-- Notes Area -->
          <div class="space-y-1">
            <label for="notes" class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Catatan</label>
            <textarea
              id="notes"
              v-model="form.notes"
              rows="4"
              placeholder="Catatan closing bulanan..."
              class="w-full bg-white border rounded-lg px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-slate-500/20 resize-none border-slate-200 focus:border-slate-500"
              :class="errors.notes ? 'border-rose-500' : ''"
            ></textarea>
            <p v-if="errors.notes" class="text-xs text-rose-600 font-medium">{{ errors.notes }}</p>
          </div>

          <!-- Buttons -->
          <div class="flex gap-2">
            <button
              type="button"
              @click="fetchPreview"
              :disabled="loadingPreview"
              class="flex-1 inline-flex items-center justify-center gap-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-bold py-3 transition-colors disabled:opacity-60"
            >
              <span v-if="loadingPreview" class="w-4 h-4 border-2 border-slate-700 border-t-transparent rounded-full animate-spin"></span>
              Preview
            </button>
            <button
              type="submit"
              :disabled="submitting || loadingPreview || (preview && !preview.can_close)"
              class="flex-1 inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-950 hover:bg-indigo-900 text-white text-sm font-bold py-3 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span v-if="submitting" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              Submit EOM
            </button>
          </div>
        </form>
      </section>

      <!-- Right Column: EOM Preview -->
      <section class="xl:col-span-8 bg-white border border-slate-100 rounded-lg p-5 shadow-sm space-y-5">
        <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
          <div>
            <h2 class="text-base font-bold text-slate-800">Preview Ringkasan Audit Bulanan</h2>
            <p class="text-xs text-slate-400 mt-1">Data kalkulasi real-time disaring berdasarkan store dan periode bulan yang dipilih.</p>
          </div>
          <span
            v-if="preview"
            class="px-3 py-1 rounded text-xs font-bold whitespace-nowrap"
            :class="preview.can_close ? 'bg-teal-50 text-teal-700 border border-teal-100' : 'bg-rose-50 text-rose-700 border border-rose-100'"
          >
            {{ preview.can_close ? 'READY TO CLOSE' : 'BLOCKED' }}
          </span>
        </div>

        <!-- Loading EOM Preview -->
        <div v-if="loadingPreview" class="py-20 text-center text-slate-400">
          <span class="inline-block w-9 h-9 border-4 border-indigo-900 border-t-transparent rounded-full animate-spin mb-2"></span>
          <p>Mengkalkulasi preview Month End...</p>
        </div>

        <!-- Blank EOM Preview -->
        <div v-else-if="!preview" class="py-20 text-center text-slate-400 border border-dashed border-slate-200 rounded-lg">
          Pilih store, bulan, dan tahun untuk memuat data audit bulanan.
        </div>

        <!-- Active EOM Preview Details -->
        <div v-else class="space-y-5">
          
          <!-- Block Warnings -->
          <div
            v-if="preview.already_closed || preview.is_future || preview.open_shift_count > 0"
            class="rounded-lg border p-4 text-sm"
            :class="preview.is_future ? 'border-amber-200 bg-amber-50 text-amber-800' : (preview.already_closed ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-rose-200 bg-rose-50 text-rose-800')"
          >
            <p class="font-bold">
              <span v-if="preview.is_future">Periode Belum Berlangsung</span>
              <span v-else-if="preview.already_closed">Periode Sudah Ditutup</span>
              <span v-else>Ada Shift Kasir yang Belum Ditutup</span>
            </p>
            <p class="mt-1">
              <span v-if="preview.is_future">Tidak bisa menutup periode bulan di masa mendatang relative terhadap waktu server.</span>
              <span v-else-if="preview.already_closed">Month End untuk store {{ preview.store?.name }} pada periode {{ getMonthName(preview.month) }} {{ preview.year }} telah selesai dikunci.</span>
              <span v-else>Ditemukan {{ preview.open_shift_count }} shift kasir aktif yang belum difinalisasi (close) di outlet ini pada periode tersebut. Tutup semua shift kasir terlebih dahulu.</span>
            </p>
          </div>

          <!-- Stats Grid -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            
            <!-- Total Sales Card -->
            <div class="rounded-lg border border-teal-100 bg-teal-50/50 p-4">
              <p class="text-[11px] uppercase font-bold text-teal-600">Total Sales (Penjualan)</p>
              <p class="text-2xl font-black text-teal-800 mt-1">{{ formatCurrency(preview.totals.total_sales) }}</p>
              <p class="text-xs text-teal-700/80 mt-1">Nilai total dari seluruh transaksi kasir yang selesai (completed).</p>
            </div>

            <!-- Total COGS Card -->
            <div class="rounded-lg border border-slate-200 bg-slate-50/70 p-4">
              <p class="text-[11px] uppercase font-bold text-slate-500">Cost of Goods Sold (HPP)</p>
              <p class="text-2xl font-black text-slate-800 mt-1">{{ formatCurrency(preview.totals.total_cost_of_goods_sold) }}</p>
              <p class="text-xs text-slate-500/80 mt-1">Harga pokok pembelian produk dari transaksi penjualan yang terjadi.</p>
            </div>

            <!-- Total Purchases Card -->
            <div class="rounded-lg border border-amber-100 bg-amber-50/50 p-4">
              <p class="text-[11px] uppercase font-bold text-amber-600">Total Purchases (Pembelian)</p>
              <p class="text-2xl font-black text-amber-800 mt-1">{{ formatCurrency(preview.totals.total_purchases) }}</p>
              <p class="text-xs text-amber-700/80 mt-1">Total pembelanjaan / stok masuk supplier yang berstatus diterima (received).</p>
            </div>

            <!-- Gross Profit Card -->
            <div
              class="rounded-lg border p-4"
              :class="Number(preview.totals.gross_profit) >= 0 ? 'border-emerald-100 bg-emerald-50/50 text-emerald-800' : 'border-rose-100 bg-rose-50/50 text-rose-800'"
            >
              <p class="text-[11px] uppercase font-bold" :class="Number(preview.totals.gross_profit) >= 0 ? 'text-emerald-600' : 'text-rose-600'">Gross Profit (Laba Kotor)</p>
              <p class="text-2xl font-black mt-1">{{ formatCurrency(preview.totals.gross_profit) }}</p>
              <p class="text-xs mt-1" :class="Number(preview.totals.gross_profit) >= 0 ? 'text-emerald-700/80' : 'text-rose-700/80'">
                {{ Number(preview.totals.gross_profit) >= 0 ? 'Surplus estimasi keuntungan kotor (Sales - COGS).' : 'Defisit kerugian kotor pada penjualan bulan ini.' }}
              </p>
            </div>

          </div>

          <!-- Alert Note -->
          <div class="rounded-lg bg-slate-50 border border-slate-100 p-4 text-xs text-slate-500 flex items-start gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-slate-400 mt-0.5 shrink-0">
              <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 1 1 1.063 1.063L12 13.504L10.646 12.29a.75.75 0 1 1 1.063-1.063l.041.02Zm0 0V9m0-2.25h.008v.008H11.25V6.75Z" />
            </svg>
            <p><strong>Perhatian:</strong> Snapshot closing Month End tidak memposting penyesuaian jurnal langsung, melainkan menangkap status audit pelaporan bulanan. Data histori masa lalu dapat dihapus/di-recreate jika diperlukan penyesuaian.</p>
          </div>

        </div>
      </section>
    </div>

    <!-- History Audit Section -->
    <section class="bg-white border border-slate-100 rounded-lg p-5 shadow-sm space-y-4">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 border-b border-slate-100 pb-4">
        <div>
          <h2 class="text-base font-bold text-slate-800">Riwayat Month End Closing</h2>
          <p class="text-xs text-slate-400 mt-1 font-medium">Log historis pencatatan closing bulanan dengan detail audit keuangan.</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-2">
          <!-- Store Filter -->
          <select
            v-model="historyStoreFilter"
            class="border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-slate-500 bg-white"
          >
            <option value="">Semua Store</option>
            <option v-for="store in stores" :key="store.id" :value="store.id">
              {{ store.name }}
            </option>
          </select>
          <!-- Search Box -->
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari catatan, bulan, tahun..."
            class="border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20"
          />
          <!-- Refresh button -->
          <button
            type="button"
            @click="fetchCloses(1)"
            :disabled="loadingCloses"
            class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold disabled:opacity-60"
          >
            <span v-if="loadingCloses" class="w-4 h-4 border-2 border-slate-700 border-t-transparent rounded-full animate-spin"></span>
            Refresh
          </button>
        </div>
      </div>

      <!-- History Table -->
      <div class="overflow-x-auto rounded-lg border border-slate-100">
        <table class="w-full border-collapse text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
              <th class="px-4 py-3">Periode</th>
              <th class="px-4 py-3">Store</th>
              <th class="px-4 py-3 text-right">Sales</th>
              <th class="px-4 py-3 text-right">COGS (HPP)</th>
              <th class="px-4 py-3 text-right">Purchases</th>
              <th class="px-4 py-3 text-right">Gross Profit</th>
              <th class="px-4 py-3">Closed At</th>
              <th class="px-4 py-3">Closed By</th>
              <th class="px-4 py-3">Catatan</th>
              <th class="px-4 py-3 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-if="loadingCloses">
              <td colspan="10" class="px-4 py-12 text-center text-slate-400">
                <span class="inline-block w-8 h-8 border-4 border-indigo-900 border-t-transparent rounded-full animate-spin mb-2"></span>
                <p>Memuat riwayat Month End...</p>
              </td>
            </tr>
            <tr v-else-if="closesData.data.length === 0">
              <td colspan="10" class="px-4 py-12 text-center text-slate-400">Belum ada catatan Month End closing untuk outlet ini.</td>
            </tr>
            <tr v-else v-for="close in closesData.data" :key="close.id" class="hover:bg-slate-50/70">
              <td class="px-4 py-3 font-bold text-slate-800 whitespace-nowrap">
                {{ getMonthName(close.month) }} {{ close.year }}
              </td>
              <td class="px-4 py-3 text-slate-700 whitespace-nowrap">{{ close.store?.name || '-' }}</td>
              <td class="px-4 py-3 text-right font-bold text-teal-700 whitespace-nowrap">{{ formatCurrency(close.total_sales) }}</td>
              <td class="px-4 py-3 text-right text-slate-700 whitespace-nowrap">{{ formatCurrency(close.total_cost_of_goods_sold) }}</td>
              <td class="px-4 py-3 text-right font-semibold text-amber-700 whitespace-nowrap">{{ formatCurrency(close.total_purchases) }}</td>
              <td class="px-4 py-3 text-right font-bold whitespace-nowrap" :class="Number(close.gross_profit) >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                {{ formatCurrency(close.gross_profit) }}
              </td>
              <td class="px-4 py-3 text-slate-500 whitespace-nowrap text-xs">{{ formatDateTime(close.closed_at) }}</td>
              <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ close.closed_by?.name || '-' }}</td>
              <td class="px-4 py-3 text-slate-500 max-w-xs truncate" :title="close.notes">{{ close.notes || '-' }}</td>
              <td class="px-4 py-3 text-center whitespace-nowrap">
                <button
                  type="button"
                  @click="deleteMonthEnd(close)"
                  :disabled="deletingId === close.id"
                  class="px-2 py-1.5 rounded bg-rose-50 hover:bg-rose-100 text-xs font-bold text-rose-700 transition-colors disabled:opacity-60 flex items-center gap-1 mx-auto"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-3.5 h-3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                  </svg>
                  Hapus
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div v-if="closesData.last_page > 1" class="flex items-center justify-between border-t border-slate-100 pt-4 text-sm text-slate-500">
        <p>Menampilkan halaman {{ closesData.current_page }} dari {{ closesData.last_page }} (Total {{ closesData.total }} data)</p>
        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="fetchCloses(closesData.current_page - 1)"
            :disabled="closesData.current_page === 1"
            class="px-3 py-1.5 border border-slate-200 rounded-md hover:bg-slate-50 text-slate-600 disabled:opacity-50 disabled:hover:bg-transparent"
          >
            Sebelumnya
          </button>
          <button
            type="button"
            @click="fetchCloses(closesData.current_page + 1)"
            :disabled="closesData.current_page === closesData.last_page"
            class="px-3 py-1.5 border border-slate-200 rounded-md hover:bg-slate-50 text-slate-600 disabled:opacity-50 disabled:hover:bg-transparent"
          >
            Selanjutnya
          </button>
        </div>
      </div>
    </section>
  </div>
</template>
