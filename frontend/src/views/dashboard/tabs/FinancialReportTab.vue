<script setup>
import { ref, onMounted, watch } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()

// Tab state: 'balance-sheet', 'profit-loss', 'cash-flow'
const activeSubTab = ref('balance-sheet')

// Report data states
const balanceSheetData = ref(null)
const profitLossData = ref(null)
const cashFlowData = ref(null)

// Loading states
const loadingBS = ref(false)
const loadingPL = ref(false)
const loadingCF = ref(false)

// Date filter states
const bsEndDate = ref(new Date().toISOString().split('T')[0])

const plStartDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0])
const plEndDate = ref(new Date().toISOString().split('T')[0])

const cfStartDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0])
const cfEndDate = ref(new Date().toISOString().split('T')[0])

// Input error states
const errors = ref({
  bsEndDate: '',
  plStartDate: '',
  plEndDate: '',
  cfStartDate: '',
  cfEndDate: ''
})

// Currency Formatter
const formatCurrency = (val) => {
  if (val === undefined || val === null) {
    return 'Rp 0'
  }
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0
  }).format(val)
}

// Client-side validations
const validateBS = () => {
  errors.value.bsEndDate = ''
  if (!bsEndDate.value) {
    errors.value.bsEndDate = 'Tanggal akhir wajib diisi.'
    return false
  }
  return true
}

const validatePL = () => {
  errors.value.plStartDate = ''
  errors.value.plEndDate = ''
  let isValid = true
  if (!plStartDate.value) {
    errors.value.plStartDate = 'Tanggal awal wajib diisi.'
    isValid = false
  }
  if (!plEndDate.value) {
    errors.value.plEndDate = 'Tanggal akhir wajib diisi.'
    isValid = false
  }
  if (plStartDate.value && plEndDate.value && plStartDate.value > plEndDate.value) {
    errors.value.plEndDate = 'Tanggal akhir tidak boleh mendahului tanggal awal.'
    isValid = false
  }
  return isValid
}

const validateCF = () => {
  errors.value.cfStartDate = ''
  errors.value.cfEndDate = ''
  let isValid = true
  if (!cfStartDate.value) {
    errors.value.cfStartDate = 'Tanggal awal wajib diisi.'
    isValid = false
  }
  if (!cfEndDate.value) {
    errors.value.cfEndDate = 'Tanggal akhir wajib diisi.'
    isValid = false
  }
  if (cfStartDate.value && cfEndDate.value && cfStartDate.value > cfEndDate.value) {
    errors.value.cfEndDate = 'Tanggal akhir tidak boleh mendahului tanggal awal.'
    isValid = false
  }
  return isValid
}

// Fetchers
const fetchBalanceSheet = async () => {
  if (!validateBS()) {
    return
  }
  loadingBS.value = true
  try {
    const res = await api.get('/reports/balance-sheet', {
      params: { end_date: bsEndDate.value }
    })
    balanceSheetData.value = res.data?.data || res.data
  } catch (err) {
    console.error(err)
    if (err.response?.status === 422) {
      const apiErrors = err.response.data?.errors || err.response.data?.data
      if (apiErrors?.end_date) {
        errors.value.bsEndDate = Array.isArray(apiErrors.end_date) ? apiErrors.end_date[0] : apiErrors.end_date
      } else {
        toast.error(err.response.data?.message || 'Gagal mengambil data Neraca.')
      }
    } else {
      toast.error('Gagal mengambil data Neraca.')
    }
  } finally {
    loadingBS.value = false
  }
}

const fetchProfitLoss = async () => {
  if (!validatePL()) {
    return
  }
  loadingPL.value = true
  try {
    const res = await api.get('/reports/profit-loss', {
      params: {
        start_date: plStartDate.value,
        end_date: plEndDate.value
      }
    })
    profitLossData.value = res.data?.data || res.data
  } catch (err) {
    console.error(err)
    if (err.response?.status === 422) {
      const apiErrors = err.response.data?.errors || err.response.data?.data
      if (apiErrors?.start_date) {
        errors.value.plStartDate = Array.isArray(apiErrors.start_date) ? apiErrors.start_date[0] : apiErrors.start_date
      }
      if (apiErrors?.end_date) {
        errors.value.plEndDate = Array.isArray(apiErrors.end_date) ? apiErrors.end_date[0] : apiErrors.end_date
      }
      if (!apiErrors?.start_date && !apiErrors?.end_date) {
        toast.error(err.response.data?.message || 'Gagal mengambil data Laba Rugi.')
      }
    } else {
      toast.error('Gagal mengambil data Laba Rugi.')
    }
  } finally {
    loadingPL.value = false
  }
}

