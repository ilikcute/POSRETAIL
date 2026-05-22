<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'
import { useAuth } from '../../../store/auth'

const toast = useToast()
const { currentUser } = useAuth()

// ============================================================
// STATE
// ============================================================
const shifts = ref([])
const selectedShiftId = ref('')
const liveSummary = ref(null)
const historyShifts = ref([])

const loadingShifts = ref(false)
const loadingSummary = ref(false)
const submitting = ref(false)
const loadingHistory = ref(false)

// Reconciliation form
const form = ref({
  actual_cash: 0,
  actual_qris: 0,
  actual_card: 0,
  notes: 'Setoran Tutup Kasir — Rekonsiliasi Akhir Shift',
})

const errors = ref({
  actual_cash: '',
  actual_qris: '',
  actual_card: '',
  notes: '',
})

// Detail modal
const selectedHistoryShift = ref(null)
const showDetailModal = ref(false)
const searchQuery = ref('')

// ============================================================
// COMPUTED
// ============================================================
const openShifts = computed(() =>
  shifts.value.filter((s) => s.status === 'open')
)

const closedShifts = computed(() =>
  shifts.value.filter((s) => s.status === 'closed')
)

const filteredHistory = computed(() => {
  if (!searchQuery.value.trim()) return closedShifts.value
  const q = searchQuery.value.toLowerCase()
  return closedShifts.value.filter(
    (s) =>
      (s.cashier_name || '').toLowerCase().includes(q) ||
      (s.station?.name || '').toLowerCase().includes(q) ||
      (s.user?.name || '').toLowerCase().includes(q)
  )
})

const selectedShift = computed(() =>
  shifts.value.find((s) => s.id == selectedShiftId.value) || null
)

// ============================================================
// CURRENCY / DATE HELPERS
// ============================================================
const formatRupiah = (value) => {
  if (value === null || value === undefined) return 'Rp 0'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value)
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

// ============================================================
// DATA FETCHING
// ============================================================
const fetchShifts = async () => {
  loadingShifts.value = true
  try {
    const res = await api.get('/shifts')
    shifts.value = res.data?.data || res.data || []

    // Auto-select the first open shift
    const firstOpen = openShifts.value[0]
    if (firstOpen && !selectedShiftId.value) {
      selectedShiftId.value = firstOpen.id
    }
  } catch (err) {
    console.error(err)
    toast.error('Gagal memuat daftar shift kasir.')
  } finally {
    loadingShifts.value = false
  }
}

const fetchSummary = async (shiftId) => {
  if (!shiftId) {
    liveSummary.value = null
    return
  }
  loadingSummary.value = true
  try {
    const res = await api.get(`/remittance/summary/${shiftId}`)
    liveSummary.value = res.data?.data || res.data

    // Pre-fill actual values with expected as a helper starting point
    if (liveSummary.value?.live_balances) {
      const lb = liveSummary.value.live_balances
      form.value.actual_cash = lb.expected_cash || 0
      form.value.actual_qris = lb.expected_qris || 0
      form.value.actual_card = lb.expected_card || 0
    }
  } catch (err) {
    console.error(err)
    toast.error('Gagal memuat ringkasan shift.')
    liveSummary.value = null
  } finally {
    loadingSummary.value = false
  }
}

// React to shift selection change
watch(selectedShiftId, (newId) => {
  liveSummary.value = null
  errors.value = { actual_cash: '', actual_qris: '', actual_card: '', notes: '' }
  fetchSummary(newId)
})

// ============================================================
// VALIDATION
// ============================================================
const validateForm = () => {
  let valid = true
  errors.value = { actual_cash: '', actual_qris: '', actual_card: '', notes: '' }

  const cash = parseFloat(form.value.actual_cash)
  const qris = parseFloat(form.value.actual_qris)
  const card = parseFloat(form.value.actual_card)

  if (isNaN(cash) || cash < 0) {
    errors.value.actual_cash = 'Jumlah kas tunai fisik harus berupa angka positif atau nol.'
    valid = false
  }
  if (isNaN(qris) || qris < 0) {
    errors.value.actual_qris = 'Jumlah QRIS fisik harus berupa angka positif atau nol.'
    valid = false
  }
  if (isNaN(card) || card < 0) {
    errors.value.actual_card = 'Jumlah kartu fisik harus berupa angka positif atau nol.'
    valid = false
  }
  if (form.value.notes && form.value.notes.length > 500) {
    errors.value.notes = 'Catatan tidak boleh melebihi 500 karakter.'
    valid = false
  }

  return valid
}

