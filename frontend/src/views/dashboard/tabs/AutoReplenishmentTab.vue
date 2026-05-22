<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()
const loading = ref(false)
const submitting = ref(false)
const stores = ref([])
const warehouses = ref([])
const suggestionsBySupplier = ref([])
const metadata = ref({})
const summary = ref({})
const searchQuery = ref('')

const filters = reactive({
  store_id: '',
  warehouse_id: '',
})

const errors = reactive({
  store_id: '',
  warehouse_id: '',
  general: '',
})

const hasSuggestions = computed(() => suggestionsBySupplier.value.length > 0)
const selectedGroups = computed(() => suggestionsBySupplier.value.filter((group) => group.selected))
const selectedItemsCount = computed(() => selectedGroups.value.reduce((sum, group) => sum + group.items.filter((item) => item.selected).length, 0))

const estimatedDraftTotal = computed(() => {
  return selectedGroups.value.reduce((groupTotal, group) => {
    return groupTotal + group.items.reduce((itemTotal, item) => {
      if (!item.selected) return itemTotal
      return itemTotal + Number(item.qty) * Number(item.cost_price)
    }, 0)
  }, 0)
})

const fetchMasterData = async () => {
  loading.value = true
  try {
    const [storesRes, warehousesRes] = await Promise.all([
      api.get('/stores'),
      api.get('/warehouses'),
    ])

    stores.value = storesRes.data?.data ?? storesRes.data ?? []
    warehouses.value = warehousesRes.data?.data ?? warehousesRes.data ?? []

    if (!filters.store_id && stores.value.length > 0) {
      filters.store_id = stores.value[0].id
    }
    if (!filters.warehouse_id && warehouses.value.length > 0) {
      filters.warehouse_id = warehouses.value[0].id
    }
  } catch (error) {
    console.error('Fetch master data failed:', error)
    toast.error('Gagal memuat daftar store dan warehouse.')
  } finally {
    loading.value = false
  }
}

const clearErrors = () => {
  errors.store_id = ''
  errors.warehouse_id = ''
  errors.general = ''
}

const validateFilters = () => {
  clearErrors()
  let valid = true

  if (!filters.store_id) {
    errors.store_id = 'Store wajib dipilih.'
    valid = false
  }

  if (!filters.warehouse_id) {
    errors.warehouse_id = 'Warehouse wajib dipilih.'
    valid = false
  }

  return valid
}

const fetchSuggestions = async () => {
  if (!validateFilters()) {
    return
  }

  loading.value = true
  errors.general = ''

  try {
    const response = await api.get('/replenishment/suggestions', {
      params: {
        store_id: filters.store_id,
        warehouse_id: filters.warehouse_id,
      },
    })

    const payload = response.data?.data ?? {}
    metadata.value = payload.metadata ?? {}
    summary.value = payload.summary ?? {}

    suggestionsBySupplier.value = (payload.suggestions_by_supplier ?? []).map((supplier) => ({
      ...supplier,
      selected: true,
      items: supplier.items.map((item) => ({
        ...item,
        selected: true,
        qty: item.suggested_qty ?? item.qty,
        cost_price: item.cost_price ?? item.cost_price,
      })),
    }))

    if (suggestionsBySupplier.value.length === 0) {
      toast.info('Tidak ada rekomendasi replenishment untuk warehouse dan store yang dipilih.')
    }
  } catch (error) {
    console.error('Fetch suggestions failed:', error)
    toast.error(error.response?.data?.message || 'Gagal memuat rekomendasi Auto Replenishment.')
  } finally {
    loading.value = false
  }
}

const toggleSupplier = (group) => {
  group.selected = !group.selected
}

const toggleItem = (item) => {
  item.selected = !item.selected
}

const updateQty = (item) => {
  item.qty = Number(item.qty) < 1 ? 1 : Number(item.qty)
}

const filteredGroups = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return suggestionsBySupplier.value

  return suggestionsBySupplier.value
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => {
        return [item.code, item.name, item.category].some((value) => value?.toLowerCase().includes(query))
      }),
    }))
    .filter((group) => group.items.length > 0)
})

