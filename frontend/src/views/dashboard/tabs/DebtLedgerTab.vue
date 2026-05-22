<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()

// Active Sub-Tab: 'ap' (Accounts Payable - Utang) or 'ar' (Accounts Receivable - Piutang)
const activeSubTab = ref('ap')

// Master / Reference lists
const suppliers = ref([])
const customers = ref([])
const accounts = ref([])

// Loadings
const loadingLedger = ref(false)
const loadingAging = ref(false)
const submittingForm = ref(false)

// Search & Filter state
const filterSupplierId = ref('')
const filterCustomerId = ref('')
const searchQuery = ref('')

// Ledger & Aging metrics data
const apLedger = ref([])
const apSummary = ref({
  total_invoices_outstanding: 0,
  total_debt_value: 0,
  total_amount_paid: 0,
  total_outstanding_balance: 0
})
const apAgingData = ref({
  total_outstanding_ap: 0,
  buckets: { current: 0, aging_31_60: 0, aging_61_90: 0, over_90: 0 },
  percentage: { current: '0%', aging_31_60: '0%', aging_61_90: '0%', over_90: '0%' }
})

const arLedger = ref([])
const arSummary = ref({
  total_invoices_outstanding: 0,
  total_receivable_value: 0,
  total_amount_received: 0,
  total_outstanding_balance: 0
})
const arAgingData = ref({
  total_outstanding_ar: 0,
  buckets: { current: 0, aging_31_60: 0, aging_61_90: 0, over_90: 0 },
  percentage: { current: '0%', aging_31_60: '0%', aging_61_90: '0%', over_90: '0%' }
})

// Modal / Form state for settlement
const showModal = ref(false)
const activeTransaction = ref(null) // holds selected purchase or sale record

const form = ref({
  amount: '',
  bank_account_code: '',
  notes: ''
})

const errors = ref({
  amount: '',
  bank_account_code: '',
  notes: ''
})

// Cash/Bank account filter (type: asset, specific codes)
const cashBankAccounts = computed(() => {
  return accounts.value.filter(acc => 
    acc.is_active && 
    (acc.type === 'asset' || acc.code === '1101' || acc.code === '1102')
  )
})

// Filtered lists based on search string
const filteredApLedger = computed(() => {
  let list = apLedger.value
  if (filterSupplierId.value) {
    list = list.filter(item => item.supplier_id === Number(filterSupplierId.value) || item.supplier_name.toLowerCase().includes(suppliers.value.find(s => s.id === Number(filterSupplierId.value))?.name.toLowerCase() || ''))
  }
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(item => 
      item.reference_no.toLowerCase().includes(q) || 
      item.supplier_name.toLowerCase().includes(q)
    )
  }
  return list
})

const filteredArLedger = computed(() => {
  let list = arLedger.value
  if (filterCustomerId.value) {
    list = list.filter(item => item.customer_id === Number(filterCustomerId.value) || item.customer_name.toLowerCase().includes(customers.value.find(c => c.id === Number(filterCustomerId.value))?.name.toLowerCase() || ''))
  }
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(item => 
      item.invoice_no.toLowerCase().includes(q) || 
      item.customer_name.toLowerCase().includes(q)
    )
  }
  return list
})

// Fetch master data
const fetchSuppliers = async () => {
  try {
    const res = await api.get('/suppliers')
    suppliers.value = res.data?.data || res.data || []
  } catch (err) {
    console.error('Error fetching suppliers:', err)
  }
}

const fetchCustomers = async () => {
  try {
    const res = await api.get('/customers')
    customers.value = res.data?.data || res.data || []
  } catch (err) {
    console.error('Error fetching customers:', err)
  }
}

const fetchAccounts = async () => {
  try {
    const res = await api.get('/accounts')
    accounts.value = res.data?.data || res.data || []
  } catch (err) {
    console.error('Error fetching accounts:', err)
  }
}

