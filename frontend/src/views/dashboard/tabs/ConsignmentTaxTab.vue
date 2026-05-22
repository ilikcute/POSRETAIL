<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()

// View state: 'tax' or 'consignment'
const activeSubTab = ref('tax')

// Master data lists
const suppliers = ref([])
const accounts = ref([])

// Loadings
const loadingTax = ref(false)
const loadingLedger = ref(false)
const submittingSettle = ref(false)

// 1. Tax Reconciliation States
const taxStartDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0])
const taxEndDate = ref(new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().split('T')[0])
const taxReport = ref(null)

// 2. Consignment Ledger States
const ledgerSupplierId = ref('')
const ledgerStartDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0])
const ledgerEndDate = ref(new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().split('T')[0])
const ledgerSummary = ref({
  total_items_sold: 0,
  total_gross_revenue: 0,
  total_store_commission: 0,
  total_supplier_payable: 0
})
const ledgerItems = ref([])

// 3. Payout Form State
const form = ref({
  supplier_id: '',
  bank_account_code: '',
  payment_amount: '',
  notes: ''
})

const errors = ref({
  supplier_id: '',
  bank_account_code: '',
  payment_amount: '',
  notes: ''
})

// Computed list of cash/bank accounts (typically asset type, specifically code 1101 and 1102)
const cashBankAccounts = computed(() => {
  return accounts.value.filter(acc => 
    acc.is_active && 
    (acc.type === 'asset' || acc.code === '1101' || acc.code === '1102')
  )
})

// Fetch master data
const fetchSuppliers = async () => {
  try {
    const res = await api.get('/suppliers')
    suppliers.value = res.data?.data || res.data || []
  } catch (err) {
    console.error('Error fetching suppliers:', err)
    toast.error('Gagal mengambil data supplier.')
  }
}

const fetchAccounts = async () => {
  try {
    const res = await api.get('/accounts')
    accounts.value = res.data?.data || res.data || []
  } catch (err) {
    console.error('Error fetching accounts:', err)
    toast.error('Gagal mengambil data bagan akun.')
  }
}

// Fetch Tax Reconciliation
const fetchTaxReconciliation = async () => {
  loadingTax.value = true
  try {
    const params = {
      start_date: taxStartDate.value,
      end_date: taxEndDate.value
    }
    const res = await api.get('/tax/reconciliation', { params })
    taxReport.value = res.data?.data || res.data
  } catch (err) {
    console.error('Error fetching tax reconciliation:', err)
    toast.error('Gagal mengambil laporan rekonsiliasi PPN.')
  } finally {
    loadingTax.value = false
  }
}

// Fetch Consignment Ledger
const fetchConsignmentLedger = async () => {
  loadingLedger.value = true
  try {
    const params = {
      start_date: ledgerStartDate.value,
      end_date: ledgerEndDate.value,
      supplier_id: ledgerSupplierId.value || undefined
    }
    const res = await api.get('/consignment/ledger', { params })
    const data = res.data?.data || res.data
    ledgerSummary.value = data?.summary || {
      total_items_sold: 0,
      total_gross_revenue: 0,
      total_store_commission: 0,
      total_supplier_payable: 0
    }
    ledgerItems.value = data?.ledger || []
  } catch (err) {
    console.error('Error fetching consignment ledger:', err)
    toast.error('Gagal mengambil buku penjualan konsinyasi.')
  } finally {
    loadingLedger.value = false
  }
}

// Watch filters
watch([taxStartDate, taxEndDate], () => {
  if (activeSubTab.value === 'tax') fetchTaxReconciliation()
})

watch([ledgerStartDate, ledgerEndDate, ledgerSupplierId], () => {
  if (activeSubTab.value === 'consignment') fetchConsignmentLedger()
})

// Sync supplier filter to payout form
watch(ledgerSupplierId, (newVal) => {
  if (newVal) {
    form.value.supplier_id = newVal
  }
})

