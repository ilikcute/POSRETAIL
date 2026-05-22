<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()
const loading = ref(false)
const submitting = ref(false)
const stores = ref([])
const suppliers = ref([])
const warehouses = ref([])
const products = ref([])
const purchases = ref([])
const searchProductQuery = ref('')

const typeOptions = [
  { value: 'order', label: 'Purchase Order' },
  { value: 'purchase', label: 'Purchase In' },
  { value: 'return', label: 'Purchase Return' }
]
const statusOptions = [
  { value: 'pending', label: 'Pending' },
  { value: 'ordered', label: 'Ordered' },
  { value: 'received', label: 'Received' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' }
]
const paymentStatusOptions = [
  { value: 'unpaid', label: 'Unpaid' },
  { value: 'partial', label: 'Partial' },
  { value: 'paid', label: 'Paid' }
]
const paymentMethodOptions = [
  { value: 'cash', label: 'Cash' },
  { value: 'bank', label: 'Bank Transfer' },
  { value: 'card', label: 'Card' },
  { value: 'qris', label: 'QRIS' }
]

const form = reactive({
  store_id: null,
  supplier_id: null,
  warehouse_id: null,
  type: 'purchase',
  status: 'pending',
  payment_status: 'unpaid',
  payment_method: 'cash',
  discount_amount: 0,
  shipping_cost: 0,
  notes: '',
  parent_id: null,
  items: []
})

const errors = reactive({})

const purchaseParents = computed(() => {
  return purchases.value.filter(p => p.type === 'purchase')
})

const productSearchResults = computed(() => {
  const query = searchProductQuery.value.trim().toLowerCase()
  if (!query) return []

  return products.value
    .filter(product => {
      return (
        product.name?.toLowerCase().includes(query) ||
        product.code?.toLowerCase().includes(query) ||
        product.barcode?.toLowerCase().includes(query) ||
        product.sku?.toLowerCase().includes(query)
      )
    })
    .slice(0, 8)
})

const loadMasterData = async () => {
  loading.value = true
  try {
    const [storesRes, suppliersRes, warehousesRes, productsRes, purchasesRes] = await Promise.all([
      api.get('/stores'),
      api.get('/suppliers'),
      api.get('/warehouses'),
      api.get('/products'),
      api.get('/purchases')
    ])

    stores.value = storesRes.data?.data || storesRes.data || []
    suppliers.value = suppliersRes.data?.data || suppliersRes.data || []
    warehouses.value = warehousesRes.data?.data || warehousesRes.data || []
    products.value = productsRes.data?.data || productsRes.data || []
    purchases.value = purchasesRes.data?.data || purchasesRes.data || []

    if (!form.store_id && stores.value.length > 0) {
      form.store_id = stores.value[0].id
    }
    if (!form.warehouse_id && warehouses.value.length > 0) {
      form.warehouse_id = warehouses.value[0].id
    }
    if (!form.supplier_id && suppliers.value.length > 0) {
      form.supplier_id = suppliers.value[0].id
    }
  } catch (error) {
    console.error(error)
    toast.error('Gagal memuat data master pembelian.')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadMasterData()
})

const clearErrors = () => {
  Object.keys(errors).forEach(key => {
    delete errors[key]
  })
}

const recalcItem = (item) => {
  item.qty = Number(item.qty || 0)
  item.unit_cost = Number(item.unit_cost || 0)
  item.discount = Number(item.discount || 0)
  item.tax = Number(item.tax || 0)
  item.subtotal = Math.max(0, item.qty * item.unit_cost - item.discount + item.tax)
}

const addProductItem = (product) => {
  if (!product) return

  const existingIndex = form.items.findIndex(
    item => item.product_id === product.id && item.product_variant_id === null
  )

  if (existingIndex !== -1) {
    form.items[existingIndex].qty += 1
    recalcItem(form.items[existingIndex])
  } else {
    form.items.push({
      product_id: product.id,
      product_variant_id: null,
      name: product.name,
      sku: product.sku || product.barcode || '',
      qty: 1,
      unit_cost: Number(product.cost_price ?? product.purchase_price ?? 0),
      discount: 0,
      tax: product.is_taxable ? Number(product.cost_price ?? product.purchase_price ?? 0) * 0.11 : 0,
      subtotal: 0,
      is_taxable: Boolean(product.is_taxable),
      variants: product.variants || []
    })

    recalcItem(form.items[form.items.length - 1])
  }

  searchProductQuery.value = ''
}

const updateItemField = (index, field, value) => {
  const item = form.items[index]
  if (!item) return

  if (field === 'product_variant_id') {
    item.product_variant_id = value || null
    const variant = item.variants.find(variant => variant.id === Number(value))
    if (variant) {
      item.unit_cost = Number(variant.cost_price ?? variant.price ?? item.unit_cost)
      item.name = `${products.value.find(p => p.id === item.product_id)?.name || item.name} (${variant.name})`
      item.tax = item.is_taxable ? Number(item.unit_cost) * 0.11 : 0
    }
  } else if (field === 'qty' || field === 'unit_cost' || field === 'discount' || field === 'tax') {
    item[field] = Number(value || 0)
  } else {
    item[field] = value
  }

  recalcItem(item)
}

const removeItem = (index) => {
  form.items.splice(index, 1)
}

const validateForm = () => {
  clearErrors()

  if (!form.store_id) {
    errors.store_id = 'Outlet harus dipilih.'
  }

  if (!form.supplier_id) {
    errors.supplier_id = 'Supplier harus dipilih.'
  }

  if (!form.warehouse_id) {
    errors.warehouse_id = 'Gudang harus dipilih.'
  }

  if (!form.type) {
    errors.type = 'Tipe dokumen harus dipilih.'
  }

  if (!form.status) {
    errors.status = 'Status dokumen harus dipilih.'
  }

  if (!form.payment_status) {
    errors.payment_status = 'Status pembayaran harus dipilih.'
  }

  if (!form.payment_method) {
    errors.payment_method = 'Metode pembayaran harus dipilih.'
  }

  if (form.type === 'return' && !form.parent_id) {
    errors.parent_id = 'Retur harus merujuk ke dokumen pembelian sebelumnya.'
  }

  if (!Array.isArray(form.items) || form.items.length === 0) {
    errors.items = 'Minimal satu item pembelian diperlukan.'
  } else {
    errors.items = []
    form.items.forEach((item, index) => {
      const itemErrors = {}
      if (!item.product_id) {
        itemErrors.product_id = 'Produk harus dipilih.'
      }
      if (item.qty <= 0) {
        itemErrors.qty = 'Kuantitas harus lebih besar dari nol.'
      }
      if (item.unit_cost < 0) {
        itemErrors.unit_cost = 'Harga pokok tidak boleh negatif.'
      }
      if (item.discount < 0) {
        itemErrors.discount = 'Diskon tidak boleh negatif.'
      }
      if (item.tax < 0) {
        itemErrors.tax = 'Pajak tidak boleh negatif.'
      }

      if (Object.keys(itemErrors).length > 0) {
        errors.items[index] = itemErrors
      }
    })

    if (errors.items.length === 0) {
      delete errors.items
    }
  }

  if (Number(form.discount_amount) < 0) {
    errors.discount_amount = 'Diskon dokumen tidak boleh negatif.'
  }

  if (Number(form.shipping_cost) < 0) {
    errors.shipping_cost = 'Biaya kirim tidak boleh negatif.'
  }

  return Object.keys(errors).length === 0
}

const parseServerErrors = (serverErrors) => {
  if (!serverErrors || typeof serverErrors !== 'object') {
    return
  }

  Object.entries(serverErrors).forEach(([key, value]) => {
    const message = Array.isArray(value) ? value[0] : value
    if (key.startsWith('items.')) {
      const parts = key.split('.')
      const index = Number(parts[1])
      const field = parts[2]
      if (!errors.items) {
        errors.items = []
      }
      if (!errors.items[index]) {
        errors.items[index] = {}
      }
      errors.items[index][field] = message
      return
    }
    errors[key] = message
  })
}

const submitPurchase = async () => {
  if (!validateForm()) {
    toast.error('Perbaiki kesalahan form sebelum menyimpan.')
    return
  }

  submitting.value = true
  try {
    const payload = {
      store_id: form.store_id,
      supplier_id: form.supplier_id,
      warehouse_id: form.warehouse_id,
      type: form.type,
      status: form.status,
      payment_status: form.payment_status,
      payment_method: form.payment_method,
      discount_amount: Number(form.discount_amount || 0),
      shipping_cost: Number(form.shipping_cost || 0),
      notes: form.notes,
      parent_id: form.parent_id,
      items: form.items.map(item => ({
        product_id: item.product_id,
        product_variant_id: item.product_variant_id,
        qty: item.qty,
        unit_cost: item.unit_cost,
        discount: item.discount,
        tax: item.tax
      }))
    }

    const response = await api.post('/purchases', payload)
    toast.success(response.data?.message || 'Dokumen pembelian berhasil disimpan.')
    await loadMasterData()
    resetForm()
  } catch (error) {
    if (error.response?.data?.errors) {
      parseServerErrors(error.response.data.errors)
    } else {
      const message = error.response?.data?.message || 'Gagal menyimpan purchase. Silakan coba lagi.'
      toast.error(message)
    }
  } finally {
    submitting.value = false
  }
}

const resetForm = () => {
  form.store_id = stores.value.length > 0 ? stores.value[0].id : null
  form.supplier_id = suppliers.value.length > 0 ? suppliers.value[0].id : null
  form.warehouse_id = warehouses.value.length > 0 ? warehouses.value[0].id : null
  form.type = 'purchase'
  form.status = 'pending'
  form.payment_status = 'unpaid'
  form.discount_amount = 0
  form.shipping_cost = 0
  form.notes = ''
  form.payment_method = 'cash'
}

const totalAmount = computed(() => {
  return form.items.reduce((sum, item) => sum + (Number(item.qty || 0) * Number(item.unit_cost || 0)), 0)
})

const totalTax = computed(() => {
  return form.items.reduce((sum, item) => sum + Number(item.tax || 0), 0)
})

const totalDiscount = computed(() => {
  return Number(form.discount_amount || 0) + form.items.reduce((sum, item) => sum + Number(item.discount || 0), 0)
})

const grandTotal = computed(() => {
  return Number(totalAmount.value || 0) + Number(totalTax.value || 0) + Number(form.shipping_cost || 0) - Number(form.discount_amount || 0)
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-slate-900">Manajemen Pembelian</h1>
        <p class="text-sm text-slate-500 mt-1">Buat dokumen pembelian, kelola item, dan simpan ke backend dengan validasi lengkap.</p>
      </div>
      <button
        type="button"
        @click="resetForm"
        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
      >
        Reset Form
      </button>
    </div>

    <section class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
      <div class="space-y-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="block text-sm font-medium text-slate-700">Outlet</label>
            <select v-model="form.store_id" class="mt-2 w-full rounded-xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
              :class="errors.store_id ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : 'border-slate-200'"
            >
              <option value="" disabled>Pilih Outlet</option>
              <option v-for="store in stores" :key="store.id" :value="store.id">{{ store.name }}</option>
            </select>
            <p v-if="errors.store_id" class="mt-1 text-xs text-rose-600">{{ errors.store_id }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700">Supplier</label>
            <select v-model="form.supplier_id" class="mt-2 w-full rounded-xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
              :class="errors.supplier_id ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : 'border-slate-200'"
            >
              <option value="" disabled>Pilih Supplier</option>
              <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
            </select>
            <p v-if="errors.supplier_id" class="mt-1 text-xs text-rose-600">{{ errors.supplier_id }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700">Gudang</label>
            <select v-model="form.warehouse_id" class="mt-2 w-full rounded-xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
              :class="errors.warehouse_id ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : 'border-slate-200'"
            >
              <option value="" disabled>Pilih Gudang</option>
              <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.name }}</option>
            </select>
            <p v-if="errors.warehouse_id" class="mt-1 text-xs text-rose-600">{{ errors.warehouse_id }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700">Tipe Dokumen</label>
            <select v-model="form.type" class="mt-2 w-full rounded-xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
              :class="errors.type ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : 'border-slate-200'"
            >
              <option value="" disabled>Pilih Tipe</option>
              <option v-for="option in typeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <p v-if="errors.type" class="mt-1 text-xs text-rose-600">{{ errors.type }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700">Status</label>
            <select v-model="form.status" class="mt-2 w-full rounded-xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
              :class="errors.status ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : 'border-slate-200'"
            >
              <option value="" disabled>Pilih Status</option>
              <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <p v-if="errors.status" class="mt-1 text-xs text-rose-600">{{ errors.status }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700">Status Pembayaran</label>
            <select v-model="form.payment_status" class="mt-2 w-full rounded-xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
              :class="errors.payment_status ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : 'border-slate-200'"
            >
              <option value="" disabled>Pilih Status Pembayaran</option>
              <option v-for="option in paymentStatusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <p v-if="errors.payment_status" class="mt-1 text-xs text-rose-600">{{ errors.payment_status }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700">Metode Pembayaran</label>
            <select v-model="form.payment_method" class="mt-2 w-full rounded-xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
              :class="errors.payment_method ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : 'border-slate-200'"
            >
              <option value="" disabled>Pilih Metode Pembayaran</option>
              <option v-for="option in paymentMethodOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>
            <p v-if="errors.payment_method" class="mt-1 text-xs text-rose-600">{{ errors.payment_method }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700">Retur dari Pembelian</label>
            <select v-model="form.parent_id" class="mt-2 w-full rounded-xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
              :disabled="form.type !== 'return'"
              :class="form.type === 'return' && errors.parent_id ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : 'border-slate-200'"
            >
              <option value="" disabled>Pilih Dokumen</option>
              <option v-for="parent in purchaseParents" :key="parent.id" :value="parent.id">
                {{ parent.reference_no }} - {{ parent.supplier?.name || 'Supplier tidak terdaftar' }}
              </option>
            </select>
            <p v-if="errors.parent_id" class="mt-1 text-xs text-rose-600">{{ errors.parent_id }}</p>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
          <div class="flex items-center justify-between gap-3 pb-4">
            <div>
              <h2 class="text-lg font-semibold text-slate-900">Item Pembelian</h2>
              <p class="text-sm text-slate-500">Cari produk lalu tambahkan ke daftar item.</p>
            </div>
            <button type="button" class="rounded-full bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-700"
              @click="addProductItem(products[0] || null)"
            >
              Tambah Item Cepat
            </button>
          </div>

          <div class="relative">
            <input
              type="text"
              v-model="searchProductQuery"
              placeholder="Cari produk berdasarkan nama, kode, barcode atau SKU"
              class="w-full rounded-3xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
            />
            <div v-if="productSearchResults.length > 0" class="absolute z-20 mt-2 w-full rounded-3xl border border-slate-200 bg-white shadow-xl">
              <ul class="max-h-56 overflow-y-auto">
                <li v-for="product in productSearchResults" :key="product.id">
                  <button
                    type="button"
                    class="w-full px-4 py-3 text-left text-sm transition hover:bg-slate-100"
                    @click="addProductItem(product); searchProductQuery = ''"
                  >
                    <span class="font-medium text-slate-900">{{ product.name }}</span>
                    <span class="ml-2 text-xs text-slate-500">{{ product.code || product.barcode || product.sku || 'Tanpa kode' }}</span>
                  </button>
                </li>
              </ul>
            </div>
          </div>
          <p v-if="errors.items && typeof errors.items === 'string'" class="mt-3 text-xs text-rose-600">{{ errors.items }}</p>

          <div class="mt-5 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
              <thead class="bg-slate-100 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th class="px-4 py-3">Produk</th>
                  <th class="px-4 py-3">Variant</th>
                  <th class="px-4 py-3 w-24">Qty</th>
                  <th class="px-4 py-3 w-32">Unit Cost</th>
                  <th class="px-4 py-3 w-32">Diskon</th>
                  <th class="px-4 py-3 w-32">Pajak</th>
                  <th class="px-4 py-3 w-32">Subtotal</th>
                  <th class="px-4 py-3 w-14"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200 bg-white">
                <tr v-for="(item, index) in form.items" :key="item.product_id + '-' + index">
                  <td class="px-4 py-3 align-top">
                    <div class="font-medium text-slate-900">{{ item.name }}</div>
                    <div class="text-xs text-slate-500">{{ item.sku }}</div>
                    <p v-if="errors.items && errors.items[index] && errors.items[index].product_id" class="mt-1 text-xs text-rose-600">{{ errors.items[index].product_id }}</p>
                  </td>

                  <td class="px-4 py-3 align-top">
                    <select v-if="item.variants.length"
                      v-model="item.product_variant_id"
                      @change="updateItemField(index, 'product_variant_id', item.product_variant_id)"
                      class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
                    >
                      <option value="">Default</option>
                      <option v-for="variant in item.variants" :key="variant.id" :value="variant.id">{{ variant.name }}</option>
                    </select>
                    <span v-else class="text-xs text-slate-500">Tidak ada variant</span>
                  </td>

                  <td class="px-4 py-3 align-top">
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      v-model.number="item.qty"
                      @input="updateItemField(index, 'qty', item.qty)"
                      class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
                    />
                    <p v-if="errors.items && errors.items[index] && errors.items[index].qty" class="mt-1 text-xs text-rose-600">{{ errors.items[index].qty }}</p>
                  </td>

                  <td class="px-4 py-3 align-top">
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      v-model.number="item.unit_cost"
                      @input="updateItemField(index, 'unit_cost', item.unit_cost)"
                      class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
                    />
                    <p v-if="errors.items && errors.items[index] && errors.items[index].unit_cost" class="mt-1 text-xs text-rose-600">{{ errors.items[index].unit_cost }}</p>
                  </td>

                  <td class="px-4 py-3 align-top">
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      v-model.number="item.discount"
                      @input="updateItemField(index, 'discount', item.discount)"
                      class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
                    />
                    <p v-if="errors.items && errors.items[index] && errors.items[index].discount" class="mt-1 text-xs text-rose-600">{{ errors.items[index].discount }}</p>
                  </td>

                  <td class="px-4 py-3 align-top">
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      v-model.number="item.tax"
                      @input="updateItemField(index, 'tax', item.tax)"
                      class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
                    />
                    <p v-if="errors.items && errors.items[index] && errors.items[index].tax" class="mt-1 text-xs text-rose-600">{{ errors.items[index].tax }}</p>
                  </td>

                  <td class="px-4 py-3 align-top">
                    <div class="font-semibold text-slate-900">{{ item.subtotal.toLocaleString('id-ID', { style: 'currency', currency: 'IDR' }) }}</div>
                  </td>

                  <td class="px-4 py-3 align-top text-right">
                    <button type="button" @click="removeItem(index)" class="rounded-full bg-rose-500 px-3 py-1 text-xs font-semibold text-white transition hover:bg-rose-600">Hapus</button>
                  </td>
                </tr>
                <tr v-if="form.items.length === 0">
                  <td colspan="8" class="px-4 py-6 text-center text-sm text-slate-500">Tidak ada item pembelian. Tambahkan produk terlebih dahulu.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <div>
            <label class="block text-sm font-medium text-slate-700">Biaya Kirim</label>
            <input type="number" min="0" step="0.01" v-model.number="form.shipping_cost" class="mt-2 w-full rounded-xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
              :class="errors.shipping_cost ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : 'border-slate-200'"
            />
            <p v-if="errors.shipping_cost" class="mt-1 text-xs text-rose-600">{{ errors.shipping_cost }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-slate-700">Diskon Dokumen</label>
            <input type="number" min="0" step="0.01" v-model.number="form.discount_amount" class="mt-2 w-full rounded-xl border px-3 py-2 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"
              :class="errors.discount_amount ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : 'border-slate-200'"
            />
            <p v-if="errors.discount_amount" class="mt-1 text-xs text-rose-600">{{ errors.discount_amount }}</p>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700">Catatan Pembelian</label>
          <textarea v-model="form.notes" rows="3" class="mt-2 w-full rounded-3xl border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-100"></textarea>
        </div>

        <div class="flex flex-col gap-3 rounded-3xl border border-slate-200 bg-slate-50 p-5 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p class="text-sm text-slate-600">Total Barang</p>
            <p class="text-xl font-semibold text-slate-900">{{ totalAmount.toLocaleString('id-ID', { style: 'currency', currency: 'IDR' }) }}</p>
          </div>
          <div>
            <p class="text-sm text-slate-600">Total Pajak</p>
            <p class="text-xl font-semibold text-slate-900">{{ totalTax.toLocaleString('id-ID', { style: 'currency', currency: 'IDR' }) }}</p>
          </div>
          <div>
            <p class="text-sm text-slate-600">Grand Total</p>
            <p class="text-2xl font-bold text-sky-700">{{ grandTotal.toLocaleString('id-ID', { style: 'currency', currency: 'IDR' }) }}</p>
          </div>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <button type="button" @click="submitPurchase" :disabled="submitting" class="inline-flex items-center justify-center rounded-3xl bg-sky-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:bg-slate-300">
            {{ submitting ? 'Menyimpan...' : 'Simpan Pembelian' }}
          </button>
          <button type="button" @click="resetForm" class="inline-flex items-center justify-center rounded-3xl border border-slate-200 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
            Batal
          </button>
        </div>
      </div>

      <aside class="space-y-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div>
          <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-slate-900">Daftar Pembelian</h2>
            <span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-700">{{ purchases.length }} dokumen</span>
          </div>
          <p class="mt-2 text-sm text-slate-500">Lihat riwayat pembelian terbaru dan referensi parent untuk retur.</p>
        </div>

        <div class="space-y-4">
          <div v-for="purchase in purchases.slice(0, 5)" :key="purchase.id" class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-sm font-semibold text-slate-900">{{ purchase.reference_no }}</p>
            <p class="text-sm text-slate-600">{{ purchase.supplier?.name || 'Supplier tidak tersedia' }}</p>
            <div class="mt-3 grid gap-2 text-xs text-slate-500 sm:grid-cols-2">
              <div>Status: <span class="font-semibold text-slate-700">{{ purchase.status }}</span></div>
              <div>Pembayaran: <span class="font-semibold text-slate-700">{{ purchase.payment_status }}</span></div>
              <div>Warehouse: <span class="font-semibold text-slate-700">{{ purchase.warehouse?.name || '-' }}</span></div>
              <div>Total: <span class="font-semibold text-slate-700">{{ Number(purchase.grand_total || 0).toLocaleString('id-ID', { style: 'currency', currency: 'IDR' }) }}</span></div>
            </div>
          </div>
        </div>

        <div>
          <h3 class="text-sm font-semibold text-slate-900">Catatan</h3>
          <p class="mt-2 text-sm leading-6 text-slate-500">Modul ini menyimpan pembelian dengan validasi client-side, menampilkan pesan kesalahan di bawah setiap field, dan memproses request ke API backend secara atomik.</p>
        </div>
      </aside>
    </section>
  </div>
</template>
