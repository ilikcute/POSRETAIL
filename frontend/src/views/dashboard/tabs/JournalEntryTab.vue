<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()

// Core States
const entries = ref([])
const accounts = ref([])
const loading = ref(false)
const submitting = ref(false)

// Query & Filter States
const searchQuery = ref('')
const filterStartDate = ref('')
const filterEndDate = ref('')
const filterAccount = ref('')

// Pagination States
const currentPage = ref(1)
const lastPage = ref(1)
const totalItems = ref(0)
const perPage = ref(10)

// Modal Visibility
const showCreateEditModal = ref(false)
const showDetailModal = ref(false)
const selectedEntry = ref(null)

// Form State
const isEditMode = ref(false)
const editId = ref(null)
const form = ref({
  transaction_date: new Date().toISOString().split('T')[0],
  description: '',
  items: [
    { account_id: '', debit: 0, credit: 0 },
    { account_id: '', debit: 0, credit: 0 }
  ]
})

// Error State
const errors = ref({
  transaction_date: '',
  description: '',
  items: []
})

// Fetch Accounts List (for dropdowns)
const fetchAccounts = async () => {
  try {
    const res = await api.get('/accounts')
    // We only want active accounts for new entries
    const allAccounts = res.data?.data || res.data || []
    accounts.value = allAccounts
  } catch (err) {
    console.error('Error fetching accounts:', err)
    toast.error('Gagal mengambil data akun.')
  }
}

// Active accounts computed helper
const activeAccounts = computed(() => {
  return accounts.value.filter(acc => acc.is_active)
})

// Fetch Journal Entries
const fetchEntries = async (page = 1) => {
  loading.value = true
  try {
    const params = {
      page,
      per_page: perPage.value,
      search: searchQuery.value,
      start_date: filterStartDate.value,
      end_date: filterEndDate.value,
      account_id: filterAccount.value
    }
    const res = await api.get('/journal-entries', { params })
    const payload = res.data?.data || res.data
    
    entries.value = payload.data || []
    currentPage.value = payload.current_page || 1
    lastPage.value = payload.last_page || 1
    totalItems.value = payload.total || 0
  } catch (err) {
    console.error('Error fetching journal entries:', err)
    toast.error('Gagal mengambil data jurnal.')
  } finally {
    loading.value = false
  }
}

// Watch filters to trigger fetch
watch([searchQuery, filterStartDate, filterEndDate, filterAccount], () => {
  fetchEntries(1)
})

// Life Cycle Hooks
onMounted(() => {
  fetchAccounts()
  fetchEntries(1)
})

// Computed totals for Form items
const totalFormDebit = computed(() => {
  return form.value.items.reduce((sum, item) => sum + (parseFloat(item.debit) || 0), 0)
})

const totalFormCredit = computed(() => {
  return form.value.items.reduce((sum, item) => sum + (parseFloat(item.credit) || 0), 0)
})

const formDifference = computed(() => {
  return Math.abs(totalFormDebit.value - totalFormCredit.value)
})

const isFormBalanced = computed(() => {
  return formDifference.value < 0.01
})

// Row manipulation
const addRow = () => {
  form.value.items.push({ account_id: '', debit: 0, credit: 0 })
  errors.value.items.push({ account_id: '', debit: '', credit: '' })
}

const removeRow = (index) => {
  if (form.value.items.length <= 2) {
    toast.warning('Jurnal entry harus memiliki minimal 2 baris.')
    return
  }
  form.value.items.splice(index, 1)
  errors.value.items.splice(index, 1)
}

