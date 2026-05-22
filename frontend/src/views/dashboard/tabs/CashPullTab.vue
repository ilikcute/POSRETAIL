<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'
import { useAuth } from '../../../store/auth'

const toast = useToast()
const { currentUser } = useAuth()

// Core States
const stations = ref([])
const selectedStationId = ref('')
const stationStatus = ref(null)
const supervisors = ref([])
const recentTransactions = ref([])

// Loaders
const loadingStations = ref(false)
const loadingStatus = ref(false)
const loadingSupervisors = ref(false)
const loadingHistory = ref(false)
const submitting = ref(false)

// Form State
const form = ref({
  supervisor_id: '',
  pull_amount: '',
  notes: 'Setor Tengah Kas Laci ke Brankas Utama'
})

// Client Validation Errors
const errors = ref({
  supervisor_id: '',
  pull_amount: '',
  notes: ''
})

// Currency Formatter Helper
const formatRupiah = (value) => {
  if (value === null || value === undefined) return 'Rp 0'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(value)
}

// Fetch active POS stations
const fetchStations = async () => {
  loadingStations.value = true
  try {
    const res = await api.get('/stations')
    const data = res.data?.data || res.data || []
    stations.value = data.filter(s => s.is_active === 1 || s.is_active === true)
    
    // Auto-select first station if available
    if (stations.value.length > 0) {
      selectedStationId.value = stations.value[0].id
    }
  } catch (error) {
    console.error('Error fetching stations:', error)
    toast.error('Gagal memuat daftar stasiun kasir.')
  } finally {
    loadingStations.value = false
  }
}

// Fetch station drawer limit and status
const checkDrawerStatus = async (stationId) => {
  if (!stationId) {
    stationStatus.value = null
    return
  }
  loadingStatus.value = true
  try {
    const res = await api.get(`/cash-pull/check/${stationId}`)
    stationStatus.value = res.data?.data || res.data
    
    // Auto fill recommended pull amount
    if (stationStatus.value?.cash_drawer_status?.suggested_pull_amount > 0) {
      form.value.pull_amount = stationStatus.value.cash_drawer_status.suggested_pull_amount
    } else {
      form.value.pull_amount = ''
    }
  } catch (error) {
    console.error('Error checking drawer limit:', error)
    toast.error('Gagal memeriksa status laci kasir.')
    stationStatus.value = null
  } finally {
    loadingStatus.value = false
  }
}

// Fetch active system users for supervisor authorization selection
const fetchSupervisors = async () => {
  loadingSupervisors.value = true
  try {
    const res = await api.get('/users')
    const data = res.data?.data || res.data || []
    supervisors.value = data
  } catch (error) {
    console.error('Error fetching supervisors:', error)
    // Fallback to active user if user list cannot be fetched due to lack of standard roles API
    if (currentUser.value) {
      supervisors.value = [currentUser.value]
    }
  } finally {
    loadingSupervisors.value = false
  }
}

// Fetch cash transactions history (Filter for setor_tengah)
const fetchHistory = async () => {
  loadingHistory.value = true
  try {
    const res = await api.get('/cash-transactions')
    const raw = res.data?.data ?? res.data ?? []
    const data = Array.isArray(raw) ? raw : []
    // Filter and show all 'setor_tengah' category or general cash pulls
    recentTransactions.value = data.filter(t =>
      t.category === 'setor_tengah' ||
      (t.type === 'out' && t.description?.toLowerCase().includes('setor tengah'))
    )
  } catch (error) {
    console.error('Error fetching cash pull history:', error)
  } finally {
    loadingHistory.value = false
  }
}

// React to station selection change
watch(selectedStationId, (newId) => {
  checkDrawerStatus(newId)
  // Clear any persistent form errors when changing station
  errors.value = { supervisor_id: '', pull_amount: '', notes: '' }
})

