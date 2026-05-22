<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()
const loading = ref(false)
const submitting = ref(false)
const isEditMode = ref(false)
const editId = ref(null)
const stocks = ref([])
const products = ref([])
const variants = ref([])
const warehouses = ref([])
const racks = ref([])
const searchQuery = ref('')

const form = reactive({
  product_id: '',
  product_variant_id: '',
  warehouse_id: '',
  rack_id: '',
  qty: '',
  min_qty: '0',
})

const errors = reactive({
  product_id: '',
  product_variant_id: '',
  warehouse_id: '',
  rack_id: '',
  qty: '',
  min_qty: '',
})

const fetchMasterData = async () => {
  loading.value = true

  try {
    const [productsRes, variantsRes, warehousesRes, racksRes] = await Promise.all([
      api.get('/products'),
      api.get('/product-variants'),
      api.get('/warehouses'),
      api.get('/racks'),
    ])

    products.value = productsRes.data?.data ?? productsRes.data ?? []
    variants.value = variantsRes.data?.data ?? variantsRes.data ?? []
    warehouses.value = warehousesRes.data?.data ?? warehousesRes.data ?? []
    racks.value = racksRes.data?.data ?? racksRes.data ?? []
  } catch (error) {
    console.error('Fetch master data failed:', error)
    toast.error('Gagal memuat data master inventory.')
  } finally {
    loading.value = false
  }
}

const fetchStocks = async () => {
  loading.value = true

  try {
    const response = await api.get('/product-stocks')
    stocks.value = response.data?.data ?? response.data ?? []
  } catch (error) {
    console.error('Fetch stocks failed:', error)
    toast.error('Gagal memuat daftar stok produk.')
  } finally {
    loading.value = false
  }
}

const clearErrors = () => {
  Object.keys(errors).forEach(key => {
    errors[key] = ''
  })
}

const resetForm = () => {
  isEditMode.value = false
  editId.value = null
  form.product_id = ''
  form.product_variant_id = ''
  form.warehouse_id = ''
  form.rack_id = ''
  form.qty = ''
  form.min_qty = '0'
  clearErrors()
}

const validateForm = () => {
  clearErrors()
  let valid = true

  if (!form.product_id) {
    errors.product_id = 'Pilih produk terlebih dahulu.'
    valid = false
  }

  if (!form.warehouse_id) {
    errors.warehouse_id = 'Pilih gudang terlebih dahulu.'
    valid = false
  }

  if (form.qty === '' || form.qty === null) {
    errors.qty = 'Jumlah stok awal wajib diisi.'
    valid = false
  } else if (isNaN(Number(form.qty)) || Number(form.qty) < 0) {
    errors.qty = 'Jumlah stok harus berupa angka dan tidak boleh negatif.'
    valid = false
  }

  if (form.min_qty !== '' && form.min_qty !== null) {
    if (isNaN(Number(form.min_qty)) || Number(form.min_qty) < 0) {
      errors.min_qty = 'Minimal stok harus berupa angka dan tidak boleh negatif.'
      valid = false
    }
  }

  return valid
}

const formattedStockLabel = (stock) => {
  const product = products.value.find(item => item.id === stock.product_id)
  const variant = variants.value.find(item => item.id === stock.product_variant_id)
  return `${product?.code || '–'} • ${product?.name || 'Tanpa Nama'}${variant ? ` (${variant.name})` : ''}`
}

const lowStockClass = (stock) => {
  if (stock.qty <= stock.min_qty) {
    return 'bg-rose-100 text-rose-700 border-rose-200'
  }
  return 'bg-emerald-100 text-emerald-700 border-emerald-200'
}

