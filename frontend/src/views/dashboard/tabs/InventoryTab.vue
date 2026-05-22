<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()
const loading = ref(false)
const activeSection = ref('stockStatus')
const warehouses = ref([])
const products = ref([])
const stockStatus = ref([])
const stockCard = ref({ product: null, stock_ledger: [] })
const valuation = ref({ metadata: null, summary: null, details: [] })
const searchQuery = ref('')

const filters = reactive({
  warehouse_id: '',
  product_id: '',
})

const errors = reactive({
  warehouse_id: '',
  product_id: '',
})

const sectionOptions = [
  { value: 'stockStatus', label: 'Stock Status' },
  { value: 'stockCard', label: 'Stock Card' },
  { value: 'valuation', label: 'Valuation' },
]

const fetchMasterData = async () => {
  loading.value = true

  try {
    const [warehousesRes, productsRes] = await Promise.all([
      api.get('/warehouses'),
      api.get('/products'),
    ])

    warehouses.value = warehousesRes.data?.data ?? warehousesRes.data ?? []
    products.value = productsRes.data?.data ?? productsRes.data ?? []
  } catch (error) {
    console.error('Fetch master data failed:', error)
    toast.error('Gagal memuat data master inventory.')
  } finally {
    loading.value = false
  }
}

const loadStockStatus = async () => {
  loading.value = true
  clearErrors()

  try {
    const response = await api.get('/inventory/stock-status', {
      params: {
        warehouse_id: filters.warehouse_id || undefined,
      },
    })
    stockStatus.value = response.data?.data ?? response.data ?? []
  } catch (error) {
    console.error('Fetch stock status failed:', error)
    toast.error('Gagal memuat stock status.')
  } finally {
    loading.value = false
  }
}

const loadValuation = async () => {
  loading.value = true
  clearErrors()

  try {
    const response = await api.get('/inventory/valuation', {
      params: {
        warehouse_id: filters.warehouse_id || undefined,
      },
    })
    valuation.value = response.data?.data ?? response.data ?? { metadata: null, summary: null, details: [] }
  } catch (error) {
    console.error('Fetch valuation failed:', error)
    toast.error('Gagal memuat laporan valuasi inventory.')
  } finally {
    loading.value = false
  }
}

const loadStockCard = async () => {
  if (!validateStockCardForm()) {
    return
  }

  loading.value = true
  clearErrors()

  try {
    const response = await api.get(`/inventory/stock-card/${filters.product_id}`, {
      params: {
        warehouse_id: filters.warehouse_id || undefined,
      },
    })
    stockCard.value = response.data?.data ?? { product: null, stock_ledger: [] }
  } catch (error) {
    console.error('Fetch stock card failed:', error)
    toast.error('Gagal memuat stock card produk.')
  } finally {
    loading.value = false
  }
}

const clearErrors = () => {
  errors.warehouse_id = ''
  errors.product_id = ''
}

const validateStockCardForm = () => {
  clearErrors()
  let valid = true

  if (!filters.product_id) {
    errors.product_id = 'Pilih produk untuk melihat Stock Card.'
    valid = false
  }

  return valid
}

const sectionLabel = computed(() => {
  const active = sectionOptions.find(option => option.value === activeSection.value)
  return active ? active.label : 'Inventory'
})

const filteredStockStatus = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) {
    return stockStatus.value
  }

  return stockStatus.value.filter(item => {
    return [
      item.product_code,
      item.product_name,
      item.variant_name,
      item.category_name,
      item.warehouse_name,
    ]
      .filter(Boolean)
      .some(value => value.toLowerCase().includes(query))
  })
})

const formattedCurrency = (value) => {
  if (value === null || value === undefined || value === '') {
    return '-'
  }

  return 'Rp ' + Number(value).toLocaleString('id-ID', { minimumFractionDigits: 0 })
}

const formatDate = (value) => {
  if (!value) return '-'
  return new Date(value).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' })
}

const statusVariant = (qty, minQty) => {
  return qty <= minQty ? 'bg-rose-100 text-rose-700 border-rose-200' : 'bg-emerald-100 text-emerald-700 border-emerald-200'
}

const onSectionSelect = (section) => {
  activeSection.value = section
  clearErrors()

  if (section === 'stockStatus') {
    loadStockStatus()
  }

  if (section === 'valuation') {
    loadValuation()
  }

  if (section === 'stockCard' && filters.product_id) {
    loadStockCard()
  }
}

const selectedProduct = computed(() => {
  return products.value.find(product => product.id === filters.product_id) || null
})

const selectedWarehouse = computed(() => {
  return warehouses.value.find(warehouse => warehouse.id === filters.warehouse_id) || null
})

