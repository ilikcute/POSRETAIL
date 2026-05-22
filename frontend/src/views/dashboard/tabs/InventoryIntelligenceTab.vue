<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()
const loading = ref(false)
const activePanel = ref('stockAlerts')
const warehouses = ref([])
const products = ref([])
const searchQuery = ref('')

const filters = reactive({
  warehouse_id: '',
  product_id: '',
  start_date: '',
  end_date: '',
  limit: 10,
})

const errors = reactive({
  warehouse_id: '',
  product_id: '',
  start_date: '',
  end_date: '',
  limit: '',
})

const stockAlerts = ref({ metadata: null, summary: null, alerts: { under_stocked: [], over_stocked: [] } })
const bestSellers = ref({ metadata: null, summary: null, best_sellers: [] })
const promoPerformance = ref({ metadata: null, summary: null, promotions_performance: [] })

const panels = [
  { value: 'stockAlerts', label: 'Stock Alerts' },
  { value: 'bestSellers', label: 'Best Sellers' },
  { value: 'promoPerformance', label: 'Promo Performance' },
]

const fetchLookups = async () => {
  loading.value = true
  try {
    const [warehousesRes, productsRes] = await Promise.all([
      api.get('/warehouses'),
      api.get('/products'),
    ])

    warehouses.value = warehousesRes.data?.data ?? warehousesRes.data ?? []
    products.value = productsRes.data?.data ?? productsRes.data ?? []
  } catch (error) {
    console.error('Fetch lookups failed:', error)
    toast.error('Gagal memuat data master inventory.')
  } finally {
    loading.value = false
  }
}

const clearErrors = () => {
  Object.keys(errors).forEach((key) => {
    errors[key] = ''
  })
}

const validateBestSellerFilters = () => {
  clearErrors()
  let valid = true

  if (!filters.start_date) {
    errors.start_date = 'Tanggal mulai wajib diisi.'
    valid = false
  }

  if (!filters.end_date) {
    errors.end_date = 'Tanggal akhir wajib diisi.'
    valid = false
  }

  if (filters.start_date && filters.end_date && filters.start_date > filters.end_date) {
    errors.end_date = 'Tanggal akhir harus sama atau setelah tanggal mulai.'
    valid = false
  }

  if (!filters.limit || Number(filters.limit) < 1) {
    errors.limit = 'Limit harus lebih besar dari nol.'
    valid = false
  }

  return valid
}

const fetchStockAlerts = async () => {
  loading.value = true
  try {
    const response = await api.get('/intelligence/stock-alerts', {
      params: {
        warehouse_id: filters.warehouse_id || undefined,
      },
    })
    stockAlerts.value = response.data?.data ?? stockAlerts.value
  } catch (error) {
    console.error('Fetch stock alerts failed:', error)
    toast.error(error.response?.data?.message || 'Gagal memuat stock alerts.')
  } finally {
    loading.value = false
  }
}

const fetchBestSellers = async () => {
  if (!validateBestSellerFilters()) {
    return
  }

  loading.value = true
  try {
    const response = await api.get('/intelligence/best-sellers', {
      params: {
        start_date: filters.start_date,
        end_date: filters.end_date,
        limit: filters.limit,
      },
    })
    bestSellers.value = response.data?.data ?? bestSellers.value
  } catch (error) {
    console.error('Fetch best sellers failed:', error)
    toast.error(error.response?.data?.message || 'Gagal memuat best sellers.')
  } finally {
    loading.value = false
  }
}

const fetchPromoPerformance = async () => {
  loading.value = true
  try {
    const response = await api.get('/intelligence/promo-performance')
    promoPerformance.value = response.data?.data ?? promoPerformance.value
  } catch (error) {
    console.error('Fetch promo performance failed:', error)
    toast.error(error.response?.data?.message || 'Gagal memuat promo performance.')
  } finally {
    loading.value = false
  }
}

const activePanelLabel = computed(() => {
  return panels.find((panel) => panel.value === activePanel.value)?.label || 'Inventory Intelligence'
})

const filterStockAlerts = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return stockAlerts.value.alerts?.under_stocked ?? []

  return (stockAlerts.value.alerts?.under_stocked ?? []).filter((item) => {
    return [item.code, item.name, item.category, item.unit, item.status]
      .filter(Boolean)
      .some((value) => value.toLowerCase().includes(query))
  })
})