// ============================================================
// SUBMIT REMITTANCE
// ============================================================
const handleSubmitRemittance = async () => {
  if (!selectedShiftId.value) {
    toast.warning('Pilih shift kasir yang akan direkonsiliasi terlebih dahulu.')
    return
  }
  if (!validateForm()) {
    toast.warning('Perbaiki kesalahan pada form sebelum melanjutkan.')
    return
  }

  const confirmed = confirm(
    `⚠️ KONFIRMASI TUTUP SHIFT\n\nAnda akan menutup dan memposting jurnal rekonsiliasi kasir untuk shift ini.\nTindakan ini TIDAK DAPAT dibatalkan.\n\nLanjutkan?`
  )
  if (!confirmed) return

  submitting.value = true
  try {
    const payload = {
      shift_id: selectedShiftId.value,
      actual_cash: parseFloat(form.value.actual_cash),
      actual_qris: parseFloat(form.value.actual_qris),
      actual_card: parseFloat(form.value.actual_card),
      notes: form.value.notes,
    }

    const res = await api.post('/remittance/submit', payload)
    const result = res.data?.data || res.data

    toast.success('Rekonsiliasi kasir berhasil! Shift ditutup dan jurnal akuntansi telah diposting.')

    // Refresh shift list & clear summary
    await fetchShifts()
    selectedShiftId.value = ''
    liveSummary.value = null

    // Show result in modal
    selectedHistoryShift.value = result
    showDetailModal.value = true
  } catch (err) {
    console.error(err)
    const status = err.response?.status
    if (status === 400) {
      toast.error(err.response.data?.message || 'Shift kasir ini sudah dalam keadaan ditutup.')
    } else if (status === 422) {
      const serverErrors = err.response.data?.errors || {}
      Object.keys(serverErrors).forEach((key) => {
        if (errors.value[key] !== undefined) {
          errors.value[key] = serverErrors[key][0]
        }
      })
      toast.error('Validasi gagal. Periksa kembali isian form.')
    } else {
      toast.error(err.response?.data?.message || 'Terjadi kesalahan saat memproses rekonsiliasi.')
    }
  } finally {
    submitting.value = false
  }
}

// ============================================================
// HISTORY DETAIL MODAL
// ============================================================
const openHistoryDetail = async (shift) => {
  loadingHistory.value = true
  try {
    const res = await api.get(`/remittance/summary/${shift.id}`)
    selectedHistoryShift.value = res.data?.data || res.data
    showDetailModal.value = true
  } catch (err) {
    toast.error('Gagal memuat detail rekonsiliasi shift ini.')
  } finally {
    loadingHistory.value = false
  }
}

const closeDetailModal = () => {
  showDetailModal.value = false
  selectedHistoryShift.value = null
}

// ============================================================
// DIFFERENCE BADGE HELPER
// ============================================================
const diffBadge = (diff) => {
  const n = parseFloat(diff)
  if (n === 0) return { label: 'BALANCE', cls: 'bg-emerald-100 text-emerald-700' }
  if (n > 0) return { label: `+${formatRupiah(n)}`, cls: 'bg-blue-100 text-blue-700' }
  return { label: formatRupiah(n), cls: 'bg-rose-100 text-rose-700' }
}

// ============================================================
// INIT
// ============================================================
onMounted(() => {
  fetchShifts()
})
</script>