onMounted(async () => {
  await fetchMasterData()
  await loadStockStatus()
  await loadValuation()
})
</script>

<template>
  <div class="space-y-6">
    <div class="rounded-3xl bg-white p-6 shadow-sm border border-slate-200">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-slate-900">Inventory Overview</h1>
          <p class="mt-1 text-sm text-slate-500">Lihat status stok, stock card per produk, dan laporan valuasi inventory.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <button
            v-for="section in sectionOptions"
            :key="section.value"
            @click="onSectionSelect(section.value)"
            :class="['rounded-2xl px-4 py-2 text-sm font-semibold transition', activeSection === section.value ? 'bg-sky-600 text-white shadow' : 'border border-slate-200 bg-white text-slate-700 hover:border-slate-300']"
          >
            {{ section.label }}
          </button>
        </div>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
      <section class="rounded-3xl bg-white border border-slate-200 p-6 shadow-sm">
        <div class="mb-5 flex items-center justify-between gap-4">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">{{ sectionLabel }}</h2>
            <p class="text-sm text-slate-500">Gunakan filter untuk data yang lebih tajam dan akurat.</p>
          </div>
          <div class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-700">
            <span class="font-semibold">Warehouse</span>
            <span>{{ selectedWarehouse?.name ?? 'Semua' }}</span>
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-sm font-medium text-slate-700">Warehouse</label>
            <select
              v-model="filters.warehouse_id"
              @change="activeSection === 'stockStatus' ? loadStockStatus() : activeSection === 'valuation' ? loadValuation() : null"
              class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
              :class="errors.warehouse_id ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : 'border-slate-200'"
            >
              <option value="">Semua Warehouse</option>
              <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
            </select>
            <p v-if="errors.warehouse_id" class="mt-1 text-xs text-rose-600">{{ errors.warehouse_id }}</p>
          </div>

          <div v-if="activeSection === 'stockCard'">
            <label class="block text-sm font-medium text-slate-700">Produk</label>
            <select
              v-model="filters.product_id"
              class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
              :class="errors.product_id ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : 'border-slate-200'"
            >
              <option value="">Pilih produk</option>
              <option v-for="product in products" :key="product.id" :value="product.id">{{ product.code }} — {{ product.name }}</option>
            </select>
            <p v-if="errors.product_id" class="mt-1 text-xs text-rose-600">{{ errors.product_id }}</p>
            <button
              @click="loadStockCard"
              class="mt-3 inline-flex items-center justify-center rounded-2xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700"
            >Lihat Stock Card</button>
          </div>
        </div>
      </section>

      <section class="rounded-3xl bg-white border border-slate-200 p-6 shadow-sm">
        <div class="space-y-4">
          <div class="rounded-3xl bg-slate-50 p-4">
            <h3 class="text-sm font-semibold text-slate-600">Highlights</h3>
            <p class="mt-2 text-sm text-slate-500">Ringkasan cepat untuk membantu Anda memantau status stok dan perbandingan valuasi.</p>
          </div>
          <div class="grid gap-3">
            <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
              <div class="text-sm text-slate-500">Data Terkini</div>
              <div class="mt-2 text-2xl font-semibold text-slate-900">{{ filteredStockStatus.length }}</div>
              <div class="text-sm text-slate-500">Item stok yang sesuai filter</div>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
              <div class="text-sm text-slate-500">Produk Terpilih</div>
              <div class="mt-2 text-lg font-semibold text-slate-900">{{ selectedProduct?.name ?? 'Belum dipilih' }}</div>
              <div class="text-sm text-slate-500">{{ selectedProduct ? selectedProduct.code : 'Tidak ada produk yang dipilih' }}</div>
            </div>
          </div>
        </div>
      </section>
    </div>

    <section class="rounded-3xl bg-white border border-slate-200 p-6 shadow-sm">
      <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h2 class="text-lg font-semibold text-slate-900">{{ sectionLabel }}</h2>
          <p class="text-sm text-slate-500">Semua data diambil langsung dari backend InventoryController.</p>
        </div>
        <div class="flex items-center gap-3">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari stok..."
            class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
          />
          <button @click="onSectionSelect(activeSection)" class="rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Refresh</button>
        </div>
      </div>

      <div v-if="activeSection === 'stockStatus'" class="space-y-4">
        <div class="overflow-x-auto rounded-3xl border border-slate-200">
          <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-700">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-4 py-3">Warehouse</th>
                <th class="px-4 py-3">Kode</th>
                <th class="px-4 py-3">Produk</th>
                <th class="px-4 py-3">Variant</th>
                <th class="px-4 py-3 text-right">Qty</th>
                <th class="px-4 py-3 text-right">Cost Price</th>
                <th class="px-4 py-3 text-right">Price</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
              <tr v-for="item in filteredStockStatus" :key="item.product_code + item.warehouse_name + item.variant_name">
                <td class="px-4 py-3">{{ item.warehouse_name }}</td>
                <td class="px-4 py-3 font-medium text-slate-900">{{ item.product_code }}</td>
                <td class="px-4 py-3">{{ item.product_name }}</td>
                <td class="px-4 py-3">{{ item.variant_name || '-' }}</td>
                <td class="px-4 py-3 text-right font-semibold">{{ Number(item.current_qty).toLocaleString('id-ID') }}</td>
                <td class="px-4 py-3 text-right">{{ formattedCurrency(item.cost_price) }}</td>
                <td class="px-4 py-3 text-right">{{ formattedCurrency(item.price) }}</td>
              </tr>
              <tr v-if="filteredStockStatus.length === 0">
                <td colspan="7" class="px-4 py-8 text-center text-slate-500">Tidak ada data stock status.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="activeSection === 'stockCard'" class="space-y-4">
        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
          <div class="font-semibold text-slate-900">Produk:</div>
          <div>{{ selectedProduct?.name || 'Belum dipilih' }}</div>
          <div class="mt-2">Warehouse: {{ selectedWarehouse?.name || 'Semua Warehouse' }}</div>
        </div>

        <div class="overflow-x-auto rounded-3xl border border-slate-200">
          <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-700">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-4 py-3">Tanggal</th>
                <th class="px-4 py-3">Ref</th>
                <th class="px-4 py-3">Jenis</th>
                <th class="px-4 py-3 text-right">In</th>
                <th class="px-4 py-3 text-right">Out</th>
                <th class="px-4 py-3 text-right">Saldo</th>
                <th class="px-4 py-3">Keterangan</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
              <tr v-for="entry in stockCard.stock_ledger" :key="entry.timestamp + entry.reference_no">
                <td class="px-4 py-3">{{ formatDate(entry.date) }}</td>
                <td class="px-4 py-3">{{ entry.reference_no }}</td>
                <td class="px-4 py-3">{{ entry.type }}</td>
                <td class="px-4 py-3 text-right">{{ Number(entry.qty_in).toLocaleString('id-ID') }}</td>
                <td class="px-4 py-3 text-right">{{ Number(entry.qty_out).toLocaleString('id-ID') }}</td>
                <td class="px-4 py-3 text-right">{{ Number(entry.running_balance).toLocaleString('id-ID') }}</td>
                <td class="px-4 py-3">{{ entry.description }}</td>
              </tr>
              <tr v-if="!stockCard.stock_ledger || stockCard.stock_ledger.length === 0">
                <td colspan="7" class="px-4 py-8 text-center text-slate-500">Pilih produk dan klik "Lihat Stock Card" untuk menampilkan riwayat.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="activeSection === 'valuation'" class="space-y-4">
        <div class="grid gap-4 lg:grid-cols-3">
          <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-500">Total Produk Terlacak</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ valuation.value?.summary?.total_products_tracked ?? 0 }}</div>
          </div>
          <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-500">Total Nilai Inventory</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ formattedCurrency(valuation.value?.summary?.total_inventory_valuation) }}</div>
          </div>
          <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-sm text-slate-500">Selisih dengan GL</div>
            <div class="mt-2 text-3xl font-semibold text-slate-900">{{ formattedCurrency(valuation.value?.summary?.valuation_difference) }}</div>
          </div>
        </div>

        <div class="overflow-x-auto rounded-3xl border border-slate-200">
          <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-700">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-4 py-3">Warehouse</th>
                <th class="px-4 py-3">Kode</th>
                <th class="px-4 py-3">Nama Produk</th>
                <th class="px-4 py-3">Qty</th>
                <th class="px-4 py-3">Cost Price</th>
                <th class="px-4 py-3">Valuasi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
              <tr v-for="row in valuation.value?.details ?? []" :key="row.warehouse_name + row.product_code">
                <td class="px-4 py-3">{{ row.warehouse_name }}</td>
                <td class="px-4 py-3">{{ row.product_code }}</td>
                <td class="px-4 py-3">{{ row.product_name }}</td>
                <td class="px-4 py-3">{{ Number(row.current_qty).toLocaleString('id-ID') }}</td>
                <td class="px-4 py-3">{{ formattedCurrency(row.cost_price) }}</td>
                <td class="px-4 py-3">{{ formattedCurrency(row.valuation_value) }}</td>
              </tr>
              <tr v-if="!(valuation.value?.details?.length)">
                <td colspan="6" class="px-4 py-8 text-center text-slate-500">Tidak ada data valuasi untuk ditampilkan.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</template>