const filterOverStockAlerts = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return stockAlerts.value.alerts?.over_stocked ?? []

  return (stockAlerts.value.alerts?.over_stocked ?? []).filter((item) => {
    return [item.code, item.name, item.category, item.unit, item.status]
      .filter(Boolean)
      .some((value) => value.toLowerCase().includes(query))
  })
})

const filteredBestSellers = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return bestSellers.value.best_sellers ?? []

  return (bestSellers.value.best_sellers ?? []).filter((item) => {
    return [item.code, item.name, item.category, item.unit]
      .filter(Boolean)
      .some((value) => value.toLowerCase().includes(query))
  })
})

const filteredPromoPerformance = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return promoPerformance.value.promotions_performance ?? []

  return (promoPerformance.value.promotions_performance ?? []).filter((item) => {
    return [item.code, item.name]
      .filter(Boolean)
      .some((value) => value.toLowerCase().includes(query))
  })
})

const formatCurrency = (value) => {
  if (value === null || value === undefined || value === '') {
    return '-'
  }
  return `Rp ${Number(value).toLocaleString('id-ID', { minimumFractionDigits: 0 })}`
}

const onPanelChange = (panel) => {
  activePanel.value = panel
  clearErrors()
  searchQuery.value = ''
  if (panel === 'stockAlerts') {
    fetchStockAlerts()
  }
  if (panel === 'bestSellers') {
    fetchBestSellers()
  }
  if (panel === 'promoPerformance') {
    fetchPromoPerformance()
  }
}

onMounted(async () => {
  await fetchLookups()
  await fetchStockAlerts()
})
</script>