const handleSubmit = async () => {
  if (!validateForm()) {
    return
  }

  submitting.value = true

  try {
    const payload = {
      product_id: form.product_id,
      product_variant_id: form.product_variant_id || null,
      warehouse_id: form.warehouse_id,
      rack_id: form.rack_id || null,
      qty: Number(form.qty),
      min_qty: Number(form.min_qty || 0),
    }

    if (isEditMode.value && editId.value) {
      await api.put(`/product-stocks/${editId.value}`, payload)
      toast.success('Stok produk berhasil diperbarui.')
    } else {
      await api.post('/product-stocks', payload)
      toast.success('Stok produk berhasil dibuat.')
    }

    resetForm()
    await fetchStocks()
  } catch (error) {
    console.error('Submit stock failed:', error)
    if (error.response?.status === 422) {
      const serverErrors = error.response.data.errors || {}
      Object.keys(serverErrors).forEach(key => {
        if (errors[key] !== undefined) {
          errors[key] = serverErrors[key][0]
        }
      })
      toast.error('Periksa kembali data form yang bertanda merah.')
    } else {
      toast.error(error.response?.data?.message || 'Terjadi kesalahan saat menyimpan stok.')
    }
  } finally {
    submitting.value = false
  }
}

const handleEdit = (stock) => {
  isEditMode.value = true
  editId.value = stock.id
  form.product_id = stock.product_id || ''
  form.product_variant_id = stock.product_variant_id || ''
  form.warehouse_id = stock.warehouse_id || ''
  form.rack_id = stock.rack_id || ''
  form.qty = stock.qty ?? ''
  form.min_qty = stock.min_qty ?? '0'
  clearErrors()
}

const handleDelete = async (id) => {
  if (!confirm('Hapus stok produk ini? Aksi tidak dapat dibatalkan.')) {
    return
  }

  try {
    await api.delete(`/product-stocks/${id}`)
    toast.success('Stok produk berhasil dihapus.')
    if (editId.value === id) {
      resetForm()
    }
    await fetchStocks()
  } catch (error) {
    console.error('Delete stock failed:', error)
    toast.error('Gagal menghapus stok produk.')
  }
}

const filteredStocks = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return stocks.value
  return stocks.value.filter(stock => {
    const product = products.value.find(item => item.id === stock.product_id)
    const variant = variants.value.find(item => item.id === stock.product_variant_id)
    const warehouse = warehouses.value.find(item => item.id === stock.warehouse_id)
    const rack = racks.value.find(item => item.id === stock.rack_id)
    return [
      product?.name,
      product?.code,
      variant?.name,
      warehouse?.name,
      rack?.name,
    ]
      .filter(Boolean)
      .some(value => value.toLowerCase().includes(query))
  })
})

onMounted(async () => {
  await fetchMasterData()
  await fetchStocks()
})
</script>