const fetchCashFlow = async () => {
  if (!validateCF()) {
    return
  }
  loadingCF.value = true
  try {
    const res = await api.get('/reports/cash-flow', {
      params: {
        start_date: cfStartDate.value,
        end_date: cfEndDate.value
      }
    })
    cashFlowData.value = res.data?.data || res.data
  } catch (err) {
    console.error(err)
    if (err.response?.status === 422) {
      const apiErrors = err.response.data?.errors || err.response.data?.data
      if (apiErrors?.start_date) {
        errors.value.cfStartDate = Array.isArray(apiErrors.start_date) ? apiErrors.start_date[0] : apiErrors.start_date
      }
      if (apiErrors?.end_date) {
        errors.value.cfEndDate = Array.isArray(apiErrors.end_date) ? apiErrors.cfEndDate[0] : apiErrors.end_date
      }
      if (!apiErrors?.start_date && !apiErrors?.end_date) {
        toast.error(err.response.data?.message || 'Gagal mengambil data Arus Kas.')
      }
    } else {
      toast.error('Gagal mengambil data Arus Kas.')
    }
  } finally {
    loadingCF.value = false
  }
}

// Watchers to trigger load on sub-tab change
watch(activeSubTab, (newTab) => {
  if (newTab === 'balance-sheet' && !balanceSheetData.value) {
    fetchBalanceSheet()
  } else if (newTab === 'profit-loss' && !profitLossData.value) {
    fetchProfitLoss()
  } else if (newTab === 'cash-flow' && !cashFlowData.value) {
    fetchCashFlow()
  }
})