<template>
  <div class="space-y-6">
    <section class="rounded-3xl bg-white p-6 shadow-sm border border-slate-200">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-slate-900">Inventory Intelligence</h1>
          <p class="mt-1 text-sm text-slate-500">Analisis otomatis berdasarkan data stok dan penjualan.</p>
        </div>

        <div class="flex flex-wrap gap-3">
          <button
            v-for="panel in panels"
            :key="panel.value"
            @click="onPanelChange(panel.value)"
            :class="['rounded-2xl px-4 py-2 text-sm font-semibold transition', activePanel === panel.value ? 'bg-sky-600 text-white shadow' : 'border border-slate-200 bg-white text-slate-700 hover:border-slate-300']"
          >
            {{ panel.label }}
          </button>
        </div>
      </div>
    </section>

    <section class="rounded-3xl bg-white p-6 shadow-sm border border-slate-200">
      <div class="grid gap-4 xl:grid-cols-[1.5fr_1fr]">
        <div class="space-y-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-slate-700">Warehouse Filter</label>
              <select
                v-model="filters.warehouse_id"
                @change="activePanel === 'stockAlerts' ? fetchStockAlerts() : null"
                class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
              >
                <option value="">Semua Warehouse</option>
                <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
              </select>
            </div>

            <div v-if="activePanel === 'bestSellers'" class="space-y-2">
              <label class="block text-sm font-medium text-slate-700">Limit Produk Terlaris</label>
              <input
                v-model="filters.limit"
                type="number"
                min="1"
                class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
              />
              <p v-if="errors.limit" class="mt-1 text-xs text-rose-600">{{ errors.limit }}</p>
            </div>
          </div>

          <div v-if="activePanel === 'bestSellers'" class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="block text-sm font-medium text-slate-700">Tanggal Mulai</label>
              <input
                v-model="filters.start_date"
                type="date"
                class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
              />
              <p v-if="errors.start_date" class="mt-1 text-xs text-rose-600">{{ errors.start_date }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700">Tanggal Akhir</label>
              <input
                v-model="filters.end_date"
                type="date"
                class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
              />
              <p v-if="errors.end_date" class="mt-1 text-xs text-rose-600">{{ errors.end_date }}</p>
            </div>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
          <div class="text-sm text-slate-500">Status Panel</div>
          <div class="mt-3 text-xl font-semibold text-slate-900">{{ activePanelLabel }}</div>
          <p class="mt-2 text-sm text-slate-500">Gunakan filter di samping untuk mempersempit laporan dan memuat data terbaru.</p>
          <div class="mt-4 grid gap-3">
            <div class="rounded-3xl bg-white p-4 shadow-sm">
              <div class="text-xs uppercase tracking-wide text-slate-500">Filter warehouse</div>
              <div class="mt-2 text-base font-semibold text-slate-900">{{ filters.warehouse_id ? warehouses.find((item) => item.id === filters.warehouse_id)?.name : 'Semua Warehouse' }}</div>
            </div>
            <div class="rounded-3xl bg-white p-4 shadow-sm">
              <div class="text-xs uppercase tracking-wide text-slate-500">Data terakhir dimuat</div>
              <div class="mt-2 text-base font-semibold text-slate-900">{{ new Date().toLocaleString('id-ID') }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <div class="text-sm text-slate-500">Cari dalam tabel hasil:</div>
        <div class="flex flex-wrap gap-3">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari item..."
            class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-900 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
          />
          <button
            @click="activePanel === 'stockAlerts' ? fetchStockAlerts() : activePanel === 'bestSellers' ? fetchBestSellers() : fetchPromoPerformance()"
            class="rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
          >Refresh</button>
        </div>
      </div>
    </section>

    <section class="rounded-3xl bg-white p-6 shadow-sm border border-slate-200">
      <div v-if="activePanel === 'stockAlerts'" class="space-y-6">
        <div class="grid gap-4 md:grid-cols-2">
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <div class="text-sm text-slate-500">Total Low Stock</div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ stockAlerts.value.summary?.total_low_stock_items ?? 0 }}</div>
          </div>
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <div class="text-sm text-slate-500">Total Over Stock</div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ stockAlerts.value.summary?.total_over_stock_items ?? 0 }}</div>
          </div>
        </div>

        <div class="space-y-6">
          <div>
            <h3 class="mb-3 text-base font-semibold text-slate-900">Low Stock Alerts</h3>
            <div class="overflow-x-auto rounded-3xl border border-slate-200">
              <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-700">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                  <tr>
                    <th class="px-4 py-3">Kode</th>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Kategori</th>
                    <th class="px-4 py-3">Stok</th>
                    <th class="px-4 py-3">Reorder Point</th>
                    <th class="px-4 py-3">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                  <tr v-for="item in filterStockAlerts" :key="item.id">
                    <td class="px-4 py-3 font-medium text-slate-900">{{ item.code }}</td>
                    <td class="px-4 py-3">{{ item.name }}</td>
                    <td class="px-4 py-3">{{ item.category }}</td>
                    <td class="px-4 py-3">{{ Number(item.current_stock).toLocaleString('id-ID') }}</td>
                    <td class="px-4 py-3">{{ Number(item.reorder_point).toLocaleString('id-ID') }}</td>
                    <td class="px-4 py-3">
                      <span class="inline-flex rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">{{ item.status }}</span>
                    </td>
                  </tr>
                  <tr v-if="filterStockAlerts.length === 0">
                    <td colspan="6" class="px-4 py-8 text-center text-slate-500">Tidak ada alert stok rendah.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div>
            <h3 class="mb-3 text-base font-semibold text-slate-900">Over Stock Alerts</h3>
            <div class="overflow-x-auto rounded-3xl border border-slate-200">
              <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-700">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                  <tr>
                    <th class="px-4 py-3">Kode</th>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Kategori</th>
                    <th class="px-4 py-3">Stok</th>
                    <th class="px-4 py-3">Batas Overstock</th>
                    <th class="px-4 py-3">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                  <tr v-for="item in filterOverStockAlerts" :key="item.id">
                    <td class="px-4 py-3 font-medium text-slate-900">{{ item.code }}</td>
                    <td class="px-4 py-3">{{ item.name }}</td>
                    <td class="px-4 py-3">{{ item.category }}</td>
                    <td class="px-4 py-3">{{ Number(item.current_stock).toLocaleString('id-ID') }}</td>
                    <td class="px-4 py-3">{{ Number(item.overstock_limit).toLocaleString('id-ID') }}</td>
                    <td class="px-4 py-3">
                      <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">{{ item.status }}</span>
                    </td>
                  </tr>
                  <tr v-if="filterOverStockAlerts.length === 0">
                    <td colspan="6" class="px-4 py-8 text-center text-slate-500">Tidak ada alert overstock.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div v-if="activePanel === 'bestSellers'" class="space-y-6">
        <div class="grid gap-4 md:grid-cols-3">
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <div class="text-sm text-slate-500">Total Qty Terjual</div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ Number(bestSellers.value.summary?.total_items_sold ?? 0).toLocaleString('id-ID') }}</div>
          </div>
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <div class="text-sm text-slate-500">Total Revenue</div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ formatCurrency(bestSellers.value.summary?.total_revenue_generated) }}</div>
          </div>
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <div class="text-sm text-slate-500">Total Estimated Profit</div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ formatCurrency(bestSellers.value.summary?.total_estimated_profit) }}</div>
          </div>
        </div>

        <div class="overflow-x-auto rounded-3xl border border-slate-200">
          <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-700">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-4 py-3">Kode</th>
                <th class="px-4 py-3">Nama</th>
                <th class="px-4 py-3">Kategori</th>
                <th class="px-4 py-3 text-right">Qty Terjual</th>
                <th class="px-4 py-3 text-right">Revenue</th>
                <th class="px-4 py-3 text-right">Estimasi Profit</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
              <tr v-for="item in filteredBestSellers" :key="item.product_id">
                <td class="px-4 py-3 font-medium text-slate-900">{{ item.code }}</td>
                <td class="px-4 py-3">{{ item.name }}</td>
                <td class="px-4 py-3">{{ item.category }}</td>
                <td class="px-4 py-3 text-right">{{ Number(item.qty_sold).toLocaleString('id-ID') }}</td>
                <td class="px-4 py-3 text-right">{{ formatCurrency(item.revenue) }}</td>
                <td class="px-4 py-3 text-right">{{ formatCurrency(item.estimated_profit) }}</td>
              </tr>
              <tr v-if="filteredBestSellers.length === 0">
                <td colspan="6" class="px-4 py-8 text-center text-slate-500">Tidak ada data best sellers untuk interval ini.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="activePanel === 'promoPerformance'" class="space-y-6">
        <div class="grid gap-4 md:grid-cols-3">
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <div class="text-sm text-slate-500">Total Promo Diperiksa</div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ promoPerformance.value.summary?.total_promotions_analyzed ?? 0 }}</div>
          </div>
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <div class="text-sm text-slate-500">Total Diskon</div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ formatCurrency(promoPerformance.value.summary?.total_discounts_incurred) }}</div>
          </div>
          <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
            <div class="text-sm text-slate-500">Total Revenue Promo</div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ formatCurrency(promoPerformance.value.summary?.total_revenue_from_promo) }}</div>
          </div>
        </div>

        <div class="overflow-x-auto rounded-3xl border border-slate-200">
          <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-700">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-4 py-3">Kode Promo</th>
                <th class="px-4 py-3">Nama Promo</th>
                <th class="px-4 py-3 text-right">Transaksi</th>
                <th class="px-4 py-3 text-right">Diskon</th>
                <th class="px-4 py-3 text-right">Sales Volume</th>
                <th class="px-4 py-3">Top Products</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
              <tr v-for="item in filteredPromoPerformance" :key="item.promo_id">
                <td class="px-4 py-3 font-medium text-slate-900">{{ item.code }}</td>
                <td class="px-4 py-3">{{ item.name }}</td>
                <td class="px-4 py-3 text-right">{{ Number(item.total_transactions_used).toLocaleString('id-ID') }}</td>
                <td class="px-4 py-3 text-right">{{ formatCurrency(item.discounts_given) }}</td>
                <td class="px-4 py-3 text-right">{{ formatCurrency(item.sales_volume) }}</td>
                <td class="px-4 py-3 text-sm text-slate-500">
                  <ul class="space-y-1">
                    <li v-for="product in item.top_sold_products" :key="product.name">
                      {{ product.name }} ({{ Number(product.qty).toLocaleString('id-ID') }})
                    </li>
                  </ul>
                </td>
              </tr>
              <tr v-if="filteredPromoPerformance.length === 0">
                <td colspan="6" class="px-4 py-8 text-center text-slate-500">Tidak ada data promo performance.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</template>
