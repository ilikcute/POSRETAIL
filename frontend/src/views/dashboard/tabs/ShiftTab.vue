<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'
import { useAuth } from '../../../store/auth'

const toast = useToast()
const { currentUser } = useAuth()

// States
const shifts = ref([])
const stations = ref([])
const activeShift = ref(null)
const liveSummary = ref(null)
const loading = ref(false)
const submitting = ref(false)
const searchQuery = ref('')

// Modal Detail State
const selectedShiftDetail = ref(null)
const showDetailModal = ref(false)

// Open Shift Form State
const openForm = ref({
  station_id: '',
  starting_cash: 0,
  notes: ''
})

const openErrors = ref({
  station_id: '',
  starting_cash: '',
  notes: ''
})

// Close Shift Form State
const closeForm = ref({
  actual_cash: 0,
  actual_qris: 0,
  actual_card: 0,
  notes: ''
})

const closeErrors = ref({
  actual_cash: '',
  actual_qris: '',
  actual_card: '',
  notes: ''
})

// Timer State
const elapsedTime = ref('00:00:00')
let timerInterval = null

// Fetch stations and shifts
const initData = async () => {
  loading.value = true
  try {
    // 1. Fetch active stations
    const stationRes = await api.get('/stations')
    const stationData = stationRes.data?.data || stationRes.data || []
    stations.value = stationData.filter(s => s.is_active === 1 || s.is_active === true)

    // 2. Fetch all shifts
    await fetchShifts()
  } catch (error) {
    console.error('Error initializing data:', error)
    toast.error('Gagal memuat data awal stasiun kasir.')
  } finally {
    loading.value = false
  }
}

const fetchShifts = async () => {
  try {
    const shiftRes = await api.get('/shifts')
    const shiftList = shiftRes.data?.data || shiftRes.data || []
    shifts.value = shiftList

    // Find if there is an active open shift
    const openShift = shiftList.find(s => s.status === 'open')
    if (openShift) {
      activeShift.value = openShift
      await fetchActiveShiftDetails(openShift.id)
    } else {
      activeShift.value = null
      liveSummary.value = null
      stopTimer()
    }
  } catch (error) {
    console.error('Error fetching shifts:', error)
    toast.error('Gagal memperbarui daftar shift.')
  }
}

const fetchActiveShiftDetails = async (shiftId) => {
  try {
    // Load shift details (with count of sales, etc.)
    const detailRes = await api.get(`/shifts/${shiftId}`)
    const detailedShift = detailRes.data?.data || detailRes.data
    activeShift.value = detailedShift

    // Load remittance expected balances
    const summaryRes = await api.get(`/remittance/summary/${shiftId}`)
    liveSummary.value = summaryRes.data?.data || summaryRes.data

    // Start Live Timer
    startTimer(detailedShift.start_time)
  } catch (error) {
    console.error('Error fetching active shift details:', error)
  }
}

// Timer Logic
const startTimer = (startTimeString) => {
  stopTimer()
  if (!startTimeString) return

  const startTime = new Date(startTimeString).getTime()
  
  const updateTimer = () => {
    const now = new Date().getTime()
    const diff = now - startTime
    
    if (diff < 0) {
      elapsedTime.value = '00:00:00'
      return
    }

    const hours = Math.floor(diff / (1000 * 60 * 60))
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))
    const seconds = Math.floor((diff % (1000 * 60)) / 1000)

    elapsedTime.value = [
      String(hours).padStart(2, '0'),
      String(minutes).padStart(2, '0'),
      String(seconds).padStart(2, '0')
    ].join(':')
  }

  updateTimer()
  timerInterval = setInterval(updateTimer, 1000)
}

const stopTimer = () => {
  if (timerInterval) {
    clearInterval(timerInterval)
    timerInterval = null
  }
  elapsedTime.value = '00:00:00'
}

// Client-side Validations
const validateOpenShift = () => {
  let isValid = true
  openErrors.value = { station_id: '', starting_cash: '', notes: '' }

  if (!openForm.value.station_id) {
    openErrors.value.station_id = 'Stasiun kasir wajib dipilih.'
    isValid = false
  }

  if (openForm.value.starting_cash === '' || openForm.value.starting_cash === null || openForm.value.starting_cash === undefined) {
    openErrors.value.starting_cash = 'Modal awal wajib diisi.'
    isValid = false
  } else if (Number(openForm.value.starting_cash) < 0) {
    openErrors.value.starting_cash = 'Modal awal tidak boleh kurang dari 0.'
    isValid = false
  }

  return isValid
}

const validateCloseShift = () => {
  let isValid = true
  closeErrors.value = { actual_cash: '', actual_qris: '', actual_card: '', notes: '' }

  if (closeForm.value.actual_cash === '' || closeForm.value.actual_cash === null || closeForm.value.actual_cash === undefined) {
    closeErrors.value.actual_cash = 'Uang tunai laci wajib diisi.'
    isValid = false
  } else if (Number(closeForm.value.actual_cash) < 0) {
    closeErrors.value.actual_cash = 'Jumlah uang laci tidak boleh kurang dari 0.'
    isValid = false
  }

  if (closeForm.value.actual_qris === '' || closeForm.value.actual_qris === null || closeForm.value.actual_qris === undefined) {
    closeErrors.value.actual_qris = 'Total nominal QRIS wajib diisi.'
    isValid = false
  } else if (Number(closeForm.value.actual_qris) < 0) {
    closeErrors.value.actual_qris = 'Nominal QRIS tidak boleh kurang dari 0.'
    isValid = false
  }

  if (closeForm.value.actual_card === '' || closeForm.value.actual_card === null || closeForm.value.actual_card === undefined) {
    closeErrors.value.actual_card = 'Total nominal Kartu wajib diisi.'
    isValid = false
  } else if (Number(closeForm.value.actual_card) < 0) {
    closeErrors.value.actual_card = 'Nominal Kartu tidak boleh kurang dari 0.'
    isValid = false
  }

  return isValid
}

