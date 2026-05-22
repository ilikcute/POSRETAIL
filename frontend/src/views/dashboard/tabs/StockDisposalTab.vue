<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useToast } from 'vue-toastification'
import api from '../../../services/api'

const toast = useToast()
const loading = ref(false)
const submitting = ref(false)
const actionLoading = ref(false)
const warehouses = ref([])
const products = ref([])
const productVariants = ref([])
const disposals = ref([])
const selectedDisposal = ref(null)
const searchQuery = ref('')
const statusFilter = ref('all')

const form = reactive({
  warehouse_id: '',
  disposal_date: '',
  reason: '',
  notes: '',
  items: [
    {
      product_id: '',
      product_variant_id: '',
      qty: 1,
      notes: '',
    },
  ],
})

const errors = reactive({
  warehouse_id: '',
  disposal_date: '',
  reason: '',
  notes: '',
  items: [
    {
      product_id: '',
      product_variant_id: '',
      qty: '',
      notes: '',
    },
  ],
  general: '',
})

const stockDisposalStatusClasses = {
  draft: 'bg-slate-100 text-slate-800',
  approved: 'bg-emerald-100 text-emerald-700',
  cancelled: 'bg-rose-100 text-rose-700',
}

const getVariantsFor = (productId) => {
  return productVariants.value.filter((variant) => variant.product_id === productId)
}

const formattedDisposalTotal = computed(() => {
  return form.items.reduce((sum, item) => {
    const product = products.value.find((product) => product.id === item.product_id)
    const price = product?.cost_price ?? 0
    return sum + Number(item.qty || 0) * Number(price)
  }, 0)
})

const filteredDisposals = computed(() => {
  return disposals.value.filter((disposal) => {
    const matchesStatus = statusFilter.value === 'all' || disposal.status === statusFilter.value
    const search = searchQuery.value.trim().toLowerCase()
    if (!search) {
      return matchesStatus
    }
    const warehouseName = disposal.warehouse?.name ?? ''
    return (
      matchesStatus &&
      [disposal.reference_no, warehouseName, disposal.status]
        .filter(Boolean)
        .some((value) => value.toLowerCase().includes(search))
    )
  })
})

const formatCurrency = (value) => {
  if (value === null || value === undefined || value === '') {
    return 'Rp 0'
  }
  return `Rp ${Number(value).toLocaleString('id-ID', { minimumFractionDigits: 0 })}`
}

