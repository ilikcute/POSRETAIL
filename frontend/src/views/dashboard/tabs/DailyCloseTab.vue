<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()

const stores = ref([])
const closes = ref([])
const preview = ref(null)
const loadingStores = ref(false)
const loadingPreview = ref(false)
const loadingCloses = ref(false)
const submitting = ref(false)
const updatingId = ref(null)
const searchQuery = ref('')

const today = new Date().toISOString().slice(0, 10)

const form = ref({
  store_id: '',
  close_date: today,
  notes: 'End of Day closing otomatis dari dashboard POS'
})

const errors = ref({
  store_id: '',
  close_date: '',
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

const formatDate = (value) => {
  if (!value) return '-'
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return '-'

  return parsed.toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}

const resetErrors = () => {
  errors.value = {
    store_id: '',
    close_date: '',
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

const fetchCloses = async () => {
  loadingCloses.value = true
  try {
    const response = await api.get('/daily-closes')
    closes.value = response.data?.data || response.data || []
  } catch (error) {
    console.error('Error fetching daily closes:', error)
    toast.error(error.response?.data?.message || 'Gagal memuat riwayat Daily Close.')
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

  if (!form.value.close_date) {
    errors.value.close_date = 'Tanggal EOD wajib diisi.'
    valid = false
  } else if (!/^\d{4}-\d{2}-\d{2}$/.test(form.value.close_date)) {
    errors.value.close_date = 'Tanggal EOD harus berformat YYYY-MM-DD.'
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
    const response = await api.get('/daily-closes/preview', {
      params: {
        store_id: form.value.store_id,
        close_date: form.value.close_date
      }
    })
    preview.value = response.data?.data || null
  } catch (error) {
    console.error('Error previewing daily close:', error)
    preview.value = null
    if (error.response?.status === 422) {
      applyServerErrors(error.response.data?.errors || {})
    }
    toast.error(error.response?.data?.message || 'Gagal membuat preview Daily Close.')
  } finally {
    loadingPreview.value = false
  }
}

const submitDailyClose = async () => {
  if (!validateForm()) {
    toast.warning('Perbaiki error pada form Daily Close terlebih dahulu.')
    return
  }

  if (preview.value && !preview.value.can_close) {
    errors.value.close_date = preview.value.already_closed
      ? 'Tanggal EOD ini sudah pernah ditutup.'
      : 'Masih ada shift kasir yang belum ditutup.'
    toast.warning(errors.value.close_date)
    return
  }

  submitting.value = true
  try {
    const payload = {
      store_id: Number(form.value.store_id),
      close_date: form.value.close_date,
      notes: form.value.notes || null
    }

    await api.post('/daily-closes', payload)
    toast.success('Daily Close berhasil diproses.')
    await Promise.all([fetchCloses(), fetchPreview()])
  } catch (error) {
    console.error('Error submitting daily close:', error)
    if (error.response?.status === 422) {
      applyServerErrors(error.response.data?.errors || {})
      toast.error(error.response.data?.message || 'Validasi Daily Close gagal.')
    } else {
      toast.error(error.response?.data?.message || 'Gagal memproses Daily Close.')
    }
  } finally {
    submitting.value = false
  }
}

const verifyClose = async (close) => {
  updatingId.value = close.id
  try {
    await api.put(`/daily-closes/${close.id}`, {
      status: 'verified',
      notes: close.notes
    })
    toast.success('Daily Close berhasil diverifikasi.')
    await fetchCloses()
  } catch (error) {
    console.error('Error verifying daily close:', error)
    toast.error(error.response?.data?.message || 'Gagal verifikasi Daily Close.')
  } finally {
    updatingId.value = null
  }
}

const filteredCloses = computed(() => {
  const keyword = searchQuery.value.trim().toLowerCase()
  if (!keyword) return closes.value

  return closes.value.filter(close => {
    return [
      close.store?.name,
      close.close_date,
      close.status,
      close.notes,
      close.closed_by?.name
    ].filter(Boolean).some(value => String(value).toLowerCase().includes(keyword))
  })
})

const selectedStore = computed(() => {
  return stores.value.find(store => store.id === Number(form.value.store_id)) || null
})

watch(
  () => [form.value.store_id, form.value.close_date],
  () => {
    if (form.value.store_id && form.value.close_date) {
      fetchPreview()
    }
  }
)

onMounted(async () => {
  await fetchStores()
  await Promise.all([fetchCloses(), fetchPreview()])
})
</script>

<template>
  <div class="space-y-6">
    <div class="bg-white border border-slate-100 rounded-lg p-5 shadow-sm flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
      <div class="flex items-center gap-3">
        <span class="w-11 h-11 rounded-lg bg-slate-800 text-white flex items-center justify-center shadow-sm">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-6 h-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
          </svg>
        </span>
        <div>
          <h1 class="text-xl font-bold text-slate-800">Daily Close</h1>
          <p class="text-sm text-slate-500">End of Day closing, validasi shift, dan audit ringkasan transaksi harian.</p>
        </div>
      </div>

      <div v-if="selectedStore" class="rounded-lg bg-slate-50 border border-slate-100 px-4 py-3">
        <p class="text-[11px] uppercase font-bold text-slate-400">Store Aktif</p>
        <p class="text-sm font-black text-slate-800">{{ selectedStore.name }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
      <section class="xl:col-span-4 bg-white border border-slate-100 rounded-lg p-5 shadow-sm space-y-5">
        <div class="border-b border-slate-100 pb-4">
          <h2 class="text-base font-bold text-slate-800">Proses EOD</h2>
          <p class="text-xs text-slate-400 mt-1">Preview dulu sebelum submit. Angka final tetap dihitung ulang di backend.</p>
        </div>

        <form @submit.prevent="submitDailyClose" class="space-y-4">
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

          <div class="space-y-1">
            <label for="close_date" class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Tanggal EOD *</label>
            <input
              id="close_date"
              v-model="form.close_date"
              type="date"
              class="w-full bg-white border rounded-lg px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-slate-500/20"
              :class="errors.close_date ? 'border-rose-500' : 'border-slate-200 focus:border-slate-500'"
            />
            <p v-if="errors.close_date" class="text-xs text-rose-600 font-medium">{{ errors.close_date }}</p>
          </div>

          <div class="space-y-1">
            <label for="notes" class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Catatan</label>
            <textarea
              id="notes"
              v-model="form.notes"
              rows="4"
              placeholder="Catatan closing harian..."
              class="w-full bg-white border rounded-lg px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-slate-500/20 resize-none"
              :class="errors.notes ? 'border-rose-500' : 'border-slate-200 focus:border-slate-500'"
            ></textarea>
            <p v-if="errors.notes" class="text-xs text-rose-600 font-medium">{{ errors.notes }}</p>
          </div>

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
              class="flex-1 inline-flex items-center justify-center gap-2 rounded-lg bg-slate-800 hover:bg-slate-900 text-white text-sm font-bold py-3 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <span v-if="submitting" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
              Submit EOD
            </button>
          </div>
        </form>
      </section>

      <section class="xl:col-span-8 bg-white border border-slate-100 rounded-lg p-5 shadow-sm space-y-5">
        <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
          <div>
            <h2 class="text-base font-bold text-slate-800">Preview Ringkasan EOD</h2>
            <p class="text-xs text-slate-400 mt-1">Status submit tergantung duplikasi tanggal dan shift kasir yang masih open.</p>
          </div>
          <span
            v-if="preview"
            class="px-3 py-1 rounded text-xs font-bold"
            :class="preview.can_close ? 'bg-teal-50 text-teal-700 border border-teal-100' : 'bg-rose-50 text-rose-700 border border-rose-100'"
          >
            {{ preview.can_close ? 'READY TO CLOSE' : 'BLOCKED' }}
          </span>
        </div>

        <div v-if="loadingPreview" class="py-16 text-center text-slate-400">
          <span class="inline-block w-9 h-9 border-4 border-slate-700 border-t-transparent rounded-full animate-spin mb-2"></span>
          <p>Memuat preview Daily Close...</p>
        </div>

        <div v-else-if="!preview" class="py-16 text-center text-slate-400 border border-dashed border-slate-200 rounded-lg">
          Pilih store dan tanggal untuk melihat preview.
        </div>

        <div v-else class="space-y-5">
          <div
            v-if="preview.already_closed || preview.open_shift_count > 0"
            class="rounded-lg border p-4 text-sm"
            :class="preview.already_closed ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-rose-200 bg-rose-50 text-rose-800'"
          >
            <p class="font-bold">{{ preview.already_closed ? 'Tanggal sudah ditutup' : 'Masih ada shift open' }}</p>
            <p class="mt-1">
              {{ preview.already_closed ? 'Daily Close untuk tanggal ini sudah ada.' : `${preview.open_shift_count} shift masih perlu ditutup sebelum EOD.` }}
            </p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="rounded-lg border border-teal-100 bg-teal-50 p-4">
              <p class="text-[11px] uppercase font-bold text-teal-600">Total Sales</p>
              <p class="text-xl font-black text-teal-800 mt-1">{{ formatCurrency(preview.totals.total_sales) }}</p>
              <p class="text-xs text-teal-700 mt-1">{{ preview.sales_count }} transaksi</p>
            </div>
            <div class="rounded-lg border border-sky-100 bg-sky-50 p-4">
              <p class="text-[11px] uppercase font-bold text-sky-600">Cash Sales</p>
              <p class="text-xl font-black text-sky-800 mt-1">{{ formatCurrency(preview.totals.total_cash_sales) }}</p>
            </div>
            <div class="rounded-lg border border-indigo-100 bg-indigo-50 p-4">
              <p class="text-[11px] uppercase font-bold text-indigo-600">Non-Cash Sales</p>
              <p class="text-xl font-black text-indigo-800 mt-1">{{ formatCurrency(preview.totals.total_non_cash_sales) }}</p>
            </div>
            <div class="rounded-lg border border-amber-100 bg-amber-50 p-4">
              <p class="text-[11px] uppercase font-bold text-amber-600">Total Purchases</p>
              <p class="text-xl font-black text-amber-800 mt-1">{{ formatCurrency(preview.totals.total_purchases) }}</p>
            </div>
            <div class="rounded-lg border border-rose-100 bg-rose-50 p-4">
              <p class="text-[11px] uppercase font-bold text-rose-600">Shift Difference</p>
              <p class="text-xl font-black text-rose-800 mt-1">{{ formatCurrency(preview.totals.total_shift_difference) }}</p>
              <p class="text-xs text-rose-700 mt-1">{{ preview.closed_shift_count }} shift closed</p>
            </div>
            <div class="rounded-lg border border-slate-100 bg-slate-50 p-4">
              <p class="text-[11px] uppercase font-bold text-slate-500">Tax / Discount</p>
              <p class="text-sm font-bold text-slate-800 mt-1">Tax: {{ formatCurrency(preview.totals.total_tax) }}</p>
              <p class="text-sm font-bold text-slate-800">Disc: {{ formatCurrency(preview.totals.total_discount) }}</p>
            </div>
          </div>
        </div>
      </section>
    </div>

    <section class="bg-white border border-slate-100 rounded-lg p-5 shadow-sm space-y-4">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 border-b border-slate-100 pb-4">
        <div>
          <h2 class="text-base font-bold text-slate-800">Riwayat Daily Close</h2>
          <p class="text-xs text-slate-400 mt-1">Data terakhir dibatasi 180 record untuk menjaga performa dashboard.</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-2">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari tanggal, store, status..."
            class="border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-500/20"
          />
          <button
            type="button"
            @click="fetchCloses"
            :disabled="loadingCloses"
            class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold disabled:opacity-60"
          >
            <span v-if="loadingCloses" class="w-4 h-4 border-2 border-slate-700 border-t-transparent rounded-full animate-spin"></span>
            Refresh
          </button>
        </div>
      </div>

      <div class="overflow-x-auto rounded-lg border border-slate-100">
        <table class="w-full border-collapse text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
              <th class="px-4 py-3">Tanggal</th>
              <th class="px-4 py-3">Store</th>
              <th class="px-4 py-3 text-right">Sales</th>
              <th class="px-4 py-3 text-right">Purchases</th>
              <th class="px-4 py-3 text-right">Selisih Shift</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3">Closed By</th>
              <th class="px-4 py-3 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-if="loadingCloses">
              <td colspan="8" class="px-4 py-12 text-center text-slate-400">
                <span class="inline-block w-8 h-8 border-4 border-slate-700 border-t-transparent rounded-full animate-spin mb-2"></span>
                <p>Memuat riwayat Daily Close...</p>
              </td>
            </tr>
            <tr v-else-if="filteredCloses.length === 0">
              <td colspan="8" class="px-4 py-12 text-center text-slate-400">Belum ada Daily Close.</td>
            </tr>
            <tr v-else v-for="close in filteredCloses" :key="close.id" class="hover:bg-slate-50/70">
              <td class="px-4 py-3 font-bold text-slate-800 whitespace-nowrap">{{ formatDate(close.close_date) }}</td>
              <td class="px-4 py-3 text-slate-700">{{ close.store?.name || '-' }}</td>
              <td class="px-4 py-3 text-right font-bold text-teal-700 whitespace-nowrap">{{ formatCurrency(close.total_sales) }}</td>
              <td class="px-4 py-3 text-right font-bold text-amber-700 whitespace-nowrap">{{ formatCurrency(close.total_purchases) }}</td>
              <td class="px-4 py-3 text-right font-bold whitespace-nowrap" :class="Number(close.total_shift_difference) === 0 ? 'text-slate-700' : 'text-rose-700'">
                {{ formatCurrency(close.total_shift_difference) }}
              </td>
              <td class="px-4 py-3">
                <span
                  class="inline-flex px-2.5 py-1 rounded text-[11px] font-bold uppercase"
                  :class="close.status === 'verified' ? 'bg-teal-50 text-teal-700 border border-teal-100' : 'bg-slate-100 text-slate-700 border border-slate-200'"
                >
                  {{ close.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-slate-600">{{ close.closed_by?.name || '-' }}</td>
              <td class="px-4 py-3 text-center">
                <button
                  v-if="close.status !== 'verified'"
                  type="button"
                  @click="verifyClose(close)"
                  :disabled="updatingId === close.id"
                  class="px-3 py-1.5 rounded-lg bg-teal-50 hover:bg-teal-100 text-xs font-bold text-teal-700 disabled:opacity-60"
                >
                  {{ updatingId === close.id ? 'Saving...' : 'Verify' }}
                </button>
                <span v-else class="text-xs text-slate-400">Verified</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
