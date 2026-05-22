<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()

const products = ref([])
const loading = ref(false)
const savingRule = ref(false)
const validatingPromo = ref(false)
const searchQuery = ref('')
const ruleResult = ref(null)
const promoResult = ref(null)

const ruleForm = ref({
  product_id: '',
  selling_price: '',
  min_margin_percentage: ''
})

const promoForm = ref({
  product_id: '',
  discount_type: 'percent',
  discount_value: ''
})

const ruleErrors = ref({
  product_id: '',
  selling_price: '',
  min_margin_percentage: ''
})

const promoErrors = ref({
  product_id: '',
  discount_type: '',
  discount_value: ''
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

const formatPercent = (value) => {
  const parsed = Number(value)
  if (Number.isNaN(parsed)) return '0%'
  return `${parsed.toLocaleString('id-ID', { maximumFractionDigits: 2 })}%`
}

const fetchSafeguards = async () => {
  loading.value = true
  try {
    const response = await api.get('/pricing/safeguards')
    products.value = response.data?.data || []
  } catch (error) {
    console.error('Error fetching pricing safeguards:', error)
    toast.error(error.response?.data?.message || 'Gagal memuat data pricing safeguard.')
  } finally {
    loading.value = false
  }
}

const selectedRuleProduct = computed(() => {
  return products.value.find(product => product.id === Number(ruleForm.value.product_id)) || null
})

const selectedPromoProduct = computed(() => {
  return products.value.find(product => product.id === Number(promoForm.value.product_id)) || null
})

const filteredProducts = computed(() => {
  const keyword = searchQuery.value.trim().toLowerCase()
  if (!keyword) return products.value

  return products.value.filter(product => {
    return [product.code, product.name, product.sku, product.barcode]
      .filter(Boolean)
      .some(value => value.toLowerCase().includes(keyword))
  })
})

const localMinimumAllowedPrice = computed(() => {
  const product = selectedRuleProduct.value
  const margin = Number(ruleForm.value.min_margin_percentage)

  if (!product || Number.isNaN(margin)) return null
  if (Number(product.cost_price) <= 0) return 0

  const safeMargin = Math.min(99.99, Math.max(0, margin))
  const minimum = Number(product.cost_price) / (1 - (safeMargin / 100))

  return Math.ceil(minimum / 100) * 100
})

const localMargin = computed(() => {
  const product = selectedRuleProduct.value
  const price = Number(ruleForm.value.selling_price)
  const cost = Number(product?.cost_price)

  if (!product || Number.isNaN(price) || Number.isNaN(cost) || price <= 0) return null
  return Number((((price - cost) / price) * 100).toFixed(2))
})

const resetRuleErrors = () => {
  ruleErrors.value = {
    product_id: '',
    selling_price: '',
    min_margin_percentage: ''
  }
}

const resetPromoErrors = () => {
  promoErrors.value = {
    product_id: '',
    discount_type: '',
    discount_value: ''
  }
}

const applyServerErrors = (targetErrors, serverErrors) => {
  Object.keys(serverErrors || {}).forEach(key => {
    if (targetErrors.value[key] !== undefined) {
      targetErrors.value[key] = Array.isArray(serverErrors[key])
        ? serverErrors[key][0]
        : serverErrors[key]
    }
  })
}

const validateRuleForm = () => {
  resetRuleErrors()
  let valid = true

  if (!ruleForm.value.product_id) {
    ruleErrors.value.product_id = 'Produk wajib dipilih.'
    valid = false
  }

  const sellingPrice = Number(ruleForm.value.selling_price)
  if (ruleForm.value.selling_price === '') {
    ruleErrors.value.selling_price = 'Harga jual wajib diisi.'
    valid = false
  } else if (Number.isNaN(sellingPrice) || sellingPrice < 0) {
    ruleErrors.value.selling_price = 'Harga jual harus berupa angka positif atau nol.'
    valid = false
  } else if (localMinimumAllowedPrice.value !== null && sellingPrice < localMinimumAllowedPrice.value) {
    ruleErrors.value.selling_price = `Harga minimal aman adalah ${formatCurrency(localMinimumAllowedPrice.value)}.`
    valid = false
  }

  const margin = Number(ruleForm.value.min_margin_percentage)
  if (ruleForm.value.min_margin_percentage === '') {
    ruleErrors.value.min_margin_percentage = 'Margin safeguard wajib diisi.'
    valid = false
  } else if (Number.isNaN(margin) || margin < 0 || margin > 99.99) {
    ruleErrors.value.min_margin_percentage = 'Margin harus berada di antara 0 sampai 99.99%.'
    valid = false
  }

  return valid
}

const validatePromoForm = () => {
  resetPromoErrors()
  let valid = true

  if (!promoForm.value.product_id) {
    promoErrors.value.product_id = 'Produk wajib dipilih.'
    valid = false
  }

  if (!['fixed', 'percent'].includes(promoForm.value.discount_type)) {
    promoErrors.value.discount_type = 'Tipe diskon wajib dipilih.'
    valid = false
  }

  const value = Number(promoForm.value.discount_value)
  if (promoForm.value.discount_value === '') {
    promoErrors.value.discount_value = 'Nilai diskon wajib diisi.'
    valid = false
  } else if (Number.isNaN(value) || value < 0) {
    promoErrors.value.discount_value = 'Nilai diskon harus berupa angka positif atau nol.'
    valid = false
  } else if (promoForm.value.discount_type === 'percent' && value > 100) {
    promoErrors.value.discount_value = 'Diskon persen tidak boleh lebih dari 100%.'
    valid = false
  }

  return valid
}

const submitRule = async () => {
  if (!validateRuleForm()) {
    toast.warning('Perbaiki error pada form aturan harga terlebih dahulu.')
    return
  }

  savingRule.value = true
  ruleResult.value = null

  try {
    const payload = {
      product_id: Number(ruleForm.value.product_id),
      selling_price: Number(ruleForm.value.selling_price),
      min_margin_percentage: Number(ruleForm.value.min_margin_percentage)
    }

    const response = await api.post('/pricing/set-rules', payload)
    ruleResult.value = response.data?.data || null
    toast.success('Aturan pricing safeguard berhasil disimpan.')
    await fetchSafeguards()
  } catch (error) {
    console.error('Error saving pricing safeguard:', error)
    if (error.response?.status === 422) {
      applyServerErrors(ruleErrors, error.response.data?.errors || {})
      toast.error(error.response.data?.message || 'Validasi pricing safeguard gagal.')
    } else {
      toast.error(error.response?.data?.message || 'Gagal menyimpan aturan pricing safeguard.')
    }
  } finally {
    savingRule.value = false
  }
}

const submitPromoValidation = async () => {
  if (!validatePromoForm()) {
    toast.warning('Perbaiki error pada form validasi promo terlebih dahulu.')
    return
  }

  validatingPromo.value = true
  promoResult.value = null

  try {
    const payload = {
      product_id: Number(promoForm.value.product_id),
      discount_type: promoForm.value.discount_type,
      discount_value: Number(promoForm.value.discount_value)
    }

    const response = await api.post('/pricing/validate-promo', payload)
    promoResult.value = response.data?.data || null
    if (promoResult.value?.validation?.is_safe_to_apply) {
      toast.success('Promo aman untuk diterapkan.')
    } else {
      toast.warning('Promo melanggar batas margin safeguard.')
    }
  } catch (error) {
    console.error('Error validating promo margin:', error)
    if (error.response?.status === 422) {
      applyServerErrors(promoErrors, error.response.data?.errors || {})
      toast.error('Validasi promo gagal.')
    } else {
      toast.error(error.response?.data?.message || 'Gagal memvalidasi promo.')
    }
  } finally {
    validatingPromo.value = false
  }
}

const fillRuleFromProduct = (product) => {
  ruleForm.value.product_id = product.id
  ruleForm.value.selling_price = product.selling_price
  ruleForm.value.min_margin_percentage = product.min_margin_percentage
  resetRuleErrors()
  ruleResult.value = null
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const fillPromoFromProduct = (product) => {
  promoForm.value.product_id = product.id
  resetPromoErrors()
  promoResult.value = null
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

watch(
  () => ruleForm.value.product_id,
  () => {
    const product = selectedRuleProduct.value
    if (!product) return

    ruleForm.value.selling_price = product.selling_price
    ruleForm.value.min_margin_percentage = product.min_margin_percentage
    resetRuleErrors()
    ruleResult.value = null
  }
)

watch(
  () => promoForm.value.product_id,
  () => {
    resetPromoErrors()
    promoResult.value = null
  }
)

onMounted(fetchSafeguards)
</script>

<template>
  <div class="space-y-6">
    <div class="bg-white border border-slate-100 rounded-lg p-5 shadow-sm flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
      <div class="flex items-center gap-3">
        <span class="w-11 h-11 rounded-lg bg-teal-600 text-white flex items-center justify-center shadow-sm">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-6 h-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
          </svg>
        </span>
        <div>
          <h1 class="text-xl font-bold text-slate-800">Pricing Safeguard</h1>
          <p class="text-sm text-slate-500">Kontrol harga jual, batas margin minimum, dan validasi promo sebelum diterapkan.</p>
        </div>
      </div>

      <button
        type="button"
        @click="fetchSafeguards"
        :disabled="loading"
        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-bold transition-colors disabled:opacity-60"
      >
        <span v-if="loading" class="w-4 h-4 border-2 border-slate-600 border-t-transparent rounded-full animate-spin"></span>
        <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
          <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
        </svg>
        Refresh
      </button>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
      <section class="bg-white border border-slate-100 rounded-lg p-5 shadow-sm space-y-5">
        <div class="border-b border-slate-100 pb-4">
          <h2 class="text-base font-bold text-slate-800">Set Harga & Margin Aman</h2>
          <p class="text-xs text-slate-400 mt-1">Update harga produk akan dikunci dalam transaksi database agar tidak bentrok dengan request lain.</p>
        </div>

        <form @submit.prevent="submitRule" class="space-y-4">
          <div class="space-y-1">
            <label for="rule_product_id" class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Produk *</label>
            <select
              id="rule_product_id"
              v-model="ruleForm.product_id"
              class="w-full bg-white border rounded-lg px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-teal-500/20"
              :class="ruleErrors.product_id ? 'border-rose-500' : 'border-slate-200 focus:border-teal-500'"
            >
              <option value="">Pilih produk</option>
              <option v-for="product in products" :key="product.id" :value="product.id">
                {{ product.code }} - {{ product.name }}
              </option>
            </select>
            <p v-if="ruleErrors.product_id" class="text-xs text-rose-600 font-medium">{{ ruleErrors.product_id }}</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-1">
              <label for="selling_price" class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Harga Jual *</label>
              <input
                id="selling_price"
                v-model="ruleForm.selling_price"
                type="number"
                min="0"
                step="0.01"
                placeholder="125000"
                class="w-full bg-white border rounded-lg px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-teal-500/20"
                :class="ruleErrors.selling_price ? 'border-rose-500' : 'border-slate-200 focus:border-teal-500'"
              />
              <p v-if="ruleErrors.selling_price" class="text-xs text-rose-600 font-medium">{{ ruleErrors.selling_price }}</p>
            </div>

            <div class="space-y-1">
              <label for="min_margin_percentage" class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Min Margin (%) *</label>
              <input
                id="min_margin_percentage"
                v-model="ruleForm.min_margin_percentage"
                type="number"
                min="0"
                max="99.99"
                step="0.01"
                placeholder="10"
                class="w-full bg-white border rounded-lg px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-teal-500/20"
                :class="ruleErrors.min_margin_percentage ? 'border-rose-500' : 'border-slate-200 focus:border-teal-500'"
              />
              <p v-if="ruleErrors.min_margin_percentage" class="text-xs text-rose-600 font-medium">{{ ruleErrors.min_margin_percentage }}</p>
            </div>
          </div>

          <div v-if="selectedRuleProduct" class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="rounded-lg border border-slate-100 bg-slate-50 p-3">
              <p class="text-[11px] uppercase font-bold text-slate-400">Modal</p>
              <p class="text-sm font-black text-slate-800 mt-1">{{ formatCurrency(selectedRuleProduct.cost_price) }}</p>
            </div>
            <div class="rounded-lg border border-amber-100 bg-amber-50 p-3">
              <p class="text-[11px] uppercase font-bold text-amber-600">Harga Minimal</p>
              <p class="text-sm font-black text-amber-700 mt-1">{{ formatCurrency(localMinimumAllowedPrice) }}</p>
            </div>
            <div class="rounded-lg border border-teal-100 bg-teal-50 p-3">
              <p class="text-[11px] uppercase font-bold text-teal-600">Margin Aktual</p>
              <p class="text-sm font-black text-teal-700 mt-1">{{ localMargin === null ? '-' : formatPercent(localMargin) }}</p>
            </div>
          </div>

          <div v-if="ruleResult" class="rounded-lg border border-teal-200 bg-teal-50 p-4 text-sm">
            <p class="font-bold text-teal-800">{{ ruleResult.rule.status_label }}</p>
            <p class="text-teal-700 mt-1">
              {{ ruleResult.product.name }} tersimpan dengan harga {{ formatCurrency(ruleResult.product.selling_price) }}
              dan margin aktual {{ formatPercent(ruleResult.rule.actual_margin_percentage) }}.
            </p>
          </div>

          <button
            type="submit"
            :disabled="savingRule"
            class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-teal-600 hover:bg-teal-700 text-white font-bold text-sm py-3 transition-colors disabled:opacity-60"
          >
            <span v-if="savingRule" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            Simpan Aturan Harga
          </button>
        </form>
      </section>

      <section class="bg-white border border-slate-100 rounded-lg p-5 shadow-sm space-y-5">
        <div class="border-b border-slate-100 pb-4">
          <h2 class="text-base font-bold text-slate-800">Validasi Promo Margin</h2>
          <p class="text-xs text-slate-400 mt-1">Simulasikan diskon sebelum promo dipakai di kasir atau modul promotion.</p>
        </div>

        <form @submit.prevent="submitPromoValidation" class="space-y-4">
          <div class="space-y-1">
            <label for="promo_product_id" class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Produk *</label>
            <select
              id="promo_product_id"
              v-model="promoForm.product_id"
              class="w-full bg-white border rounded-lg px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500/20"
              :class="promoErrors.product_id ? 'border-rose-500' : 'border-slate-200 focus:border-indigo-500'"
            >
              <option value="">Pilih produk</option>
              <option v-for="product in products" :key="product.id" :value="product.id">
                {{ product.code }} - {{ product.name }}
              </option>
            </select>
            <p v-if="promoErrors.product_id" class="text-xs text-rose-600 font-medium">{{ promoErrors.product_id }}</p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-1">
              <label for="discount_type" class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Tipe Diskon *</label>
              <select
                id="discount_type"
                v-model="promoForm.discount_type"
                class="w-full bg-white border rounded-lg px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500/20"
                :class="promoErrors.discount_type ? 'border-rose-500' : 'border-slate-200 focus:border-indigo-500'"
              >
                <option value="percent">Persen (%)</option>
                <option value="fixed">Nominal Rupiah</option>
              </select>
              <p v-if="promoErrors.discount_type" class="text-xs text-rose-600 font-medium">{{ promoErrors.discount_type }}</p>
            </div>

            <div class="space-y-1">
              <label for="discount_value" class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Nilai Diskon *</label>
              <input
                id="discount_value"
                v-model="promoForm.discount_value"
                type="number"
                min="0"
                step="0.01"
                :placeholder="promoForm.discount_type === 'percent' ? '10' : '25000'"
                class="w-full bg-white border rounded-lg px-3 py-2.5 text-sm text-slate-800 outline-none focus:ring-2 focus:ring-indigo-500/20"
                :class="promoErrors.discount_value ? 'border-rose-500' : 'border-slate-200 focus:border-indigo-500'"
              />
              <p v-if="promoErrors.discount_value" class="text-xs text-rose-600 font-medium">{{ promoErrors.discount_value }}</p>
            </div>
          </div>

          <div v-if="selectedPromoProduct" class="rounded-lg border border-slate-100 bg-slate-50 p-4 text-sm text-slate-600">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
              <div>
                <p class="font-bold text-slate-800">{{ selectedPromoProduct.name }}</p>
                <p class="text-xs text-slate-400">{{ selectedPromoProduct.code }}</p>
              </div>
              <div class="text-left sm:text-right">
                <p class="text-xs text-slate-400">Harga Jual Saat Ini</p>
                <p class="font-black text-slate-800">{{ formatCurrency(selectedPromoProduct.selling_price) }}</p>
              </div>
            </div>
          </div>

          <div
            v-if="promoResult"
            class="rounded-lg border p-4 text-sm"
            :class="promoResult.validation.is_safe_to_apply ? 'border-teal-200 bg-teal-50 text-teal-800' : 'border-rose-200 bg-rose-50 text-rose-800'"
          >
            <p class="font-bold">{{ promoResult.validation.status_label }}</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
              <div>
                <p class="text-xs opacity-70">Harga Setelah Diskon</p>
                <p class="font-black">{{ formatCurrency(promoResult.proposed_promotion.proposed_selling_price) }}</p>
              </div>
              <div>
                <p class="text-xs opacity-70">Batas Harga Aman</p>
                <p class="font-black">{{ formatCurrency(promoResult.safeguard_limits.minimum_allowed_price) }}</p>
              </div>
              <div>
                <p class="text-xs opacity-70">Margin Setelah Diskon</p>
                <p class="font-black">{{ formatPercent(promoResult.proposed_promotion.margin_percentage_after_discount) }}</p>
              </div>
              <div>
                <p class="text-xs opacity-70">Maks Diskon Aman</p>
                <p class="font-black">{{ formatCurrency(promoResult.safeguard_limits.maximum_safe_discount_amount) }}</p>
              </div>
            </div>
            <p v-if="promoResult.validation.warning_message" class="mt-3 text-xs font-semibold">{{ promoResult.validation.warning_message }}</p>
          </div>

          <button
            type="submit"
            :disabled="validatingPromo"
            class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm py-3 transition-colors disabled:opacity-60"
          >
            <span v-if="validatingPromo" class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            Validasi Promo
          </button>
        </form>
      </section>
    </div>

    <section class="bg-white border border-slate-100 rounded-lg p-5 shadow-sm space-y-4">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
          <h2 class="text-base font-bold text-slate-800">Daftar Produk Terproteksi</h2>
          <p class="text-xs text-slate-400 mt-1">Pantau harga, modal, margin aktual, dan batas minimum aman.</p>
        </div>
        <div class="relative w-full lg:w-80">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Cari kode, nama, SKU, barcode..."
            class="w-full border border-slate-200 rounded-lg pl-10 pr-3 py-2.5 text-sm outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20"
          />
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.602 10.602Z" />
          </svg>
        </div>
      </div>

      <div class="overflow-x-auto rounded-lg border border-slate-100">
        <table class="w-full text-sm text-left border-collapse">
          <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
              <th class="px-4 py-3">Produk</th>
              <th class="px-4 py-3 text-right">Modal</th>
              <th class="px-4 py-3 text-right">Harga Jual</th>
              <th class="px-4 py-3 text-right">Min Margin</th>
              <th class="px-4 py-3 text-right">Harga Minimum</th>
              <th class="px-4 py-3 text-center">Status</th>
              <th class="px-4 py-3 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-if="loading">
              <td colspan="7" class="px-4 py-12 text-center text-slate-400">
                <span class="inline-block w-8 h-8 border-4 border-teal-500 border-t-transparent rounded-full animate-spin mb-2"></span>
                <p>Memuat daftar pricing safeguard...</p>
              </td>
            </tr>
            <tr v-else-if="filteredProducts.length === 0">
              <td colspan="7" class="px-4 py-12 text-center text-slate-400">Tidak ada produk yang cocok.</td>
            </tr>
            <tr v-else v-for="product in filteredProducts" :key="product.id" class="hover:bg-slate-50/70 transition-colors">
              <td class="px-4 py-3">
                <p class="font-bold text-slate-800">{{ product.name }}</p>
                <p class="text-xs text-slate-400">{{ product.code }} <span v-if="product.sku">| SKU {{ product.sku }}</span></p>
              </td>
              <td class="px-4 py-3 text-right font-semibold text-slate-600 whitespace-nowrap">{{ formatCurrency(product.cost_price) }}</td>
              <td class="px-4 py-3 text-right font-bold text-slate-800 whitespace-nowrap">{{ formatCurrency(product.selling_price) }}</td>
              <td class="px-4 py-3 text-right text-slate-600 whitespace-nowrap">{{ formatPercent(product.min_margin_percentage) }}</td>
              <td class="px-4 py-3 text-right font-bold text-amber-700 whitespace-nowrap">{{ formatCurrency(product.minimum_allowed_price) }}</td>
              <td class="px-4 py-3 text-center">
                <span
                  class="inline-flex px-2.5 py-1 rounded text-[11px] font-bold"
                  :class="Number(product.selling_price) >= Number(product.minimum_allowed_price) ? 'bg-teal-50 text-teal-700 border border-teal-100' : 'bg-rose-50 text-rose-700 border border-rose-100'"
                >
                  {{ Number(product.selling_price) >= Number(product.minimum_allowed_price) ? 'SAFE' : 'RISK' }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-center gap-2">
                  <button type="button" @click="fillRuleFromProduct(product)" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-xs font-bold text-slate-700">
                    Edit Rule
                  </button>
                  <button type="button" @click="fillPromoFromProduct(product)" class="px-3 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-xs font-bold text-indigo-700">
                    Test Promo
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