const formatDate = (value) => {
  if (!value) return '-'
  return new Date(value).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

const clearErrors = () => {
  errors.warehouse_id = ''
  errors.disposal_date = ''
  errors.reason = ''
  errors.notes = ''
  errors.general = ''
  errors.items = form.items.map(() => ({ product_id: '', product_variant_id: '', qty: '', notes: '' }))
}

const syncItemErrors = () => {
  errors.items = form.items.map((item, index) => errors.items[index] || { product_id: '', product_variant_id: '', qty: '', notes: '' })
}

const resetForm = () => {
  form.warehouse_id = warehouses.value[0]?.id || ''
  form.disposal_date = ''
  form.reason = ''
  form.notes = ''
  form.items = [
    {
      product_id: '',
      product_variant_id: '',
      qty: 1,
      notes: '',
    },
  ]
  syncItemErrors()
  clearErrors()
}

const validateForm = () => {
  clearErrors()
  let valid = true

  if (!form.warehouse_id) {
    errors.warehouse_id = 'Warehouse wajib dipilih.'
    valid = false
  }

  if (!form.disposal_date) {
    errors.disposal_date = 'Tanggal pembuangan wajib diisi.'
    valid = false
  }

  if (!form.reason) {
    errors.reason = 'Alasan pembuangan wajib diisi.'
    valid = false
  }

  if (form.items.length === 0) {
    errors.general = 'Silakan tambahkan setidaknya satu produk untuk dibuang.'
    valid = false
  }

  form.items.forEach((item, index) => {
    if (!errors.items[index]) {
      errors.items[index] = { product_id: '', product_variant_id: '', qty: '', notes: '' }
    }

    if (!item.product_id) {
      errors.items[index].product_id = 'Pilih produk terlebih dahulu.'
      valid = false
    }

    if (!item.qty || Number(item.qty) <= 0) {
      errors.items[index].qty = 'Kuantitas harus lebih besar dari 0.'
      valid = false
    }
  })

  return valid
}

const parseBackendErrors = (backendErrors) => {
  if (!backendErrors) return

  Object.entries(backendErrors).forEach(([key, messages]) => {
    const message = Array.isArray(messages) ? messages.join(' ') : messages

    if (key === 'warehouse_id') {
      errors.warehouse_id = message
      return
    }
    if (key === 'disposal_date') {
      errors.disposal_date = message
      return
    }
    if (key === 'reason') {
      errors.reason = message
      return
    }
    if (key === 'notes') {
      errors.notes = message
      return
    }
    const itemMatch = key.match(/^items\.(\d+)\.(.+)$/)
    if (itemMatch) {
      const index = Number(itemMatch[1])
      const field = itemMatch[2]
      if (!errors.items[index]) {
        errors.items[index] = { product_id: '', product_variant_id: '', qty: '', notes: '' }
      }
      errors.items[index][field] = message
      return
    }

    errors.general += `${message} `
  })
}

const fetchMasterData = async () => {
  loading.value = true
  try {
    const [warehousesRes, productsRes, variantsRes] = await Promise.all([
      api.get('/warehouses'),
      api.get('/products'),
      api.get('/product-variants'),
    ])

    warehouses.value = warehousesRes.data?.data ?? warehousesRes.data ?? []
    products.value = productsRes.data?.data ?? productsRes.data ?? []
    productVariants.value = variantsRes.data?.data ?? variantsRes.data ?? []

    if (!form.warehouse_id && warehouses.value.length > 0) {
      form.warehouse_id = warehouses.value[0].id
    }
  } catch (error) {
    console.error('Fetch master data failed:', error)
    toast.error('Gagal memuat data master Stock Disposal.')
  } finally {
    loading.value = false
  }
}

const fetchDisposals = async () => {
  loading.value = true
  try {
    const response = await api.get('/stock-disposals')
    disposals.value = response.data?.data ?? response.data ?? []
  } catch (error) {
    console.error('Fetch stock disposals failed:', error)
    toast.error(error.response?.data?.message || 'Gagal memuat daftar stock disposal.')
  } finally {
    loading.value = false
  }
}

const addItem = () => {
  form.items.push({ product_id: '', product_variant_id: '', qty: 1, notes: '' })
  syncItemErrors()
}

const removeItem = (index) => {
  form.items.splice(index, 1)
  syncItemErrors()
}

const handleProductChange = (index) => {
  form.items[index].product_variant_id = ''
}

const submitDisposal = async () => {
  if (!validateForm()) {
    return
  }

  submitting.value = true
  errors.general = ''

  const payload = {
    warehouse_id: form.warehouse_id,
    disposal_date: form.disposal_date,
    reason: form.reason,
    notes: form.notes,
    items: form.items.map((item) => ({
      product_id: item.product_id,
      product_variant_id: item.product_variant_id || null,
      qty: Number(item.qty),
      notes: item.notes || null,
    })),
  }

  try {
    await api.post('/stock-disposals', payload)
    toast.success('Draft stock disposal berhasil dibuat.')
    resetForm()
    await fetchDisposals()
  } catch (error) {
    console.error('Submit stock disposal failed:', error)
    const backendErrors = error.response?.data?.errors
    if (backendErrors) {
      parseBackendErrors(backendErrors)
    }
    const message = error.response?.data?.message || 'Gagal menyimpan stock disposal.'
    toast.error(message)
  } finally {
    submitting.value = false
  }
}

const openDetails = async (disposal) => {
  actionLoading.value = true
  try {
    const response = await api.get(`/stock-disposals/${disposal.id}`)
    selectedDisposal.value = response.data?.data ?? response.data ?? null
  } catch (error) {
    console.error('Fetch disposal details failed:', error)
    toast.error(error.response?.data?.message || 'Gagal memuat detail stock disposal.')
  } finally {
    actionLoading.value = false
  }
}

const updateDisposalStatus = async (disposal, status) => {
  if (!window.confirm(`Yakin ingin ${status === 'approved' ? 'menyetujui' : 'membatalkan'} stock disposal ini?`)) {
    return
  }

  actionLoading.value = true
  try {
    await api.put(`/stock-disposals/${disposal.id}`, { status })
    toast.success(`Stock disposal berhasil ${status === 'approved' ? 'disetujui' : 'dibatalkan'}.`)
    await fetchDisposals()
    if (selectedDisposal.value?.id === disposal.id) {
      await openDetails(disposal)
    }
  } catch (error) {
    console.error('Update status failed:', error)
    toast.error(error.response?.data?.message || 'Gagal memperbarui status stock disposal.')
  } finally {
    actionLoading.value = false
  }
}

const deleteDisposal = async (disposal) => {
  if (!window.confirm('Hapus draft stock disposal ini?')) {
    return
  }

  actionLoading.value = true
  try {
    await api.delete(`/stock-disposals/${disposal.id}`)
    toast.success('Draft stock disposal berhasil dihapus.')
    await fetchDisposals()
    if (selectedDisposal.value?.id === disposal.id) {
      selectedDisposal.value = null
    }
  } catch (error) {
    console.error('Delete disposal failed:', error)
    toast.error(error.response?.data?.message || 'Gagal menghapus stock disposal.')
  } finally {
    actionLoading.value = false
  }
}

const clearSelectedDisposal = () => {
  selectedDisposal.value = null
}

watch(
  () => form.items.length,
  () => {
    syncItemErrors()
  }
)

onMounted(async () => {
  await fetchMasterData()
  await fetchDisposals()
  if (!form.warehouse_id && warehouses.value.length > 0) {
    form.warehouse_id = warehouses.value[0].id
  }
})
</script>

<template>
  <div class="space-y-6">
    <section class="rounded-3xl bg-white p-6 shadow-sm border border-slate-200">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-slate-900">Stock Disposal</h1>
          <p class="mt-1 text-sm text-slate-500">Buat dan kelola draft pemusnahan stok dengan validasi form penuh.</p>
        </div>
      </div>

      <div class="mt-6 grid gap-6 xl:grid-cols-[1.5fr_0.9fr]">
        <div class="space-y-6">
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-slate-700">Warehouse</label>
              <select
                v-model="form.warehouse_id"
                class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
              >
                <option value="">Pilih Warehouse</option>
                <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
              </select>
              <p v-if="errors.warehouse_id" class="mt-1 text-sm text-rose-600">{{ errors.warehouse_id }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700">Tanggal Pemusnahan</label>
              <input
                type="date"
                v-model="form.disposal_date"
                class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
              />
              <p v-if="errors.disposal_date" class="mt-1 text-sm text-rose-600">{{ errors.disposal_date }}</p>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700">Alasan Pemusnahan</label>
            <input
              type="text"
              v-model="form.reason"
              placeholder="Misal: kerusakan, kadaluarsa, rusak"
              class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
            />
            <p v-if="errors.reason" class="mt-1 text-sm text-rose-600">{{ errors.reason }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700">Catatan Tambahan</label>
            <textarea
              v-model="form.notes"
              rows="3"
              placeholder="Opsional"
              class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
            ></textarea>
            <p v-if="errors.notes" class="mt-1 text-sm text-rose-600">{{ errors.notes }}</p>
          </div>

          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <h2 class="text-lg font-semibold text-slate-900">Produk untuk Dimusnahkan</h2>
                <p class="text-sm text-slate-500">Tambah minimal satu baris item produk.</p>
              </div>
              <button
                type="button"
                @click="addItem"
                class="rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
              >
                Tambah Item
              </button>
            </div>

            <div class="space-y-4">
              <div
                v-for="(item, index) in form.items"
                :key="index"
                class="rounded-3xl border border-slate-200 bg-slate-50 p-4"
              >
                <div class="grid gap-4 xl:grid-cols-[1.2fr_0.9fr_0.7fr]">
                  <div>
                    <label class="block text-sm font-medium text-slate-700">Produk</label>
                    <select
                      v-model="item.product_id"
                      @change="handleProductChange(index)"
                      class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
                    >
                      <option value="">Pilih Produk</option>
                      <option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }}</option>
                    </select>
                    <p v-if="errors.items[index]?.product_id" class="mt-1 text-sm text-rose-600">{{ errors.items[index].product_id }}</p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700">Varian (Opsional)</label>
                    <select
                      v-model="item.product_variant_id"
                      class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
                      :disabled="!getVariantsFor(item.product_id).length"
                    >
                      <option value="">Tidak ada / tidak dipilih</option>
                      <option
                        v-for="variant in getVariantsFor(item.product_id)"
                        :key="variant.id"
                        :value="variant.id"
                      >
                        {{ variant.name }}
                      </option>
                    </select>
                    <p v-if="errors.items[index]?.product_variant_id" class="mt-1 text-sm text-rose-600">{{ errors.items[index].product_variant_id }}</p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700">Kuantitas</label>
                    <input
                      type="number"
                      min="0.01"
                      step="0.01"
                      v-model.number="item.qty"
                      class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
                    />
                    <p v-if="errors.items[index]?.qty" class="mt-1 text-sm text-rose-600">{{ errors.items[index].qty }}</p>
                  </div>
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_0.8fr]">
                  <div>
                    <label class="block text-sm font-medium text-slate-700">Catatan item</label>
                    <input
                      type="text"
                      v-model="item.notes"
                      placeholder="Opsional"
                      class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
                    />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-slate-700">Estimasi Subtotal</label>
                    <div class="mt-2 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm">
                      {{ formatCurrency((products.find((product) => product.id === item.product_id)?.cost_price ?? 0) * Number(item.qty || 0)) }}
                    </div>
                  </div>
                </div>

                <div class="mt-4 flex justify-end">
                  <button
                    type="button"
                    @click="removeItem(index)"
                    class="rounded-2xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-500"
                  >
                    Hapus
                  </button>
                </div>
              </div>
            </div>
          </div>

          <p v-if="errors.general" class="text-sm text-rose-600">{{ errors.general }}</p>
        </div>

        <div class="space-y-6 rounded-3xl border border-slate-200 bg-slate-50 p-6">
          <div class="space-y-3 rounded-3xl bg-white p-4 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Ringkasan</h2>
            <div class="grid gap-3 text-sm text-slate-600">
              <div class="flex items-center justify-between">
                <span>Total Item</span>
                <span>{{ form.items.length }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span>Total Estimasi Biaya</span>
                <span class="font-semibold text-slate-900">{{ formatCurrency(formattedDisposalTotal) }}</span>
              </div>
            </div>
          </div>

          <button
            type="button"
            @click="submitDisposal"
            :disabled="submitting || loading"
            class="w-full rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {{ submitting ? 'Menyimpan...' : 'Simpan Draft Stock Disposal' }}
          </button>
        </div>
      </div>
    </section>

    <section class="rounded-3xl bg-white p-6 shadow-sm border border-slate-200">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h2 class="text-xl font-semibold text-slate-900">Daftar Stock Disposal</h2>
          <p class="mt-1 text-sm text-slate-500">Kelola draft pemusnahan stok dan proses persetujuan.</p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_0.9fr]">
          <input
            type="text"
            v-model="searchQuery"
            placeholder="Cari reference atau warehouse"
            class="rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
          />
          <select
            v-model="statusFilter"
            class="rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
          >
            <option value="all">Semua Status</option>
            <option value="draft">Draft</option>
            <option value="approved">Disetujui</option>
            <option value="cancelled">Dibatalkan</option>
          </select>
          <button
            @click="fetchDisposals"
            :disabled="loading"
            class="rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {{ loading ? 'Memuat...' : 'Refresh' }}
          </button>
        </div>
      </div>

      <div class="mt-6 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
          <thead class="bg-slate-50 text-slate-700">
            <tr>
              <th class="px-4 py-3">Reference</th>
              <th class="px-4 py-3">Warehouse</th>
              <th class="px-4 py-3">Tanggal</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3">Item</th>
              <th class="px-4 py-3">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 bg-white">
            <tr v-for="disposal in filteredDisposals" :key="disposal.id">
              <td class="px-4 py-4 font-medium text-slate-900">{{ disposal.reference_no }}</td>
              <td class="px-4 py-4">{{ disposal.warehouse?.name ?? '-' }}</td>
              <td class="px-4 py-4">{{ formatDate(disposal.disposal_date) }}</td>
              <td class="px-4 py-4">
                <span
                  :class="['inline-flex rounded-full px-3 py-1 text-xs font-semibold', stockDisposalStatusClasses[disposal.status] || 'bg-slate-100 text-slate-700']"
                >
                  {{ disposal.status }}
                </span>
              </td>
              <td class="px-4 py-4">{{ disposal.items?.length ?? 0 }}</td>
              <td class="px-4 py-4 space-x-2">
                <button
                  type="button"
                  @click="openDetails(disposal)"
                  class="rounded-2xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:border-slate-300"
                >
                  Detail
                </button>
                <button
                  v-if="disposal.status === 'draft'"
                  type="button"
                  @click="updateDisposalStatus(disposal, 'approved')"
                  :disabled="actionLoading"
                  class="rounded-2xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  Setujui
                </button>
                <button
                  v-if="disposal.status === 'draft'"
                  type="button"
                  @click="updateDisposalStatus(disposal, 'cancelled')"
                  :disabled="actionLoading"
                  class="rounded-2xl bg-amber-600 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-500 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  Batalkan
                </button>
                <button
                  v-if="disposal.status === 'draft'"
                  type="button"
                  @click="deleteDisposal(disposal)"
                  :disabled="actionLoading"
                  class="rounded-2xl bg-rose-600 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-500 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  Hapus
                </button>
              </td>
            </tr>
            <tr v-if="filteredDisposals.length === 0">
              <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">Belum ada stock disposal untuk ditampilkan.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section v-if="selectedDisposal" class="rounded-3xl bg-white p-6 shadow-sm border border-slate-200">
      <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
          <h2 class="text-xl font-semibold text-slate-900">Detail Stock Disposal</h2>
          <p class="mt-1 text-sm text-slate-500">{{ selectedDisposal.reference_no }} - {{ selectedDisposal.warehouse?.name ?? '-' }}</p>
        </div>
        <button
          type="button"
          @click="clearSelectedDisposal"
          class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
        >
          Tutup Detail
        </button>
      </div>

      <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <p class="text-sm text-slate-500">Tanggal</p>
          <p class="mt-2 text-base font-semibold text-slate-900">{{ formatDate(selectedDisposal.disposal_date) }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <p class="text-sm text-slate-500">Status</p>
          <span
            :class="['inline-flex rounded-full px-3 py-1 text-xs font-semibold', stockDisposalStatusClasses[selectedDisposal.status] || 'bg-slate-100 text-slate-700']"
          >
            {{ selectedDisposal.status }}
          </span>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <p class="text-sm text-slate-500">Referensi</p>
          <p class="mt-2 text-base font-semibold text-slate-900">{{ selectedDisposal.reference_no }}</p>
        </div>
      </div>

      <div class="mt-6 grid gap-4 lg:grid-cols-[1fr_1fr]">
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <p class="text-sm text-slate-500">Alasan</p>
          <p class="mt-2 text-sm text-slate-700">{{ selectedDisposal.reason }}</p>
        </div>
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
          <p class="text-sm text-slate-500">Catatan</p>
          <p class="mt-2 text-sm text-slate-700">{{ selectedDisposal.notes || 'Tidak ada catatan tambahan.' }}</p>
        </div>
      </div>

      <div class="mt-6 overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
          <thead class="bg-slate-50 text-slate-700">
            <tr>
              <th class="px-4 py-3">Produk</th>
              <th class="px-4 py-3">Varian</th>
              <th class="px-4 py-3">Qty</th>
              <th class="px-4 py-3">Biaya per Unit</th>
              <th class="px-4 py-3">Subtotal</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 bg-white">
            <tr v-for="item in selectedDisposal.items" :key="item.id">
              <td class="px-4 py-4">{{ item.product?.name ?? '-' }}</td>
              <td class="px-4 py-4">{{ item.product_variant?.name ?? '-' }}</td>
              <td class="px-4 py-4">{{ item.qty }}</td>
              <td class="px-4 py-4">{{ formatCurrency(item.unit_cost) }}</td>
              <td class="px-4 py-4">{{ formatCurrency(item.subtotal) }}</td>
            </tr>
            <tr v-if="selectedDisposal.items.length === 0">
              <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">Tidak ada item pada stock disposal ini.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
