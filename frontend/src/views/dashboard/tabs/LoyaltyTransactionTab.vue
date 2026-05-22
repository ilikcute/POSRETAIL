<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()

const customers = ref([])
const transactions = ref([])
const loadingCustomers = ref(false)
const loadingTransactions = ref(false)
const submitting = ref(false)
const savingNote = ref(false)
const searchQuery = ref('')
const transactionSearch = ref('')
const result = ref(null)
const editingTransaction = ref(null)

const form = ref({
  customer_id: '',
  sale_id: '',
  type: 'earn',
  points: '',
  amount: '',
  description: ''
})

const noteForm = ref({
  description: ''
})

const errors = ref({
  customer_id: '',
  sale_id: '',
  type: '',
  points: '',
  amount: '',
  description: ''
})

const noteErrors = ref({
  description: ''
})

const typeOptions = [
  { value: 'earn', label: 'Earn Points', helper: 'Tambah poin ke saldo customer.' },
  { value: 'redeem', label: 'Redeem Points', helper: 'Tukar poin menjadi potongan belanja.' },
  { value: 'adjust', label: 'Adjust Balance', helper: 'Koreksi manual, boleh positif atau negatif.' }
]

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

const formatDate = (value) => {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'

  return parsed.toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const formatPoints = (points) => {
  const parsed = Number(points)
  if (Number.isNaN(parsed)) return '0'
  return parsed.toLocaleString('id-ID')
}

const normalizeError = (message) => {
  return Array.isArray(message) ? message[0] : message
}

const applyServerErrors = (serverErrors) => {
  Object.keys(serverErrors || {}).forEach(key => {
    if (errors.value[key] !== undefined) {
      errors.value[key] = normalizeError(serverErrors[key])
    }
  })
}

const resetErrors = () => {
  errors.value = {
    customer_id: '',
    sale_id: '',
    type: '',
    points: '',
    amount: '',
    description: ''
  }
}

const resetForm = (clearResult = true) => {
  form.value = {
    customer_id: '',
    sale_id: '',
    type: 'earn',
    points: '',
    amount: '',
    description: ''
  }
  resetErrors()
  if (clearResult) {
    result.value = null
  }
}

const fetchCustomers = async () => {
  loadingCustomers.value = true
  try {
    const response = await api.get('/customers')
    const data = response.data?.data || response.data || []
    customers.value = data.filter(customer => customer.is_active === true || customer.is_active === 1)
  } catch (error) {
    console.error('Error fetching customers:', error)
    toast.error(error.response?.data?.message || 'Gagal memuat customer loyalty.')
  } finally {
    loadingCustomers.value = false
  }
}

const fetchTransactions = async () => {
  loadingTransactions.value = true
  try {
    const response = await api.get('/loyalty-transactions')
    transactions.value = response.data?.data || response.data || []
  } catch (error) {
    console.error('Error fetching loyalty transactions:', error)
    toast.error(error.response?.data?.message || 'Gagal memuat ledger loyalty.')
  } finally {
    loadingTransactions.value = false
  }
}

const selectedCustomer = computed(() => {
  return customers.value.find(customer => customer.id === Number(form.value.customer_id)) || null
})

const filteredCustomers = computed(() => {
  const keyword = searchQuery.value.trim().toLowerCase()
  if (!keyword) return customers.value

  return customers.value.filter(customer => {
    return [customer.name, customer.member_code, customer.phone, customer.email]
      .filter(Boolean)
      .some(value => value.toLowerCase().includes(keyword))
  })
})

const filteredTransactions = computed(() => {
  const keyword = transactionSearch.value.trim().toLowerCase()
  if (!keyword) return transactions.value

  return transactions.value.filter(transaction => {
    return [
      transaction.customer?.name,
      transaction.customer?.member_code,
      transaction.sale?.invoice_no,
      transaction.description,
      transaction.type
    ].filter(Boolean).some(value => value.toLowerCase().includes(keyword))
  })
})

const totalEarned = computed(() => {
  return transactions.value
    .filter(transaction => Number(transaction.points) > 0)
    .reduce((sum, transaction) => sum + Number(transaction.points), 0)
})

const totalRedeemed = computed(() => {
  return Math.abs(transactions.value
    .filter(transaction => Number(transaction.points) < 0)
    .reduce((sum, transaction) => sum + Number(transaction.points), 0))
})

const projectedBalance = computed(() => {
  if (!selectedCustomer.value) return null

  const rawPoints = Number(form.value.points)
  if (Number.isNaN(rawPoints) || rawPoints === 0) return Number(selectedCustomer.value.point_balance || 0)

  const points = form.value.type === 'earn'
    ? Math.abs(rawPoints)
    : form.value.type === 'redeem'
      ? -Math.abs(rawPoints)
      : rawPoints

  return Number(selectedCustomer.value.point_balance || 0) + points
})

const validateForm = () => {
  resetErrors()
  let valid = true

  if (!form.value.customer_id) {
    errors.value.customer_id = 'Customer wajib dipilih.'
    valid = false
  }

  if (form.value.sale_id !== '') {
    const saleId = Number(form.value.sale_id)
    if (!Number.isInteger(saleId) || saleId <= 0) {
      errors.value.sale_id = 'Sale ID harus berupa angka positif.'
      valid = false
    }
  }

  if (!['earn', 'redeem', 'adjust'].includes(form.value.type)) {
    errors.value.type = 'Tipe transaksi loyalty wajib dipilih.'
    valid = false
  }

  const points = Number(form.value.points)
  if (form.value.points === '') {
    errors.value.points = 'Jumlah poin wajib diisi.'
    valid = false
  } else if (!Number.isInteger(points) || points === 0) {
    errors.value.points = 'Jumlah poin harus bilangan bulat dan tidak boleh 0.'
    valid = false
  } else if (form.value.type !== 'adjust' && points < 0) {
    errors.value.points = 'Earn/Redeem memakai angka positif. Sistem akan mengubah redeem menjadi pengurang saldo.'
    valid = false
  } else if (projectedBalance.value !== null && projectedBalance.value < 0) {
    errors.value.points = `Saldo tidak cukup. Saldo customer saat ini ${formatPoints(selectedCustomer.value?.point_balance || 0)} poin.`
    valid = false
  }

  if (form.value.amount !== '') {
    const amount = Number(form.value.amount)
    if (Number.isNaN(amount) || amount < 0) {
      errors.value.amount = 'Nilai rupiah harus berupa angka positif atau nol.'
      valid = false
    }
  }

  if (!form.value.description || form.value.description.trim() === '') {
    errors.value.description = 'Deskripsi transaksi wajib diisi.'
    valid = false
  } else if (form.value.description.length > 255) {
    errors.value.description = 'Deskripsi tidak boleh lebih dari 255 karakter.'
    valid = false
  }

  return valid
}

const submitTransaction = async () => {
  if (!validateForm()) {
    toast.warning('Perbaiki error pada form loyalty terlebih dahulu.')
    return
  }

  submitting.value = true
  result.value = null

  try {
    const payload = {
      customer_id: Number(form.value.customer_id),
      sale_id: form.value.sale_id !== '' ? Number(form.value.sale_id) : null,
      type: form.value.type,
      points: Number(form.value.points),
      amount: form.value.amount !== '' ? Number(form.value.amount) : null,
      description: form.value.description.trim()
    }

    const response = await api.post('/loyalty-transactions', payload)
    result.value = response.data?.data || null
    toast.success('Transaksi loyalty berhasil diproses.')
    await Promise.all([fetchTransactions(), fetchCustomers()])
    resetForm(false)
  } catch (error) {
    console.error('Error submitting loyalty transaction:', error)
    if (error.response?.status === 422) {
      applyServerErrors(error.response.data?.errors || {})
      toast.error(error.response.data?.message || 'Validasi transaksi loyalty gagal.')
    } else {
      toast.error(error.response?.data?.message || 'Gagal memproses transaksi loyalty.')
    }
  } finally {
    submitting.value = false
  }
}

const startEditNote = (transaction) => {
  editingTransaction.value = transaction
  noteForm.value.description = transaction.description || ''
  noteErrors.value.description = ''
}

const cancelEditNote = () => {
  editingTransaction.value = null
  noteForm.value.description = ''
  noteErrors.value.description = ''
}

const submitNote = async () => {
  noteErrors.value.description = ''

  if (!noteForm.value.description || noteForm.value.description.trim() === '') {
    noteErrors.value.description = 'Deskripsi transaksi wajib diisi.'
    return
  }

  if (noteForm.value.description.length > 255) {
    noteErrors.value.description = 'Deskripsi tidak boleh lebih dari 255 karakter.'
    return
  }

  savingNote.value = true
  try {
    await api.put(`/loyalty-transactions/${editingTransaction.value.id}`, {
      description: noteForm.value.description.trim()
    })
    toast.success('Catatan loyalty berhasil diperbarui.')
    cancelEditNote()
    await fetchTransactions()
  } catch (error) {
    console.error('Error updating loyalty note:', error)
    if (error.response?.status === 422) {
      noteErrors.value.description = normalizeError(error.response.data?.errors?.description || error.response.data?.message)
    } else {
      toast.error(error.response?.data?.message || 'Gagal memperbarui catatan transaksi.')
    }
  } finally {
    savingNote.value = false
  }
}

watch(
  () => form.value.type,
  (type) => {
    errors.value.type = ''
    if (type === 'earn') {
      form.value.description = form.value.description || 'Akumulasi poin manual customer'
    } else if (type === 'redeem') {
      form.value.description = form.value.description || 'Penukaran poin loyalty customer'
    } else if (type === 'adjust') {
      form.value.description = form.value.description || 'Koreksi manual saldo poin loyalty'
    }
  },
  { immediate: true }
)

onMounted(() => {
  fetchCustomers()
  fetchTransactions()
})
</script>

<template>
  <div class="space-y-6">
    <div class="bg-white border border-slate-100 rounded-lg p-5 shadow-sm flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
      <div class="flex items-center gap-3">
        <span class="w-11 h-11 rounded-lg bg-violet-600 text-white flex items-center justify-center shadow-sm">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-6 h-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 0 1-1.5 1.5H5.25a2.25 2.25 0 0 1-2.25-2.25V7.5m18 3.75H8.25m12.75 0-3.75-3.75m3.75 3.75-3.75 3.75M3 7.5A2.25 2.25 0 0 1 5.25 5.25h12A2.25 2.25 0 0 1 19.5 7.5v.75" />
          </svg>
        </span>
        <div>
          <h1 class="text-xl font-bold text-slate-800">Loyalty Transaction</h1>
          <p class="text-sm text-slate-500">Kelola earn, redeem, dan adjustment poin customer dengan ledger yang aman.</p>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="rounded-lg bg-violet-50 border border-violet-100 px-4 py-3">
          <p class="text-[11px] uppercase font-bold text-violet-600">Customer Aktif</p>
          <p class="text-lg font-black text-violet-800">{{ customers.length }}</p>
        </div>
        <div class="rounded-lg bg-teal-50 border border-teal-100 px-4 py-3">
          <p class="text-[11px] uppercase font-bold text-teal-600">Total Earned</p>
          <p class="text-lg font-black text-teal-800">{{ formatPoints(totalEarned) }}</p>
        </div>
        <div class="rounded-lg bg-rose-50 border border-rose-100 px-4 py-3">
          <p class="text-[11px] uppercase font-bold text-rose-600">Total Redeemed</p>
          <p class="text-lg font-black text-rose-800">{{ formatPoints(totalRedeemed) }}</p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
      <section class="xl:col-span-5 bg-white border border-slate-100 rounded-lg p-5 shadow-sm space-y-5">
        <div class="border-b border-slate-100 pb-4">
          <h2 class="text-base font-bold text-slate-800">Input Transaksi Loyalty</h2>
          <p class="text-xs text-slate-400 mt-1">Saldo customer dikunci dan dihitung ulang di backend saat submit.</p>
        </div>

        <form @submit.prevent="submitTransaction" class="space-y-4">
          <div class="space-y-1">
            <label for="customer_search" class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Cari Customer</label>
            <input
              id="customer_search"
              v-model="searchQuery"
              type="text"
              placeholder="Nama, kode member, telepon..."
              class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20"
            />
          </div>

          <div class="space-y-1">
            <label for="customer_id" class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Customer *</label>
            <select
              id="customer_id"
              v-model="form.customer_id"
              class="w-full bg-white border rounded-lg px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-violet-500/20"
              :class="errors.customer_id ? 'border-rose-500' : 'border-slate-200 focus:border-violet-500'"
              :disabled="loadingCustomers"
            >
              <option value="">{{ loadingCustomers ? 'Memuat customer...' : 'Pilih customer loyalty' }}</option>
              <option v-for="customer in filteredCustomers" :key="customer.id" :value="customer.id">
                {{ customer.name }} - {{ customer.member_code || 'No Member Code' }} ({{ formatPoints(customer.point_balance) }} pts)
              </option>
            </select>
            <p v-if="errors.customer_id" class="text-xs text-rose-600 font-medium">{{ errors.customer_id }}</p>
          </div>

          <div v-if="selectedCustomer" class="rounded-lg bg-slate-50 border border-slate-100 p-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
            <div>
              <p class="text-xs text-slate-400">Saldo Saat Ini</p>
              <p class="font-black text-slate-800">{{ formatPoints(selectedCustomer.point_balance) }} poin</p>
            </div>
            <div>
              <p class="text-xs text-slate-400">Saldo Setelah Submit</p>
              <p class="font-black" :class="projectedBalance < 0 ? 'text-rose-600' : 'text-violet-700'">{{ formatPoints(projectedBalance) }} poin</p>
            </div>
            <div class="sm:col-span-2 text-xs text-slate-500">
              {{ selectedCustomer.phone || 'No phone' }} <span v-if="selectedCustomer.email">| {{ selectedCustomer.email }}</span>
            </div>
          </div>

          <div class="space-y-2">
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Tipe Transaksi *</label>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
              <label
                v-for="option in typeOptions"
                :key="option.value"
                class="border rounded-lg p-3 cursor-pointer transition-colors"
                :class="form.type === option.value ? 'border-violet-500 bg-violet-50 text-violet-800' : 'border-slate-200 hover:bg-slate-50 text-slate-600'"
              >
                <input v-model="form.type" type="radio" :value="option.value" class="sr-only" />
                <span class="block text-sm font-bold">{{ option.label }}</span>
                <span class="block text-[11px] mt-1 leading-snug">{{ option.helper }}</span>
              </label>
            </div>
            <p v-if="errors.type" class="text-xs text-rose-600 font-medium">{{ errors.type }}</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1">
              <label for="points" class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Jumlah Poin *</label>
              <input
                id="points"
                v-model="form.points"
                type="number"
                step="1"
                placeholder="100"
                class="w-full border rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-violet-500/20"
                :class="errors.points ? 'border-rose-500' : 'border-slate-200 focus:border-violet-500'"
              />
              <p v-if="errors.points" class="text-xs text-rose-600 font-medium">{{ errors.points }}</p>
            </div>

            <div class="space-y-1">
              <label for="amount" class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Nilai Rupiah</label>
              <input
                id="amount"
                v-model="form.amount"
                type="number"
                min="0"
                step="0.01"
                placeholder="Kosongkan untuk auto"
                class="w-full border rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-violet-500/20"
                :class="errors.amount ? 'border-rose-500' : 'border-slate-200 focus:border-violet-500'"
              />
              <p v-if="errors.amount" class="text-xs text-rose-600 font-medium">{{ errors.amount }}</p>
            </div>
          </div>

          <div class="space-y-1">
            <label for="sale_id" class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Sale ID Referensi</label>
            <input
              id="sale_id"
              v-model="form.sale_id"
              type="number"
              min="1"
              step="1"
              placeholder="Opsional"
              class="w-full border rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-violet-500/20"
              :class="errors.sale_id ? 'border-rose-500' : 'border-slate-200 focus:border-violet-500'"
            />
            <p v-if="errors.sale_id" class="text-xs text-rose-600 font-medium">{{ errors.sale_id }}</p>
          </div>

          <div class="space-y-1">
            <label for="description" class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Deskripsi *</label>
            <textarea
              id="description"
              v-model="form.description"
              rows="3"
              placeholder="Contoh: Bonus poin campaign akhir bulan"
              class="w-full border rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-violet-500/20 resize-none"
              :class="errors.description ? 'border-rose-500' : 'border-slate-200 focus:border-violet-500'"
            ></textarea>
            <p v-if="errors.description" class="text-xs text-rose-600 font-medium">{{ errors.description }}</p>
          </div>

          <div v-if="result" class="rounded-lg border border-teal-200 bg-teal-50 p-4 text-sm text-teal-800">
            <p class="font-bold">Transaksi terakhir berhasil disimpan.</p>
            <p class="mt-1">{{ result.customer?.name }} sekarang memiliki {{ formatPoints(result.customer?.point_balance) }} poin.</p>
          </div>

          <div class="flex gap-2">
            <button
              type="submit"
              :disabled="submitting"
              class="flex-1 inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-sm font-bold py-3 transition-colors disabled:opacity-60"
            >
              <span v-if="submitting" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              Submit Transaksi
            </button>
            <button type="button" @click="resetForm" class="px-4 py-3 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600 text-sm font-bold">
              Reset
            </button>
          </div>
        </form>
      </section>

      <section class="xl:col-span-7 bg-white border border-slate-100 rounded-lg p-5 shadow-sm space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 border-b border-slate-100 pb-4">
          <div>
            <h2 class="text-base font-bold text-slate-800">Ledger Transaksi Loyalty</h2>
            <p class="text-xs text-slate-400 mt-1">Riwayat terakhir dibatasi 300 transaksi untuk performa dashboard.</p>
          </div>
          <div class="flex flex-col sm:flex-row gap-2">
            <input
              v-model="transactionSearch"
              type="text"
              placeholder="Cari ledger..."
              class="border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20"
            />
            <button
              type="button"
              @click="fetchTransactions"
              :disabled="loadingTransactions"
              class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold disabled:opacity-60"
            >
              <span v-if="loadingTransactions" class="w-4 h-4 border-2 border-slate-600 border-t-transparent rounded-full animate-spin"></span>
              Refresh
            </button>
          </div>
        </div>

        <div v-if="editingTransaction" class="rounded-lg border border-violet-200 bg-violet-50 p-4 space-y-3">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="font-bold text-violet-900">Edit Catatan Ledger #{{ editingTransaction.id }}</p>
              <p class="text-xs text-violet-700">Saldo dan jumlah poin tidak bisa diedit agar audit trail tetap utuh.</p>
            </div>
            <button type="button" @click="cancelEditNote" class="text-violet-700 hover:text-violet-900 text-sm font-bold">Batal</button>
          </div>
          <textarea
            v-model="noteForm.description"
            rows="2"
            class="w-full border rounded-lg px-3 py-2.5 text-sm outline-none focus:ring-2 focus:ring-violet-500/20 resize-none"
            :class="noteErrors.description ? 'border-rose-500' : 'border-violet-200 focus:border-violet-500'"
          ></textarea>
          <p v-if="noteErrors.description" class="text-xs text-rose-600 font-medium">{{ noteErrors.description }}</p>
          <button
            type="button"
            @click="submitNote"
            :disabled="savingNote"
            class="inline-flex items-center justify-center gap-2 rounded-lg bg-violet-600 hover:bg-violet-700 text-white text-sm font-bold px-4 py-2 disabled:opacity-60"
          >
            <span v-if="savingNote" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            Simpan Catatan
          </button>
        </div>

        <div class="overflow-x-auto rounded-lg border border-slate-100">
          <table class="w-full border-collapse text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
              <tr>
                <th class="px-4 py-3">Tanggal</th>
                <th class="px-4 py-3">Customer</th>
                <th class="px-4 py-3">Tipe</th>
                <th class="px-4 py-3 text-right">Poin</th>
                <th class="px-4 py-3 text-right">Nilai</th>
                <th class="px-4 py-3">Deskripsi</th>
                <th class="px-4 py-3 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              <tr v-if="loadingTransactions">
                <td colspan="7" class="px-4 py-12 text-center text-slate-400">
                  <span class="inline-block w-8 h-8 border-4 border-violet-500 border-t-transparent rounded-full animate-spin mb-2"></span>
                  <p>Memuat ledger loyalty...</p>
                </td>
              </tr>
              <tr v-else-if="filteredTransactions.length === 0">
                <td colspan="7" class="px-4 py-12 text-center text-slate-400">Belum ada transaksi loyalty.</td>
              </tr>
              <tr v-else v-for="transaction in filteredTransactions" :key="transaction.id" class="hover:bg-slate-50/70">
                <td class="px-4 py-3 whitespace-nowrap text-slate-500">{{ formatDate(transaction.created_at) }}</td>
                <td class="px-4 py-3">
                  <p class="font-bold text-slate-800">{{ transaction.customer?.name || '-' }}</p>
                  <p class="text-xs text-slate-400">{{ transaction.customer?.member_code || 'No member code' }}</p>
                </td>
                <td class="px-4 py-3">
                  <span
                    class="inline-flex px-2.5 py-1 rounded text-[11px] font-bold uppercase"
                    :class="transaction.type === 'earn' ? 'bg-teal-50 text-teal-700 border border-teal-100' : transaction.type === 'redeem' ? 'bg-rose-50 text-rose-700 border border-rose-100' : 'bg-amber-50 text-amber-700 border border-amber-100'"
                  >
                    {{ transaction.type }}
                  </span>
                  <p v-if="transaction.sale" class="text-[11px] text-slate-400 mt-1">{{ transaction.sale.invoice_no }}</p>
                </td>
                <td class="px-4 py-3 text-right font-black whitespace-nowrap" :class="Number(transaction.points) < 0 ? 'text-rose-600' : 'text-teal-700'">
                  {{ Number(transaction.points) > 0 ? '+' : '' }}{{ formatPoints(transaction.points) }}
                </td>
                <td class="px-4 py-3 text-right font-semibold text-slate-700 whitespace-nowrap">{{ formatCurrency(transaction.amount) }}</td>
                <td class="px-4 py-3 max-w-[260px]">
                  <p class="truncate text-slate-600" :title="transaction.description">{{ transaction.description }}</p>
                  <p class="text-[11px] text-slate-400 mt-1">By {{ transaction.creator?.name || 'System' }}</p>
                </td>
                <td class="px-4 py-3 text-center">
                  <button
                    type="button"
                    @click="startEditNote(transaction)"
                    class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-xs font-bold text-slate-700"
                  >
                    Edit Note
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</template>