// Validation logic
const validateForm = () => {
  let isValid = true
  errors.value = {
    transaction_date: '',
    description: '',
    items: []
  }

  // Initialise item errors array
  form.value.items.forEach(() => {
    errors.value.items.push({ account_id: '', debit: '', credit: '' })
  })

  // Date Check
  if (!form.value.transaction_date) {
    errors.value.transaction_date = 'Tanggal transaksi wajib diisi.'
    isValid = false
  }

  // Min lines check
  if (form.value.items.length < 2) {
    toast.error('Jurnal entry harus memiliki minimal 2 baris.')
    isValid = false
  }

  // Row checks
  form.value.items.forEach((item, index) => {
    if (!item.account_id) {
      errors.value.items[index].account_id = 'Pilih akun terlebih dahulu.'
      isValid = false
    }

    const d = parseFloat(item.debit) || 0
    const c = parseFloat(item.credit) || 0

    if (d < 0) {
      errors.value.items[index].debit = 'Nilai debit tidak boleh negatif.'
      isValid = false
    }
    if (c < 0) {
      errors.value.items[index].credit = 'Nilai credit tidak boleh negatif.'
      isValid = false
    }

    if (d > 0 && c > 0) {
      errors.value.items[index].debit = 'Satu baris tidak boleh memiliki Debit dan Credit sekaligus.'
      errors.value.items[index].credit = 'Satu baris tidak boleh memiliki Debit dan Credit sekaligus.'
      isValid = false
    }

    if (d === 0 && c === 0) {
      errors.value.items[index].debit = 'Isi salah satu antara Debit atau Credit.'
      errors.value.items[index].credit = 'Isi salah satu antara Debit atau Credit.'
      isValid = false
    }
  })

  // Balanced check
  if (isValid && !isFormBalanced.value) {
    toast.error(`Jurnal tidak seimbang. Selisih: Rp ${formatNumber(formDifference.value)}`)
    isValid = false
  }

  return isValid
}

// Open modal helper
const openCreateModal = () => {
  isEditMode.value = false
  editId.value = null
  form.value = {
    transaction_date: new Date().toISOString().split('T')[0],
    description: '',
    items: [
      { account_id: '', debit: 0, credit: 0 },
      { account_id: '', debit: 0, credit: 0 }
    ]
  }
  errors.value = {
    transaction_date: '',
    description: '',
    items: [
      { account_id: '', debit: '', credit: '' },
      { account_id: '', debit: '', credit: '' }
    ]
  }
  showCreateEditModal.value = true
}

const openEditModal = (entry) => {
  if (entry.reference_no && !entry.reference_no.startsWith('JV-')) {
    toast.error('Jurnal otomatis sistem tidak dapat diubah secara manual.')
    return
  }

  isEditMode.value = true
  editId.value = entry.id
  
  // Format items for edit
  const formattedItems = entry.items.map(item => ({
    account_id: item.account_id,
    debit: parseFloat(item.debit),
    credit: parseFloat(item.credit)
  }))

  form.value = {
    transaction_date: entry.transaction_date.split('T')[0],
    description: entry.description || '',
    items: formattedItems
  }

  // Pre-fill errors
  errors.value = {
    transaction_date: '',
    description: '',
    items: formattedItems.map(() => ({ account_id: '', debit: '', credit: '' }))
  }

  showCreateEditModal.value = true
}

const openDetailModal = (entry) => {
  selectedEntry.value = entry
  showDetailModal.value = true
}

// Format numbers
const formatNumber = (num) => {
  return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num)
}

// Calculate sum of debits for an entry
const getEntryTotal = (entry) => {
  if (!entry.items) return 0
  return entry.items.reduce((sum, item) => sum + parseFloat(item.debit || 0), 0)
}