// Form Validation
const validateForm = () => {
  let isValid = true
  errors.value = {
    supplier_id: '',
    bank_account_code: '',
    payment_amount: '',
    notes: ''
  }

  if (!form.value.supplier_id) {
    errors.value.supplier_id = 'Supplier wajib dipilih.'
    isValid = false
  }

  if (!form.value.bank_account_code) {
    errors.value.bank_account_code = 'Sumber kas/bank wajib dipilih.'
    isValid = false
  }

  if (!form.value.payment_amount || Number(form.value.payment_amount) <= 0) {
    errors.value.payment_amount = 'Nominal pembayaran harus berupa angka lebih besar dari 0.'
    isValid = false
  }

  // Check if balance of selected account is sufficient
  const selectedAcc = cashBankAccounts.value.find(acc => acc.code === form.value.bank_account_code)
  if (selectedAcc && Number(form.value.payment_amount) > Number(selectedAcc.balance)) {
    errors.value.payment_amount = `Saldo tidak mencukupi. Saldo saat ini: ${formatCurrency(selectedAcc.balance)}`
    isValid = false
  }

  return isValid
}

const resetForm = () => {
  form.value = {
    supplier_id: ledgerSupplierId.value || '',
    bank_account_code: cashBankAccounts.value[0]?.code || '',
    payment_amount: '',
    notes: ''
  }
  errors.value = {
    supplier_id: '',
    bank_account_code: '',
    payment_amount: '',
    notes: ''
  }
}

// Payout Submit
const handleSettleSubmit = async () => {
  if (!validateForm()) return

  submittingSettle.value = true
  const payload = {
    supplier_id: Number(form.value.supplier_id),
    bank_account_code: form.value.bank_account_code,
    payment_amount: Number(form.value.payment_amount),
    notes: form.value.notes || undefined
  }

  try {
    const res = await api.post('/consignment/settle', payload)
    toast.success(res.data?.message || 'Pelunasan konsinyasi berhasil diproses dan dijurnal!')
    resetForm()
    
    // Refresh ledger, tax, and accounts balance
    await Promise.all([
      fetchConsignmentLedger(),
      fetchAccounts()
    ])
  } catch (err) {
    console.error('Error submitting consignment settlement:', err)
    if (err.response && err.response.status === 422) {
      const serverErrors = err.response.data.errors || {}
      Object.keys(serverErrors).forEach(key => {
        if (errors.value[key] !== undefined) {
          errors.value[key] = Array.isArray(serverErrors[key]) ? serverErrors[key][0] : serverErrors[key]
        }
      })
      if (err.response.data.message) {
        toast.error(err.response.data.message)
      } else {
        toast.error('Periksa kembali input pembayaran Anda.')
      }
    } else {
      toast.error(err.response?.data?.message || 'Terjadi kesalahan sistem saat memproses pelunasan.')
    }
  } finally {
    submittingSettle.value = false
  }
}

// Helpers
const formatCurrency = (val) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 2
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