// Real-time local validations
const validateForm = () => {
  let isValid = true
  errors.value = { supervisor_id: '', pull_amount: '', notes: '' }

  if (!form.value.supervisor_id) {
    errors.value.supervisor_id = 'Supervisor Otoritas wajib dipilih.'
    isValid = false
  }

  const amt = parseFloat(form.value.pull_amount)
  const currentCash = stationStatus.value?.cash_drawer_status?.current_cash_in_drawer || 0

  if (form.value.pull_amount === '' || form.value.pull_amount === null || form.value.pull_amount === undefined) {
    errors.value.pull_amount = 'Nominal penarikan wajib diisi.'
    isValid = false
  } else if (isNaN(amt) || amt <= 0) {
    errors.value.pull_amount = 'Nominal penarikan harus berupa angka positif lebih besar dari Rp 0.'
    isValid = false
  } else if (amt > currentCash) {
    errors.value.pull_amount = `Nominal melebihi saldo kas fisik saat ini (${formatRupiah(currentCash)}).`
    isValid = false
  }

  if (form.value.notes && form.value.notes.length > 255) {
    errors.value.notes = 'Catatan tidak boleh melebihi 255 karakter.'
    isValid = false
  }

  return isValid
}

// Handle Form Submission
const handleExecuteCashPull = async () => {
  if (!validateForm()) {
    toast.warning('Harap perbaiki kesalahan input sebelum melanjutkan.')
    return
  }

  submitting.value = true
  try {
    const payload = {
      station_id: selectedStationId.value,
      pull_amount: parseFloat(form.value.pull_amount),
      supervisor_id: form.value.supervisor_id,
      notes: form.value.notes
    }

    const res = await api.post('/cash-pull/execute', payload)
    
    toast.success('Setor Tengah (Cash Pull) berhasil dieksekusi dan dicatat di Jurnal Ledger!')
    
    // Reset Form
    form.value.pull_amount = ''
    form.value.supervisor_id = ''
    form.value.notes = 'Setor Tengah Kas Laci ke Brankas Utama'
    errors.value = { supervisor_id: '', pull_amount: '', notes: '' }
    
    // Refresh Data
    await checkDrawerStatus(selectedStationId.value)
    await fetchHistory()
  } catch (error) {
    console.error('Error executing cash pull:', error)
    if (error.response && error.response.status === 422) {
      const serverErrors = error.response.data.errors || {}
      Object.keys(serverErrors).forEach(key => {
        if (errors.value[key] !== undefined) {
          errors.value[key] = serverErrors[key][0]
        }
      })
      toast.error('Gagal mengeksekusi. Validasi data server gagal.')
    } else {
      toast.error(error.response?.data?.message || 'Gagal mengeksekusi Setor Tengah.')
    }
  } finally {
    submitting.value = false
  }
}

// Format date helper
const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  return date.toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  })
}