<template>
  <div class="space-y-6">

    <!-- ================================================================ -->
    <!-- HEADER -->
    <!-- ================================================================ -->
    <div class="backdrop-blur-md bg-white/70 border border-white/20 rounded-2xl p-6 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight flex items-center gap-2">
          <span class="p-2 rounded-lg bg-indigo-500 text-white shadow-md shadow-indigo-500/30">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
            </svg>
          </span>
          Station Remittance
        </h1>
        <p class="text-slate-500 text-sm mt-1">
          Rekonsiliasi dan tutup shift kasir. Hitung selisih kas, QRIS, dan kartu, lalu posting jurnal double-entry otomatis ke Ledger Akuntansi.
        </p>
      </div>

      <!-- Open Shift Badge -->
      <div class="flex items-center gap-3">
        <div v-if="openShifts.length > 0" class="flex items-center gap-2 px-4 py-2 bg-emerald-50 border border-emerald-200 rounded-xl">
          <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
          <span class="text-sm font-semibold text-emerald-700">{{ openShifts.length }} Shift Aktif</span>
        </div>
        <div v-else class="flex items-center gap-2 px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl">
          <span class="w-2 h-2 bg-slate-400 rounded-full"></span>
          <span class="text-sm font-semibold text-slate-500">Tidak ada shift aktif</span>
        </div>
        <button
          @click="fetchShifts"
          :disabled="loadingShifts"
          class="p-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 transition-all cursor-pointer"
          title="Refresh daftar shift"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4" :class="{ 'animate-spin': loadingShifts }">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
          </svg>
        </button>
      </div>
    </div>

    <!-- ================================================================ -->
    <!-- MAIN GRID: Left = Summary, Right = Form -->
    <!-- ================================================================ -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

      <!-- LEFT COLUMN: Shift Selector + Live Summary (7 cols) -->
      <div class="lg:col-span-7 space-y-5">

        <!-- Shift Selector Card -->
        <div class="backdrop-blur-md bg-white/70 border border-white/20 rounded-2xl p-5 shadow-sm space-y-3">
          <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wider flex items-center gap-2">
            <span class="p-1 rounded bg-indigo-100 text-indigo-600">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </span>
            Pilih Shift untuk Direkonsiliasi
          </h2>

          <div class="relative">
            <select
              id="shift-select"
              v-model="selectedShiftId"
              :disabled="loadingShifts"
              class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-800 font-medium shadow-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all cursor-pointer appearance-none"
            >
              <option value="">-- Pilih Shift Kasir yang Akan Ditutup --</option>
              <optgroup label="🟢 Shift Aktif (Open)">
                <option v-for="s in openShifts" :key="s.id" :value="s.id">
                  #{{ s.id }} | {{ s.user?.name || s.cashier_name || 'Kasir' }} — {{ s.station?.name || 'Stasiun' }} | Mulai: {{ formatDate(s.start_time) }}
                </option>
              </optgroup>
              <optgroup label="🔒 Shift Selesai (Closed)" v-if="closedShifts.length > 0">
                <option v-for="s in closedShifts" :key="s.id" :value="s.id" disabled class="text-slate-400">
                  #{{ s.id }} | {{ s.user?.name || s.cashier_name || 'Kasir' }} — CLOSED
                </option>
              </optgroup>
            </select>
            <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
              </svg>
            </div>
          </div>
        </div>

        <!-- Loading Summary -->
        <div v-if="loadingSummary" class="backdrop-blur-md bg-white/70 border border-slate-100 rounded-2xl p-12 shadow-sm flex flex-col items-center justify-center space-y-3 min-h-[300px]">
          <div class="w-10 h-10 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
          <p class="text-slate-500 text-sm font-medium">Menghitung saldo live shift kasir...</p>
        </div>

        <!-- No Shift Selected -->
        <div v-else-if="!selectedShiftId" class="backdrop-blur-md bg-white/70 border border-slate-100 rounded-2xl p-10 shadow-sm text-center min-h-[300px] flex flex-col items-center justify-center">
          <div class="p-4 bg-indigo-50 text-indigo-400 rounded-full mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10">
              <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
            </svg>
          </div>
          <h3 class="text-base font-bold text-slate-700">Belum ada shift dipilih</h3>
          <p class="text-slate-400 text-sm mt-1 max-w-xs">Pilih shift kasir aktif dari dropdown di atas untuk melihat ringkasan saldo live sebelum menutup shift.</p>
        </div>

        <!-- Live Summary Panel -->
        <div v-else-if="liveSummary" class="backdrop-blur-md bg-white/70 border border-white/20 rounded-2xl p-6 shadow-sm space-y-5">

          <!-- Already Closed Notice -->
          <div v-if="liveSummary.final_reconciliation" class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-center">
            <span class="px-3 py-1 text-xs font-bold text-slate-500 bg-slate-200 rounded-full uppercase">Shift Sudah Ditutup</span>
            <p class="text-slate-500 text-xs mt-2">Shift ini sudah direkonsiliasi. Lihat detail di bawah.</p>
          </div>

          <!-- Open Shift Live Data -->
          <template v-if="liveSummary.live_balances">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
              <div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-bold text-emerald-700 bg-emerald-100 rounded-full">
                  <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                  SHIFT AKTIF
                </span>
                <h2 class="text-lg font-bold text-slate-800 mt-2">
                  Kasir: <span class="text-indigo-600">{{ liveSummary.shift?.cashier_name }}</span>
                  &nbsp;|&nbsp; {{ liveSummary.shift?.station_name }}
                </h2>
              </div>
              <div class="text-right">
                <p class="text-xs text-slate-400">Mulai Shift</p>
                <p class="text-sm font-semibold text-slate-700">{{ formatDate(liveSummary.shift?.start_time) }}</p>
              </div>
            </div>

            <!-- KPI Summary Grid -->
            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
              <div class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl p-4 border border-slate-200/60 shadow-inner">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Penjualan</p>
                <p class="text-xl font-black text-slate-800 mt-1">{{ formatRupiah(liveSummary.live_balances.total_sales) }}</p>
                <p class="text-[10px] text-slate-400 mt-1">{{ liveSummary.live_balances.total_transactions }} transaksi</p>
              </div>
              <div class="bg-gradient-to-br from-emerald-50 to-emerald-100/60 rounded-xl p-4 border border-emerald-200/50 shadow-inner">
                <p class="text-[10px] font-bold text-emerald-500 uppercase tracking-wider">Kas Tunai Ekspektasi</p>
                <p class="text-xl font-black text-emerald-700 mt-1">{{ formatRupiah(liveSummary.live_balances.expected_cash) }}</p>
                <p class="text-[10px] text-emerald-400 mt-1">Modal: {{ formatRupiah(liveSummary.live_balances.starting_cash) }}</p>
              </div>
              <div class="bg-gradient-to-br from-blue-50 to-blue-100/60 rounded-xl p-4 border border-blue-200/50 shadow-inner">
                <p class="text-[10px] font-bold text-blue-500 uppercase tracking-wider">QRIS Ekspektasi</p>
                <p class="text-xl font-black text-blue-700 mt-1">{{ formatRupiah(liveSummary.live_balances.expected_qris) }}</p>
                <p class="text-[10px] text-blue-400 mt-1">Penjualan QRIS</p>
              </div>
              <div class="bg-gradient-to-br from-violet-50 to-violet-100/60 rounded-xl p-4 border border-violet-200/50 shadow-inner">
                <p class="text-[10px] font-bold text-violet-500 uppercase tracking-wider">Kartu Ekspektasi</p>
                <p class="text-xl font-black text-violet-700 mt-1">{{ formatRupiah(liveSummary.live_balances.expected_card) }}</p>
                <p class="text-[10px] text-violet-400 mt-1">Debit + Kredit</p>
              </div>
              <div class="bg-gradient-to-br from-amber-50 to-amber-100/60 rounded-xl p-4 border border-amber-200/50 shadow-inner">
                <p class="text-[10px] font-bold text-amber-500 uppercase tracking-wider">Total Diskon</p>
                <p class="text-xl font-black text-amber-700 mt-1">{{ formatRupiah(liveSummary.live_balances.total_discount) }}</p>
                <p class="text-[10px] text-amber-400 mt-1">Semua promosi</p>
              </div>
              <div class="bg-gradient-to-br from-slate-50 to-slate-100 rounded-xl p-4 border border-slate-200/60 shadow-inner">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Mutasi Kas</p>
                <p class="text-sm font-bold text-slate-700 mt-1">+{{ formatRupiah(liveSummary.live_balances.cash_in) }}</p>
                <p class="text-[10px] text-rose-400 mt-0.5">-{{ formatRupiah(liveSummary.live_balances.cash_out) }}</p>
              </div>
            </div>

            <!-- Detail Breakdown Table -->
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
              <table class="w-full text-sm text-slate-600 border-collapse">
                <thead class="bg-slate-50 text-xs font-bold text-slate-500 uppercase border-b border-slate-200">
                  <tr>
                    <th class="px-4 py-3 text-left">Komponen</th>
                    <th class="px-4 py-3 text-right">Nominal Ekspektasi Sistem</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr class="hover:bg-slate-50/60">
                    <td class="px-4 py-3 font-medium">Modal Awal Kas (Starting Cash)</td>
                    <td class="px-4 py-3 text-right font-bold text-slate-800">{{ formatRupiah(liveSummary.live_balances.starting_cash) }}</td>
                  </tr>
                  <tr class="hover:bg-slate-50/60">
                    <td class="px-4 py-3 font-medium text-emerald-700">+ Penjualan Tunai (Cash Sales)</td>
                    <td class="px-4 py-3 text-right font-bold text-emerald-600">+{{ formatRupiah(liveSummary.live_balances.cash_sales) }}</td>
                  </tr>
                  <tr class="hover:bg-slate-50/60">
                    <td class="px-4 py-3 font-medium text-emerald-700">+ Kas Masuk Lain-lain (Cash In)</td>
                    <td class="px-4 py-3 text-right font-bold text-emerald-600">+{{ formatRupiah(liveSummary.live_balances.cash_in) }}</td>
                  </tr>
                  <tr class="hover:bg-slate-50/60">
                    <td class="px-4 py-3 font-medium text-rose-700">- Kas Keluar / Setor Tengah</td>
                    <td class="px-4 py-3 text-right font-bold text-rose-600">-{{ formatRupiah(liveSummary.live_balances.cash_out) }}</td>
                  </tr>
                  <tr class="bg-indigo-50/60 border-t-2 border-indigo-200/60">
                    <td class="px-4 py-3 font-extrabold text-indigo-800">= Total Kas Ekspektasi di Laci</td>
                    <td class="px-4 py-3 text-right font-extrabold text-indigo-700 text-base">{{ formatRupiah(liveSummary.live_balances.expected_cash) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>

        </div>
      </div>

      <!-- RIGHT COLUMN: Reconciliation Form (5 cols) -->
      <div class="lg:col-span-5">
        <div class="backdrop-blur-md bg-white/70 border border-white/20 rounded-2xl p-6 shadow-sm space-y-5 sticky top-4">
          <div class="border-b border-slate-100 pb-4">
            <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
              <span class="p-1.5 rounded bg-indigo-500 text-white">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="w-4 h-4">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                </svg>
              </span>
              Input Rekonsiliasi Fisik
            </h2>
            <p class="text-slate-400 text-xs mt-1">Masukkan jumlah kas fisik yang Anda hitung di laci/mesin EDC. Sistem akan menghitung selisih otomatis dan posting jurnal GL.</p>
          </div>

          <!-- Disabled if no active shift selected -->
          <div
            v-if="!selectedShiftId || !liveSummary?.live_balances"
            class="text-center py-12 px-4 border border-dashed border-slate-200 rounded-xl text-slate-400 bg-slate-50/50"
          >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 mx-auto mb-3 text-slate-300">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
            </svg>
            <p class="text-sm font-medium">Form rekonsiliasi tidak tersedia.</p>
            <p class="text-xs mt-1">Pilih shift aktif terlebih dahulu di panel sebelah kiri.</p>
          </div>

          <!-- Form active -->
          <form v-else @submit.prevent="handleSubmitRemittance" class="space-y-4">

            <!-- Actual Cash -->
            <div class="space-y-1">
              <label for="actual_cash" class="text-xs font-bold text-slate-600 uppercase tracking-wider">
                Kas Tunai Fisik di Laci (Rp):
              </label>
              <div
                v-if="liveSummary?.live_balances"
                class="text-[10px] text-slate-400 mb-1"
              >
                Ekspektasi sistem: <span class="font-bold text-emerald-600">{{ formatRupiah(liveSummary.live_balances.expected_cash) }}</span>
              </div>
              <div class="relative">
                <div class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-semibold select-none">Rp</div>
                <input
                  id="actual_cash"
                  v-model="form.actual_cash"
                  type="number"
                  step="1000"
                  min="0"
                  placeholder="Contoh: 1500000"
                  class="w-full bg-white border rounded-xl pl-10 pr-4 py-2.5 text-sm text-slate-800 font-bold shadow-sm outline-none focus:ring-2 focus:ring-indigo-500/25 transition-all"
                  :class="errors.actual_cash ? 'border-rose-500 ring-2 ring-rose-500/20' : 'border-slate-200 focus:border-indigo-500'"
                />
              </div>
              <p v-if="errors.actual_cash" class="text-rose-500 font-medium text-xs mt-1">{{ errors.actual_cash }}</p>
            </div>

            <!-- Actual QRIS -->
            <div class="space-y-1">
              <label for="actual_qris" class="text-xs font-bold text-slate-600 uppercase tracking-wider">
                Penerimaan QRIS (Rp):
              </label>
              <div
                v-if="liveSummary?.live_balances"
                class="text-[10px] text-slate-400 mb-1"
              >
                Ekspektasi sistem: <span class="font-bold text-blue-600">{{ formatRupiah(liveSummary.live_balances.expected_qris) }}</span>
              </div>
              <div class="relative">
                <div class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-semibold select-none">Rp</div>
                <input
                  id="actual_qris"
                  v-model="form.actual_qris"
                  type="number"
                  step="1000"
                  min="0"
                  placeholder="Contoh: 500000"
                  class="w-full bg-white border rounded-xl pl-10 pr-4 py-2.5 text-sm text-slate-800 font-bold shadow-sm outline-none focus:ring-2 focus:ring-indigo-500/25 transition-all"
                  :class="errors.actual_qris ? 'border-rose-500 ring-2 ring-rose-500/20' : 'border-slate-200 focus:border-indigo-500'"
                />
              </div>
              <p v-if="errors.actual_qris" class="text-rose-500 font-medium text-xs mt-1">{{ errors.actual_qris }}</p>
            </div>

            <!-- Actual Card -->
            <div class="space-y-1">
              <label for="actual_card" class="text-xs font-bold text-slate-600 uppercase tracking-wider">
                Penerimaan Kartu Debit/Kredit (Rp):
              </label>
              <div
                v-if="liveSummary?.live_balances"
                class="text-[10px] text-slate-400 mb-1"
              >
                Ekspektasi sistem: <span class="font-bold text-violet-600">{{ formatRupiah(liveSummary.live_balances.expected_card) }}</span>
              </div>
              <div class="relative">
                <div class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-semibold select-none">Rp</div>
                <input
                  id="actual_card"
                  v-model="form.actual_card"
                  type="number"
                  step="1000"
                  min="0"
                  placeholder="Contoh: 200000"
                  class="w-full bg-white border rounded-xl pl-10 pr-4 py-2.5 text-sm text-slate-800 font-bold shadow-sm outline-none focus:ring-2 focus:ring-indigo-500/25 transition-all"
                  :class="errors.actual_card ? 'border-rose-500 ring-2 ring-rose-500/20' : 'border-slate-200 focus:border-indigo-500'"
                />
              </div>
              <p v-if="errors.actual_card" class="text-rose-500 font-medium text-xs mt-1">{{ errors.actual_card }}</p>
            </div>

            <!-- Live Difference Preview -->
            <div v-if="liveSummary?.live_balances" class="bg-slate-50 border border-slate-200 rounded-xl p-4 space-y-2 text-xs">
              <p class="font-bold text-slate-500 uppercase tracking-wider mb-2">Preview Selisih (Real-time)</p>
              <div class="flex justify-between">
                <span class="text-slate-500">Selisih Kas:</span>
                <span
                  class="font-extrabold"
                  :class="(form.actual_cash - liveSummary.live_balances.expected_cash) === 0 ? 'text-emerald-600' : (form.actual_cash - liveSummary.live_balances.expected_cash) > 0 ? 'text-blue-600' : 'text-rose-600'"
                >
                  {{ formatRupiah(form.actual_cash - liveSummary.live_balances.expected_cash) }}
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500">Selisih QRIS:</span>
                <span
                  class="font-extrabold"
                  :class="(form.actual_qris - liveSummary.live_balances.expected_qris) === 0 ? 'text-emerald-600' : (form.actual_qris - liveSummary.live_balances.expected_qris) > 0 ? 'text-blue-600' : 'text-rose-600'"
                >
                  {{ formatRupiah(form.actual_qris - liveSummary.live_balances.expected_qris) }}
                </span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500">Selisih Kartu:</span>
                <span
                  class="font-extrabold"
                  :class="(form.actual_card - liveSummary.live_balances.expected_card) === 0 ? 'text-emerald-600' : (form.actual_card - liveSummary.live_balances.expected_card) > 0 ? 'text-blue-600' : 'text-rose-600'"
                >
                  {{ formatRupiah(form.actual_card - liveSummary.live_balances.expected_card) }}
                </span>
              </div>
            </div>

            <!-- Notes -->
            <div class="space-y-1">
              <label for="notes" class="text-xs font-bold text-slate-600 uppercase tracking-wider">Catatan Rekonsiliasi:</label>
              <textarea
                id="notes"
                v-model="form.notes"
                rows="2"
                placeholder="Deskripsi rekonsiliasi atau catatan khusus..."
                class="w-full bg-white border rounded-xl px-4 py-2.5 text-sm text-slate-800 shadow-sm outline-none focus:ring-2 focus:ring-indigo-500/25 transition-all resize-none"
                :class="errors.notes ? 'border-rose-500 ring-2 ring-rose-500/20' : 'border-slate-200 focus:border-indigo-500'"
              ></textarea>
              <p v-if="errors.notes" class="text-rose-500 font-medium text-xs mt-1">{{ errors.notes }}</p>
            </div>

            <!-- Submit Button -->
            <button
              type="submit"
              :disabled="submitting"
              class="w-full py-3 bg-gradient-to-r from-indigo-500 to-violet-600 hover:from-indigo-600 hover:to-violet-700 text-white rounded-xl font-bold text-sm shadow-md hover:shadow-lg transition-all duration-200 flex items-center justify-center gap-2 cursor-pointer border border-indigo-600/10 disabled:opacity-60 disabled:cursor-not-allowed"
            >
              <span v-if="submitting" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-12v.75m0 3v.75m0 3v.75m0 3V18M3 8.25a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 8.25v7.5a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15.75V8.25z" />
              </svg>
              {{ submitting ? 'Memproses Rekonsiliasi...' : 'Tutup Shift & Posting Jurnal GL' }}
            </button>

          </form>
        </div>
      </div>

    </div>

    <!-- ================================================================ -->
    <!-- HISTORY TABLE: Closed Shifts Audit Trail -->
    <!-- ================================================================ -->
    <div class="backdrop-blur-md bg-white/70 border border-white/20 rounded-2xl p-6 shadow-sm space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4 gap-3">
        <div>
          <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
            <span class="p-1.5 rounded bg-slate-100 text-slate-600 border border-slate-200">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />
              </svg>
            </span>
            Riwayat Rekonsiliasi Shift (Audit Trail)
          </h2>
          <p class="text-slate-400 text-xs mt-1">Daftar semua shift kasir yang telah ditutup dan direkonsiliasi.</p>
        </div>

        <div class="flex items-center gap-2">
          <!-- Search Box -->
          <div class="relative">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari kasir atau stasiun..."
              class="pl-9 pr-4 py-2 text-xs border border-slate-200 rounded-lg bg-white text-slate-700 outline-none focus:border-indigo-400 focus:ring-1 focus:ring-indigo-400/30 transition-all w-48"
            />
          </div>
          <button
            @click="fetchShifts"
            :disabled="loadingShifts"
            class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition-all duration-200 flex items-center gap-1.5 cursor-pointer shadow-sm"
          >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5" :class="{ 'animate-spin': loadingShifts }">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
            Refresh
          </button>
        </div>
      </div>

      <div class="overflow-x-auto rounded-xl border border-slate-100 bg-white">
        <table class="w-full border-collapse text-left text-sm text-slate-600">
          <thead class="bg-slate-50 text-xs font-bold text-slate-500 uppercase border-b border-slate-200">
            <tr>
              <th scope="col" class="px-5 py-3.5">ID Shift</th>
              <th scope="col" class="px-5 py-3.5">Kasir</th>
              <th scope="col" class="px-5 py-3.5">Stasiun</th>
              <th scope="col" class="px-5 py-3.5">Tutup Shift</th>
              <th scope="col" class="px-5 py-3.5 text-right">Total Penjualan</th>
              <th scope="col" class="px-5 py-3.5 text-center">Selisih Kas</th>
              <th scope="col" class="px-5 py-3.5 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">

            <tr v-if="loadingShifts">
              <td colspan="7" class="px-5 py-12 text-center text-slate-400">
                <div class="w-8 h-8 border-3 border-indigo-400 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
                Memuat riwayat shift...
              </td>
            </tr>

            <tr v-else-if="filteredHistory.length === 0">
              <td colspan="7" class="px-5 py-12 text-center text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 mx-auto mb-2 text-slate-300">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />
                </svg>
                Belum ada riwayat shift yang ditutup.
              </td>
            </tr>

            <tr
              v-for="s in filteredHistory"
              :key="s.id"
              class="hover:bg-slate-50/60 transition-colors"
            >
              <td class="px-5 py-4">
                <span class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded font-mono text-xs font-bold">#{{ s.id }}</span>
              </td>
              <td class="px-5 py-4 font-semibold text-slate-800">{{ s.user?.name || s.cashier_name || '-' }}</td>
              <td class="px-5 py-4 text-slate-500">{{ s.station?.name || '-' }}</td>
              <td class="px-5 py-4 text-slate-500 whitespace-nowrap">{{ formatDate(s.end_time) }}</td>
              <td class="px-5 py-4 font-bold text-right text-slate-800 whitespace-nowrap">{{ formatRupiah(s.total_sales) }}</td>
              <td class="px-5 py-4 text-center">
                <span
                  class="px-2.5 py-1 rounded-full text-xs font-bold"
                  :class="diffBadge(s.difference_cash).cls"
                >
                  {{ diffBadge(s.difference_cash).label }}
                </span>
              </td>
              <td class="px-5 py-4 text-center">
                <button
                  @click="openHistoryDetail(s)"
                  :disabled="loadingHistory"
                  class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-lg transition-all cursor-pointer border border-indigo-200/60"
                >
                  Detail
                </button>
              </td>
            </tr>

          </tbody>
        </table>
      </div>
    </div>

    <!-- ================================================================ -->
    <!-- DETAIL MODAL -->
    <!-- ================================================================ -->
    <Transition name="modal-fade">
      <div
        v-if="showDetailModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @click.self="closeDetailModal"
      >
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto z-10">

          <!-- Modal Header -->
          <div class="flex items-center justify-between p-6 border-b border-slate-100 bg-gradient-to-r from-indigo-500 to-violet-600 rounded-t-2xl">
            <div>
              <h3 class="text-lg font-extrabold text-white">Detail Rekonsiliasi Shift</h3>
              <p class="text-indigo-200 text-xs mt-0.5">Hasil akhir rekonsiliasi kasir & posting jurnal GL</p>
            </div>
            <button
              @click="closeDetailModal"
              class="p-2 rounded-lg bg-white/20 hover:bg-white/30 text-white transition-all cursor-pointer"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Modal Body -->
          <div class="p-6 space-y-5" v-if="selectedHistoryShift">

            <!-- Shift Info -->
            <div class="bg-slate-50 rounded-xl p-4 grid grid-cols-2 gap-3 text-sm">
              <div>
                <p class="text-slate-400 text-xs">Kasir</p>
                <p class="font-bold text-slate-800">{{ selectedHistoryShift.shift?.cashier_name || selectedHistoryShift.cashier_name }}</p>
              </div>
              <div>
                <p class="text-slate-400 text-xs">Stasiun</p>
                <p class="font-bold text-slate-800">{{ selectedHistoryShift.shift?.station_name || selectedHistoryShift.station_name }}</p>
              </div>
              <div>
                <p class="text-slate-400 text-xs">Mulai Shift</p>
                <p class="font-semibold text-slate-700">{{ formatDate(selectedHistoryShift.shift?.start_time) }}</p>
              </div>
              <div>
                <p class="text-slate-400 text-xs">Tutup Shift</p>
                <p class="font-semibold text-slate-700">{{ formatDate(selectedHistoryShift.shift?.end_time || selectedHistoryShift.end_time) }}</p>
              </div>
              <div v-if="selectedHistoryShift.journal_reference">
                <p class="text-slate-400 text-xs">No. Jurnal</p>
                <p class="font-mono font-bold text-indigo-600">{{ selectedHistoryShift.journal_reference }}</p>
              </div>
            </div>

            <!-- Reconciliation Table -->
            <div v-if="selectedHistoryShift.reconciliation || selectedHistoryShift.final_reconciliation">
              <h4 class="text-sm font-bold text-slate-700 mb-3">Rincian Rekonsiliasi</h4>
              <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-sm border-collapse">
                  <thead class="bg-slate-50 text-xs font-bold text-slate-500 uppercase border-b border-slate-200">
                    <tr>
                      <th class="px-4 py-3 text-left">Tipe Pembayaran</th>
                      <th class="px-4 py-3 text-right">Ekspektasi</th>
                      <th class="px-4 py-3 text-right">Aktual</th>
                      <th class="px-4 py-3 text-center">Selisih</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100">
                    <template v-if="selectedHistoryShift.reconciliation">
                      <tr>
                        <td class="px-4 py-3 font-semibold text-slate-700">Kas Tunai</td>
                        <td class="px-4 py-3 text-right text-slate-600">{{ formatRupiah(selectedHistoryShift.reconciliation.expected_cash) }}</td>
                        <td class="px-4 py-3 text-right font-bold text-slate-800">{{ formatRupiah(selectedHistoryShift.reconciliation.actual_cash) }}</td>
                        <td class="px-4 py-3 text-center">
                          <span class="px-2 py-0.5 rounded text-xs font-bold" :class="diffBadge(selectedHistoryShift.reconciliation.difference_cash).cls">
                            {{ diffBadge(selectedHistoryShift.reconciliation.difference_cash).label }}
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td class="px-4 py-3 font-semibold text-slate-700">QRIS</td>
                        <td class="px-4 py-3 text-right text-slate-600">{{ formatRupiah(selectedHistoryShift.reconciliation.expected_qris) }}</td>
                        <td class="px-4 py-3 text-right font-bold text-slate-800">{{ formatRupiah(selectedHistoryShift.reconciliation.actual_qris) }}</td>
                        <td class="px-4 py-3 text-center">
                          <span class="px-2 py-0.5 rounded text-xs font-bold" :class="diffBadge(selectedHistoryShift.reconciliation.difference_qris).cls">
                            {{ diffBadge(selectedHistoryShift.reconciliation.difference_qris).label }}
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td class="px-4 py-3 font-semibold text-slate-700">Kartu Debit/Kredit</td>
                        <td class="px-4 py-3 text-right text-slate-600">{{ formatRupiah(selectedHistoryShift.reconciliation.expected_card) }}</td>
                        <td class="px-4 py-3 text-right font-bold text-slate-800">{{ formatRupiah(selectedHistoryShift.reconciliation.actual_card) }}</td>
                        <td class="px-4 py-3 text-center">
                          <span class="px-2 py-0.5 rounded text-xs font-bold" :class="diffBadge(selectedHistoryShift.reconciliation.difference_card).cls">
                            {{ diffBadge(selectedHistoryShift.reconciliation.difference_card).label }}
                          </span>
                        </td>
                      </tr>
                      <!-- Cash Status Banner -->
                      <tr class="bg-slate-50 border-t-2 border-slate-200">
                        <td colspan="4" class="px-4 py-3 text-center font-bold text-sm" :class="{
                          'text-emerald-600': selectedHistoryShift.reconciliation.cash_status === 'BALANCE',
                          'text-blue-600': selectedHistoryShift.reconciliation.cash_status === 'OVERAGE',
                          'text-rose-600': selectedHistoryShift.reconciliation.cash_status === 'SHORTAGE',
                        }">
                          {{ selectedHistoryShift.reconciliation.cash_status_label }}
                        </td>
                      </tr>
                    </template>
                    <template v-else-if="selectedHistoryShift.final_reconciliation">
                      <tr>
                        <td class="px-4 py-3 font-semibold text-slate-700">Kas Tunai</td>
                        <td class="px-4 py-3 text-right text-slate-600">{{ formatRupiah(selectedHistoryShift.final_reconciliation.expected_cash) }}</td>
                        <td class="px-4 py-3 text-right font-bold text-slate-800">{{ formatRupiah(selectedHistoryShift.final_reconciliation.actual_cash) }}</td>
                        <td class="px-4 py-3 text-center">
                          <span class="px-2 py-0.5 rounded text-xs font-bold" :class="diffBadge(selectedHistoryShift.final_reconciliation.difference_cash).cls">
                            {{ diffBadge(selectedHistoryShift.final_reconciliation.difference_cash).label }}
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td class="px-4 py-3 font-semibold text-slate-700">QRIS</td>
                        <td class="px-4 py-3 text-right text-slate-600">{{ formatRupiah(selectedHistoryShift.final_reconciliation.expected_qris) }}</td>
                        <td class="px-4 py-3 text-right font-bold text-slate-800">{{ formatRupiah(selectedHistoryShift.final_reconciliation.actual_qris) }}</td>
                        <td class="px-4 py-3 text-center">
                          <span class="px-2 py-0.5 rounded text-xs font-bold" :class="diffBadge(selectedHistoryShift.final_reconciliation.difference_qris).cls">
                            {{ diffBadge(selectedHistoryShift.final_reconciliation.difference_qris).label }}
                          </span>
                        </td>
                      </tr>
                      <tr>
                        <td class="px-4 py-3 font-semibold text-slate-700">Kartu</td>
                        <td class="px-4 py-3 text-right text-slate-600">{{ formatRupiah(selectedHistoryShift.final_reconciliation.expected_card) }}</td>
                        <td class="px-4 py-3 text-right font-bold text-slate-800">{{ formatRupiah(selectedHistoryShift.final_reconciliation.actual_card) }}</td>
                        <td class="px-4 py-3 text-center">
                          <span class="px-2 py-0.5 rounded text-xs font-bold" :class="diffBadge(selectedHistoryShift.final_reconciliation.difference_card).cls">
                            {{ diffBadge(selectedHistoryShift.final_reconciliation.difference_card).label }}
                          </span>
                        </td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>
            </div>

          </div>

          <!-- Modal Footer -->
          <div class="p-5 border-t border-slate-100 flex justify-end">
            <button
              @click="closeDetailModal"
              class="px-6 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-bold rounded-xl transition-all cursor-pointer"
            >
              Tutup
            </button>
          </div>

        </div>
      </div>
    </Transition>

  </div>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.25s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
</style>