// Action Handlers
const handleOpenShift = async () => {
  if (!validateOpenShift()) return
  submitting.value = true

  const payload = {
    station_id: openForm.value.station_id,
    starting_cash: Number(openForm.value.starting_cash),
    notes: openForm.value.notes?.trim() || null
  }

  try {
    const res = await api.post('/shifts', payload)
    toast.success('Shift kasir berhasil dibuka! Selamat bertugas.')
    
    // Reset Form
    openForm.value = { station_id: '', starting_cash: 0, notes: '' }
    openErrors.value = { station_id: '', starting_cash: '', notes: '' }
    
    await fetchShifts()
  } catch (error) {
    console.error('Error opening shift:', error)
    if (error.response?.status === 422) {
      const serverErrors = error.response.data.errors || {}
      Object.keys(serverErrors).forEach(key => {
        if (openErrors.value[key] !== undefined) {
          openErrors.value[key] = serverErrors[key][0]
        }
      })
      toast.error('Harap koreksi data form pembukaan shift.')
    } else {
      toast.error(error.response?.data?.message || 'Gagal membuka shift baru.')
    }
  } finally {
    submitting.value = false
  }
}

const handleCloseShift = async () => {
  if (!validateCloseShift()) return
  if (!confirm('Apakah Anda yakin ingin melakukan rekonsiliasi kas dan menutup shift ini?')) return
  submitting.value = true

  const payload = {
    actual_cash: Number(closeForm.value.actual_cash),
    actual_qris: Number(closeForm.value.actual_qris),
    actual_card: Number(closeForm.value.actual_card),
    notes: closeForm.value.notes?.trim() || null
  }

  try {
    await api.put(`/shifts/${activeShift.value.id}`, payload)
    toast.success('Shift kasir berhasil ditutup & direkonsiliasi. Terima kasih!')
    
    // Reset close form
    closeForm.value = { actual_cash: 0, actual_qris: 0, actual_card: 0, notes: '' }
    closeErrors.value = { actual_cash: '', actual_qris: '', actual_card: '', notes: '' }
    
    await fetchShifts()
  } catch (error) {
    console.error('Error closing shift:', error)
    if (error.response?.status === 422) {
      const serverErrors = error.response.data.errors || {}
      Object.keys(serverErrors).forEach(key => {
        if (closeErrors.value[key] !== undefined) {
          closeErrors.value[key] = serverErrors[key][0]
        }
      })
      toast.error('Harap koreksi data form penutupan shift.')
    } else {
      toast.error(error.response?.data?.message || 'Gagal menutup shift.')
    }
  } finally {
    submitting.value = false
  }
}

// Open detail modal for closed shift
const viewShiftDetails = async (shift) => {
  try {
    loading.value = true
    const res = await api.get(`/shifts/${shift.id}`)
    const detailedShift = res.data?.data || res.data

    let summaryData = null
    try {
      const summaryRes = await api.get(`/remittance/summary/${shift.id}`)
      summaryData = summaryRes.data?.data || summaryRes.data
    } catch (e) {
      console.warn('Remittance summary not fully found/archived', e)
    }

    selectedShiftDetail.value = {
      ...detailedShift,
      summary: summaryData
    }
    showDetailModal.value = true
  } catch (error) {
    console.error('Error loading shift details:', error)
    toast.error('Gagal memuat detail shift.')
  } finally {
    loading.value = false
  }
}

// Helpers / Formatters
const formatCurrency = (value) => {
  if (value === null || value === undefined || value === '') return 'Rp 0'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(value)
}

const formatDateTime = (value) => {
  if (!value) return '-'
  return new Date(value).toLocaleString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const getDuration = (start, end) => {
  if (!start) return '-'
  const startTime = new Date(start).getTime()
  const endTime = end ? new Date(end).getTime() : new Date().getTime()
  const diff = endTime - startTime
  
  if (diff < 0) return '0 menit'

  const hours = Math.floor(diff / (1000 * 60 * 60))
  const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))

  if (hours > 0) {
    return `${hours} jam ${minutes} menit`
  }
  return `${minutes} menit`
}

// Computed States for Active Shift Stats
const activeSalesList = computed(() => {
  return activeShift.value?.sales || []
})

const completedSales = computed(() => {
  return activeSalesList.value.filter(s => s.status === 'completed')
})

const totalTransactionsCount = computed(() => {
  return completedSales.value.length
})

const totalRevenue = computed(() => {
  // Use either expectation balances or sum of sales
  if (liveSummary.value?.expected_balances) {
    const b = liveSummary.value.expected_balances
    return b.cash_sales + b.qris_sales + b.card_sales
  }
  return completedSales.value.reduce((acc, s) => acc + Number(s.grand_total), 0)
})

const uniqueCustomersCount = computed(() => {
  const customerIds = completedSales.value.map(s => s.customer_id).filter(Boolean)
  return new Set(customerIds).size
})

const totalPromotions = computed(() => {
  const promoSales = completedSales.value.filter(s => s.promotion_id || Number(s.discount_amount) > 0)
  const promoDiscountValue = completedSales.value.reduce((acc, s) => acc + Number(s.discount_amount), 0)
  return {
    count: promoSales.length,
    value: promoDiscountValue
  }
})