// Submit data
const handleSubmit = async () => {
  if (!validateForm()) return

  submitting.value = true
  try {
    const payload = {
      transaction_date: form.value.transaction_date,
      description: form.value.description,
      items: form.value.items
    }

    if (isEditMode.value) {
      await api.put(`/journal-entries/${editId.value}`, payload)
      toast.success('Jurnal berhasil diperbarui!')
    } else {
      await api.post('/journal-entries', payload)
      toast.success('Jurnal berhasil disimpan!')
    }

    showCreateEditModal.value = false
    fetchEntries(currentPage.value)
  } catch (err) {
    console.error('Error submitting form:', err)
    const backendErrors = err.response?.data?.errors
    const message = err.response?.data?.message

    if (backendErrors) {
      // Map validation errors back to inputs
      if (backendErrors.transaction_date) errors.value.transaction_date = backendErrors.transaction_date[0]
      if (backendErrors.description) errors.value.description = backendErrors.description[0]
      
      // Items list index mapping
      Object.keys(backendErrors).forEach(key => {
        if (key.startsWith('items.')) {
          const parts = key.split('.')
          const idx = parseInt(parts[1])
          const field = parts[2]
          if (errors.value.items[idx]) {
            errors.value.items[idx][field] = backendErrors[key][0]
          }
        }
      })
      toast.error('Gagal memvalidasi data jurnal. Silakan periksa kolom input.')
    } else {
      toast.error(message || 'Terjadi kesalahan pada server saat memposting jurnal.')
    }
  } finally {
    submitting.value = false
  }
}