<template>
  <div class="space-y-6">
    <div class="rounded-3xl bg-white p-6 shadow-sm border border-slate-200">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-slate-900">Inventory Produk</h1>
          <p class="mt-1 text-sm text-slate-500">Kelola stok produk per warehouse, variant, dan rak secara atomik.</p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari kode, produk, warehouse atau rak..."
            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-sm text-slate-900 shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100 sm:w-80"
          />
        </div>
      </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.7fr_1fr]">
      <section class="rounded-3xl bg-white border border-slate-200 p-6 shadow-sm">
        <div class="mb-5 flex items-center justify-between gap-4">
          <div>
            <h2 class="text-lg font-semibold text-slate-900">Daftar Stok Produk</h2>
            <p class="text-sm text-slate-500">Tampilkan semua entri stok yang terdaftar.</p>
          </div>
          <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-sm text-slate-700">
            Total: {{ stocks.length }} entri
          </span>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200 text-sm text-slate-700">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-4 py-3">Produk</th>
                <th class="px-4 py-3">Warehouse / Rak</th>
                <th class="px-4 py-3 text-right">Qty</th>
                <th class="px-4 py-3 text-right">Min Qty</th>
                <th class="px-4 py-3 text-center">Status</th>
                <th class="px-4 py-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <tr v-for="stock in filteredStocks" :key="stock.id" class="hover:bg-slate-50">
                <td class="px-4 py-3">
                  <div class="font-medium text-slate-900">
                    {{ formattedStockLabel(stock) }}
                  </div>
                  <div class="text-xs text-slate-500">
                    {{ products.find(item => item.id === stock.product_id)?.name || 'Produk tidak ditemukan' }}
                  </div>
                </td>
                <td class="px-4 py-3">
                  <div>{{ warehouses.find(item => item.id === stock.warehouse_id)?.name || 'Gudang tidak ditemukan' }}</div>
                  <div class="text-xs text-slate-500">{{ racks.find(item => item.id === stock.rack_id)?.name || 'Tanpa rak' }}</div>
                </td>
                <td class="px-4 py-3 text-right">{{ Number(stock.qty).toLocaleString('id-ID') }}</td>
                <td class="px-4 py-3 text-right">{{ Number(stock.min_qty).toLocaleString('id-ID') }}</td>
                <td class="px-4 py-3 text-center">
                  <span :class="['inline-flex rounded-full border px-2 py-1 text-xs font-semibold', lowStockClass(stock)]">
                    {{ stock.qty <= stock.min_qty ? ' rendah' : 'aman' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right space-x-2">
                  <button
                    @click="handleEdit(stock)"
                    class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                  >Edit</button>
                  <button
                    @click="handleDelete(stock.id)"
                    class="inline-flex items-center rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100"
                  >Hapus</button>
                </td>
              </tr>
              <tr v-if="filteredStocks.length === 0">
                <td class="px-4 py-8 text-center text-slate-500" colspan="6">Tidak ada data stok untuk ditampilkan.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <section class="rounded-3xl bg-white border border-slate-200 p-6 shadow-sm">
        <div class="mb-5">
          <h2 class="text-lg font-semibold text-slate-900">{{ isEditMode ? 'Update Stok Produk' : 'Tambah Stok Baru' }}</h2>
          <p class="text-sm text-slate-500">Form ini menggunakan validasi client-side dan memanggil API Laravel secara langsung.</p>
        </div>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-slate-700">Produk</label>
            <select v-model="form.product_id" class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100" :class="errors.product_id ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : 'border-slate-200'">
              <option value="" disabled>Pilih produk</option>
              <option v-for="product in products" :key="product.id" :value="product.id">{{ product.code }} — {{ product.name }}</option>
            </select>
            <p v-if="errors.product_id" class="mt-1 text-xs text-rose-600">{{ errors.product_id }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700">Variant Produk</label>
            <select v-model="form.product_variant_id" class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100" :class="errors.product_variant_id ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : 'border-slate-200'">
              <option value="">Tidak ada variant</option>
              <option v-for="variant in variants" :key="variant.id" :value="variant.id">{{ variant.name }}</option>
            </select>
            <p v-if="errors.product_variant_id" class="mt-1 text-xs text-rose-600">{{ errors.product_variant_id }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700">Warehouse</label>
            <select v-model="form.warehouse_id" class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100" :class="errors.warehouse_id ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : 'border-slate-200'">
              <option value="" disabled>Pilih warehouse</option>
              <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
            </select>
            <p v-if="errors.warehouse_id" class="mt-1 text-xs text-rose-600">{{ errors.warehouse_id }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700">Rak</label>
            <select v-model="form.rack_id" class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100" :class="errors.rack_id ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : 'border-slate-200'">
              <option value="">Tidak ada rak</option>
              <option v-for="rack in racks" :key="rack.id" :value="rack.id">{{ rack.name }}</option>
            </select>
            <p v-if="errors.rack_id" class="mt-1 text-xs text-rose-600">{{ errors.rack_id }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700">Jumlah Stok</label>
            <input v-model="form.qty" type="number" step="0.01" min="0" class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100" :class="errors.qty ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : 'border-slate-200'" />
            <p v-if="errors.qty" class="mt-1 text-xs text-rose-600">{{ errors.qty }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700">Minimal Stok</label>
            <input v-model="form.min_qty" type="number" step="0.01" min="0" class="mt-2 w-full rounded-2xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100" :class="errors.min_qty ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : 'border-slate-200'" />
            <p v-if="errors.min_qty" class="mt-1 text-xs text-rose-600">{{ errors.min_qty }}</p>
          </div>

          <div class="flex flex-col gap-3 pt-3 sm:flex-row sm:items-center sm:justify-between">
            <button @click="handleSubmit" :disabled="submitting" class="inline-flex items-center justify-center rounded-2xl bg-sky-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:bg-slate-300">
              {{ isEditMode ? 'Perbarui Stok' : 'Simpan Stok' }}
            </button>
            <button type="button" @click="resetForm" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
              Reset Form
            </button>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