// Search/Filter for History Directory
const filteredShifts = computed(() => {
  if (!searchQuery.value) return shifts.value
  const query = searchQuery.value.toLowerCase().trim()
  return shifts.value.filter(s => 
    s.station?.name?.toLowerCase().includes(query) ||
    s.user?.name?.toLowerCase().includes(query) ||
    (s.notes && s.notes.toLowerCase().includes(query)) ||
    s.status.toLowerCase().includes(query)
  )
})

// Pagination
const currentPage = ref(1)
const pageSize = ref(10)

watch(searchQuery, () => {
  currentPage.value = 1
})

const paginatedShifts = computed(() => {
  const startIndex = (currentPage.value - 1) * pageSize.value
  const endIndex = startIndex + pageSize.value
  return filteredShifts.value.slice(startIndex, endIndex)
})

const totalPages = computed(() => {
  return Math.ceil(filteredShifts.value.length / pageSize.value) || 1
})

const startRange = computed(() => {
  if (filteredShifts.value.length === 0) return 0
  return (currentPage.value - 1) * pageSize.value + 1
})

const endRange = computed(() => {
  return Math.min(currentPage.value * pageSize.value, filteredShifts.value.length)
})

const goToPage = (page) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

onMounted(() => {
  initData()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
      <div>
        <h2 class="text-xl font-bold text-gray-800">Shift Management</h2>
        <p class="text-xs text-gray-500">Buka, pantau aktivitas laci kasir secara langsung, lakukan rekonsiliasi, dan tutup shift penjualan Anda.</p>
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
          placeholder="Cari riwayat shift..." 
          class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
        />
      </div>
    </div>

    <!-- Main Content Layout (Grid Split) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      
      <!-- Left Column: Shift Lifecycle Form -->
      <div class="lg:col-span-1 space-y-6">
        
        <!-- CARD 1: OPEN SHIFT (If no active shift) -->
        <div v-if="!activeShift" class="bg-white border border-gray-100 p-6 rounded-2xl shadow-sm">
          <div class="flex items-center gap-2 mb-5">
            <span class="p-2 rounded-xl bg-emerald-50 text-emerald-600 shadow-sm">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
              </svg>
            </span>
            <div>
              <h3 class="text-md font-bold text-gray-800">Mulai Shift Baru</h3>
              <p class="text-[10px] text-gray-400">Buka laci register untuk mulai melayani transaksi penjualan.</p>
            </div>
          </div>

          <form @submit.prevent="handleOpenShift" class="space-y-4">
            <!-- Station ID selector -->
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Stasiun Kasir *</label>
              <select 
                v-model="openForm.station_id"
                class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors bg-white cursor-pointer"
                :class="openErrors.station_id ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
              >
                <option value="" disabled>-- Pilih Stasiun POS --</option>
                <option v-for="station in stations" :key="station.id" :value="station.id">
                  {{ station.name }} {{ station.location ? `(${station.location})` : '' }}
                </option>
              </select>
              <p v-if="openErrors.station_id" class="text-xs text-red-500 font-medium mt-1">{{ openErrors.station_id }}</p>
            </div>

            <!-- Starting Cash field -->
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Modal Uang Laci (Starting Cash) *</label>
              <div class="relative rounded-xl shadow-sm">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-sm font-bold text-gray-400">Rp</span>
                <input 
                  v-model.number="openForm.starting_cash"
                  type="number" 
                  step="any"
                  min="0"
                  placeholder="0"
                  class="w-full pl-10 pr-4 py-2 border rounded-xl text-sm font-semibold focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors"
                  :class="openErrors.starting_cash ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
                />
              </div>
              <p v-if="openErrors.starting_cash" class="text-xs text-red-500 font-medium mt-1">{{ openErrors.starting_cash }}</p>
            </div>

            <!-- Notes field -->
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Catatan Awal</label>
              <textarea 
                v-model="openForm.notes"
                rows="3"
                placeholder="Catatan kondisi awal laci kasir (opsional)..."
                class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-colors resize-none"
                :class="openErrors.notes ? 'border-red-400 focus:border-red-400' : 'border-gray-200'"
              ></textarea>
              <p v-if="openErrors.notes" class="text-xs text-red-500 font-medium mt-1">{{ openErrors.notes }}</p>
            </div>

            <!-- Submit Button -->
            <button 
              type="submit" 
              :disabled="submitting"
              class="w-full py-2.5 px-4 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-bold flex items-center justify-center gap-2 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed shadow-md hover:shadow-lg active:scale-98"
            >
              <svg v-if="submitting" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>Mulai Shift Kasir</span>
            </button>
          </form>
        </div>

        <!-- CARD 2: RECONCILE & CLOSE SHIFT (If active shift open) -->
        <div v-else class="bg-white border border-gray-100 p-6 rounded-2xl shadow-sm">
          <div class="flex items-center gap-2 mb-5">
            <span class="p-2 rounded-xl bg-amber-50 text-amber-600 shadow-sm animate-pulse">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
              </svg>
            </span>
            <div>
              <h3 class="text-md font-bold text-gray-800">Tutup Shift & Rekonsiliasi</h3>
              <p class="text-[10px] text-gray-400">Verifikasi uang fisik di laci dan tutup shift kasir Anda.</p>
            </div>
          </div>

          <form @submit.prevent="handleCloseShift" class="space-y-4">
            <!-- Actual Cash field -->
            <div class="space-y-1">
              <div class="flex justify-between items-center">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Uang Laci Fisik (Cash) *</label>
                <span class="text-[10px] text-emerald-600 font-bold bg-emerald-50 px-1.5 py-0.5 rounded">
                  Ekspektasi: {{ formatCurrency(liveSummary?.expected_balances?.expected_cash || activeShift?.expected_cash) }}
                </span>
              </div>
              <div class="relative rounded-xl shadow-sm">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-sm font-bold text-gray-400">Rp</span>
                <input 
                  v-model.number="closeForm.actual_cash"
                  type="number" 
                  step="any"
                  min="0"
                  placeholder="0"
                  class="w-full pl-10 pr-4 py-2 border rounded-xl text-sm font-semibold focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-colors"
                  :class="closeErrors.actual_cash ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
                />
              </div>
              <p v-if="closeErrors.actual_cash" class="text-xs text-red-500 font-medium mt-1">{{ closeErrors.actual_cash }}</p>
            </div>

            <!-- Actual QRIS field -->
            <div class="space-y-1">
              <div class="flex justify-between items-center">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Total Fisik QRIS *</label>
                <span class="text-[10px] text-emerald-600 font-bold bg-emerald-50 px-1.5 py-0.5 rounded">
                  Ekspektasi: {{ formatCurrency(liveSummary?.expected_balances?.expected_qris || activeShift?.expected_qris) }}
                </span>
              </div>
              <div class="relative rounded-xl shadow-sm">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-sm font-bold text-gray-400">Rp</span>
                <input 
                  v-model.number="closeForm.actual_qris"
                  type="number" 
                  step="any"
                  min="0"
                  placeholder="0"
                  class="w-full pl-10 pr-4 py-2 border rounded-xl text-sm font-semibold focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-colors"
                  :class="closeErrors.actual_qris ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
                />
              </div>
              <p v-if="closeErrors.actual_qris" class="text-xs text-red-500 font-medium mt-1">{{ closeErrors.actual_qris }}</p>
            </div>

            <!-- Actual Card field -->
            <div class="space-y-1">
              <div class="flex justify-between items-center">
                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Total Fisik Kartu (Debit/Kredit) *</label>
                <span class="text-[10px] text-emerald-600 font-bold bg-emerald-50 px-1.5 py-0.5 rounded">
                  Ekspektasi: {{ formatCurrency(liveSummary?.expected_balances?.expected_card || activeShift?.expected_card) }}
                </span>
              </div>
              <div class="relative rounded-xl shadow-sm">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-sm font-bold text-gray-400">Rp</span>
                <input 
                  v-model.number="closeForm.actual_card"
                  type="number" 
                  step="any"
                  min="0"
                  placeholder="0"
                  class="w-full pl-10 pr-4 py-2 border rounded-xl text-sm font-semibold focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-colors"
                  :class="closeErrors.actual_card ? 'border-red-400 focus:border-red-400 focus:ring-red-400' : 'border-gray-200'"
                />
              </div>
              <p v-if="closeErrors.actual_card" class="text-xs text-red-500 font-medium mt-1">{{ closeErrors.actual_card }}</p>
            </div>

            <!-- Close Notes field -->
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Catatan Penutupan / Hasil Reconcile</label>
              <textarea 
                v-model="closeForm.notes"
                rows="3"
                placeholder="Catatan kondisi akhir drawer atau selisih uang jika ada..."
                class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-amber-500 focus:ring-1 focus:ring-amber-500 transition-colors resize-none border-gray-200"
              ></textarea>
            </div>

            <!-- Close Shift Button -->
            <button 
              type="submit" 
              :disabled="submitting"
              class="w-full py-2.5 px-4 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-bold flex items-center justify-center gap-2 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed shadow-md hover:shadow-lg active:scale-98"
            >
              <svg v-if="submitting" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>Selesaikan & Tutup Shift</span>
            </button>
          </form>
        </div>

      </div>

      <!-- Right Column: Shift Statistics Monitor -->
      <div class="lg:col-span-2 space-y-6">
        
        <!-- ACTIVE SHIFT MONITOR PANEL -->
        <div class="bg-white border border-gray-100 p-6 rounded-2xl shadow-sm flex flex-col h-full justify-between">
          <div>
            <div class="flex justify-between items-center mb-6">
              <h3 class="text-md font-bold text-gray-800 flex items-center gap-2">
                Monitor Shift Berjalan
                <span v-if="activeShift" class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-50 text-emerald-600">
                  <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse mr-1"></span>
                  Aktif
                </span>
                <span v-else class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold bg-gray-100 text-gray-500">
                  Non-Aktif
                </span>
              </h3>

              <div v-if="activeShift" class="text-xs text-gray-500 font-bold bg-gray-50 border border-gray-100 rounded-lg px-2.5 py-1 flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-emerald-500">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                Durasi: <span class="font-mono text-emerald-600">{{ elapsedTime }}</span>
              </div>
            </div>

            <!-- IF NO SHIFT RUNNING -->
            <div v-if="!activeShift" class="flex-1 flex flex-col items-center justify-center py-16 text-center space-y-4">
              <span class="w-16 h-16 rounded-2xl bg-gray-50 flex items-center justify-center text-gray-300">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
              </span>
              <div>
                <h4 class="text-sm font-bold text-gray-700">Tidak Ada Shift Kasir Berjalan</h4>
                <p class="text-xs text-gray-400 max-w-[340px] mx-auto mt-1">
                  Pilih stasiun register kasir dan masukkan uang modal awal di kolom kiri untuk memulai pemantauan transaksi penjualan secara langsung.
                </p>
              </div>
            </div>

            <!-- IF ACTIVE SHIFT IS RUNNING -->
            <div v-else class="space-y-6">
              <!-- Cashier & Register Header Details -->
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-gray-50 rounded-xl border border-gray-100">
                <div>
                  <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider block">Petugas Kasir</span>
                  <span class="text-xs font-bold text-gray-800">{{ activeShift?.user?.name || 'Kasir Aktif' }}</span>
                </div>
                <div>
                  <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider block">Stasiun POS</span>
                  <span class="text-xs font-bold text-gray-800">{{ activeShift?.station?.name || 'Stasiun POS' }}</span>
                </div>
                <div>
                  <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider block">Mulai Bertugas</span>
                  <span class="text-xs font-bold text-gray-800">{{ formatDateTime(activeShift?.start_time) }}</span>
                </div>
                <div>
                  <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider block">Modal Awal Drawer</span>
                  <span class="text-xs font-bold text-emerald-600">{{ formatCurrency(activeShift?.starting_cash) }}</span>
                </div>
              </div>

              <!-- Analytics Cards (4 grids) -->
              <div>
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Statistik Transaksi Terkini</h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <!-- CARD 1: REVENUE -->
                  <div class="bg-gradient-to-br from-emerald-500 to-teal-600 text-white p-4 rounded-xl shadow-sm flex flex-col justify-between h-24">
                    <div class="flex justify-between items-start">
                      <span class="text-[9px] font-bold text-white/80 uppercase">Pendapatan</span>
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-white/70">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5h16.5a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5H3.75a1.5 1.5 0 0 1-1.5-1.5v-12a1.5 1.5 0 0 1 1.5-1.5zM12 12.75a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5z" />
                      </svg>
                    </div>
                    <div>
                      <span class="text-sm font-bold block truncate">{{ formatCurrency(totalRevenue) }}</span>
                      <span class="text-[8px] text-white/70">Total dari sales terbayar</span>
                    </div>
                  </div>

                  <!-- CARD 2: TRANSACTIONS -->
                  <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 text-white p-4 rounded-xl shadow-sm flex flex-col justify-between h-24">
                    <div class="flex justify-between items-start">
                      <span class="text-[9px] font-bold text-white/80 uppercase">Penjualan</span>
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-white/70">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0z" />
                      </svg>
                    </div>
                    <div>
                      <span class="text-sm font-bold block">{{ totalTransactionsCount }} Transaksi</span>
                      <span class="text-[8px] text-indigo-100">Status Completed</span>
                    </div>
                  </div>

                  <!-- CARD 3: CUSTOMERS -->
                  <div class="bg-gradient-to-br from-teal-500 to-cyan-600 text-white p-4 rounded-xl shadow-sm flex flex-col justify-between h-24">
                    <div class="flex justify-between items-start">
                      <span class="text-[9px] font-bold text-white/80 uppercase">Konsumen</span>
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-white/70">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                      </svg>
                    </div>
                    <div>
                      <span class="text-sm font-bold block">{{ uniqueCustomersCount }} Member</span>
                      <span class="text-[8px] text-teal-100">Pelanggan terdaftar dilayani</span>
                    </div>
                  </div>

                  <!-- CARD 4: PROMOTIONS -->
                  <div class="bg-gradient-to-br from-rose-500 to-rose-600 text-white p-4 rounded-xl shadow-sm flex flex-col justify-between h-24">
                    <div class="flex justify-between items-start">
                      <span class="text-[9px] font-bold text-white/80 uppercase">Jumlah Promo</span>
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-white/70">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 010 12.728M16.463 8.288a5.25 5.25 0 010 7.424M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z" />
                      </svg>
                    </div>
                    <div>
                      <span class="text-sm font-bold block truncate">{{ formatCurrency(totalPromotions.value) }}</span>
                      <span class="text-[8px] text-rose-100">{{ totalPromotions.count }} penjualan dengan diskon</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Live Reconciliation / Expected Balance details -->
              <div>
                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Detail Ekspektasi Drawer Stasiun</h4>
                <div class="border border-gray-100 rounded-xl overflow-hidden text-xs">
                  <div class="bg-gray-50 border-b border-gray-100 px-4 py-2.5 font-bold text-gray-600 grid grid-cols-3">
                    <span>Metode / Kategori</span>
                    <span class="text-right">Mutasi / Masuk</span>
                    <span class="text-right">Ekspektasi Kas Laci</span>
                  </div>

                  <div class="divide-y divide-gray-50 font-semibold text-gray-600">
                    <!-- Cash expected -->
                    <div class="px-4 py-2.5 grid grid-cols-3 hover:bg-gray-50/30">
                      <div>
                        <span class="font-bold text-gray-800 block">1. Tunai (Cash)</span>
                        <span class="text-[10px] text-gray-400 block mt-0.5">Mulai: {{ formatCurrency(liveSummary?.expected_balances?.starting_cash || activeShift?.starting_cash) }}</span>
                      </div>
                      <div class="text-right font-medium text-gray-400">
                        + {{ formatCurrency(liveSummary?.expected_balances?.cash_sales) }} sales <br/>
                        + {{ formatCurrency(liveSummary?.expected_balances?.cash_in) }} Petty In <br/>
                        - {{ formatCurrency(liveSummary?.expected_balances?.cash_out) }} Petty Out
                      </div>
                      <span class="text-right font-bold text-gray-800 self-center">{{ formatCurrency(liveSummary?.expected_balances?.expected_cash) }}</span>
                    </div>

                    <!-- QRIS expected -->
                    <div class="px-4 py-2.5 grid grid-cols-3 hover:bg-gray-50/30">
                      <div>
                        <span class="font-bold text-gray-800 block">2. QRIS (Digital)</span>
                        <span class="text-[10px] text-gray-400 block mt-0.5">Tanpa uang modal fisik</span>
                      </div>
                      <div class="text-right font-medium text-gray-400 self-center">
                        + {{ formatCurrency(liveSummary?.expected_balances?.qris_sales) }} QRIS sales
                      </div>
                      <span class="text-right font-bold text-gray-800 self-center">{{ formatCurrency(liveSummary?.expected_balances?.expected_qris) }}</span>
                    </div>

                    <!-- Card expected -->
                    <div class="px-4 py-2.5 grid grid-cols-3 hover:bg-gray-50/30">
                      <div>
                        <span class="font-bold text-gray-800 block">3. Kartu Debit/Kredit</span>
                        <span class="text-[10px] text-gray-400 block mt-0.5">Struk receipt EDC</span>
                      </div>
                      <div class="text-right font-medium text-gray-400 self-center">
                        + {{ formatCurrency(liveSummary?.expected_balances?.card_sales) }} Card sales
                      </div>
                      <span class="text-right font-bold text-gray-800 self-center">{{ formatCurrency(liveSummary?.expected_balances?.expected_card) }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>

          <div v-if="activeShift" class="mt-6 pt-4 border-t border-gray-100 flex items-center gap-2 text-[10px] text-amber-500 font-bold bg-amber-50/40 p-3 rounded-xl border border-amber-100">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 flex-shrink-0 text-amber-600">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span>Perhatian: Harap hitung jumlah uang laci fisik dengan teliti sebelum menekan tombol Tutup Shift. Nilai selisih akan otomatis diposting di Jurnal Ledger.</span>
          </div>
        </div>

      </div>

    </div>

    <!-- Shifts History Table Directory (Bottom Layout) -->
    <div class="bg-white border border-gray-100 p-6 rounded-2xl shadow-sm flex flex-col min-w-0">
      <h3 class="text-md font-bold text-gray-800 mb-5">Riwayat Shift Kasir (Audit Directory)</h3>

      <!-- Loading State -->
      <div v-if="loading" class="flex-1 flex flex-col items-center justify-center py-12 space-y-3">
        <svg class="animate-spin h-7 w-7 text-emerald-500" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="text-xs font-semibold text-gray-500">Memuat direktori riwayat shift...</span>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredShifts.length === 0" class="flex-1 flex flex-col items-center justify-center py-12 text-center space-y-4">
        <span class="w-14 h-14 rounded-2xl bg-gray-50 flex items-center justify-center text-gray-300">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
          </svg>
        </span>
        <div>
          <h4 class="text-sm font-bold text-gray-700">Tidak Ada Riwayat Ditemukan</h4>
          <p class="text-xs text-gray-400 max-w-[280px] mx-auto mt-1">
            {{ searchQuery ? 'Tidak ada riwayat shift yang sesuai dengan filter pencarian.' : 'Belum ada shift kasir yang tercatat dalam database.' }}
          </p>
        </div>
      </div>

      <!-- Shifts Table -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs font-semibold text-gray-600">
          <thead>
            <tr class="border-b border-gray-100 text-gray-400 uppercase tracking-wider text-[10px]">
              <th class="pb-3">Stasiun / Cashier</th>
              <th class="pb-3">Waktu Shift (Start - End)</th>
              <th class="pb-3 text-center">Durasi</th>
              <th class="pb-3 text-right">Modal Awal</th>
              <th class="pb-3 text-right">Total Penjualan</th>
              <th class="pb-3 text-right">Total Promo</th>
              <th class="pb-3 w-20 text-center">Status</th>
              <th class="pb-3 w-24 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="shift in paginatedShifts" :key="shift.id" class="hover:bg-gray-50/50 transition-colors">
              
              <!-- Station & Cashier Info -->
              <td class="py-3.5 pr-4">
                <span class="text-gray-850 font-bold block">{{ shift.station?.name || 'Stasiun POS' }}</span>
                <span class="text-[10px] text-gray-400 block mt-0.5">Petugas: {{ shift.user?.name || 'Kasir' }}</span>
              </td>

              <!-- Date time info -->
              <td class="py-3.5 pr-4 font-semibold text-gray-600">
                <span class="block">Mulai: {{ formatDateTime(shift.start_time) }}</span>
                <span class="block text-[10px] text-gray-400 mt-0.5">Selesai: {{ shift.end_time ? formatDateTime(shift.end_time) : 'Sedang Aktif' }}</span>
              </td>

              <!-- Duration -->
              <td class="py-3.5 text-center text-gray-700">
                {{ getDuration(shift.start_time, shift.end_time) }}
              </td>

              <!-- Starting Cash -->
              <td class="py-3.5 text-right font-bold text-gray-750">
                {{ formatCurrency(shift.starting_cash) }}
              </td>

              <!-- Total Sales Revenue -->
              <td class="py-3.5 text-right font-bold text-emerald-600">
                {{ formatCurrency(shift.total_sales) }}
              </td>

              <!-- Total Discount Promotions -->
              <td class="py-3.5 text-right font-bold text-rose-500">
                {{ formatCurrency(shift.total_discount) }}
              </td>

              <!-- Status badge -->
              <td class="py-3.5 text-center">
                <span 
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold"
                  :class="shift.status === 'open'
                    ? 'bg-emerald-50 text-emerald-600'
                    : 'bg-gray-100 text-gray-500'"
                >
                  <span 
                    class="w-1.5 h-1.5 rounded-full"
                    :class="shift.status === 'open' ? 'bg-emerald-500 animate-pulse' : 'bg-gray-450'"
                  ></span>
                  {{ shift.status === 'open' ? 'Open' : 'Closed' }}
                </span>
              </td>

              <!-- Action details -->
              <td class="py-3.5 text-center">
                <button 
                  @click="viewShiftDetails(shift)"
                  class="px-2.5 py-1 rounded-lg bg-gray-50 text-gray-500 hover:text-emerald-600 hover:bg-emerald-50 border border-gray-200 transition-colors flex items-center justify-center gap-1 mx-auto cursor-pointer font-bold"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-3.5 h-3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                  </svg>
                  <span>Audit</span>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Controls -->
      <div v-if="filteredShifts.length > 0" class="mt-6 pt-4 border-t border-gray-100 flex flex-col sm:flex-row justify-between items-center gap-4">
        <div class="flex items-center gap-3 text-[10px] text-gray-500 font-bold">
          <span>Menampilkan {{ startRange }} - {{ endRange }} dari {{ filteredShifts.length }} entri</span>
          <div class="flex items-center gap-1.5 border-l border-gray-200 pl-3">
            <label class="font-medium text-gray-400">Baris:</label>
            <select 
              v-model="pageSize" 
              @change="currentPage = 1"
              class="bg-gray-50 border border-gray-200 rounded-lg px-2 py-0.5 focus:outline-none focus:border-emerald-500 text-xs font-bold text-gray-700 transition-colors cursor-pointer"
            >
              <option :value="5">5</option>
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
            </select>
          </div>
        </div>

        <div class="flex items-center gap-1">
          <!-- First Page -->
          <button 
            @click="goToPage(1)" 
            :disabled="currentPage === 1"
            type="button"
            class="w-7 h-7 rounded-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 hover:text-emerald-600 disabled:opacity-40 disabled:hover:bg-white transition-all cursor-pointer"
          >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3">
              <path stroke-linecap="round" stroke-linejoin="round" d="m18.75 5.25-7.5 7.5 7.5 7.5m-6-15L5.25 12l7.5 7.5" />
            </svg>
          </button>

          <!-- Previous Page -->
          <button 
            @click="goToPage(currentPage - 1)" 
            :disabled="currentPage === 1"
            type="button"
            class="w-7 h-7 rounded-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 hover:text-emerald-600 disabled:opacity-40 disabled:hover:bg-white transition-all cursor-pointer"
          >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
          </button>

          <template v-for="page in totalPages" :key="page">
            <button 
              v-if="Math.abs(page - currentPage) <= 1 || page === 1 || page === totalPages"
              @click="goToPage(page)"
              type="button"
              class="w-7 h-7 rounded-lg border text-xs font-bold transition-all cursor-pointer"
              :class="currentPage === page 
                ? 'bg-emerald-500 border-emerald-500 text-white shadow-sm' 
                : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
            >
              {{ page }}
            </button>
            <span 
              v-else-if="(page === 2 && currentPage > 3) || (page === totalPages - 1 && currentPage < totalPages - 2)"
              class="w-7 h-7 flex items-center justify-center text-gray-400 font-bold"
            >
              ...
            </span>
          </template>

          <!-- Next Page -->
          <button 
            @click="goToPage(currentPage + 1)" 
            :disabled="currentPage === totalPages"
            type="button"
            class="w-7 h-7 rounded-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 hover:text-emerald-600 disabled:opacity-40 disabled:hover:bg-white transition-all cursor-pointer"
          >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3">
              <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
          </button>

          <!-- Last Page -->
          <button 
            @click="goToPage(totalPages)" 
            :disabled="currentPage === totalPages"
            type="button"
            class="w-7 h-7 rounded-lg border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 hover:text-emerald-600 disabled:opacity-40 disabled:hover:bg-white transition-all cursor-pointer"
          >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3">
              <path stroke-linecap="round" stroke-linejoin="round" d="m5.25 5.25 7.5 7.5-7.5 7.5m6-15 7.5 7.5-7.5 7.5" />
            </svg>
          </button>
        </div>
      </div>
    </div>

    <!-- AUDIT DETAIL MODAL/OVERLAY -->
    <div v-if="showDetailModal && selectedShiftDetail" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 flex items-center justify-center p-4 backdrop-blur-sm">
      <div class="bg-white rounded-2xl max-w-2xl w-full border border-gray-100 shadow-2xl p-6 relative animate-fade-in max-h-[90vh] overflow-y-auto">
        <!-- Close button -->
        <button 
          @click="showDetailModal = false"
          class="absolute top-4 right-4 text-gray-400 hover:text-gray-650 hover:bg-gray-100 p-1.5 rounded-xl cursor-pointer transition-colors"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
          </svg>
        </button>

        <!-- Modal Header -->
        <div class="border-b border-gray-100 pb-4 mb-5 flex items-center gap-3">
          <span class="p-2.5 rounded-2xl bg-indigo-50 text-indigo-600">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25" />
            </svg>
          </span>
          <div>
            <h3 class="text-base font-bold text-gray-800">Detail Rekonsiliasi & Audit Shift</h3>
            <p class="text-[10px] text-gray-400">Informasi detil laporan penutupan kasir untuk pembukuan akuntansi.</p>
          </div>
        </div>

        <!-- Modal Content -->
        <div class="space-y-6">
          <!-- Information Header Group -->
          <div class="grid grid-cols-2 gap-4 bg-gray-50 p-4 rounded-xl border border-gray-150 text-xs">
            <div>
              <span class="font-bold text-gray-400 block uppercase text-[9px] tracking-wide">Register / Stasiun</span>
              <span class="font-bold text-gray-800 block mt-0.5">{{ selectedShiftDetail.station?.name || '-' }}</span>
            </div>
            <div>
              <span class="font-bold text-gray-400 block uppercase text-[9px] tracking-wide">Cashier (Kasir)</span>
              <span class="font-bold text-gray-800 block mt-0.5">{{ selectedShiftDetail.user?.name || '-' }}</span>
            </div>
            <div>
              <span class="font-bold text-gray-400 block uppercase text-[9px] tracking-wide">Waktu Mulai</span>
              <span class="font-bold text-gray-800 block mt-0.5">{{ formatDateTime(selectedShiftDetail.start_time) }}</span>
            </div>
            <div>
              <span class="font-bold text-gray-400 block uppercase text-[9px] tracking-wide">Waktu Selesai</span>
              <span class="font-bold text-gray-800 block mt-0.5">{{ formatDateTime(selectedShiftDetail.end_time) }}</span>
            </div>
          </div>

          <!-- Expected vs Actual Balance Table -->
          <div>
            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Tabel Penandingan (Reconciliation)</h4>
            <div class="border border-gray-100 rounded-xl overflow-hidden text-xs">
              <div class="bg-gray-50 border-b border-gray-100 px-4 py-2 font-bold text-gray-500 grid grid-cols-4">
                <span>Metode Pembayaran</span>
                <span class="text-right">Ekspektasi Sistem</span>
                <span class="text-right">Hitung Fisik</span>
                <span class="text-right">Selisih (Diff)</span>
              </div>

              <div class="divide-y divide-gray-50 font-semibold text-gray-600">
                <!-- Cash row -->
                <div class="px-4 py-3 grid grid-cols-4 hover:bg-gray-50/20">
                  <span class="font-bold text-gray-800">1. Tunai (Cash)</span>
                  <span class="text-right text-gray-700">{{ formatCurrency(selectedShiftDetail.expected_cash) }}</span>
                  <span class="text-right text-gray-800">{{ formatCurrency(selectedShiftDetail.actual_cash) }}</span>
                  <span 
                    class="text-right font-bold"
                    :class="Number(selectedShiftDetail.difference_cash) === 0 
                      ? 'text-emerald-500' 
                      : (Number(selectedShiftDetail.difference_cash) > 0 ? 'text-blue-500' : 'text-rose-500')"
                  >
                    {{ Number(selectedShiftDetail.difference_cash) > 0 ? '+' : '' }}{{ formatCurrency(selectedShiftDetail.difference_cash) }}
                  </span>
                </div>

                <!-- QRIS row -->
                <div class="px-4 py-3 grid grid-cols-4 hover:bg-gray-50/20">
                  <span class="font-bold text-gray-800">2. QRIS (Digital)</span>
                  <span class="text-right text-gray-700">{{ formatCurrency(selectedShiftDetail.expected_qris) }}</span>
                  <span class="text-right text-gray-800">{{ formatCurrency(selectedShiftDetail.actual_qris) }}</span>
                  <span 
                    class="text-right font-bold"
                    :class="Number(selectedShiftDetail.difference_qris) === 0 
                      ? 'text-emerald-500' 
                      : (Number(selectedShiftDetail.difference_qris) > 0 ? 'text-blue-500' : 'text-rose-500')"
                  >
                    {{ Number(selectedShiftDetail.difference_qris) > 0 ? '+' : '' }}{{ formatCurrency(selectedShiftDetail.difference_qris) }}
                  </span>
                </div>

                <!-- Card row -->
                <div class="px-4 py-3 grid grid-cols-4 hover:bg-gray-50/20">
                  <span class="font-bold text-gray-800">3. Kartu Debit/Kredit</span>
                  <span class="text-right text-gray-700">{{ formatCurrency(selectedShiftDetail.expected_card) }}</span>
                  <span class="text-right text-gray-800">{{ formatCurrency(selectedShiftDetail.actual_card) }}</span>
                  <span 
                    class="text-right font-bold"
                    :class="Number(selectedShiftDetail.difference_card) === 0 
                      ? 'text-emerald-500' 
                      : (Number(selectedShiftDetail.difference_card) > 0 ? 'text-blue-500' : 'text-rose-500')"
                  >
                    {{ Number(selectedShiftDetail.difference_card) > 0 ? '+' : '' }}{{ formatCurrency(selectedShiftDetail.difference_card) }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Summary transaction count -->
          <div class="grid grid-cols-3 gap-4 border border-gray-150 p-4 rounded-xl text-center">
            <div>
              <span class="text-[9px] font-bold text-gray-400 block uppercase">Total Omzet Penjualan</span>
              <span class="text-sm font-bold text-emerald-600 block mt-0.5">{{ formatCurrency(selectedShiftDetail.total_sales) }}</span>
            </div>
            <div>
              <span class="text-[9px] font-bold text-gray-400 block uppercase">Jumlah Transaksi</span>
              <span class="text-sm font-bold text-gray-800 block mt-0.5">{{ selectedShiftDetail.sales?.length || 0 }} Penjualan</span>
            </div>
            <div>
              <span class="text-[9px] font-bold text-gray-400 block uppercase">Potongan Promosi</span>
              <span class="text-sm font-bold text-rose-500 block mt-0.5">{{ formatCurrency(selectedShiftDetail.total_discount) }}</span>
            </div>
          </div>

          <!-- Notes / Logs -->
          <div class="space-y-1">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Catatan Audit Shift</span>
            <div class="p-3 bg-gray-50 border border-gray-100 rounded-xl text-xs text-gray-650 font-semibold italic">
              {{ selectedShiftDetail.notes || 'Tidak ada catatan tambahan untuk shift ini.' }}
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="mt-8 pt-4 border-t border-gray-100 flex justify-end">
          <button 
            @click="showDetailModal = false"
            type="button" 
            class="px-5 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl text-xs font-bold transition-all cursor-pointer"
          >
            Tutup Audit
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.25s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: scale(0.96);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}
</style>