// Fetch ledger/aging reports
const fetchApData = async () => {
  loadingLedger.value = true
  try {
    const res = await api.get('/debt/ap-ledger')
    const resData = res.data?.data || res.data
    apLedger.value = resData?.ledger || []
    apSummary.value = resData?.summary || {
      total_invoices_outstanding: 0,
      total_debt_value: 0,
      total_amount_paid: 0,
      total_outstanding_balance: 0
    }
  } catch (err) {
    console.error('Error fetching AP Ledger:', err)
    toast.error('Gagal memuat buku besar utang dagang (AP).')
  } finally {
    loadingLedger.value = false
  }

  loadingAging.value = true
  try {
    const res = await api.get('/debt/ap-aging')
    apAgingData.value = res.data?.data || res.data || {
      total_outstanding_ap: 0,
      buckets: { current: 0, aging_31_60: 0, aging_61_90: 0, over_90: 0 },
      percentage: { current: '0%', aging_31_60: '0%', aging_61_90: '0%', over_90: '0%' }
    }
  } catch (err) {
    console.error('Error fetching AP Aging:', err)
  } finally {
    loadingAging.value = false
  }
}

const fetchArData = async () => {
  loadingLedger.value = true
  try {
    const res = await api.get('/debt/ar-ledger')
    const resData = res.data?.data || res.data
    arLedger.value = resData?.ledger || []
    arSummary.value = resData?.summary || {
      total_invoices_outstanding: 0,
      total_receivable_value: 0,
      total_amount_received: 0,
      total_outstanding_balance: 0
    }
  } catch (err) {
    console.error('Error fetching AR Ledger:', err)
    toast.error('Gagal memuat buku besar piutang dagang (AR).')
  } finally {
    loadingLedger.value = false
  }

  loadingAging.value = true
  try {
    const res = await api.get('/debt/ar-aging')
    arAgingData.value = res.data?.data || res.data || {
      total_outstanding_ar: 0,
      buckets: { current: 0, aging_31_60: 0, aging_61_90: 0, over_90: 0 },
      percentage: { current: '0%', aging_31_60: '0%', aging_61_90: '0%', over_90: '0%' }
    }
  } catch (err) {
    console.error('Error fetching AR Aging:', err)
  } finally {
    loadingAging.value = false
  }
}

const handleSubTabChange = (tab) => {
  activeSubTab.value = tab
  searchQuery.value = ''
  filterSupplierId.value = ''
  filterCustomerId.value = ''
  if (tab === 'ap') {
    fetchApData()
  } else {
    fetchArData()
  }
}

// Settlement quick-action trigger
const openSettlementModal = (item) => {
  activeTransaction.value = item
  form.value = {
    amount: '',
    bank_account_code: cashBankAccounts.value[0]?.code || '',
    notes: ''
  }
  errors.value = {
    amount: '',
    bank_account_code: '',
    notes: ''
  }
  showModal.value = true
}

// Auto-fill outstanding balance
const fillFullOutstanding = () => {
  if (!activeTransaction.value) return
  form.value.amount = activeSubTab.value === 'ap' 
    ? activeTransaction.value.outstanding_debt 
    : activeTransaction.value.outstanding_receivable
}

// Client-side validation
const validateForm = () => {
  let isValid = true
  errors.value = {
    amount: '',
    bank_account_code: '',
    notes: ''
  }

  if (!form.value.amount || Number(form.value.amount) <= 0) {
    errors.value.amount = 'Nominal transaksi wajib diisi dan harus lebih besar dari 0.'
    isValid = false
  }

  const outstanding = activeSubTab.value === 'ap' 
    ? Number(activeTransaction.value?.outstanding_debt || 0)
    : Number(activeTransaction.value?.outstanding_receivable || 0)

  if (Number(form.value.amount) > outstanding) {
    errors.value.amount = `Jumlah tidak boleh melebihi sisa outstanding (${formatCurrency(outstanding)})`
    isValid = false
  }

  if (!form.value.bank_account_code) {
    errors.value.bank_account_code = 'Bagan akun Kas/Bank wajib dipilih.'
    isValid = false
  }

  // Cash sufficiency check for Accounts Payable
  if (activeSubTab.value === 'ap') {
    const selectedAcc = cashBankAccounts.value.find(acc => acc.code === form.value.bank_account_code)
    if (selectedAcc && Number(form.value.amount) > Number(selectedAcc.balance)) {
      errors.value.amount = `Saldo Kas/Bank tidak cukup. Saldo saat ini: ${formatCurrency(selectedAcc.balance)}`
      isValid = false
    }
  }

  return isValid
}