const validateDraftPayload = () => {
  clearErrors()
  let valid = true

  if (!validateFilters()) {
    valid = false
  }

  if (selectedGroups.value.length === 0) {
    errors.general = 'Pilih setidaknya satu supplier agar dapat membuat draft PO.'
    valid = false
  }

  selectedGroups.value.forEach((group) => {
    group.items.forEach((item) => {
      if (!item.selected) return
      if (Number(item.qty) < 1) {
        errors.general = 'Jumlah item harus minimal 1 untuk semua produk yang dipilih.'
        valid = false
      }
      if (Number(item.cost_price) < 0) {
        errors.general = 'Harga pokok tidak boleh negatif.'
        valid = false
      }
    })
  })

  return valid
}

const submitDrafts = async () => {
  if (!validateDraftPayload()) {
    return
  }

  submitting.value = true
  errors.general = ''

  const payload = {
    store_id: filters.store_id,
    warehouse_id: filters.warehouse_id,
    suppliers: selectedGroups.value.map((group) => ({
      supplier_id: group.supplier_id,
      items: group.items.filter((item) => item.selected).map((item) => ({
        product_id: item.product_id,
        qty: Number(item.qty),
        cost_price: Number(item.cost_price),
      })),
    })),
  }

  try {
    const response = await api.post('/replenishment/create-drafts', payload)
    const created = response.data?.data ?? []
    toast.success(`Berhasil membuat ${created.length} draft PO.`)
    suggestionsBySupplier.value = []
    summary.value = {}
    metadata.value = {}
    searchQuery.value = ''
  } catch (error) {
    console.error('Create drafts failed:', error)
    const message = error.response?.data?.message || 'Gagal membuat draft PO.'
    toast.error(message)
    if (error.response?.data?.errors) {
      errors.general = Object.values(error.response.data.errors).flat().join(' ')
    }
  } finally {
    submitting.value = false
  }
}

const formatCurrency = (value) => {
  if (value === null || value === undefined || value === '') {
    return '-'
  }
  return `Rp ${Number(value).toLocaleString('id-ID', { minimumFractionDigits: 0 })}`
}

onMounted(async () => {
  await fetchMasterData()
})
</script>