onMounted(() => {
  fetchBalanceSheet()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="bg-gradient-to-r from-teal-500 via-emerald-500 to-green-600 p-6 rounded-3xl text-white shadow-md flex justify-between items-center">
      <div class="space-y-1">
        <h1 class="text-2xl font-black tracking-tight">Laporan Keuangan</h1>
        <p class="text-xs text-teal-50 opacity-90 font-medium">Neraca, Laba Rugi, dan Arus Kas Terintegrasi Buku Besar</p>
      </div>
      <div class="flex gap-2">
        <button 
          @click="activeSubTab = 'balance-sheet'"
          class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shadow-sm"
          :class="activeSubTab === 'balance-sheet' ? 'bg-white text-emerald-600' : 'bg-teal-600/30 text-teal-100 hover:bg-teal-600/50'"
        >
          Neraca (Balance Sheet)
        </button>
        <button 
          @click="activeSubTab = 'profit-loss'"
          class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shadow-sm"
          :class="activeSubTab === 'profit-loss' ? 'bg-white text-emerald-600' : 'bg-teal-600/30 text-teal-100 hover:bg-teal-600/50'"
        >
          Laba Rugi (P&L)
        </button>
        <button 
          @click="activeSubTab = 'cash-flow'"
          class="px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shadow-sm"
          :class="activeSubTab === 'cash-flow' ? 'bg-white text-emerald-600' : 'bg-teal-600/30 text-teal-100 hover:bg-teal-600/50'"
        >
          Arus Kas (Cash Flow)
        </button>
      </div>
    </div>

    <!-- ================== SUB-TAB 1: BALANCE SHEET ================== -->
    <div v-if="activeSubTab === 'balance-sheet'" class="space-y-6">
      <!-- Filters -->
      <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-wrap gap-4 items-center">
        <div class="text-xs font-bold text-gray-400 uppercase tracking-wider">Posisi Neraca Per Tanggal:</div>
        <div class="flex flex-col relative">
          <input 
            v-model="bsEndDate"
            type="date"
            class="px-3 py-2 border rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
            :class="errors.bsEndDate ? 'border-red-400' : 'border-gray-200'"
          />
          <p v-if="errors.bsEndDate" class="text-red-500 text-[10px] mt-1 absolute top-full left-0">{{ errors.bsEndDate }}</p>
        </div>
        <button 
          @click="fetchBalanceSheet"
          class="ml-auto px-4 py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-xs font-bold text-emerald-700 flex items-center gap-1.5 transition-colors cursor-pointer"
          :disabled="loadingBS"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5" :class="{'animate-spin': loadingBS}">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
          </svg>
          Segarkan Laporan
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loadingBS" class="bg-white p-12 rounded-2xl border border-gray-100 shadow-sm text-center text-gray-400">
        <div class="flex items-center justify-center gap-2">
          <svg class="animate-spin h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span class="font-medium text-xs">Menghitung posisi neraca saldo akun riil...</span>
        </div>
      </div>

      <!-- Data Display -->
      <div v-else-if="balanceSheetData" class="space-y-6">
        <!-- Balance status bar -->
        <div 
          class="p-4 rounded-2xl border flex items-center justify-between shadow-sm"
          :class="balanceSheetData.metadata?.is_balanced ? 'bg-emerald-50 border-emerald-100 text-emerald-800' : 'bg-red-50 border-red-100 text-red-800'"
        >
          <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider">
            <span class="p-1 rounded-full bg-white shadow-sm">
              <svg v-if="balanceSheetData.metadata?.is_balanced" class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"></path>
              </svg>
              <svg v-else class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"></path>
              </svg>
            </span>
            <span>Status Persamaan Akuntansi (Aset = Kewajiban + Ekuitas):</span>
            <span :class="balanceSheetData.metadata?.is_balanced ? 'text-emerald-600' : 'text-red-600'">
              {{ balanceSheetData.metadata?.is_balanced ? 'SEIMBANG (BALANCED)' : 'TIDAK SEIMBANG' }}
            </span>
          </div>
          <div v-if="!balanceSheetData.metadata?.is_balanced" class="text-xs font-black font-mono">
            Selisih: {{ formatCurrency(balanceSheetData.metadata?.discrepancy) }}
          </div>
        </div>

        <!-- Columns assets vs liabilities -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Assets Section -->
          <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="bg-gradient-to-r from-emerald-500 to-teal-500 p-4 text-white text-xs font-black uppercase tracking-wider">
              Aset (Assets)
            </div>
            <div class="p-4 space-y-4">
              <table class="w-full text-xs text-left">
                <thead>
                  <tr class="border-b border-gray-50 text-gray-400 font-bold uppercase">
                    <th class="py-2">Kode</th>
                    <th class="py-2">Nama Akun</th>
                    <th class="py-2 text-right">Saldo</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 font-medium text-gray-700">
                  <tr v-for="item in balanceSheetData.assets?.items" :key="item.code" class="hover:bg-slate-50">
                    <td class="py-2.5 font-bold text-gray-400">{{ item.code }}</td>
                    <td class="py-2.5">{{ item.name }}</td>
                    <td class="py-2.5 text-right font-mono" :class="{'text-red-500': item.balance < 0}">{{ formatCurrency(item.balance) }}</td>
                  </tr>
                  <tr v-if="!balanceSheetData.assets?.items?.length">
                    <td colspan="3" class="py-4 text-center text-gray-400 font-medium">Tidak ada akun aset aktif.</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="bg-slate-50 border-t border-gray-100 p-4 flex justify-between items-center text-xs font-black text-gray-800">
              <span>TOTAL ASET</span>
              <span class="font-mono text-emerald-600">{{ formatCurrency(balanceSheetData.assets?.total) }}</span>
            </div>
          </div>

          <!-- Liabilities & Equity Section -->
          <div class="space-y-6">
            <!-- Liabilities -->
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
              <div class="bg-gradient-to-r from-slate-700 to-slate-800 p-4 text-white text-xs font-black uppercase tracking-wider">
                Kewajiban (Liabilities)
              </div>
              <div class="p-4 space-y-4">
                <table class="w-full text-xs text-left">
                  <thead>
                    <tr class="border-b border-gray-50 text-gray-400 font-bold uppercase">
                      <th class="py-2">Kode</th>
                      <th class="py-2">Nama Akun</th>
                      <th class="py-2 text-right">Saldo</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-50 font-medium text-gray-700">
                    <tr v-for="item in balanceSheetData.liabilities?.items" :key="item.code" class="hover:bg-slate-50">
                      <td class="py-2.5 font-bold text-gray-400">{{ item.code }}</td>
                      <td class="py-2.5">{{ item.name }}</td>
                      <td class="py-2.5 text-right font-mono" :class="{'text-red-500': item.balance < 0}">{{ formatCurrency(item.balance) }}</td>
                    </tr>
                    <tr v-if="!balanceSheetData.liabilities?.items?.length">
                      <td colspan="3" class="py-4 text-center text-gray-400 font-medium">Tidak ada akun kewajiban aktif.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="bg-slate-50 border-t border-gray-100 p-4 flex justify-between items-center text-xs font-black text-gray-800">
                <span>TOTAL KEWAJIBAN</span>
                <span class="font-mono">{{ formatCurrency(balanceSheetData.liabilities?.total) }}</span>
              </div>
            </div>

            <!-- Equity -->
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
              <div class="bg-gradient-to-r from-blue-700 to-blue-800 p-4 text-white text-xs font-black uppercase tracking-wider">
                Ekuitas (Equity)
              </div>
              <div class="p-4 space-y-4">
                <table class="w-full text-xs text-left">
                  <thead>
                    <tr class="border-b border-gray-50 text-gray-400 font-bold uppercase">
                      <th class="py-2">Kode</th>
                      <th class="py-2">Nama Akun</th>
                      <th class="py-2 text-right">Saldo</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-50 font-medium text-gray-700">
                    <tr v-for="item in balanceSheetData.equity?.items" :key="item.code" class="hover:bg-slate-50">
                      <td class="py-2.5 font-bold text-gray-400">{{ item.code }}</td>
                      <td class="py-2.5" :class="{'font-bold text-emerald-600': item.code === '3999'}">{{ item.name }}</td>
                      <td class="py-2.5 text-right font-mono" :class="{'text-red-500': item.balance < 0, 'font-bold text-emerald-600': item.code === '3999'}">{{ formatCurrency(item.balance) }}</td>
                    </tr>
                    <tr v-if="!balanceSheetData.equity?.items?.length">
                      <td colspan="3" class="py-4 text-center text-gray-400 font-medium">Tidak ada akun ekuitas aktif.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div class="bg-slate-50 border-t border-gray-100 p-4 flex justify-between items-center text-xs font-black text-gray-800">
                <span>TOTAL EKUITAS</span>
                <span class="font-mono">{{ formatCurrency(balanceSheetData.equity?.total) }}</span>
              </div>
            </div>

            <!-- Liabilities & Equity Summary Card -->
            <div class="bg-gray-800 text-white rounded-2xl p-4 flex justify-between items-center shadow-md text-xs font-black uppercase tracking-wider">
              <span>TOTAL KEWAJIBAN & EKUITAS</span>
              <span class="font-mono text-emerald-400">{{ formatCurrency(balanceSheetData.summary?.total_liabilities_and_equity) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ================== SUB-TAB 2: PROFIT & LOSS ================== -->
    <div v-if="activeSubTab === 'profit-loss'" class="space-y-6">
      <!-- Filters -->
      <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-wrap gap-4 items-center">
        <div class="text-xs font-bold text-gray-400 uppercase tracking-wider">Rentang Periode Laba Rugi:</div>
        <div class="flex items-center gap-2">
          <div class="flex flex-col relative">
            <input 
              v-model="plStartDate"
              type="date"
              class="px-3 py-2 border rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
              :class="errors.plStartDate ? 'border-red-400' : 'border-gray-200'"
            />
            <p v-if="errors.plStartDate" class="text-red-500 text-[10px] mt-1 absolute top-full left-0 whitespace-nowrap">{{ errors.plStartDate }}</p>
          </div>
          <span class="text-gray-400 text-xs font-medium">s/d</span>
          <div class="flex flex-col relative">
            <input 
              v-model="plEndDate"
              type="date"
              class="px-3 py-2 border rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
              :class="errors.plEndDate ? 'border-red-400' : 'border-gray-200'"
            />
            <p v-if="errors.plEndDate" class="text-red-500 text-[10px] mt-1 absolute top-full left-0 whitespace-nowrap">{{ errors.plEndDate }}</p>
          </div>
        </div>
        <button 
          @click="fetchProfitLoss"
          class="ml-auto px-4 py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-xs font-bold text-emerald-700 flex items-center gap-1.5 transition-colors cursor-pointer"
          :disabled="loadingPL"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5" :class="{'animate-spin': loadingPL}">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
          </svg>
          Segarkan Laporan
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loadingPL" class="bg-white p-12 rounded-2xl border border-gray-100 shadow-sm text-center text-gray-400">
        <div class="flex items-center justify-center gap-2">
          <svg class="animate-spin h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span class="font-medium text-xs">Menghitung laba rugi aktivitas operasional...</span>
        </div>
      </div>

      <!-- Data Display -->
      <div v-else-if="profitLossData" class="space-y-6">
        <!-- Net Profit Card Highlight -->
        <div 
          class="p-6 rounded-3xl border flex items-center justify-between shadow-sm bg-gradient-to-r"
          :class="profitLossData.summary?.net_profit >= 0 ? 'from-emerald-500 to-green-600 border-emerald-100 text-white' : 'from-red-500 to-rose-600 border-red-100 text-white'"
        >
          <div class="space-y-1">
            <h3 class="text-xs uppercase font-black tracking-widest text-emerald-50">Laba Rugi Bersih (Net Profit / Loss)</h3>
            <div class="text-3xl font-black font-mono">
              {{ formatCurrency(profitLossData.summary?.net_profit) }}
            </div>
            <p class="text-[10px] text-teal-50 opacity-90 font-medium">
              Hasil bersih dari pendapatan dikurangi beban pengeluaran operasional.
            </p>
          </div>
          <div class="p-3 bg-white/10 rounded-2xl">
            <svg v-if="profitLossData.summary?.net_profit >= 0" class="w-8 h-8 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"></path>
            </svg>
            <svg v-else class="w-8 h-8 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6L9 12.75l4.306-4.307a11.95 11.95 0 015.814 5.519l2.74 1.22m0 0l-5.94 2.28m5.94-2.28l-2.28-5.941"></path>
            </svg>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Revenues Card -->
          <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="bg-gradient-to-r from-emerald-500 to-teal-500 p-4 text-white text-xs font-black uppercase tracking-wider">
              Pendapatan (Revenues)
            </div>
            <div class="p-4">
              <table class="w-full text-xs text-left">
                <thead>
                  <tr class="border-b border-gray-50 text-gray-400 font-bold uppercase">
                    <th class="py-2">Kode</th>
                    <th class="py-2">Nama Akun</th>
                    <th class="py-2 text-right">Nominal</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 font-medium text-gray-700">
                  <tr v-for="item in profitLossData.revenues?.items" :key="item.code" class="hover:bg-slate-50">
                    <td class="py-2.5 font-bold text-gray-400">{{ item.code }}</td>
                    <td class="py-2.5">{{ item.name }}</td>
                    <td class="py-2.5 text-right font-mono">{{ formatCurrency(item.balance) }}</td>
                  </tr>
                  <tr v-if="!profitLossData.revenues?.items?.length">
                    <td colspan="3" class="py-4 text-center text-gray-400 font-medium">Tidak ada akun pendapatan aktif.</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="bg-slate-50 border-t border-gray-100 p-4 flex justify-between items-center text-xs font-black text-gray-800">
              <span>TOTAL PENDAPATAN</span>
              <span class="font-mono text-emerald-600">{{ formatCurrency(profitLossData.revenues?.total) }}</span>
            </div>
          </div>

          <!-- Expenses Card -->
          <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="bg-gradient-to-r from-orange-500 to-red-500 p-4 text-white text-xs font-black uppercase tracking-wider">
              Beban Pengeluaran (Expenses)
            </div>
            <div class="p-4">
              <table class="w-full text-xs text-left">
                <thead>
                  <tr class="border-b border-gray-50 text-gray-400 font-bold uppercase">
                    <th class="py-2">Kode</th>
                    <th class="py-2">Nama Akun</th>
                    <th class="py-2 text-right">Nominal</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 font-medium text-gray-700">
                  <tr v-for="item in profitLossData.expenses?.items" :key="item.code" class="hover:bg-slate-50">
                    <td class="py-2.5 font-bold text-gray-400">{{ item.code }}</td>
                    <td class="py-2.5">{{ item.name }}</td>
                    <td class="py-2.5 text-right font-mono text-red-500">{{ formatCurrency(item.balance) }}</td>
                  </tr>
                  <tr v-if="!profitLossData.expenses?.items?.length">
                    <td colspan="3" class="py-4 text-center text-gray-400 font-medium">Tidak ada akun beban aktif.</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="bg-slate-50 border-t border-gray-100 p-4 flex justify-between items-center text-xs font-black text-gray-800">
              <span>TOTAL BEBAN PENGELUARAN</span>
              <span class="font-mono text-red-500">{{ formatCurrency(profitLossData.expenses?.total) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ================== SUB-TAB 3: CASH FLOW ================== -->
    <div v-if="activeSubTab === 'cash-flow'" class="space-y-6">
      <!-- Filters -->
      <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-wrap gap-4 items-center">
        <div class="text-xs font-bold text-gray-400 uppercase tracking-wider">Rentang Periode Arus Kas:</div>
        <div class="flex items-center gap-2">
          <div class="flex flex-col relative">
            <input 
              v-model="cfStartDate"
              type="date"
              class="px-3 py-2 border rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
              :class="errors.cfStartDate ? 'border-red-400' : 'border-gray-200'"
            />
            <p v-if="errors.cfStartDate" class="text-red-500 text-[10px] mt-1 absolute top-full left-0 whitespace-nowrap">{{ errors.cfStartDate }}</p>
          </div>
          <span class="text-gray-400 text-xs font-medium">s/d</span>
          <div class="flex flex-col relative">
            <input 
              v-model="cfEndDate"
              type="date"
              class="px-3 py-2 border rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
              :class="errors.cfEndDate ? 'border-red-400' : 'border-gray-200'"
            />
            <p v-if="errors.cfEndDate" class="text-red-500 text-[10px] mt-1 absolute top-full left-0 whitespace-nowrap">{{ errors.cfEndDate }}</p>
          </div>
        </div>
        <button 
          @click="fetchCashFlow"
          class="ml-auto px-4 py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-xs font-bold text-emerald-700 flex items-center gap-1.5 transition-colors cursor-pointer"
          :disabled="loadingCF"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5" :class="{'animate-spin': loadingCF}">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
          </svg>
          Segarkan Laporan
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loadingCF" class="bg-white p-12 rounded-2xl border border-gray-100 shadow-sm text-center text-gray-400">
        <div class="flex items-center justify-center gap-2">
          <svg class="animate-spin h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span class="font-medium text-xs">Menelusuri mutasi jurnal umum akun kas...</span>
        </div>
      </div>

      <!-- Data Display -->
      <div v-else-if="cashFlowData" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Operating Activity Card -->
          <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden flex flex-col justify-between">
            <div>
              <div class="bg-slate-700 text-white p-4 text-xs font-black uppercase tracking-wider">Aktivitas Operasional</div>
              <div class="p-4 space-y-3 text-xs text-gray-600 font-medium">
                <div class="flex justify-between">
                  <span>Penerimaan Kas dari Pelanggan:</span>
                  <span class="font-mono text-emerald-600 font-bold">+{{ formatCurrency(cashFlowData.operating_activities?.cash_inflow_customers) }}</span>
                </div>
                <div class="flex justify-between">
                  <span>Pembayaran Kas ke Supplier:</span>
                  <span class="font-mono text-red-500 font-bold">-{{ formatCurrency(cashFlowData.operating_activities?.cash_outflow_suppliers) }}</span>
                </div>
                <div class="flex justify-between">
                  <span>Pembayaran Kas untuk Beban:</span>
                  <span class="font-mono text-red-500 font-bold">-{{ formatCurrency(cashFlowData.operating_activities?.cash_outflow_expenses) }}</span>
                </div>
              </div>
            </div>
            <div class="bg-slate-50 border-t border-gray-100 p-4 flex justify-between items-center text-xs font-black text-gray-800">
              <span>ARUS KAS OPERASIONAL</span>
              <span class="font-mono" :class="cashFlowData.operating_activities?.net_cash_from_operating >= 0 ? 'text-emerald-600' : 'text-red-500'">
                {{ formatCurrency(cashFlowData.operating_activities?.net_cash_from_operating) }}
              </span>
            </div>
          </div>

          <!-- Investing Activity Card -->
          <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden flex flex-col justify-between">
            <div>
              <div class="bg-slate-700 text-white p-4 text-xs font-black uppercase tracking-wider">Aktivitas Investasi</div>
              <div class="p-4 space-y-3 text-xs text-gray-600 font-medium">
                <div class="flex justify-between">
                  <span>Pembelian Aset Tetap:</span>
                  <span class="font-mono text-gray-500 font-bold">Rp 0</span>
                </div>
                <div class="flex justify-between">
                  <span>Penerimaan Investasi:</span>
                  <span class="font-mono text-gray-500 font-bold">Rp 0</span>
                </div>
              </div>
            </div>
            <div class="bg-slate-50 border-t border-gray-100 p-4 flex justify-between items-center text-xs font-black text-gray-800">
              <span>ARUS KAS INVESTASI</span>
              <span class="font-mono text-gray-500">{{ formatCurrency(cashFlowData.investing_activities?.net_cash_from_investing) }}</span>
            </div>
          </div>

          <!-- Financing Activity Card -->
          <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden flex flex-col justify-between">
            <div>
              <div class="bg-slate-700 text-white p-4 text-xs font-black uppercase tracking-wider">Aktivitas Pendanaan</div>
              <div class="p-4 space-y-3 text-xs text-gray-600 font-medium">
                <div class="flex justify-between">
                  <span>Setoran Modal Pemilik:</span>
                  <span class="font-mono text-emerald-600 font-bold">+{{ formatCurrency(cashFlowData.financing_activities?.cash_inflow_owners) }}</span>
                </div>
                <div class="flex justify-between">
                  <span>Penarikan Modal / Prive:</span>
                  <span class="font-mono text-red-500 font-bold">-{{ formatCurrency(cashFlowData.financing_activities?.cash_outflow_owners) }}</span>
                </div>
              </div>
            </div>
            <div class="bg-slate-50 border-t border-gray-100 p-4 flex justify-between items-center text-xs font-black text-gray-800">
              <span>ARUS KAS PENDANAAN</span>
              <span class="font-mono" :class="cashFlowData.financing_activities?.net_cash_from_financing >= 0 ? 'text-emerald-600' : 'text-red-500'">
                {{ formatCurrency(cashFlowData.financing_activities?.net_cash_from_financing) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Cash Flow Summary Card (Bottom Card) -->
        <div class="bg-gradient-to-r from-gray-800 via-gray-900 to-black text-white rounded-3xl p-6 shadow-lg space-y-4">
          <div class="text-xs uppercase font-black tracking-widest text-gray-400">Ikhtisar Pergerakan Kas (Direct Method)</div>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-2">
            <div class="space-y-1">
              <span class="text-[10px] text-gray-400 font-bold uppercase">Saldo Kas Awal Periode</span>
              <div class="text-lg font-black font-mono text-gray-300">
                {{ formatCurrency(cashFlowData.summary?.beginning_cash_balance) }}
              </div>
            </div>
            <div class="space-y-1 border-y md:border-y-0 md:border-x border-gray-700/60 py-3 md:py-0 md:px-6">
              <span class="text-[10px] text-gray-400 font-bold uppercase">Kenaikan / Penurunan Bersih</span>
              <div class="text-lg font-black font-mono" :class="cashFlowData.summary?.net_cash_increase >= 0 ? 'text-emerald-400' : 'text-rose-400'">
                {{ cashFlowData.summary?.net_cash_increase >= 0 ? '+' : '' }}{{ formatCurrency(cashFlowData.summary?.net_cash_increase) }}
              </div>
            </div>
            <div class="space-y-1 md:pl-6">
              <span class="text-[10px] text-emerald-400 font-bold uppercase">Saldo Kas Akhir Periode</span>
              <div class="text-2xl font-black font-mono text-emerald-400">
                {{ formatCurrency(cashFlowData.summary?.ending_cash_balance) }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