// Form Submission
const handleSettlementSubmit = async () => {
  if (!validateForm()) return

  submittingForm.value = true
  
  const isAp = activeSubTab.value === 'ap'
  const endpoint = isAp ? '/debt/pay-ap' : '/debt/receive-ar'
  const payload = {
    bank_account_code: form.value.bank_account_code,
    payment_amount: Number(form.value.amount),
    notes: form.value.notes || undefined
  }

  if (isAp) {
    payload.purchase_id = activeTransaction.value.purchase_id
  } else {
    payload.sale_id = activeTransaction.value.sale_id
  }

  try {
    const res = await api.post(endpoint, payload)
    toast.success(res.data?.message || 'Transaksi berhasil diproses & dicatat di jurnal umum.')
    showModal.value = false
    
    // Refresh ledger, aging status, and account list
    await Promise.all([
      isAp ? fetchApData() : fetchArData(),
      fetchAccounts()
    ])
  } catch (err) {
    console.error('Error submitting transaction:', err)
    if (err.response && err.response.status === 422) {
      const serverErrors = err.response.data.errors || {}
      Object.keys(serverErrors).forEach(key => {
        // map API inputs: payment_amount -> amount
        const mappedKey = key === 'payment_amount' ? 'amount' : key
        if (errors.value[mappedKey] !== undefined) {
          errors.value[mappedKey] = Array.isArray(serverErrors[key]) ? serverErrors[key][0] : serverErrors[key]
        }
      })
      if (err.response.data.message) {
        toast.error(err.response.data.message)
      } else {
        toast.error('Periksa kembali input data pembayaran Anda.')
      }
    } else {
      toast.error(err.response?.data?.message || 'Gagal memproses transaksi pelunasan.')
    }
  } finally {
    submittingForm.value = false
  }
}

// Helpers
const formatCurrency = (val) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0
  }).format(val || 0)
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