// Initial Mount
onMounted(() => {
  fetchStations()
  fetchSupervisors()
  fetchHistory()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header Tab dengan Glassmorphic style -->
    <div class="backdrop-blur-md bg-white/70 border border-white/20 rounded-2xl p-6 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight flex items-center gap-2">
          <span class="p-2 rounded-lg bg-emerald-500 text-white shadow-md shadow-emerald-500/20">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 0 0-2.25-2.25H15a3 3 0 1 1-6 0H5.25A2.25 2.25 0 0 0 3 12m18 0v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6m18 0V9M3 12V9m18-3a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v3" />
            </svg>
          </span>
          Setor Tengah (Cash Pull)
        </h1>
        <p class="text-slate-500 text-sm mt-1">
          Monitor saldo fisik kasir secara real-time, pantau ambang batas pengaman, dan lakukan pemindahan kas laci ke brankas utama secara aman.
        </p>
      </div>

      <!-- Station Selector Dropdown -->
      <div class="flex items-center gap-2 min-w-[240px]">
        <label for="station-select" class="text-sm font-semibold text-slate-600 shrink-0">Stasiun Kasir:</label>
        <div class="relative w-full">
          <select
            id="station-select"
            v-model="selectedStationId"
            class="w-full bg-white border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-800 font-medium shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-all cursor-pointer appearance-none"
            :disabled="loadingStations"
          >
            <option v-if="loadingStations" value="">Memuat stasiun...</option>
            <option v-else-if="stations.length === 0" value="">Tidak ada stasiun aktif</option>
            <option v-for="st in stations" :key="st.id" :value="st.id">
              {{ st.name }} ({{ st.location || 'No Location' }})
            </option>
          </select>
          <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-500">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Two-Column Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      
      <!-- LEFT COLUMN: Drawer Safety Metric Visualizer (7 cols) -->
      <div class="lg:col-span-7 space-y-6">
        
        <!-- Loading State -->
        <div v-if="loadingStatus" class="backdrop-blur-md bg-white/70 border border-slate-100 rounded-2xl p-12 shadow-sm flex flex-col items-center justify-center space-y-3 min-h-[400px]">
          <div class="w-12 h-12 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
          <p class="text-slate-500 font-medium text-sm">Sedang menganalisis saldo kas stasiun...</p>
        </div>

        <!-- Empty State (No active shift) -->
        <div v-else-if="stationStatus && !stationStatus.active_shift" class="backdrop-blur-md bg-white/70 border border-slate-100 rounded-2xl p-8 shadow-sm text-center min-h-[400px] flex flex-col items-center justify-center">
          <div class="p-4 bg-amber-50 text-amber-500 rounded-full mb-4 shadow-inner">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-10 h-10 animate-pulse">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
          <h3 class="text-lg font-bold text-slate-800">Shift Tidak Aktif</h3>
          <p class="text-slate-500 text-sm max-w-md mx-auto mt-2">
            Stasiun <span class="font-semibold text-slate-700">{{ stationStatus.station_name }}</span> saat ini tidak memiliki shift kasir aktif yang sedang berjalan.
          </p>
          <p class="text-slate-400 text-xs mt-4">
            Buka shift baru di menu <strong>Shift</strong> agar kasir dapat memproses transaksi dan memonitor cash pull.
          </p>
        </div>

        <!-- Active Shift Drawer Safe Visualizer -->
        <div v-else-if="stationStatus && stationStatus.active_shift" class="backdrop-blur-md bg-white/70 border border-white/20 rounded-2xl p-6 shadow-sm space-y-6">
          <div class="flex items-center justify-between border-b border-slate-100 pb-4">
            <div>
              <span class="px-2.5 py-1 text-xs font-semibold text-emerald-700 bg-emerald-100 rounded-full">Active Shift Running</span>
              <h2 class="text-lg font-bold text-slate-800 mt-2">
                Pemantauan Saldo Kas Laci stasiun <span class="text-emerald-600">{{ stationStatus.station.name }}</span>
              </h2>
            </div>
            <div class="text-right">
              <p class="text-xs text-slate-400">Dimulai sejak</p>
              <p class="text-sm font-semibold text-slate-700">{{ formatDate(stationStatus.active_shift.start_time) }}</p>
            </div>
          </div>

          <!-- Pulsing Alert Banner if Limit Exceeded -->
          <Transition name="fade">
            <div
              v-if="stationStatus.cash_drawer_status.is_alert_triggered"
              class="relative bg-gradient-to-r from-rose-500 to-red-600 text-white rounded-xl p-4 shadow-lg overflow-hidden animate-pulse border border-red-400/30"
            >
              <div class="absolute right-0 top-0 translate-x-4 -translate-y-4 text-white/10 pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-32 h-32">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
              </div>
              <div class="flex items-start gap-3">
                <span class="p-2 bg-white/20 rounded-lg shrink-0 mt-0.5">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-white">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                </span>
                <div>
                  <h4 class="font-bold text-base">PERINGATAN: Batas Aman Laci Kas Terlampaui!</h4>
                  <p class="text-white/80 text-xs mt-1 leading-relaxed">
                    Jumlah uang tunai fisik yang tersimpan di laci stasiun ini telah melampaui batas aman keamanan kasir yang dikonfigurasi sebesar <strong>{{ formatRupiah(stationStatus.station.drawer_safety_limit) }}</strong>. Supervisor diwajibkan segera menyetujui transaksi **Setor Tengah** untuk mengurangi risiko keamanan toko.
                  </p>
                </div>
              </div>
            </div>
          </Transition>

          <!-- Metrics Panel -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            
            <!-- Current Balance Card -->
            <div class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-2xl p-5 border border-slate-200/50 shadow-inner flex items-center justify-between">
              <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Saldo Laci Kas Aktual</p>
                <p class="text-2xl font-black text-slate-800 mt-2">{{ formatRupiah(stationStatus.cash_drawer_status.current_cash_in_drawer) }}</p>
                <div class="flex items-center gap-1.5 text-xs text-slate-500 mt-2">
                  <span>Kasir:</span>
                  <span class="font-semibold text-slate-700">{{ stationStatus.active_shift.cashier_name }}</span>
                </div>
              </div>
              <div class="p-3.5 bg-emerald-500/10 rounded-xl text-emerald-600 shadow-sm border border-emerald-500/25">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-1.921-.659-1.171-.88-1.171-2.303 0-3.182.47-.439 1.196-.659 1.92-.659.726 0 1.453.22 1.923.659l.879.659M12 3v3m0 12v3" />
                </svg>
              </div>
            </div>

            <!-- Safety Limit Card -->
            <div class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-2xl p-5 border border-slate-200/50 shadow-inner flex items-center justify-between">
              <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Ambang Batas Pengaman</p>
                <p class="text-2xl font-black text-slate-800 mt-2">{{ formatRupiah(stationStatus.station.drawer_safety_limit) }}</p>
                <div class="flex items-center gap-1.5 text-xs text-slate-500 mt-2">
                  <span>Status:</span>
                  <span
                    class="font-bold uppercase"
                    :class="stationStatus.cash_drawer_status.is_alert_triggered ? 'text-red-500 animate-pulse' : 'text-emerald-500'"
                  >
                    {{ stationStatus.cash_drawer_status.is_alert_triggered ? 'Warning Limit' : 'Safe Balance' }}
                  </span>
                </div>
              </div>
              <div
                class="p-3.5 rounded-xl shadow-sm border"
                :class="[
                  stationStatus.cash_drawer_status.is_alert_triggered
                    ? 'bg-rose-500/10 text-rose-600 border-rose-500/20'
                    : 'bg-slate-500/10 text-slate-600 border-slate-500/20'
                ]"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                </svg>
              </div>
            </div>
          </div>

          <!-- Circular / Linear Gauge Visualizer -->
          <div class="space-y-2">
            <div class="flex items-center justify-between text-xs text-slate-500 font-semibold">
              <span>Rasio Kapasitas Keamanan Kas</span>
              <span class="font-bold text-slate-700">
                {{ Math.round((stationStatus.cash_drawer_status.current_cash_in_drawer / stationStatus.station.drawer_safety_limit) * 100) }}%
              </span>
            </div>
            
            <div class="w-full bg-slate-100 rounded-full h-4 overflow-hidden border border-slate-200 shadow-inner">
              <div
                class="h-full rounded-full transition-all duration-500 shadow-md"
                :class="[
                  stationStatus.cash_drawer_status.is_alert_triggered
                    ? 'bg-gradient-to-r from-rose-500 to-red-600 animate-pulse'
                    : 'bg-gradient-to-r from-emerald-400 to-teal-500'
                ]"
                :style="{ width: Math.min(100, (stationStatus.cash_drawer_status.current_cash_in_drawer / stationStatus.station.drawer_safety_limit) * 100) + '%' }"
              ></div>
            </div>
          </div>

          <!-- Float Analytics Info -->
          <div class="p-4 bg-slate-50/70 border border-slate-100 rounded-xl grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
              <p class="text-slate-400 text-xs">Rekomendasi Setoran Tengah</p>
              <p class="text-base font-extrabold text-indigo-600 mt-1">
                {{ formatRupiah(stationStatus.cash_drawer_status.suggested_pull_amount) }}
              </p>
              <p class="text-[10px] text-slate-400 mt-1">
                (Saldo Laci dikurangi Float Modal Standar Rp 500.000)
              </p>
            </div>
            <div class="border-t md:border-t-0 md:border-l border-slate-200/60 pt-3 md:pt-0 md:pl-4">
              <p class="text-slate-400 text-xs">Uang Laci yang Ditinggalkan (Float)</p>
              <p class="text-base font-extrabold text-slate-700 mt-1">
                {{ formatRupiah(stationStatus.cash_drawer_status.remaining_cash_if_pulled) }}
              </p>
              <p class="text-[10px] text-slate-400 mt-1">
                Saldo minimum untuk modal kembalian kasir berikutnya.
              </p>
            </div>
          </div>

        </div>

      </div>

      <!-- RIGHT COLUMN: Secure Cash Pull Form (5 cols) -->
      <div class="lg:col-span-5">
        <div class="backdrop-blur-md bg-white/70 border border-white/20 rounded-2xl p-6 shadow-sm space-y-5">
          <div class="border-b border-slate-100 pb-4">
            <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
              <span class="p-1.5 rounded bg-indigo-500 text-white">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="w-4 h-4">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25z" />
                </svg>
              </span>
              Otorisasi & Eksekusi Setoran
            </h2>
            <p class="text-slate-400 text-xs mt-1">Eksekusi secure double-entry debit-kredit jurnal ledger otomatis.</p>
          </div>

          <!-- Disabled if no active shift -->
          <div
            v-if="!stationStatus || !stationStatus.active_shift"
            class="text-center py-12 px-4 border border-dashed border-slate-200 rounded-xl text-slate-400 bg-slate-50/50"
          >
            <p class="text-sm font-medium">Form setor tengah tidak tersedia.</p>
            <p class="text-xs mt-1 text-slate-400">Harap pilih stasiun dengan shift aktif terlebih dahulu.</p>
          </div>

          <!-- Form Active -->
          <form v-else @submit.prevent="handleExecuteCashPull" class="space-y-4">
            
            <!-- Supervisor Selector -->
            <div class="space-y-1">
              <label for="supervisor_id" class="text-xs font-bold text-slate-600 uppercase tracking-wider">Supervisor Otoritas:</label>
              <div class="relative">
                <select
                  id="supervisor_id"
                  v-model="form.supervisor_id"
                  class="w-full bg-white border rounded-xl px-4 py-2.5 text-sm text-slate-800 shadow-sm outline-none focus:ring-2 focus:ring-emerald-500/25 transition-all appearance-none cursor-pointer"
                  :class="errors.supervisor_id ? 'border-rose-500 ring-2 ring-rose-500/20' : 'border-slate-200 focus:border-emerald-500'"
                >
                  <option value="">-- Pilih Supervisor / Petugas --</option>
                  <option v-for="user in supervisors" :key="user.id" :value="user.id">
                    {{ user.name }} ({{ user.email }})
                  </option>
                </select>
                <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-slate-500">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                  </svg>
                </div>
              </div>
              <p v-if="errors.supervisor_id" class="text-rose-500 font-medium text-xs mt-1">
                {{ errors.supervisor_id }}
              </p>
            </div>

            <!-- Pull Amount Input -->
            <div class="space-y-1">
              <label for="pull_amount" class="text-xs font-bold text-slate-600 uppercase tracking-wider">Nominal Penarikan (Rp):</label>
              <div class="relative">
                <div class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-semibold select-none">
                  Rp
                </div>
                <input
                  id="pull_amount"
                  v-model="form.pull_amount"
                  type="number"
                  placeholder="Contoh: 1000000"
                  class="w-full bg-white border rounded-xl pl-10 pr-4 py-2.5 text-sm text-slate-800 font-bold shadow-sm outline-none focus:ring-2 focus:ring-emerald-500/25 transition-all"
                  :class="errors.pull_amount ? 'border-rose-500 ring-2 ring-rose-500/20' : 'border-slate-200 focus:border-emerald-500'"
                />
              </div>
              <p v-if="errors.pull_amount" class="text-rose-500 font-medium text-xs mt-1">
                {{ errors.pull_amount }}
              </p>
            </div>

            <!-- Notes -->
            <div class="space-y-1">
              <label for="notes" class="text-xs font-bold text-slate-600 uppercase tracking-wider">Catatan Jurnal / Deskripsi:</label>
              <textarea
                id="notes"
                v-model="form.notes"
                rows="3"
                placeholder="Masukkan deskripsi setor tengah..."
                class="w-full bg-white border rounded-xl px-4 py-2 text-sm text-slate-800 shadow-sm outline-none focus:ring-2 focus:ring-emerald-500/25 transition-all resize-none"
                :class="errors.notes ? 'border-rose-500 ring-2 ring-rose-500/20' : 'border-slate-200 focus:border-emerald-500'"
              ></textarea>
              <p v-if="errors.notes" class="text-rose-500 font-medium text-xs mt-1">
                {{ errors.notes }}
              </p>
            </div>

            <!-- Action Button -->
            <button
              type="submit"
              class="w-full py-3 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white rounded-xl font-bold text-sm shadow-md hover:shadow-lg transition-all duration-200 flex items-center justify-center gap-2 cursor-pointer border border-emerald-600/10"
              :disabled="submitting"
            >
              <span v-if="submitting" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.746 3.746 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12z" />
              </svg>
              {{ submitting ? 'Memproses Otorisasi...' : 'Submit & Posting Jurnal' }}
            </button>

          </form>

        </div>
      </div>

    </div>

    <!-- AUDIT TRAIL: Riwayat Transaksi Setor Tengah -->
    <div class="backdrop-blur-md bg-white/70 border border-white/20 rounded-2xl p-6 shadow-sm space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4 gap-2">
        <div>
          <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
            <span class="p-1.5 rounded bg-emerald-500/10 text-emerald-600 border border-emerald-500/20">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />
              </svg>
            </span>
            Riwayat Setor Tengah (Audit Trail)
          </h2>
          <p class="text-slate-400 text-xs mt-1">Daftar transaksi setor tengah kas laci yang tercatat dalam sistem pos.</p>
        </div>
        <button
          @click="fetchHistory"
          class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition-all duration-200 flex items-center gap-1.5 cursor-pointer self-start sm:self-auto shadow-sm"
          :disabled="loadingHistory"
        >
          <span v-if="loadingHistory" class="w-3.5 h-3.5 border-2 border-slate-700 border-t-transparent rounded-full animate-spin"></span>
          <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
          </svg>
          Sync History
        </button>
      </div>

      <!-- Audit History Table -->
      <div class="overflow-x-auto rounded-xl border border-slate-150 bg-white">
        <table class="w-full border-collapse text-left text-sm text-slate-600">
          <thead class="bg-slate-50 text-xs font-bold text-slate-500 uppercase border-b border-slate-200">
            <tr>
              <th scope="col" class="px-6 py-4">Tanggal Transaksi</th>
              <th scope="col" class="px-6 py-4">Deskripsi / Catatan</th>
              <th scope="col" class="px-6 py-4">Tipe Kas</th>
              <th scope="col" class="px-6 py-4">Status / Kategori</th>
              <th scope="col" class="px-6 py-4 text-right">Jumlah Setoran</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            
            <tr v-if="loadingHistory" class="hover:bg-slate-50/50">
              <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                <div class="w-8 h-8 border-3 border-emerald-500 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
                Memuat riwayat transaksi...
              </td>
            </tr>

            <tr v-else-if="recentTransactions.length === 0" class="hover:bg-slate-50/50">
              <td colspan="5" class="px-6 py-12 text-center text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 mx-auto mb-2 text-slate-300">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h.007v.008H8.25v-.008z" />
                </svg>
                Tidak ada data setor tengah yang ditemukan.
              </td>
            </tr>

            <tr
              v-else
              v-for="tx in recentTransactions"
              :key="tx.id"
              class="hover:bg-slate-50/60 transition-colors"
            >
              <td class="px-6 py-4 font-semibold text-slate-700 whitespace-nowrap">
                {{ formatDate(tx.created_at || tx.transaction_date) }}
              </td>
              <td class="px-6 py-4 max-w-[320px] truncate">
                <div class="font-medium text-slate-700">{{ tx.description }}</div>
                <div class="text-[10px] text-slate-400 mt-0.5">
                  Shift ID: {{ tx.shift_id }} | Operator ID: {{ tx.created_by }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                  :class="tx.type === 'out' ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800'"
                >
                  {{ tx.type === 'out' ? 'Cash OUT' : 'Cash IN' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center gap-1 text-slate-500 font-semibold">
                  <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                  {{ tx.category || 'Setor Tengah' }}
                </span>
              </td>
              <td class="px-6 py-4 font-black text-right text-rose-600 whitespace-nowrap">
                - {{ formatRupiah(tx.amount) }}
              </td>
            </tr>

          </tbody>
        </table>
      </div>
    </div>

  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