// Initialize
onMounted(async () => {
  await Promise.all([
    fetchSuppliers(),
    fetchAccounts()
  ])
  
  if (cashBankAccounts.value.length > 0) {
    form.value.bank_account_code = cashBankAccounts.value[0].code
  }

  fetchTaxReconciliation()
  fetchConsignmentLedger()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header Card -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
      <div>
        <h2 class="text-xl font-bold text-gray-800">Consignment & Tax Compliance</h2>
        <p class="text-xs text-gray-500">Pantau rekonsiliasi PPN (Masukan vs Keluaran) dan buku penjualan konsinyasi supplier beserta pelunasan otomatis.</p>
      </div>

      <!-- Main Sub Tab Navigator -->
      <div class="flex bg-gray-100 p-1.5 rounded-xl text-xs font-bold text-gray-600 gap-1 w-full md:w-auto">
        <button 
          @click="activeSubTab = 'tax'"
          class="flex-1 md:flex-none px-4 py-2 rounded-lg transition-all cursor-pointer"
          :class="activeSubTab === 'tax' ? 'bg-white text-emerald-600 shadow-sm' : 'hover:bg-white/50 text-gray-500'"
        >
          Rekonsiliasi Pajak PPN
        </button>
        <button 
          @click="activeSubTab = 'consignment'"
          class="flex-1 md:flex-none px-4 py-2 rounded-lg transition-all cursor-pointer"
          :class="activeSubTab === 'consignment' ? 'bg-white text-emerald-600 shadow-sm' : 'hover:bg-white/50 text-gray-500'"
        >
          Penjualan Konsinyasi & Payout
        </button>
      </div>
    </div>

    <!-- SUB-TAB 1: TAX RECONCILIATION -->
    <div v-if="activeSubTab === 'tax'" class="space-y-6">
      <!-- Period Selector Card -->
      <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-wrap gap-4 items-center">
        <div class="text-xs font-bold text-gray-400 uppercase tracking-wider">Periode Rekonsiliasi:</div>
        <div class="flex items-center gap-2">
          <input 
            v-model="taxStartDate"
            type="date"
            class="px-3 py-1.5 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-emerald-500"
          />
          <span class="text-gray-400 text-xs">s/d</span>
          <input 
            v-model="taxEndDate"
            type="date"
            class="px-3 py-1.5 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-emerald-500"
          />
        </div>
        <button 
          @click="fetchTaxReconciliation"
          class="ml-auto px-4 py-1.5 rounded-xl border border-gray-200 hover:bg-gray-50 text-xs font-bold text-gray-600 flex items-center gap-1.5 transition-colors cursor-pointer"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5" :class="{'animate-spin': loadingTax}">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
          </svg>
          Refresh Data
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loadingTax" class="bg-white p-12 rounded-2xl border border-gray-100 shadow-sm text-center text-gray-400 font-medium">
        <div class="flex items-center justify-center gap-2">
          <svg class="animate-spin h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          Menghitung rekonsiliasi PPN masukan & keluaran...
        </div>
      </div>

      <!-- Report Content -->
      <div v-else-if="taxReport" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- VAT Output Sales Card -->
        <div class="bg-white border border-gray-100 p-6 rounded-2xl shadow-sm space-y-4">
          <div class="flex justify-between items-center">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">PPN Keluaran (VAT Output)</h3>
            <span class="p-1.5 rounded-lg bg-orange-50 text-orange-600">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5h16.5a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5H3.75a1.5 1.5 0 0 1-1.5-1.5v-12a1.5 1.5 0 0 1 1.5-1.5z" />
              </svg>
            </span>
          </div>
          <div class="space-y-1">
            <div class="text-2xl font-black text-gray-800 font-mono">
              {{ formatCurrency(taxReport.vat_output_sales?.vat_output) }}
            </div>
            <p class="text-[11px] text-gray-500">Akumulasi PPN 11% dari total omset kasir POS.</p>
          </div>
          <div class="border-t border-gray-50 pt-3 flex justify-between items-center text-xs">
            <span class="text-gray-400">Total Penjualan Kotor:</span>
            <span class="font-bold text-gray-700 font-mono">{{ formatCurrency(taxReport.vat_output_sales?.gross_sales) }}</span>
          </div>
        </div>

        <!-- VAT Input Purchase Card -->
        <div class="bg-white border border-gray-100 p-6 rounded-2xl shadow-sm space-y-4">
          <div class="flex justify-between items-center">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">PPN Masukan (VAT Input)</h3>
            <span class="p-1.5 rounded-lg bg-blue-50 text-blue-600">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1.75 0zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1.75 0z" />
              </svg>
            </span>
          </div>
          <div class="space-y-1">
            <div class="text-2xl font-black text-gray-800 font-mono">
              {{ formatCurrency(taxReport.vat_input_purchase?.vat_input) }}
            </div>
            <p class="text-[11px] text-gray-500">Akumulasi PPN 11% dari total belanja produk ke supplier.</p>
          </div>
          <div class="border-t border-gray-50 pt-3 flex justify-between items-center text-xs">
            <span class="text-gray-400">Total Pembelian Kotor:</span>
            <span class="font-bold text-gray-700 font-mono">{{ formatCurrency(taxReport.vat_input_purchase?.gross_purchases) }}</span>
          </div>
        </div>

        <!-- Net VAT Position Card -->
        <div class="bg-white border border-gray-100 p-6 rounded-2xl shadow-sm space-y-4">
          <div class="flex justify-between items-center">
            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Selisih PPN & Posisi Pajak</h3>
            <span 
              class="p-1.5 rounded-lg text-xs font-bold"
              :class="taxReport.vat_position?.net_vat_payable > 0 ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-600'"
            >
              {{ taxReport.vat_position?.net_vat_payable > 0 ? 'Kurang Bayar' : 'Lebih Bayar' }}
            </span>
          </div>
          <div class="space-y-1">
            <div 
              class="text-2xl font-black font-mono"
              :class="taxReport.vat_position?.net_vat_payable > 0 ? 'text-red-600' : 'text-emerald-600'"
            >
              {{ formatCurrency(Math.abs(taxReport.vat_position?.net_vat_payable)) }}
            </div>
            <p class="text-[10px] uppercase font-bold tracking-wide mt-1" :class="taxReport.vat_position?.net_vat_payable > 0 ? 'text-red-500' : 'text-emerald-500'">
              {{ taxReport.vat_position?.status }}
            </p>
          </div>
          <div class="border-t border-gray-50 pt-3 text-[11px] text-gray-400 leading-normal">
            {{ 
              taxReport.vat_position?.net_vat_payable > 0 
                ? 'Keluaran > Masukan. Selisih wajib disetor ke kas negara paling lambat akhir bulan berikutnya.' 
                : 'Masukan > Keluaran. Selisih lebih bayar dapat dikompensasikan ke masa pajak berikutnya.' 
            }}
          </div>
        </div>
      </div>
    </div>

    <!-- SUB-TAB 2: CONSIGNMENT SALES LEDGER & PAYOUT -->
    <div v-if="activeSubTab === 'consignment'" class="space-y-6">
      <!-- Period & Supplier Filter Card -->
      <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm flex flex-wrap gap-4 items-center text-xs">
        <div class="flex items-center gap-1">
          <span class="font-bold text-gray-400 uppercase tracking-wider">Filter Ledger:</span>
        </div>
        <!-- Supplier Select -->
        <select v-model="ledgerSupplierId" class="px-3 py-1.5 border border-gray-200 rounded-xl focus:outline-none focus:border-emerald-500 bg-white">
          <option value="">Semua Supplier</option>
          <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
            {{ supplier.name }}
          </option>
        </select>
        <!-- Date ranges -->
        <div class="flex items-center gap-2">
          <input 
            v-model="ledgerStartDate"
            type="date"
            class="px-3 py-1.5 border border-gray-200 rounded-xl focus:outline-none focus:border-emerald-500"
          />
          <span class="text-gray-400 font-medium">s/d</span>
          <input 
            v-model="ledgerEndDate"
            type="date"
            class="px-3 py-1.5 border border-gray-200 rounded-xl focus:outline-none focus:border-emerald-500"
          />
        </div>
        <button 
          @click="fetchConsignmentLedger"
          class="ml-auto px-4 py-1.5 rounded-xl border border-gray-200 hover:bg-gray-50 font-bold text-gray-600 flex items-center gap-1.5 transition-colors cursor-pointer"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5" :class="{'animate-spin': loadingLedger}">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
          </svg>
          Refresh Ledger
        </button>
      </div>

      <!-- Ledger Summary Statistics -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Qty Sold -->
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
          <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Total Item Laku</div>
          <div class="text-xl font-black text-gray-800 mt-1 font-mono">
            {{ ledgerSummary.total_items_sold }} <span class="text-xs font-normal text-gray-400">pcs</span>
          </div>
        </div>
        <!-- Gross Revenue -->
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
          <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Omset Penjualan Kotor</div>
          <div class="text-xl font-black text-emerald-600 mt-1 font-mono">
            {{ formatCurrency(ledgerSummary.total_gross_revenue) }}
          </div>
        </div>
        <!-- Store Commission -->
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
          <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider font-semibold">Komisi Toko (Profit)</div>
          <div class="text-xl font-black text-blue-600 mt-1 font-mono">
            {{ formatCurrency(ledgerSummary.total_store_commission) }}
          </div>
        </div>
        <!-- Supplier Payable -->
        <div class="bg-white p-5 rounded-2xl border border-gray-100 shadow-sm">
          <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Hutang Bersih Supplier</div>
          <div class="text-xl font-black text-orange-600 mt-1 font-mono">
            {{ formatCurrency(ledgerSummary.total_supplier_payable) }}
          </div>
        </div>
      </div>

      <!-- Main Layout Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left side: Ledger Table (col-span-2) -->
        <div class="lg:col-span-2 bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden flex flex-col justify-between">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-gray-50 border-b border-gray-100">
                  <th class="p-4 text-xs font-bold text-gray-500 uppercase tracking-wider w-24">Faktur / Tgl</th>
                  <th class="p-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Nama Produk & Supplier</th>
                  <th class="p-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-center w-20">Qty x Harga</th>
                  <th class="p-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right w-24">Gross</th>
                  <th class="p-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right w-24">Bagi Hasil</th>
                  <th class="p-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right w-28">Hutang Supplier</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 text-sm">
                <!-- Loading -->
                <tr v-if="loadingLedger">
                  <td colspan="6" class="p-8 text-center text-gray-400 font-medium">
                    <div class="flex items-center justify-center gap-2">
                      <svg class="animate-spin h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      Memuat laporan penjualan konsinyasi...
                    </div>
                  </td>
                </tr>

                <!-- Empty State -->
                <tr v-else-if="ledgerItems.length === 0">
                  <td colspan="6" class="p-8 text-center text-gray-400 font-medium">
                    Tidak ada transaksi penjualan barang konsinyasi dalam periode ini.
                  </td>
                </tr>

                <!-- Rows -->
                <tr 
                  v-else 
                  v-for="item in ledgerItems" 
                  :key="item.sale_item_id"
                  class="hover:bg-gray-50 transition-colors"
                >
                  <td class="p-4">
                    <div class="font-mono text-xs text-gray-700 font-bold">{{ item.invoice_no }}</div>
                    <div class="text-[9px] text-gray-400 mt-0.5">{{ formatDate(item.sold_at) }}</div>
                  </td>
                  <td class="p-4">
                    <div class="font-medium text-gray-800">{{ item.product_name }}</div>
                    <div class="text-[10px] text-gray-400 font-mono mt-0.5">{{ item.product_code }}</div>
                    <div class="text-[9px] text-indigo-500 font-bold mt-1">Supplier: {{ item.supplier?.name }}</div>
                  </td>
                  <td class="p-4 text-center">
                    <div class="text-xs text-gray-700">{{ item.qty_sold }} pcs</div>
                    <div class="text-[9px] text-gray-400 font-mono mt-0.5">{{ formatCurrency(item.price) }}</div>
                  </td>
                  <td class="p-4 text-right font-mono text-xs text-gray-700">
                    {{ formatCurrency(item.gross_revenue) }}
                  </td>
                  <td class="p-4 text-right">
                    <div class="text-xs text-blue-600 font-semibold font-mono">{{ formatCurrency(item.store_commission) }}</div>
                    <div class="text-[9px] text-gray-400 mt-0.5">Fee: {{ item.commission_rate }}</div>
                  </td>
                  <td class="p-4 text-right font-mono text-xs font-bold text-orange-600">
                    {{ formatCurrency(item.supplier_payable) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Right side: Settle Form (col-span-1) -->
        <div class="lg:col-span-1 bg-white border border-gray-100 p-6 rounded-2xl shadow-sm h-fit">
          <h3 class="text-md font-bold text-gray-800 mb-5 flex items-center gap-2">
            <span class="p-1.5 rounded-lg bg-orange-50 text-orange-600">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-6.188-12h12.375c1.036 0 1.875.84 1.875 1.875v11.25c0 1.035-.84 1.875-1.875 1.875H3.062A1.875 1.875 0 011.187 18V5.625c0-1.036.84-1.875 1.875-1.875z" />
              </svg>
            </span>
            Settle & Payout Supplier
          </h3>

          <form @submit.prevent="handleSettleSubmit" class="space-y-4">
            <!-- Supplier Select -->
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Supplier Tujuan *</label>
              <select 
                v-model="form.supplier_id"
                class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition-colors bg-white"
                :class="errors.supplier_id ? 'border-red-400' : 'border-gray-200'"
              >
                <option value="" disabled>Pilih Supplier</option>
                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                  {{ supplier.name }}
                </option>
              </select>
              <p v-if="errors.supplier_id" class="text-xs text-red-500 font-medium mt-0.5">{{ errors.supplier_id }}</p>
            </div>

            <!-- Bank / Cash Account Code -->
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Sumber Payout Kas/Bank *</label>
              <select 
                v-model="form.bank_account_code"
                class="w-full px-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition-colors bg-white text-xs"
                :class="errors.bank_account_code ? 'border-red-400' : 'border-gray-200'"
              >
                <option value="" disabled>Pilih Akun Kas/Bank</option>
                <option v-for="acc in cashBankAccounts" :key="acc.id" :value="acc.code">
                  {{ acc.code }} - {{ acc.name }} (Saldo: {{ formatCurrency(acc.balance) }})
                </option>
              </select>
              <p v-if="errors.bank_account_code" class="text-xs text-red-500 font-medium mt-0.5">{{ errors.bank_account_code }}</p>
            </div>

            <!-- Payment Amount -->
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Nominal Pembayaran *</label>
              <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500 text-sm">Rp</span>
                <input 
                  v-model="form.payment_amount"
                  type="number"
                  step="0.01" 
                  placeholder="0.00" 
                  class="w-full pl-9 pr-4 py-2 border rounded-xl text-sm focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition-colors font-mono font-bold"
                  :class="errors.payment_amount ? 'border-red-400 focus:ring-red-400' : 'border-gray-200'"
                />
              </div>
              <!-- Fill outstanding helper link -->
              <div v-if="ledgerSupplierId && ledgerSummary.total_supplier_payable > 0" class="flex justify-between items-center mt-1">
                <span class="text-[10px] text-gray-400">Total hutang terhitung:</span>
                <button 
                  type="button" 
                  @click="form.payment_amount = ledgerSummary.total_supplier_payable"
                  class="text-[10px] text-orange-500 hover:text-orange-700 font-bold transition-colors cursor-pointer"
                >
                  Bayar Semua ({{ formatCurrency(ledgerSummary.total_supplier_payable) }})
                </button>
              </div>
              <p v-if="errors.payment_amount" class="text-xs text-red-500 font-medium mt-0.5">{{ errors.payment_amount }}</p>
            </div>

            <!-- Notes -->
            <div class="space-y-1">
              <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider">Catatan / Memo</label>
              <textarea 
                v-model="form.notes"
                rows="3"
                placeholder="Pelunasan Hutang Konsinyasi..." 
                class="w-full px-4 py-2 border border-gray-200 rounded-xl text-xs focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition-colors resize-none"
              ></textarea>
            </div>

            <!-- Submit buttons -->
            <div class="flex gap-2 pt-2">
              <button 
                type="submit" 
                :disabled="submittingSettle"
                class="flex-1 py-2.5 px-4 rounded-xl bg-orange-500 hover:bg-orange-600 text-white text-sm font-bold flex items-center justify-center gap-2 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed shadow-md hover:shadow-lg active:scale-98"
              >
                <svg v-if="submittingSettle" class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ submittingSettle ? 'Memproses...' : 'Proses Payout & Jurnal' }}
              </button>
              <button 
                v-if="form.payment_amount || form.notes"
                type="button" 
                @click="resetForm"
                class="py-2.5 px-4 rounded-xl border border-gray-200 hover:bg-gray-50 text-gray-600 text-sm font-bold transition-all cursor-pointer"
              >
                Batal
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>