// Init
onMounted(async () => {
  await Promise.all([
    fetchSuppliers(),
    fetchCustomers(),
    fetchAccounts()
  ])
  
  if (cashBankAccounts.value.length > 0) {
    form.value.bank_account_code = cashBankAccounts.value[0].code
  }

  // Load AP by default
  fetchApData()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header Card -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
      <div>
        <h2 class="text-xl font-bold text-gray-800">Debt & Receivable Ledger</h2>
        <p class="text-xs text-gray-500">Kelola buku besar utang dagang (Accounts Payable) dan piutang dagang (Accounts Receivable) serta pencatatan pelunasan otomatis.</p>
      </div>

      <!-- Main Sub Tab Navigator -->
      <div class="flex bg-gray-100 p-1.5 rounded-xl text-xs font-bold text-gray-600 gap-1 w-full md:w-auto">
        <button 
          @click="handleSubTabChange('ap')"
          class="flex-1 md:flex-none px-4 py-2 rounded-lg transition-all cursor-pointer"
          :class="activeSubTab === 'ap' ? 'bg-white text-indigo-600 shadow-sm' : 'hover:bg-white/50 text-gray-500'"
        >
          Accounts Payable (Utang)
        </button>
        <button 
          @click="handleSubTabChange('ar')"
          class="flex-1 md:flex-none px-4 py-2 rounded-lg transition-all cursor-pointer"
          :class="activeSubTab === 'ar' ? 'bg-white text-indigo-600 shadow-sm' : 'hover:bg-white/50 text-gray-500'"
        >
          Accounts Receivable (Piutang)
        </button>
      </div>
    </div>

    <!-- Active Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      
      <!-- Summary and Aging Stats (Left Side 1-column on lg) -->
      <div class="lg:col-span-1 space-y-6">
        
        <!-- Summary Card: Info totals -->
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm space-y-4">
          <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Ringkasan Buku Ledger</h3>
          
          <div class="space-y-3">
            <div>
              <p class="text-xs text-gray-400">Total Invoice Outstanding</p>
              <p class="text-lg font-black text-gray-700 font-mono">
                {{ activeSubTab === 'ap' ? apSummary.total_invoices_outstanding : arSummary.total_invoices_outstanding }} Invoice
              </p>
            </div>
            
            <div>
              <p class="text-xs text-gray-400">Total Nilai Tagihan</p>
              <p class="text-lg font-black text-gray-700 font-mono">
                {{ formatCurrency(activeSubTab === 'ap' ? apSummary.total_debt_value : arSummary.total_receivable_value) }}
              </p>
            </div>

            <div>
              <p class="text-xs text-gray-400">Total Terbayar/Diterima</p>
              <p class="text-lg font-black text-emerald-600 font-mono">
                {{ formatCurrency(activeSubTab === 'ap' ? apSummary.total_amount_paid : arSummary.total_amount_received) }}
              </p>
            </div>

            <hr class="border-gray-100" />

            <div>
              <p class="text-xs text-gray-400">Net Sisa Outstanding</p>
              <p class="text-xl font-black text-red-500 font-mono">
                {{ formatCurrency(activeSubTab === 'ap' ? apSummary.total_outstanding_balance : arSummary.total_outstanding_balance) }}
              </p>
            </div>
          </div>
        </div>

        <!-- Aging Bucket Distributions -->
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm space-y-4">
          <div class="flex justify-between items-center">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Distribusi Umur (Aging)</h3>
            <span class="text-[10px] bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded font-bold">Auto</span>
          </div>

          <div class="space-y-4">
            <!-- Loading aging -->
            <div v-if="loadingAging" class="text-center py-4 text-xs text-gray-400">Loading aging...</div>
            <template v-else>
              <!-- 0-30 Days -->
              <div class="space-y-1">
                <div class="flex justify-between text-xs font-medium text-gray-600">
                  <span>0 - 30 Hari</span>
                  <span class="font-mono">{{ activeSubTab === 'ap' ? apAgingData.percentage.current : arAgingData.percentage.current }}</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-1.5">
                  <div 
                    class="bg-emerald-500 h-1.5 rounded-full transition-all duration-500" 
                    :style="{ width: activeSubTab === 'ap' ? apAgingData.percentage.current : arAgingData.percentage.current }"
                  ></div>
                </div>
                <p class="text-[10px] text-gray-400 font-mono text-right">
                  {{ formatCurrency(activeSubTab === 'ap' ? apAgingData.buckets.current : arAgingData.buckets.current) }}
                </p>
              </div>

              <!-- 31-60 Days -->
              <div class="space-y-1">
                <div class="flex justify-between text-xs font-medium text-gray-600">
                  <span>31 - 60 Hari</span>
                  <span class="font-mono">{{ activeSubTab === 'ap' ? apAgingData.percentage.aging_31_60 : arAgingData.percentage.aging_31_60 }}</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-1.5">
                  <div 
                    class="bg-blue-500 h-1.5 rounded-full transition-all duration-500" 
                    :style="{ width: activeSubTab === 'ap' ? apAgingData.percentage.aging_31_60 : arAgingData.percentage.aging_31_60 }"
                  ></div>
                </div>
                <p class="text-[10px] text-gray-400 font-mono text-right">
                  {{ formatCurrency(activeSubTab === 'ap' ? apAgingData.buckets.aging_31_60 : arAgingData.buckets.aging_31_60) }}
                </p>
              </div>

              <!-- 61-90 Days -->
              <div class="space-y-1">
                <div class="flex justify-between text-xs font-medium text-gray-600">
                  <span>61 - 90 Hari</span>
                  <span class="font-mono">{{ activeSubTab === 'ap' ? apAgingData.percentage.aging_61_90 : arAgingData.percentage.aging_61_90 }}</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-1.5">
                  <div 
                    class="bg-orange-500 h-1.5 rounded-full transition-all duration-500" 
                    :style="{ width: activeSubTab === 'ap' ? apAgingData.percentage.aging_61_90 : arAgingData.percentage.aging_61_90 }"
                  ></div>
                </div>
                <p class="text-[10px] text-gray-400 font-mono text-right">
                  {{ formatCurrency(activeSubTab === 'ap' ? apAgingData.buckets.aging_61_90 : arAgingData.buckets.aging_61_90) }}
                </p>
              </div>

              <!-- >90 Days -->
              <div class="space-y-1">
                <div class="flex justify-between text-xs font-medium text-gray-600">
                  <span>&gt; 90 Hari</span>
                  <span class="font-mono">{{ activeSubTab === 'ap' ? apAgingData.percentage.over_90 : arAgingData.percentage.over_90 }}</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-1.5">
                  <div 
                    class="bg-red-500 h-1.5 rounded-full transition-all duration-500" 
                    :style="{ width: activeSubTab === 'ap' ? apAgingData.percentage.over_90 : arAgingData.percentage.over_90 }"
                  ></div>
                </div>
                <p class="text-[10px] text-gray-400 font-mono text-right">
                  {{ formatCurrency(activeSubTab === 'ap' ? apAgingData.buckets.over_90 : arAgingData.buckets.over_90) }}
                </p>
              </div>
            </template>
          </div>
        </div>
      </div>

      <!-- Ledger Table List (Right Side 3-columns on lg) -->
      <div class="lg:col-span-3 space-y-6">
        
        <!-- Filter Card -->
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-wrap gap-4 items-center justify-between">
          <div class="flex items-center gap-3 flex-1 min-w-[280px]">
            <!-- Search bar -->
            <div class="relative w-full max-w-sm">
              <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.637 10.637z" />
                </svg>
              </span>
              <input 
                v-model="searchQuery"
                type="text" 
                :placeholder="activeSubTab === 'ap' ? 'Cari Ref No / Supplier...' : 'Cari Invoice / Customer...'"
                class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 transition-colors"
              />
            </div>

            <!-- Supplier Filter (AP) -->
            <select 
              v-if="activeSubTab === 'ap'"
              v-model="filterSupplierId"
              class="px-3 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 bg-white"
            >
              <option value="">Semua Supplier</option>
              <option v-for="sup in suppliers" :key="sup.id" :value="sup.id">
                {{ sup.name }}
              </option>
            </select>

            <!-- Customer Filter (AR) -->
            <select 
              v-if="activeSubTab === 'ar'"
              v-model="filterCustomerId"
              class="px-3 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 bg-white"
            >
              <option value="">Semua Pelanggan</option>
              <option v-for="cust in customers" :key="cust.id" :value="cust.id">
                {{ cust.name }}
              </option>
            </select>
          </div>

          <!-- Refresh action -->
          <button 
            @click="activeSubTab === 'ap' ? fetchApData() : fetchArData()"
            class="px-4 py-2 rounded-xl border border-gray-200 hover:bg-gray-50 text-xs font-bold text-gray-600 flex items-center gap-1.5 transition-colors cursor-pointer"
          >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5" :class="{'animate-spin': loadingLedger}">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
            Refresh
          </button>
        </div>

        <!-- Ledger Table List -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div v-if="loadingLedger" class="p-12 text-center text-gray-400 font-medium">
            <div class="flex items-center justify-center gap-2">
              <svg class="animate-spin h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Sedang mengambil data ledger...
            </div>
          </div>

          <div v-else-if="activeSubTab === 'ap'">
            <div class="overflow-x-auto">
              <table class="w-full text-left border-collapse text-xs">
                <thead>
                  <tr class="bg-gray-50 text-gray-400 border-b border-gray-100 font-bold uppercase tracking-wider">
                    <th class="p-4">Ref No</th>
                    <th class="p-4">Supplier</th>
                    <th class="p-4 text-center">Tgl Beli</th>
                    <th class="p-4 text-center">Jatuh Tempo</th>
                    <th class="p-4 text-right">Total Tagihan</th>
                    <th class="p-4 text-right">Sisa Utang</th>
                    <th class="p-4 text-center">Umur</th>
                    <th class="p-4 text-center">Status</th>
                    <th class="p-4 text-center">Aksi</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                  <tr v-if="filteredApLedger.length === 0">
                    <td colspan="9" class="p-8 text-center text-gray-400">Tidak ada utang dagang outstanding yang ditemukan.</td>
                  </tr>
                  <tr 
                    v-for="item in filteredApLedger" 
                    :key="item.purchase_id" 
                    class="hover:bg-gray-50/50 transition-colors"
                  >
                    <td class="p-4 font-mono font-bold text-gray-700">{{ item.reference_no }}</td>
                    <td class="p-4 font-medium text-gray-600">{{ item.supplier_name }}</td>
                    <td class="p-4 text-center text-gray-500">{{ formatDate(item.purchase_date) }}</td>
                    <td class="p-4 text-center text-gray-500">{{ formatDate(item.due_date) }}</td>
                    <td class="p-4 text-right font-mono font-bold text-gray-700">{{ formatCurrency(item.grand_total) }}</td>
                    <td class="p-4 text-right font-mono font-bold text-red-500">{{ formatCurrency(item.outstanding_debt) }}</td>
                    <td class="p-4 text-center text-gray-500 font-mono">{{ item.days_outstanding }} hari</td>
                    <td class="p-4 text-center">
                      <span 
                        class="px-2 py-0.5 rounded text-[10px] font-extrabold"
                        :class="item.status === 'OVERDUE' ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-600'"
                      >
                        {{ item.status }}
                      </span>
                    </td>
                    <td class="p-4 text-center">
                      <button 
                        @click="openSettlementModal(item)"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-[10px] font-black px-3 py-1.5 rounded-lg transition-colors shadow-sm shadow-indigo-100 cursor-pointer"
                      >
                        Bayar Utang
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div v-else-if="activeSubTab === 'ar'">
            <div class="overflow-x-auto">
              <table class="w-full text-left border-collapse text-xs">
                <thead>
                  <tr class="bg-gray-50 text-gray-400 border-b border-gray-100 font-bold uppercase tracking-wider">
                    <th class="p-4">Invoice No</th>
                    <th class="p-4">Pelanggan</th>
                    <th class="p-4 text-center">Tgl Jual</th>
                    <th class="p-4 text-center">Jatuh Tempo</th>
                    <th class="p-4 text-right">Total Tagihan</th>
                    <th class="p-4 text-right">Sisa Piutang</th>
                    <th class="p-4 text-center">Umur</th>
                    <th class="p-4 text-center">Status</th>
                    <th class="p-4 text-center">Aksi</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                  <tr v-if="filteredArLedger.length === 0">
                    <td colspan="9" class="p-8 text-center text-gray-400">Tidak ada piutang dagang outstanding yang ditemukan.</td>
                  </tr>
                  <tr 
                    v-for="item in filteredArLedger" 
                    :key="item.sale_id" 
                    class="hover:bg-gray-50/50 transition-colors"
                  >
                    <td class="p-4 font-mono font-bold text-gray-700">{{ item.invoice_no }}</td>
                    <td class="p-4 font-medium text-gray-600">{{ item.customer_name }}</td>
                    <td class="p-4 text-center text-gray-500">{{ formatDate(item.sale_date) }}</td>
                    <td class="p-4 text-center text-gray-500">{{ formatDate(item.due_date) }}</td>
                    <td class="p-4 text-right font-mono font-bold text-gray-700">{{ formatCurrency(item.grand_total) }}</td>
                    <td class="p-4 text-right font-mono font-bold text-red-500">{{ formatCurrency(item.outstanding_receivable) }}</td>
                    <td class="p-4 text-center text-gray-500 font-mono">{{ item.days_outstanding }} hari</td>
                    <td class="p-4 text-center">
                      <span 
                        class="px-2 py-0.5 rounded text-[10px] font-extrabold"
                        :class="item.status === 'OVERDUE' ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-600'"
                      >
                        {{ item.status }}
                      </span>
                    </td>
                    <td class="p-4 text-center">
                      <button 
                        @click="openSettlementModal(item)"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white text-[10px] font-black px-3 py-1.5 rounded-lg transition-colors shadow-sm shadow-indigo-100 cursor-pointer"
                      >
                        Terima Bayar
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Settlement Modal -->
    <div 
      v-if="showModal" 
      class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/40 backdrop-blur-sm flex justify-center items-center p-4"
    >
      <div class="bg-white rounded-3xl max-w-md w-full shadow-2xl border border-gray-100 overflow-hidden transform transition-all duration-300">
        
        <!-- Modal Header -->
        <div class="px-6 py-4 bg-indigo-50 border-b border-indigo-100 flex justify-between items-center">
          <div>
            <h3 class="text-sm font-black text-indigo-900 uppercase tracking-wide">
              {{ activeSubTab === 'ap' ? 'Pelunasan Utang Dagang (AP)' : 'Penerimaan Piutang (AR)' }}
            </h3>
            <p class="text-[10px] text-indigo-600 font-medium">Jurnal otomatis double-entry double ledger</p>
          </div>
          <button 
            @click="showModal = false"
            class="text-indigo-400 hover:text-indigo-600 transition-colors p-1 bg-white rounded-lg shadow-sm border border-indigo-100 cursor-pointer"
          >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Modal Body & Form -->
        <form @submit.prevent="handleSettlementSubmit" class="p-6 space-y-4">
          
          <!-- Selected Bill Info Box -->
          <div class="bg-gray-50 border border-gray-200/50 p-4 rounded-2xl space-y-2 text-xs">
            <div class="flex justify-between">
              <span class="text-gray-400 font-semibold">Nomor Invoice/Ref:</span>
              <span class="font-mono font-bold text-gray-700">
                {{ activeSubTab === 'ap' ? activeTransaction?.reference_no : activeTransaction?.invoice_no }}
              </span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400 font-semibold">{{ activeSubTab === 'ap' ? 'Nama Supplier:' : 'Nama Pelanggan:' }}</span>
              <span class="font-bold text-gray-700">
                {{ activeSubTab === 'ap' ? activeTransaction?.supplier_name : activeTransaction?.customer_name }}
              </span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400 font-semibold">Tgl Jatuh Tempo:</span>
              <span class="font-bold text-gray-600">
                {{ formatDate(activeTransaction?.due_date) }}
              </span>
            </div>
            <hr class="border-gray-200/50 my-1" />
            <div class="flex justify-between items-center">
              <span class="text-gray-400 font-semibold">Sisa Outstanding:</span>
              <span class="font-mono font-black text-red-500 text-sm">
                {{ formatCurrency(activeSubTab === 'ap' ? activeTransaction?.outstanding_debt : activeTransaction?.outstanding_receivable) }}
              </span>
            </div>
          </div>

          <!-- Input Payment Amount -->
          <div class="space-y-1">
            <label class="block text-xs font-black text-gray-600 uppercase tracking-wide">Nominal Transaksi (Rp)</label>
            <div class="relative">
              <input 
                v-model="form.amount"
                type="number" 
                step="0.01"
                placeholder="Masukkan nominal pelunasan..."
                class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 pr-24"
                :class="{'border-red-400 focus:border-red-400': errors.amount}"
              />
              <button 
                type="button"
                @click="fillFullOutstanding"
                class="absolute right-2.5 top-1.5 px-2 py-1 rounded bg-indigo-50 hover:bg-indigo-100 text-[10px] font-black text-indigo-700 transition-colors cursor-pointer"
              >
                Lunasi Semua
              </button>
            </div>
            <!-- Client Validation Error -->
            <p v-if="errors.amount" class="text-[10px] font-medium text-red-500">
              {{ errors.amount }}
            </p>
          </div>

          <!-- Cash / Bank Source selection -->
          <div class="space-y-1">
            <label class="block text-xs font-black text-gray-600 uppercase tracking-wide">
              {{ activeSubTab === 'ap' ? 'Bayar Melalui Kas/Bank' : 'Terima Melalui Kas/Bank' }}
            </label>
            <select 
              v-model="form.bank_account_code"
              class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500 bg-white"
              :class="{'border-red-400 focus:border-red-400': errors.bank_account_code}"
            >
              <option value="" disabled>Pilih Kas/Bank Sumber</option>
              <option v-for="acc in cashBankAccounts" :key="acc.id" :value="acc.code">
                {{ acc.code }} - {{ acc.name }} (Saldo: {{ formatCurrency(acc.balance) }})
              </option>
            </select>
            <!-- Client Validation Error -->
            <p v-if="errors.bank_account_code" class="text-[10px] font-medium text-red-500">
              {{ errors.bank_account_code }}
            </p>
          </div>

          <!-- Notes -->
          <div class="space-y-1">
            <label class="block text-xs font-black text-gray-600 uppercase tracking-wide">Catatan Penjelas</label>
            <textarea 
              v-model="form.notes"
              rows="2"
              placeholder="Catatan jurnal..."
              class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-indigo-500"
              :class="{'border-red-400 focus:border-red-400': errors.notes}"
            ></textarea>
            <p v-if="errors.notes" class="text-[10px] font-medium text-red-500">
              {{ errors.notes }}
            </p>
          </div>

          <!-- Actions -->
          <div class="flex gap-3 pt-2">
            <button 
              type="button"
              @click="showModal = false"
              class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 hover:bg-gray-50 text-xs font-bold text-gray-600 transition-colors cursor-pointer text-center"
            >
              Batal
            </button>
            <button 
              type="submit"
              :disabled="submittingForm"
              class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black px-4 py-2.5 rounded-xl transition-colors shadow-lg shadow-indigo-100 flex items-center justify-center gap-1.5 cursor-pointer disabled:opacity-50"
            >
              <svg v-if="submittingForm" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>{{ activeSubTab === 'ap' ? 'Bayar Sekarang' : 'Terima Pembayaran' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