// Delete entry
const handleDelete = async (entry) => {
  if (entry.reference_no && !entry.reference_no.startsWith('JV-')) {
    toast.error('Jurnal otomatis sistem tidak dapat dihapus secara manual.')
    return
  }

  if (confirm(`Apakah Anda yakin ingin menghapus jurnal ${entry.reference_no}? Saldo di buku besar akan secara otomatis dibalik.`)) {
    try {
      await api.delete(`/journal-entries/${entry.id}`)
      toast.success('Jurnal berhasil dihapus dan saldo buku besar dibalik!')
      fetchEntries(currentPage.value)
    } catch (err) {
      console.error('Error deleting entry:', err)
      const message = err.response?.data?.message || 'Gagal menghapus jurnal entry.'
      toast.error(message)
    }
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header Card -->
    <div class="bg-white rounded-2xl shadow-sm p-6 border border-teal-50 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Jurnal Umum (General Ledger Entries)</h1>
        <p class="text-slate-500 text-sm mt-1">Kelola pencatatan double-entry secara manual untuk penyesuaian kas, modal, beban, atau piutang/utang dagang.</p>
      </div>
      <div>
        <button
          @click="openCreateModal"
          class="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-teal-600 to-emerald-600 hover:from-teal-700 hover:to-emerald-700 text-white font-medium rounded-xl shadow-md shadow-teal-500/10 transition-all duration-200 cursor-pointer"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          Tambah Jurnal Baru
        </button>
      </div>
    </div>

    <!-- Filter & Search Controls -->
    <div class="bg-white rounded-2xl shadow-sm p-5 border border-teal-50 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Search -->
      <div class="relative">
        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Pencarian</label>
        <div class="relative">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="No. Ref / Deskripsi..."
            class="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-100 text-slate-700 text-sm transition-all duration-150 outline-none"
          />
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.602 10.602z" />
          </svg>
        </div>
      </div>

      <!-- Start Date -->
      <div>
        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Tanggal Mulai</label>
        <input
          v-model="filterStartDate"
          type="date"
          class="w-full px-4 py-2 border border-slate-200 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-100 text-slate-700 text-sm transition-all duration-150 outline-none"
        />
      </div>

      <!-- End Date -->
      <div>
        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Tanggal Selesai</label>
        <input
          v-model="filterEndDate"
          type="date"
          class="w-full px-4 py-2 border border-slate-200 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-100 text-slate-700 text-sm transition-all duration-150 outline-none"
        />
      </div>

      <!-- Account Filter -->
      <div>
        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Filter Akun</label>
        <select
          v-model="filterAccount"
          class="w-full px-4 py-2 border border-slate-200 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-100 text-slate-700 text-sm transition-all duration-150 outline-none bg-white"
        >
          <option value="">Semua Akun</option>
          <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
            {{ acc.code }} - {{ acc.name }}
          </option>
        </select>
      </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-2xl shadow-sm border border-teal-50 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-slate-50/75 border-b border-slate-100 text-slate-600 text-xs font-bold uppercase tracking-wider">
              <th class="py-4 px-6">No. Referensi</th>
              <th class="py-4 px-6">Tanggal</th>
              <th class="py-4 px-6">Deskripsi</th>
              <th class="py-4 px-6 text-right">Total Debit/Credit</th>
              <th class="py-4 px-6">Dibuat Oleh</th>
              <th class="py-4 px-6 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
            <!-- Loading State -->
            <tr v-if="loading">
              <td colspan="6" class="py-12 text-center text-slate-400">
                <div class="flex items-center justify-center gap-3">
                  <div class="w-6 h-6 border-2 border-teal-500 border-t-transparent rounded-full animate-spin"></div>
                  Memuat data jurnal...
                </div>
              </td>
            </tr>

            <!-- Empty State -->
            <tr v-else-if="entries.length === 0">
              <td colspan="6" class="py-12 text-center text-slate-400">
                Tidak ada jurnal entry yang ditemukan sesuai filter.
              </td>
            </tr>

            <!-- List Data -->
            <tr v-else v-for="entry in entries" :key="entry.id" class="hover:bg-slate-50/50 transition-colors duration-150">
              <td class="py-4 px-6 font-semibold text-slate-900">
                {{ entry.reference_no }}
                <span 
                  v-if="!entry.reference_no.startsWith('JV-')" 
                  class="ml-1 px-1.5 py-0.5 text-[10px] font-bold uppercase rounded bg-blue-50 text-blue-600 border border-blue-100"
                >
                  System
                </span>
              </td>
              <td class="py-4 px-6">
                {{ new Date(entry.transaction_date).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' }) }}
              </td>
              <td class="py-4 px-6 max-w-xs truncate" :title="entry.description">
                {{ entry.description || '-' }}
              </td>
              <td class="py-4 px-6 text-right font-mono font-bold text-slate-800">
                Rp {{ formatNumber(getEntryTotal(entry)) }}
              </td>
              <td class="py-4 px-6">
                {{ entry.creator?.name || 'Sistem' }}
              </td>
              <td class="py-4 px-6 text-center">
                <div class="flex items-center justify-center gap-2">
                  <!-- Detail Button -->
                  <button
                    @click="openDetailModal(entry)"
                    title="Lihat Detail Jurnal"
                    class="p-2 text-slate-500 hover:text-teal-600 hover:bg-teal-50 rounded-lg transition-colors cursor-pointer"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                  </button>

                  <!-- Edit Button -->
                  <button
                    v-if="entry.reference_no.startsWith('JV-')"
                    @click="openEditModal(entry)"
                    title="Ubah Jurnal"
                    class="p-2 text-slate-500 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-colors cursor-pointer"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                    </svg>
                  </button>

                  <!-- Delete Button -->
                  <button
                    v-if="entry.reference_no.startsWith('JV-')"
                    @click="handleDelete(entry)"
                    title="Hapus Jurnal"
                    class="p-2 text-slate-500 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div v-if="lastPage > 1" class="bg-slate-50/50 border-t border-slate-100 py-4 px-6 flex items-center justify-between">
        <span class="text-xs text-slate-500">
          Menampilkan halaman {{ currentPage }} dari {{ lastPage }} (Total {{ totalItems }} entri)
        </span>
        <div class="flex items-center gap-1">
          <button
            :disabled="currentPage === 1"
            @click="fetchEntries(currentPage - 1)"
            class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-white disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            Kembali
          </button>
          <button
            v-for="page in lastPage"
            :key="page"
            @click="fetchEntries(page)"
            class="w-8 h-8 rounded-lg text-xs font-semibold transition-colors"
            :class="[
              page === currentPage
                ? 'bg-teal-600 text-white shadow-sm'
                : 'border border-slate-200 text-slate-600 hover:bg-white'
            ]"
          >
            {{ page }}
          </button>
          <button
            :disabled="currentPage === lastPage"
            @click="fetchEntries(currentPage + 1)"
            class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-white disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            Lanjut
          </button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showCreateEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 overflow-y-auto">
      <div class="bg-white w-full max-w-4xl rounded-2xl shadow-2xl border border-teal-50 flex flex-col max-h-[90vh]">
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h3 class="text-lg font-bold text-slate-800">{{ isEditMode ? 'Ubah Jurnal Umum' : 'Buat Jurnal Umum Baru' }}</h3>
            <p class="text-xs text-slate-500 mt-1">Pastikan total sisi debit dan kredit seimbang (balance).</p>
          </div>
          <button @click="showCreateEditModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Modal Body (Form) -->
        <div class="p-6 overflow-y-auto space-y-6 flex-1">
          <!-- Date and Desc Row -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1.5">Tanggal Transaksi <span class="text-red-500">*</span></label>
              <input
                v-model="form.transaction_date"
                type="date"
                class="w-full px-4 py-2 border border-slate-200 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-100 text-slate-700 text-sm outline-none transition-all"
                :class="errors.transaction_date ? 'border-red-400 ring-2 ring-red-50 font-medium' : ''"
              />
              <p v-if="errors.transaction_date" class="text-xs text-red-500 mt-1">{{ errors.transaction_date }}</p>
            </div>
            
            <div class="md:col-span-2">
              <label class="block text-sm font-semibold text-slate-700 mb-1.5">Deskripsi / Keterangan</label>
              <input
                v-model="form.description"
                type="text"
                placeholder="Tulis alasan penyesuaian atau pencatatan..."
                class="w-full px-4 py-2 border border-slate-200 rounded-xl focus:border-teal-500 focus:ring-2 focus:ring-teal-100 text-slate-700 text-sm outline-none transition-all"
                :class="errors.description ? 'border-red-400 ring-2 ring-red-50' : ''"
              />
              <p v-if="errors.description" class="text-xs text-red-500 mt-1">{{ errors.description }}</p>
            </div>
          </div>

          <!-- Journal Lines Table -->
          <div>
            <div class="flex items-center justify-between mb-3">
              <h4 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Item Baris Jurnal</h4>
              <button
                @click="addRow"
                type="button"
                class="flex items-center gap-1.5 px-3 py-1.5 bg-teal-50 hover:bg-teal-100 text-teal-700 font-semibold text-xs rounded-lg transition-colors cursor-pointer border border-teal-200/50"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Baris
              </button>
            </div>

            <div class="border border-slate-100 rounded-xl overflow-hidden shadow-inner bg-slate-50/20">
              <table class="w-full text-left border-collapse">
                <thead>
                  <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 text-xs font-bold uppercase tracking-wider">
                    <th class="py-3 px-4 w-1/2">Akun Perkiraan <span class="text-red-500">*</span></th>
                    <th class="py-3 px-4 text-right w-1/4">Debit (Rp)</th>
                    <th class="py-3 px-4 text-right w-1/4">Credit (Rp)</th>
                    <th class="py-3 px-4 text-center w-12">Hapus</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="(item, idx) in form.items" :key="idx" class="bg-white hover:bg-slate-50/30">
                    <!-- Account Dropdown -->
                    <td class="py-3.5 px-4 vertical-top">
                      <select
                        v-model="item.account_id"
                        class="w-full px-3 py-1.5 border border-slate-200 rounded-lg text-slate-700 text-xs outline-none bg-white focus:border-teal-500"
                        :class="errors.items[idx]?.account_id ? 'border-red-400 ring-1 ring-red-50' : ''"
                      >
                        <option value="" disabled>Pilih Akun...</option>
                        <option v-for="acc in activeAccounts" :key="acc.id" :value="acc.id">
                          {{ acc.code }} - {{ acc.name }} ({{ acc.type.toUpperCase() }})
                        </option>
                      </select>
                      <p v-if="errors.items[idx]?.account_id" class="text-[10px] text-red-500 mt-1 font-semibold">{{ errors.items[idx].account_id }}</p>
                    </td>

                    <!-- Debit Input -->
                    <td class="py-3.5 px-4">
                      <input
                        v-model.number="item.debit"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        class="w-full text-right px-3 py-1.5 border border-slate-200 rounded-lg text-xs font-mono outline-none focus:border-teal-500"
                        :class="errors.items[idx]?.debit ? 'border-red-400 ring-1 ring-red-50' : ''"
                      />
                      <p v-if="errors.items[idx]?.debit" class="text-[10px] text-red-500 mt-1 font-semibold text-right">{{ errors.items[idx].debit }}</p>
                    </td>

                    <!-- Credit Input -->
                    <td class="py-3.5 px-4">
                      <input
                        v-model.number="item.credit"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="0.00"
                        class="w-full text-right px-3 py-1.5 border border-slate-200 rounded-lg text-xs font-mono outline-none focus:border-teal-500"
                        :class="errors.items[idx]?.credit ? 'border-red-400 ring-1 ring-red-50' : ''"
                      />
                      <p v-if="errors.items[idx]?.credit" class="text-[10px] text-red-500 mt-1 font-semibold text-right">{{ errors.items[idx].credit }}</p>
                    </td>

                    <!-- Delete Row Button -->
                    <td class="py-3.5 px-4 text-center">
                      <button
                        @click="removeRow(idx)"
                        type="button"
                        class="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors cursor-pointer"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                        </svg>
                      </button>
                    </td>
                  </tr>

                  <!-- Totals Summary Row -->
                  <tr class="bg-slate-50 font-bold border-t border-slate-200">
                    <td class="py-3 px-4 text-slate-700 text-xs uppercase tracking-wider text-right">Total Akhir:</td>
                    <td class="py-3 px-4 text-right font-mono text-xs text-slate-800">Rp {{ formatNumber(totalFormDebit) }}</td>
                    <td class="py-3 px-4 text-right font-mono text-xs text-slate-800">Rp {{ formatNumber(totalFormCredit) }}</td>
                    <td></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Balance Mismatch Warning Banner -->
            <div class="mt-4 flex items-center justify-between px-4 py-3 rounded-xl border" :class="isFormBalanced ? 'bg-emerald-50 border-emerald-100 text-emerald-800' : 'bg-rose-50 border-rose-100 text-rose-800'">
              <div class="flex items-center gap-2 text-xs font-semibold">
                <span class="w-2.5 h-2.5 rounded-full animate-pulse" :class="isFormBalanced ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]' : 'bg-rose-500 shadow-[0_0_8px_rgba(239,68,68,0.5)]'"></span>
                <span>{{ isFormBalanced ? 'Sisi Debit dan Kredit Seimbang (Balanced)' : 'Sisi Debit dan Kredit Belum Seimbang' }}</span>
              </div>
              <div v-if="!isFormBalanced" class="font-mono text-xs font-bold">
                Selisih: Rp {{ formatNumber(formDifference) }}
              </div>
              <div v-else class="text-xs font-semibold">
                Sesuai Aturan Double-Entry
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end gap-3 rounded-b-2xl">
          <button
            @click="showCreateEditModal = false"
            type="button"
            class="px-4 py-2 border border-slate-200 rounded-xl text-slate-600 hover:bg-slate-50 font-medium text-sm transition-colors cursor-pointer"
          >
            Batal
          </button>
          <button
            @click="handleSubmit"
            :disabled="submitting || !isFormBalanced"
            type="button"
            class="px-5 py-2 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white font-medium text-sm rounded-xl shadow-md shadow-teal-500/10 transition-all flex items-center gap-2 cursor-pointer"
          >
            <div v-if="submitting" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
            <span>{{ isEditMode ? 'Simpan Perubahan' : 'Posting Jurnal' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Detail View Modal -->
    <div v-if="showDetailModal && selectedEntry" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 overflow-y-auto">
      <div class="bg-white w-full max-w-3xl rounded-2xl shadow-2xl border border-teal-50 flex flex-col">
        <!-- Modal Header -->
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
          <div>
            <span class="text-xs font-bold text-teal-600 bg-teal-50 px-2.5 py-1 rounded-full uppercase tracking-wider">Jurnal Voucher</span>
            <h3 class="text-xl font-bold text-slate-800 mt-1.5">No. Ref: {{ selectedEntry.reference_no }}</h3>
          </div>
          <button @click="showDetailModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6 space-y-6">
          <!-- Information Grid -->
          <div class="grid grid-cols-2 gap-6 bg-slate-50/50 p-4 border border-slate-100 rounded-xl text-sm">
            <div>
              <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Tanggal Posting</p>
              <p class="font-semibold text-slate-800 mt-1">
                {{ new Date(selectedEntry.transaction_date).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
              </p>
            </div>
            <div>
              <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Operator Pembuat</p>
              <p class="font-semibold text-slate-800 mt-1">{{ selectedEntry.creator?.name || 'Sistem' }}</p>
            </div>
            <div class="col-span-2">
              <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Deskripsi Kegiatan</p>
              <p class="text-slate-700 mt-1">{{ selectedEntry.description || 'Tidak ada deskripsi.' }}</p>
            </div>
          </div>

          <!-- Entries Breakdown Table -->
          <div>
            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2.5">Rincian Debet / Kredit (Double Entry)</h4>
            <div class="border border-slate-100 rounded-xl overflow-hidden shadow-sm">
              <table class="w-full text-left border-collapse text-sm">
                <thead>
                  <tr class="bg-slate-50 border-b border-slate-100 text-slate-600 text-xs font-bold uppercase tracking-wider">
                    <th class="py-3 px-4">Kode Perkiraan</th>
                    <th class="py-3 px-4">Nama Akun</th>
                    <th class="py-3 px-4 text-right">Debit (Rp)</th>
                    <th class="py-3 px-4 text-right">Credit (Rp)</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                  <tr v-for="item in selectedEntry.items" :key="item.id" class="hover:bg-slate-50/30">
                    <td class="py-3 px-4 font-mono font-medium" :class="parseFloat(item.credit) > 0 ? 'pl-8 text-slate-500' : 'text-slate-800'">
                      {{ item.account?.code }}
                    </td>
                    <td class="py-3 px-4" :class="parseFloat(item.credit) > 0 ? 'pl-8 text-slate-500' : 'font-semibold text-slate-800'">
                      {{ item.account?.name }}
                    </td>
                    <td class="py-3 px-4 text-right font-mono" :class="parseFloat(item.debit) > 0 ? 'text-slate-900 font-bold' : 'text-slate-300'">
                      {{ parseFloat(item.debit) > 0 ? 'Rp ' + formatNumber(item.debit) : '-' }}
                    </td>
                    <td class="py-3 px-4 text-right font-mono" :class="parseFloat(item.credit) > 0 ? 'text-slate-900 font-bold' : 'text-slate-300'">
                      {{ parseFloat(item.credit) > 0 ? 'Rp ' + formatNumber(item.credit) : '-' }}
                    </td>
                  </tr>
                  <!-- Footer totals -->
                  <tr class="bg-slate-50 font-bold border-t border-slate-100">
                    <td colspan="2" class="py-3 px-4 text-right text-xs uppercase tracking-wider text-slate-500">Total Seimbang:</td>
                    <td class="py-3 px-4 text-right font-mono">Rp {{ formatNumber(getEntryTotal(selectedEntry)) }}</td>
                    <td class="py-3 px-4 text-right font-mono">Rp {{ formatNumber(getEntryTotal(selectedEntry)) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-end rounded-b-2xl">
          <button
            @click="showDetailModal = false"
            class="px-5 py-2 bg-slate-800 hover:bg-slate-900 text-white font-medium text-sm rounded-xl transition-all cursor-pointer shadow-md"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.vertical-top {
  vertical-align: top;
}
</style>