<template>
  <div class="space-y-6">
    <section class="rounded-3xl bg-white p-6 shadow-sm border border-slate-200">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-slate-900">Auto Replenishment</h1>
          <p class="mt-1 text-sm text-slate-500">Buat draf PO otomatis berdasarkan rekomendasi stok rendah dan history pembelian.</p>
        </div>
        <button
          @click="fetchSuggestions"
          :disabled="loading"
          class="rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {{ loading ? 'Memuat...' : 'Ambil Rekomendasi' }}
        </button>
      </div>

      <div class="mt-6 grid gap-4 lg:grid-cols-[1.5fr_1fr]">
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-sm font-medium text-slate-700">Store</label>
            <select
              v-model="filters.store_id"
              class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
            >
              <option value="">Pilih Store</option>
              <option v-for="store in stores" :key="store.id" :value="store.id">{{ store.name }}</option>
            </select>
            <p v-if="errors.store_id" class="mt-1 text-xs text-rose-600">{{ errors.store_id }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700">Warehouse</label>
            <select
              v-model="filters.warehouse_id"
              class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
            >
              <option value="">Pilih Warehouse</option>
              <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
            </select>
            <p v-if="errors.warehouse_id" class="mt-1 text-xs text-rose-600">{{ errors.warehouse_id }}</p>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
          <div class="text-sm text-slate-500">Ringkasan Rekomendasi</div>
          <div class="mt-3 grid gap-3">
            <div class="rounded-3xl bg-white p-4 shadow-sm">
              <div class="text-xs uppercase tracking-wide text-slate-500">Total Supplier</div>
              <div class="mt-2 text-2xl font-semibold text-slate-900">{{ summary.total_suppliers_involved ?? 0 }}</div>
            </div>
            <div class="rounded-3xl bg-white p-4 shadow-sm">
              <div class="text-xs uppercase tracking-wide text-slate-500">Produk Understock</div>
              <div class="mt-2 text-2xl font-semibold text-slate-900">{{ summary.total_unique_products_understocked ?? 0 }}</div>
            </div>
            <div class="rounded-3xl bg-white p-4 shadow-sm">
              <div class="text-xs uppercase tracking-wide text-slate-500">Estimasi Investasi</div>
              <div class="mt-2 text-2xl font-semibold text-slate-900">{{ formatCurrency(summary.estimated_total_investment ?? 0) }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-6">
        <label class="block text-sm font-medium text-slate-700">Cari produk / kategori</label>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Cari berdasarkan kode, nama, kategori..."
          class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
        />
      </div>
    </section>

    <section v-if="errors.general" class="rounded-3xl bg-rose-50 p-4 text-rose-700 border border-rose-200">
      {{ errors.general }}
    </section>

    <section class="space-y-6">
      <div v-if="!hasSuggestions && !loading" class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-10 text-center text-slate-500">
        Pilih Store dan Warehouse lalu klik "Ambil Rekomendasi" untuk melihat usulan draf PO otomatis.
      </div>

      <div v-for="group in filteredGroups" :key="group.supplier_id" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <div class="flex items-center gap-3">
              <input type="checkbox" v-model="group.selected" class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900" />
              <div>
                <h2 class="text-lg font-semibold text-slate-900">{{ group.supplier_name }}</h2>
                <p class="text-sm text-slate-500">{{ group.supplier_code }}</p>
              </div>
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-3xl bg-slate-50 p-4 text-center">
              <div class="text-xs uppercase tracking-wide text-slate-500">Produk</div>
              <div class="mt-2 text-xl font-semibold text-slate-900">{{ group.total_items_to_order }}</div>
            </div>
            <div class="rounded-3xl bg-slate-50 p-4 text-center">
              <div class="text-xs uppercase tracking-wide text-slate-500">Qty Total</div>
              <div class="mt-2 text-xl font-semibold text-slate-900">{{ Number(group.total_qty_to_order).toLocaleString('id-ID') }}</div>
            </div>
            <div class="rounded-3xl bg-slate-50 p-4 text-center">
              <div class="text-xs uppercase tracking-wide text-slate-500">Estimasi Total</div>
              <div class="mt-2 text-xl font-semibold text-slate-900">{{ formatCurrency(group.estimated_grand_total) }}</div>
            </div>
          </div>
        </div>

        <div class="mt-6 overflow-x-auto rounded-3xl border border-slate-200">
          <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-700">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-4 py-3">Pilih</th>
                <th class="px-4 py-3">Kode</th>
                <th class="px-4 py-3">Nama Produk</th>
                <th class="px-4 py-3">Kategori</th>
                <th class="px-4 py-3">Stok Saat Ini</th>
                <th class="px-4 py-3">Qty Usulan</th>
                <th class="px-4 py-3">Harga Pokok</th>
                <th class="px-4 py-3">Subtotal</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
              <tr v-for="item in group.items" :key="item.product_id">
                <td class="px-4 py-3">
                  <input type="checkbox" v-model="item.selected" class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900" />
                </td>
                <td class="px-4 py-3 font-medium text-slate-900">{{ item.code }}</td>
                <td class="px-4 py-3">{{ item.name }}</td>
                <td class="px-4 py-3">{{ item.category }}</td>
                <td class="px-4 py-3">{{ Number(item.current_stock).toLocaleString('id-ID') }}</td>
                <td class="px-4 py-3">
                  <input
                    type="number"
                    min="1"
                    v-model.number="item.qty"
                    @blur="updateQty(item)"
                    class="w-24 rounded-2xl border border-slate-200 px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
                  />
                </td>
                <td class="px-4 py-3">{{ formatCurrency(item.cost_price) }}</td>
                <td class="px-4 py-3">{{ formatCurrency(item.qty * item.cost_price) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="hasSuggestions" class="rounded-3xl bg-white p-6 shadow-sm border border-slate-200">
        <div class="grid gap-4 lg:grid-cols-[1fr_auto] lg:items-center">
          <div>
            <div class="text-sm text-slate-500">Total Supplier Terpilih</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ selectedGroups.value.length }}</div>
            <div class="mt-1 text-sm text-slate-500">Total item terpilih: {{ selectedItemsCount.value }}</div>
          </div>
          <div class="text-right">
            <div class="text-sm text-slate-500">Estimasi Draft PO</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ formatCurrency(estimatedDraftTotal.value) }}</div>
          </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
          <button
            @click="submitDrafts"
            :disabled="submitting || selectedItemsCount.value === 0"
            class="rounded-2xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {{ submitting ? 'Membuat Draft...' : 'Buat Smart PO Drafts' }}
          </button>
          <button
            @click="fetchSuggestions"
            :disabled="loading"
            class="rounded-2xl border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-900 hover:border-slate-300"
          >
            Muat Ulang Rekomendasi
          </button>
        </div>
      </div>
    </section>
  </div>
</template>
