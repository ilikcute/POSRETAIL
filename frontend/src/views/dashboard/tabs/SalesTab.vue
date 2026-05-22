<script setup>
import { ref, onMounted, computed, watch, nextTick } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()

// Active Context States
const activeShift = ref(null)
const warehouses = ref([])
const stations = ref([])
const customers = ref([])
const promotions = ref([])
const products = ref([])

// POS Selection States
const selectedWarehouseId = ref(null)
const selectedStationId = ref(null)
const selectedCustomerId = ref('')
const selectedPromotionId = ref('')
const pointsRedeemed = ref(0)
const redeemPointsChecked = ref(false)

// UI and Interactive States
const loading = ref(false)
const submitting = ref(false)
const searchProductQuery = ref('')
const barcodeSearchInput = ref(null)
const showPaymentModal = ref(false)
const showSuspendedCartsModal = ref(false)
const paymentMethod = ref('cash')
const amountPaid = ref(0)
const simulatePaymentFail = ref(false)
const cardNumber = ref('')
const notes = ref('')

// Cart State
const cart = ref([])

// Suspended Carts State
const suspendedCarts = ref([])
const suspendNotes = ref('')
const showSuspendNotesModal = ref(false)

// Fetch Initial POS Configurations
const initPOSContext = async () => {
  loading.value = true
  try {
    // 1. Get Shift
    const shiftRes = await api.get('/shifts')
    const shiftsList = shiftRes.data?.data || shiftRes.data || []
    activeShift.value = shiftsList.find(s => s.status === 'open') || null

    if (activeShift.value) {
      selectedStationId.value = activeShift.value.station_id
    }

    // 2. Get Warehouses
    const whRes = await api.get('/warehouses')
    warehouses.value = whRes.data?.data || whRes.data || []
    if (warehouses.value.length > 0) {
      selectedWarehouseId.value = warehouses.value[0].id
    }

    // 3. Get Stations
    const stRes = await api.get('/stations')
    stations.value = stRes.data?.data || stRes.data || []

    // 4. Get Customers
    const custRes = await api.get('/customers')
    customers.value = custRes.data?.data || custRes.data || []

    // 5. Get Promotions
    const promoRes = await api.get('/promotions')
    promotions.value = (promoRes.data?.data || promoRes.data || []).filter(p => p.is_active === 1 || p.is_active === true)

    // 6. Get Products with variant and stock relations
    const prodRes = await api.get('/products')
    products.value = prodRes.data?.data || prodRes.data || []

    // 7. Get Suspended Carts
    fetchSuspendedCarts()

  } catch (error) {
    console.error('POS context fetch error:', error)
    toast.error('Gagal menginisialisasi modul POS Kasir.')
  } finally {
    loading.value = false
  }
}

const fetchSuspendedCarts = async () => {
  try {
    const res = await api.get('/suspended-carts/pending')
    suspendedCarts.value = res.data?.data || res.data || []
  } catch (error) {
    console.error('Error fetching suspended carts:', error)
  }
}

onMounted(() => {
  initPOSContext()
  focusBarcode()
})

// Auto Focus Barcode input
const focusBarcode = () => {
  nextTick(() => {
    if (barcodeSearchInput.value) {
      barcodeSearchInput.value.focus()
    }
  })
}

// Interactive Product Filter / Scan
const filteredProducts = computed(() => {
  if (!searchProductQuery.value) return []
  const query = searchProductQuery.value.toLowerCase().trim()
  return products.value.filter(p => {
    return p.name.toLowerCase().includes(query) ||
           (p.barcode && p.barcode.toLowerCase().includes(query)) ||
           (p.sku && p.sku.toLowerCase().includes(query)) ||
           (p.code && p.code.toLowerCase().includes(query))
  }).slice(0, 8) // Limit to top 8 quick results
})

// Quick-select dynamic customer profile
const selectedCustomerProfile = computed(() => {
  if (!selectedCustomerId.value) return null
  return customers.value.find(c => c.id === parseInt(selectedCustomerId.value))
})

// Reset redeemed points when customer changes or unchecked
watch(selectedCustomerId, () => {
  pointsRedeemed.value = 0
  redeemPointsChecked.value = false
})

watch(redeemPointsChecked, (newVal) => {
  if (!newVal || !selectedCustomerProfile.value) {
    pointsRedeemed.value = 0
  } else {
    // Redeem either entire points balance or enough to cover grand total
    const pointsBal = parseFloat(selectedCustomerProfile.value.point_balance || 0)
    const currentTotalBeforePoints = subtotal.value + taxAmount.value - appliedPromotionDiscount.value
    pointsRedeemed.value = Math.min(pointsBal, Math.max(0, Math.floor(currentTotalBeforePoints)))
  }
})

// Active Promotions List Filter
const selectedPromotionProfile = computed(() => {
  if (!selectedPromotionId.value) return null
  return promotions.value.find(p => p.id === parseInt(selectedPromotionId.value))
})

// Cart item management
const addToCart = (product, variant = null) => {
  // Cek apakah ada shift aktif
  if (!activeShift.value) {
    toast.error('Wajib membuka Shift Kasir terlebih dahulu sebelum melayani penjualan!')
    return
  }

  // Ambil data stok di gudang terpilih
  const whId = selectedWarehouseId.value
  const stockRecord = product.stocks ? product.stocks.find(s => s.warehouse_id === whId) : null
  const stockQty = stockRecord ? parseFloat(stockRecord.qty) : 0.0

  if (stockQty <= 0) {
    toast.warning(`Stok produk '${product.name}' kosong di gudang terpilih!`)
    return
  }

  const cartKey = variant ? `v_${variant.id}` : `p_${product.id}`
  const existingItemIndex = cart.value.findIndex(item => item.key === cartKey)

  if (existingItemIndex > -1) {
    const existingItem = cart.value[existingItemIndex]
    if (existingItem.qty + 1 > stockQty) {
      toast.warning(`Tidak dapat menambahkan! Stok maksimal yang tersedia adalah ${stockQty}`)
      return
    }
    cart.value[existingItemIndex].qty += 1
  } else {
    cart.value.push({
      key: cartKey,
      product_id: product.id,
      product_variant_id: variant ? variant.id : null,
      name: variant ? `${product.name} (${variant.name})` : product.name,
      barcode: variant ? variant.barcode : product.barcode,
      sku: variant ? variant.sku : product.sku,
      unit_price: parseFloat(variant ? variant.price : product.price),
      cost_price: parseFloat(variant ? variant.cost_price : product.cost_price),
      qty: 1,
      discount: 0,
      tax: product.is_taxable ? parseFloat(variant ? variant.price : product.price) * 0.11 : 0, // PPN 11%
      stockQty: stockQty
    })
  }

  searchProductQuery.value = ''
  focusBarcode()
}

// Handle Direct Barcode Scan Enter Event
const handleBarcodeSubmit = () => {
  const query = searchProductQuery.value.trim()
  if (!query) return

  // Cari produk dengan barcode / sku yang cocok persis
  const exactProduct = products.value.find(p =>
    (p.barcode && p.barcode.toLowerCase() === query.toLowerCase()) ||
    (p.sku && p.sku.toLowerCase() === query.toLowerCase())
  )

  if (exactProduct) {
    addToCart(exactProduct)
    toast.success(`Scan berhasil: ${exactProduct.name}`)
  } else {
    // Cari variant
    let foundVariant = null
    let parentProduct = null
    for (const p of products.value) {
      if (p.variants && p.variants.length > 0) {
        const v = p.variants.find(varItem => varItem.barcode && varItem.barcode.toLowerCase() === query.toLowerCase())
        if (v) {
          foundVariant = v
          parentProduct = p
          break
        }
      }
    }

    if (foundVariant && parentProduct) {
      addToCart(parentProduct, foundVariant)
      toast.success(`Scan Variant: ${parentProduct.name} (${foundVariant.name})`)
    } else {
      toast.info('Barang tidak terdeteksi via barcode scan. Menampilkan menu pencarian manual.')
    }
  }
}

// Update Cart Quantity with Stock Bounds checking
const updateQty = (index, delta) => {
  const item = cart.value[index]
  const newQty = item.qty + delta
  if (newQty <= 0) {
    cart.value.splice(index, 1)
    toast.info('Item dihapus dari keranjang.')
  } else if (newQty > item.stockQty) {
    toast.warning(`Stok tidak mencukupi! Maksimal yang tersedia: ${item.stockQty}`)
  } else {
    cart.value[index].qty = newQty
    // Recalculate tax if taxable
    cart.value[index].tax = (item.unit_price * newQty) * 0.11
  }
}

// Calculations
const subtotal = computed(() => {
  return cart.value.reduce((sum, item) => sum + (item.qty * item.unit_price), 0)
})

const taxAmount = computed(() => {
  return cart.value.reduce((sum, item) => sum + (item.tax || 0), 0)
})

const appliedPromotionDiscount = computed(() => {
  const total = subtotal.value
  const promo = selectedPromotionProfile.value
  if (!promo) return 0.0

  if (total < parseFloat(promo.min_purchase_amount || 0)) {
    return 0.0
  }

  let calculatedDisc = 0.0
  if (promo.type === 'percentage') {
    calculatedDisc = (total * parseFloat(promo.value)) / 100
    const maxDisc = parseFloat(promo.max_discount_amount || 0)
    if (maxDisc > 0 && calculatedDisc > maxDisc) {
      calculatedDisc = maxDisc
    }
  } else if (promo.type === 'fixed_amount') {
    calculatedDisc = parseFloat(promo.value)
  }

  return calculatedDisc
})

const grandTotal = computed(() => {
  const sumVal = subtotal.value + taxAmount.value
  const promoDisc = appliedPromotionDiscount.value
  const pointsDisc = redeemPointsChecked.value ? pointsRedeemed.value * 1 : 0
  const finalVal = sumVal - promoDisc - pointsDisc
  return Math.max(0, finalVal)
})

// Real-time Cash Change
const cashChange = computed(() => {
  if (paymentMethod.value !== 'cash') return 0
  const change = amountPaid.value - grandTotal.value
  return Math.max(0, change)
})

// Quick cash selectors
const selectQuickCash = (value) => {
  amountPaid.value = value
}

// Reset Local Cart State
const handleReset = () => {
  if (confirm('Apakah Anda yakin ingin mengosongkan seluruh keranjang belanja ini?')) {
    cart.value = []
    selectedCustomerId.value = ''
    selectedPromotionId.value = ''
    redeemPointsChecked.value = false
    pointsRedeemed.value = 0
    notes.value = ''
    toast.success('Transaksi laci kasir berhasil di-reset.')
  }
}

// Suspend / Hold Transaction
const openSuspendModal = () => {
  if (cart.value.length === 0) {
    toast.warning('Keranjang belanja masih kosong!')
    return
  }
  suspendNotes.value = ''
  showSuspendNotesModal.value = true
}

const handleSuspend = async () => {
  showSuspendNotesModal.value = false
  submitting.value = true
  try {
    const payload = {
      station_id: selectedStationId.value,
      customer_id: selectedCustomerId.value || null,
      notes: suspendNotes.value || 'Gantung Penjualan Kasir',
      items: cart.value.map(item => ({
        product_id: item.product_id,
        qty: item.qty,
        unit_price: item.unit_price,
        notes: item.name
      }))
    }

    const response = await api.post('/suspended-carts/suspend', payload)
    toast.success(`Keranjang belanja berhasil digantung! Kode Antrean: ${response.data.data.queue_code}`)
    
    // Reset Cart
    cart.value = []
    selectedCustomerId.value = ''
    selectedPromotionId.value = ''
    redeemPointsChecked.value = false
    pointsRedeemed.value = 0
    
    // Refresh suspended carts
    fetchSuspendedCarts()
  } catch (error) {
    console.error('Suspend cart error:', error)
    toast.error('Gagal menggantung transaksi kasir.')
  } finally {
    submitting.value = false
  }
}

// Retrieve Suspended Cart
const handleRetrieveCart = async (queueCode) => {
  try {
    const res = await api.get(`/suspended-carts/retrieve/${queueCode}`)
    const cartData = res.data?.data || res.data

    if (cartData) {
      // Load into local cart
      cart.value = cartData.items.map(item => {
        // Cari stock produk di master
        const masterProd = products.value.find(p => p.id === item.product_id)
        const stockRecord = masterProd?.stocks ? masterProd.stocks.find(s => s.warehouse_id === selectedWarehouseId.value) : null
        const stockQty = stockRecord ? parseFloat(stockRecord.qty) : 999.0

        return {
          key: `p_${item.product_id}`,
          product_id: item.product_id,
          product_variant_id: null,
          name: item.product?.name || item.notes || 'Produk POS',
          barcode: item.product?.barcode || '',
          sku: item.product?.sku || '',
          unit_price: parseFloat(item.unit_price),
          cost_price: parseFloat(item.product?.cost_price || item.unit_price * 0.8),
          qty: parseFloat(item.qty),
          discount: 0,
          tax: item.product?.is_taxable ? parseFloat(item.unit_price) * parseFloat(item.qty) * 0.11 : 0,
          stockQty: stockQty
        }
      })

      selectedCustomerId.value = cartData.customer_id || ''
      showSuspendedCartsModal.value = false
      toast.success(`Antrean #${queueCode} berhasil dimuat kembali ke kasir!`)
    }
  } catch (error) {
    console.error('Retrieve suspended cart error:', error)
    toast.error('Gagal memuat antrean transaksi gantung.')
  }
}

// Show Payment Modal
const openPayment = () => {
  if (cart.value.length === 0) {
    toast.warning('Keranjang belanja kosong! Silakan tambahkan barang terlebih dahulu.')
    return
  }
  amountPaid.value = Math.ceil(grandTotal.value)
  simulatePaymentFail.value = false
  cardNumber.value = ''
  showPaymentModal.value = true
}

// Checkout Submission
const handleCheckout = async () => {
  if (paymentMethod.value === 'cash' && amountPaid.value < grandTotal.value) {
    toast.error('Nominal uang dibayarkan kurang dari Grand Total belanja!')
    return
  }

  submitting.value = true
  try {
    const payload = {
      store_id: activeShift.value.store_id || 1,
      station_id: selectedStationId.value,
      shift_id: activeShift.value.id,
      warehouse_id: selectedWarehouseId.value,
      customer_id: selectedCustomerId.value || null,
      promotion_id: selectedPromotionId.value || null,
      status: 'completed', // Complete checkout will deduct stock and post ledger journals
      payment_method: paymentMethod.value,
      discount_amount: appliedPromotionDiscount.value,
      amount_paid: amountPaid.value,
      points_redeemed: redeemPointsChecked.value ? pointsRedeemed.value : 0,
      notes: notes.value || 'Penjualan POS Kasir',
      simulate_payment_fail: simulatePaymentFail.value,
      card_number: cardNumber.value,
      items: cart.value.map(item => ({
        product_id: item.product_id,
        product_variant_id: item.product_variant_id,
        qty: item.qty,
        unit_price: item.unit_price,
        cost_price: item.cost_price,
        discount: item.discount,
        tax: item.tax
      }))
    }

    const response = await api.post('/sales', payload)
    
    // Success handling
    toast.success('Penjualan Sukses! Transaksi laci kasir berhasil dibukukan.')
    
    // Print Receipt
    const saleId = response.data.data.id
    try {
      await api.post(`/sales/${saleId}/print-receipt`, {
        connector_type: 'network',
        connector_target: '192.168.1.200',
        paper_width: 32
      })
      toast.info('Struk belanja dicetak otomatis ke printer thermal.')
    } catch (e) {
      console.warn('Thermal print auto failure:', e)
    }

    // Refresh products list to update inventory counts
    const prodRes = await api.get('/products')
    products.value = prodRes.data?.data || prodRes.data || []

    // Reset local state
    cart.value = []
    selectedCustomerId.value = ''
    selectedPromotionId.value = ''
    redeemPointsChecked.value = false
    pointsRedeemed.value = 0
    notes.value = ''
    showPaymentModal.value = false

    // Refresh suspended carts
    fetchSuspendedCarts()

  } catch (error) {
    console.error('POS Checkout error:', error)
    const errMessage = error.response?.data?.message || 'Gagal memproses checkout penjualan.'
    toast.error(errMessage)
  } finally {
    submitting.value = false
  }
}

// Void a completed transaction
const handleVoidSale = async () => {
  const saleIdInput = prompt('Masukkan ID / Nomor Invoice Transaksi Penjualan yang akan di-Void:')
  if (!saleIdInput) return

  if (!confirm('Apakah Anda yakin ingin membatalkan (Void) transaksi ini? Semua stok barang akan dikembalikan dan jurnal akuntansi akan diposting balik.')) {
    return
  }

  loading.value = true
  try {
    const response = await api.put(`/sales/${saleIdInput}`, {
      status: 'void'
    })
    toast.success('Void transaksi berhasil diproses dan dibukukan!')
    
    // Refresh products
    const prodRes = await api.get('/products')
    products.value = prodRes.data?.data || prodRes.data || []
  } catch (error) {
    console.error('Void transaction error:', error)
    toast.error(error.response?.data?.message || 'Gagal melakukan void transaksi.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header Page dengan Glassmorphism Status Panel -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white/70 backdrop-blur-md p-6 rounded-2xl border border-slate-100 shadow-sm">
      <div>
        <h1 class="text-2xl font-bold bg-gradient-to-r from-slate-800 to-teal-700 bg-clip-text text-transparent">POS Terminal Kasir Utama</h1>
        <p class="text-slate-500 text-sm mt-1">Sistem pencatatan transaksi penjualan ritel kasir real-time.</p>
      </div>

      <!-- Shift & Station Context Status Indicator -->
      <div class="flex items-center gap-3">
        <!-- Status Shift -->
        <div v-if="activeShift" class="flex items-center gap-2 bg-emerald-50 text-emerald-700 px-4 py-2 rounded-xl text-sm font-semibold border border-emerald-100 shadow-sm animate-pulse-subtle">
          <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full"></span>
          Shift Kasir Aktif: #{{ activeShift.id }} ({{ activeShift.user?.name || 'Petugas' }})
        </div>
        <div v-else class="flex items-center gap-2 bg-rose-50 text-rose-700 px-4 py-2 rounded-xl text-sm font-semibold border border-rose-100 shadow-sm">
          <span class="w-2.5 h-2.5 bg-rose-500 rounded-full"></span>
          BELUM BUKA SHIFT
        </div>

        <!-- Quick actions buttons -->
        <button
          @click="showSuspendedCartsModal = true"
          class="flex items-center gap-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-4 py-2 rounded-xl text-sm font-semibold border border-indigo-100 shadow-sm transition-all duration-200 cursor-pointer"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z" />
          </svg>
          Antrean ({{ suspendedCarts.length }})
        </button>

        <button
          @click="handleVoidSale"
          class="flex items-center gap-2 bg-rose-50 hover:bg-rose-100 text-rose-700 px-4 py-2 rounded-xl text-sm font-semibold border border-rose-100 shadow-sm transition-all duration-200 cursor-pointer"
          title="Void Penjualan"
        >
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Void Transaksi
        </button>
      </div>
    </div>

    <!-- Layout Utama: 2 Kolom Ramping POS -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
      <!-- Left Column (Shopping Cart & Product Search Grid) -->
      <div class="lg:col-span-8 space-y-6">
        <!-- Interactive Scan & Barcode Area -->
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5 text-slate-400">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 014.875 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 12v1.5m0 3v1.5m3-4.5v3M13.5 13.5h.75m0 3h-.75m3-3h.75m0 3h-.75m-6 3h.75m3-3h.75" />
              </svg>
            </div>
            <input
              ref="barcodeSearchInput"
              type="text"
              v-model="searchProductQuery"
              @keydown.enter="handleBarcodeSubmit"
              placeholder="Scan Barcode Barang atau ketik Nama / SKU / Kode Produk..."
              class="w-full pl-12 pr-24 py-4 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-slate-800 placeholder-slate-400 font-medium transition-all duration-200 shadow-inner"
            />
            <div class="absolute inset-y-0 right-0 pr-3 flex items-center gap-2">
              <span class="text-xs bg-slate-100 text-slate-500 px-2 py-1.5 rounded-lg border border-slate-200 font-mono shadow-sm">ENTER TO SCAN</span>
            </div>
          </div>

          <!-- Dynamic Search Results Dropdown List -->
          <div v-if="filteredProducts.length > 0" class="mt-2 bg-white rounded-xl border border-slate-200 shadow-xl overflow-hidden divide-y divide-slate-100 relative z-20">
            <div
              v-for="product in filteredProducts"
              :key="product.id"
              @click="addToCart(product)"
              class="flex justify-between items-center p-3.5 hover:bg-teal-50/50 cursor-pointer transition-all duration-200"
            >
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-teal-50 border border-teal-100 rounded-lg flex items-center justify-center text-teal-600 font-bold text-sm">
                  {{ product.name.charAt(0) }}
                </div>
                <div>
                  <h4 class="font-semibold text-slate-800 text-sm">{{ product.name }}</h4>
                  <p class="text-xs text-slate-400 font-mono">{{ product.barcode }} | SKU: {{ product.sku }}</p>
                </div>
              </div>

              <!-- Price & Stock tag inside search -->
              <div class="text-right">
                <p class="font-bold text-teal-600 text-sm">Rp {{ parseFloat(product.price).toLocaleString('id-ID') }}</p>
                <p class="text-xs font-semibold" :class="(product.stocks && product.stocks[0]?.qty > 5) ? 'text-emerald-500' : 'text-amber-500'">
                  Stok: {{ product.stocks ? parseFloat(product.stocks[0]?.qty || 0) : 0 }} Pcs
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Keranjang Belanja Ritel POS (Shopping Cart List) -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden flex flex-col">
          <!-- Cart Header -->
          <div class="p-5 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
            <div class="flex items-center gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5 text-slate-500">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z" />
              </svg>
              <h2 class="font-bold text-slate-800">Daftar Item Belanja Kasir</h2>
            </div>
            <span class="bg-teal-50 text-teal-700 font-bold text-xs px-2.5 py-1 rounded-full border border-teal-100">
              Total: {{ cart.length }} Item
            </span>
          </div>

          <!-- Empty Cart State -->
          <div v-if="cart.length === 0" class="flex flex-col items-center justify-center p-12 text-center text-slate-400">
            <div class="w-16 h-16 bg-slate-50 border border-slate-100 rounded-full flex items-center justify-center mb-4 text-slate-300">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-8 h-8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
              </svg>
            </div>
            <p class="font-semibold text-slate-500">Keranjang kasir kosong.</p>
            <p class="text-xs mt-1 text-slate-400">Scan barcode barang atau gunakan fitur pencarian di atas untuk memasukkan barang.</p>
          </div>

          <!-- Table Item Belanja -->
          <div v-else class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-slate-50/50 border-b border-slate-100 text-slate-400 text-xs font-semibold uppercase tracking-wider">
                  <th class="p-4 pl-6">Produk & Detail</th>
                  <th class="p-4 text-center">Jumlah (Qty)</th>
                  <th class="p-4 text-right">Harga Satuan</th>
                  <th class="p-4 text-right">Subtotal</th>
                  <th class="p-4 pr-6 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr v-for="(item, index) in cart" :key="item.key" class="hover:bg-slate-50/40 transition-colors">
                  <!-- Name & Info -->
                  <td class="p-4 pl-6">
                    <div class="font-semibold text-slate-800 text-sm">{{ item.name }}</div>
                    <div class="text-xs text-slate-400 font-mono mt-0.5">BC: {{ item.barcode }} | Gudang Stok: <span class="font-bold text-teal-600">{{ item.stockQty }} Pcs</span></div>
                  </td>

                  <!-- Qty Changer with stock validator -->
                  <td class="p-4">
                    <div class="flex items-center justify-center gap-1.5">
                      <button
                        @click="updateQty(index, -1)"
                        class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 active:scale-95 text-slate-600 font-bold transition-all flex items-center justify-center cursor-pointer"
                      >
                        -
                      </button>
                      <span class="w-10 text-center font-bold text-sm text-slate-800 font-mono">{{ item.qty }}</span>
                      <button
                        @click="updateQty(index, 1)"
                        class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 active:scale-95 text-slate-600 font-bold transition-all flex items-center justify-center cursor-pointer"
                        :disabled="item.qty >= item.stockQty"
                        :class="{'opacity-50 cursor-not-allowed': item.qty >= item.stockQty}"
                      >
                        +
                      </button>
                    </div>
                  </td>

                  <!-- Price -->
                  <td class="p-4 text-right font-semibold text-slate-600 text-sm">
                    Rp {{ item.unit_price.toLocaleString('id-ID') }}
                  </td>

                  <!-- Total -->
                  <td class="p-4 text-right font-bold text-slate-800 text-sm">
                    Rp {{ (item.qty * item.unit_price).toLocaleString('id-ID') }}
                  </td>

                  <!-- Trash -->
                  <td class="p-4 pr-6 text-center">
                    <button
                      @click="cart.splice(index, 1)"
                      class="text-rose-500 hover:text-rose-700 hover:bg-rose-50 p-2 rounded-lg transition-all duration-200 cursor-pointer"
                      title="Hapus"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                      </svg>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Right Column (Customer, Loyalty, Promotions & Billing Panel) -->
      <div class="lg:col-span-4 space-y-6">
        <!-- POS Configuration Panel -->
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm space-y-4">
          <h3 class="font-bold text-slate-800 text-sm border-b border-slate-50 pb-2">Konfigurasi Laci POS</h3>
          
          <!-- Gudang & Station Selectors -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Gudang Stok</label>
              <select
                v-model="selectedWarehouseId"
                class="w-full p-2.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-xs font-semibold text-slate-700"
              >
                <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Kasir Station</label>
              <select
                v-model="selectedStationId"
                class="w-full p-2.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-xs font-semibold text-slate-700"
                disabled
              >
                <option v-for="st in stations" :key="st.id" :value="st.id">{{ st.name }}</option>
              </select>
            </div>
          </div>
        </div>

        <!-- CRM Customer & Loyalty Panel -->
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm space-y-4">
          <div class="flex justify-between items-center">
            <h3 class="font-bold text-slate-800 text-sm">Pelanggan & Poin</h3>
            <span class="text-xs text-teal-600 font-semibold bg-teal-50 px-2 py-0.5 rounded-lg">CRM</span>
          </div>

          <!-- Select Customer -->
          <div>
            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Pilih Customer</label>
            <select
              v-model="selectedCustomerId"
              class="w-full p-3 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm font-semibold text-slate-700"
            >
              <option value="">-- Pelanggan Umum (Guest) --</option>
              <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }} (Poin: {{ parseFloat(c.point_balance || 0) }})</option>
            </select>
          </div>

          <!-- Loyalty points reward & redeem card -->
          <div v-if="selectedCustomerProfile" class="bg-teal-50/50 p-4 rounded-xl border border-teal-100/50 space-y-2.5">
            <div class="flex justify-between text-xs text-teal-800 font-medium">
              <span>Poin Terkumpul:</span>
              <span class="font-bold">{{ parseFloat(selectedCustomerProfile.point_balance || 0) }} Poin</span>
            </div>

            <!-- Point redeem checkbox -->
            <label class="flex items-center gap-2 cursor-pointer pt-1">
              <input
                type="checkbox"
                v-model="redeemPointsChecked"
                class="rounded border-slate-300 text-teal-600 focus:ring-teal-500 w-4 h-4 cursor-pointer"
              />
              <span class="text-xs text-slate-700 font-semibold">Tukarkan Poin Belanja</span>
            </label>

            <!-- Redeemed amount display -->
            <div v-if="redeemPointsChecked" class="text-xs text-emerald-600 font-semibold flex justify-between bg-white/70 p-2 rounded-lg border border-emerald-100">
              <span>Potongan Belanja:</span>
              <span>- Rp {{ pointsRedeemed.toLocaleString('id-ID') }}</span>
            </div>
          </div>
        </div>

        <!-- Promotion & Voucher Selector -->
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm space-y-4">
          <h3 class="font-bold text-slate-800 text-sm">Diskon & Promo Kupon</h3>
          <div>
            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Pilih Promo Aktif</label>
            <select
              v-model="selectedPromotionId"
              class="w-full p-3 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm font-semibold text-slate-700"
            >
              <option value="">-- Tidak Memakai Kupon --</option>
              <option v-for="p in promotions" :key="p.id" :value="p.id">
                {{ p.name }} (Min: Rp {{ parseFloat(p.min_purchase_amount).toLocaleString('id-ID') }} / Potong: {{ p.type === 'percentage' ? p.value + '%' : 'Rp ' + parseFloat(p.value).toLocaleString('id-ID') }})
              </option>
            </select>
          </div>

          <!-- Promo active alert banner -->
          <div v-if="selectedPromotionProfile" class="text-xs p-3 rounded-xl border"
               :class="subtotal >= parseFloat(selectedPromotionProfile.min_purchase_amount) ? 'bg-emerald-50 text-emerald-800 border-emerald-100' : 'bg-amber-50 text-amber-800 border-amber-100'">
            <div v-if="subtotal >= parseFloat(selectedPromotionProfile.min_purchase_amount)">
              <span class="font-bold">✓ Promo Aktif:</span> Potongan harga Rp {{ appliedPromotionDiscount.toLocaleString('id-ID') }} berhasil diterapkan!
            </div>
            <div v-else>
              <span class="font-bold">⚠ Syarat Belum Cukup:</span> Kurang Rp {{ (parseFloat(selectedPromotionProfile.min_purchase_amount) - subtotal).toLocaleString('id-ID') }} lagi untuk mendapatkan potongan.
            </div>
          </div>
        </div>

        <!-- Billing & Totals Checkout Panel -->
        <div class="bg-gradient-to-br from-slate-900 via-slate-850 to-teal-950 text-white p-6 rounded-2xl border border-slate-800 shadow-xl space-y-6">
          <h3 class="font-bold text-sm tracking-wide uppercase text-white/50 border-b border-white/10 pb-3">Ringkasan Tagihan Kasir</h3>

          <div class="space-y-3.5 text-sm">
            <div class="flex justify-between text-white/70">
              <span>Subtotal Item:</span>
              <span class="font-mono">Rp {{ subtotal.toLocaleString('id-ID') }}</span>
            </div>
            <div class="flex justify-between text-white/70">
              <span>Pajak Belanja (PPN 11%):</span>
              <span class="font-mono">Rp {{ taxAmount.toLocaleString('id-ID') }}</span>
            </div>
            <div v-if="appliedPromotionDiscount > 0" class="flex justify-between text-teal-400 font-semibold">
              <span>Diskon Kupon Promo:</span>
              <span class="font-mono">- Rp {{ appliedPromotionDiscount.toLocaleString('id-ID') }}</span>
            </div>
            <div v-if="redeemPointsChecked && pointsRedeemed > 0" class="flex justify-between text-emerald-400 font-semibold">
              <span>Diskon Poin Loyalty:</span>
              <span class="font-mono">- Rp {{ (pointsRedeemed * 1).toLocaleString('id-ID') }}</span>
            </div>

            <!-- Large grand total highlights -->
            <div class="border-t border-white/10 pt-4 mt-2 flex flex-col gap-1">
              <span class="text-xs uppercase text-white/50 font-semibold tracking-wider">Grand Total Akhir:</span>
              <span class="text-3xl font-black font-mono text-teal-400">
                Rp {{ grandTotal.toLocaleString('id-ID') }}
              </span>
            </div>
          </div>

          <!-- Checkout & Control buttons -->
          <div class="space-y-3 pt-2">
            <!-- Main Proceed Payment -->
            <button
              @click="openPayment"
              class="w-full bg-gradient-to-r from-teal-500 to-emerald-500 hover:from-teal-600 hover:to-emerald-600 active:scale-[0.98] text-white font-bold py-3.5 px-4 rounded-xl shadow-lg shadow-teal-500/20 transition-all duration-200 cursor-pointer flex items-center justify-center gap-2"
              :disabled="cart.length === 0"
              :class="{'opacity-50 cursor-not-allowed': cart.length === 0}"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5h16.5a1.5 1.5 0 011.5 1.5v12a1.5 1.5 0 01-1.5 1.5H3.75a1.5 1.5 0 01-1.5-1.5v-12a1.5 1.5 0 01-1.5-1.5zM12 12.75a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z" />
              </svg>
              Bayar Transaksi (F8)
            </button>

            <div class="grid grid-cols-2 gap-3">
              <!-- Suspend / Hold -->
              <button
                @click="openSuspendModal"
                class="bg-white/10 hover:bg-white/20 active:scale-95 text-white py-2.5 px-3 rounded-xl text-xs font-bold border border-white/10 transition-all duration-200 cursor-pointer flex items-center justify-center gap-1.5"
                :disabled="cart.length === 0"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-slate-300">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 9v6m-4.5-6v6M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Gantung Cart
              </button>

              <!-- Reset / Clear -->
              <button
                @click="handleReset"
                class="bg-rose-500/20 hover:bg-rose-500/30 text-rose-300 hover:text-white py-2.5 px-3 rounded-xl text-xs font-bold border border-rose-500/20 transition-all duration-200 cursor-pointer flex items-center justify-center gap-1.5"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
                Reset Laci
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL 1: Suspended Carts Queue List Drawer -->
    <div v-if="showSuspendedCartsModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
      <div class="bg-white rounded-2xl border border-slate-100 shadow-2xl w-full max-w-2xl overflow-hidden animate-scale-up">
        <div class="p-6 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
          <h3 class="font-bold text-slate-800 text-lg">Daftar Antrean Belanja Gantung</h3>
          <button @click="showSuspendedCartsModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="p-6 overflow-y-auto max-h-[400px] divide-y divide-slate-100">
          <div v-if="suspendedCarts.length === 0" class="text-center py-12 text-slate-400">
            Tidak ada transaksi gantung yang tertunda saat ini.
          </div>
          <div
            v-for="cartItem in suspendedCarts"
            :key="cartItem.id"
            class="flex justify-between items-center py-4 first:pt-0 last:pb-0"
          >
            <div>
              <div class="font-bold text-teal-600 font-mono text-sm">{{ cartItem.queue_code }}</div>
              <div class="text-xs text-slate-500 font-medium mt-0.5">Catatan: "{{ cartItem.notes }}"</div>
              <div class="text-xs text-slate-400 mt-1 font-mono">Pelanggan: {{ cartItem.customer?.name || 'Umum (Guest)' }} | {{ cartItem.total_items }} Pcs barang</div>
            </div>

            <div class="text-right flex items-center gap-4">
              <div>
                <p class="font-bold text-slate-800 text-sm">Rp {{ parseFloat(cartItem.total_amount).toLocaleString('id-ID') }}</p>
                <p class="text-xs text-slate-400 font-mono mt-0.5">{{ new Date(cartItem.created_at).toLocaleTimeString('id-ID') }}</p>
              </div>
              <button
                @click="handleRetrieveCart(cartItem.queue_code)"
                class="bg-teal-50 hover:bg-teal-100 text-teal-700 font-bold text-xs px-3.5 py-2 rounded-lg border border-teal-100 transition-all duration-200 cursor-pointer"
              >
                Panggil Ke Kasir
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL 2: Suspend Note Form -->
    <div v-if="showSuspendNotesModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
      <div class="bg-white rounded-2xl border border-slate-100 shadow-2xl w-full max-w-md overflow-hidden">
        <div class="p-6 border-b border-slate-100">
          <h3 class="font-bold text-slate-800 text-lg">Catatan Gantung Transaksi</h3>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Tulis Catatan Antrean</label>
            <input
              type="text"
              v-model="suspendNotes"
              placeholder="Contoh: Belanjaan Ibu Budi / Tertinggal Dompet"
              class="w-full p-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500 text-sm"
            />
          </div>
        </div>
        <div class="p-6 bg-slate-50 flex justify-end gap-3">
          <button @click="showSuspendNotesModal = false" class="bg-slate-200 text-slate-700 px-4 py-2.5 rounded-xl font-bold text-xs cursor-pointer">Batal</button>
          <button @click="handleSuspend" class="bg-teal-600 hover:bg-teal-700 text-white px-5 py-2.5 rounded-xl font-bold text-xs cursor-pointer">Gantung Sekarang</button>
        </div>
      </div>
    </div>

    <!-- MODAL 3: Interactive Payment Terminal Modal -->
    <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
      <div class="bg-white rounded-3xl border border-slate-100 shadow-2xl w-full max-w-4xl overflow-hidden animate-scale-up grid grid-cols-1 md:grid-cols-12">
        
        <!-- Left Side: Invoice Summary Billing -->
        <div class="md:col-span-5 bg-slate-900 text-white p-8 flex flex-col justify-between">
          <div>
            <h4 class="text-xs uppercase text-teal-400 font-bold tracking-wider mb-2">Terminal Pembayaran POS</h4>
            <h2 class="text-2xl font-black tracking-tight">SAS Ritel Indonesia</h2>
            
            <div class="border-t border-white/10 pt-6 mt-6 space-y-3.5 text-xs text-white/70">
              <div class="flex justify-between">
                <span>Subtotal Item:</span>
                <span class="font-mono">Rp {{ subtotal.toLocaleString('id-ID') }}</span>
              </div>
              <div class="flex justify-between">
                <span>Pajak (PPN 11%):</span>
                <span class="font-mono">Rp {{ taxAmount.toLocaleString('id-ID') }}</span>
              </div>
              <div v-if="appliedPromotionDiscount > 0" class="flex justify-between text-teal-400 font-bold">
                <span>Diskon Kupon:</span>
                <span class="font-mono">- Rp {{ appliedPromotionDiscount.toLocaleString('id-ID') }}</span>
              </div>
              <div v-if="redeemPointsChecked && pointsRedeemed > 0" class="flex justify-between text-emerald-400 font-bold">
                <span>Diskon Poin:</span>
                <span class="font-mono">- Rp {{ (pointsRedeemed * 1).toLocaleString('id-ID') }}</span>
              </div>
            </div>
          </div>

          <div class="border-t border-white/10 pt-6 mt-6">
            <span class="text-xs text-white/50 uppercase font-semibold">Total Tagihan Wajib Bayar:</span>
            <div class="text-3xl font-black font-mono text-teal-400 mt-1">
              Rp {{ grandTotal.toLocaleString('id-ID') }}
            </div>
          </div>
        </div>

        <!-- Right Side: Active Payment Gateway simulation panel -->
        <div class="md:col-span-7 p-8 space-y-6 flex flex-col justify-between">
          <!-- Close button -->
          <div class="flex justify-between items-center">
            <h3 class="font-bold text-slate-800 text-lg">Pilih Metode Pembayaran</h3>
            <button @click="showPaymentModal = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Method Selection Tabs -->
          <div class="grid grid-cols-3 gap-3">
            <!-- Cash -->
            <button
              @click="paymentMethod = 'cash'"
              class="p-4 rounded-2xl border text-center font-bold text-sm cursor-pointer transition-all duration-200 flex flex-col items-center gap-1.5"
              :class="paymentMethod === 'cash' ? 'border-teal-500 bg-teal-50/50 text-teal-700' : 'border-slate-200 text-slate-500 hover:bg-slate-50'"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5h16.5a1.5 1.5 0 011.5 1.5v12a1.5 1.5 0 01-1.5 1.5H3.75a1.5 1.5 0 01-1.5-1.5v-12a1.5 1.5 0 011.5-1.5z" />
              </svg>
              Tunai / Cash
            </button>

            <!-- QRIS -->
            <button
              @click="paymentMethod = 'qris'"
              class="p-4 rounded-2xl border text-center font-bold text-sm cursor-pointer transition-all duration-200 flex flex-col items-center gap-1.5"
              :class="paymentMethod === 'qris' ? 'border-teal-500 bg-teal-50/50 text-teal-700' : 'border-slate-200 text-slate-500 hover:bg-slate-50'"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z" />
              </svg>
              QRIS / e-Wallet
            </button>

            <!-- Credit Card -->
            <button
              @click="paymentMethod = 'card'"
              class="p-4 rounded-2xl border text-center font-bold text-sm cursor-pointer transition-all duration-200 flex flex-col items-center gap-1.5"
              :class="paymentMethod === 'card' ? 'border-teal-500 bg-teal-50/50 text-teal-700' : 'border-slate-200 text-slate-500 hover:bg-slate-50'"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3" />
              </svg>
              Kartu Debit/Kredit
            </button>
          </div>

          <!-- Dynamic Panel based on payment method selection -->
          <div class="flex-1 min-h-[160px] bg-slate-50/50 border border-slate-100 rounded-2xl p-5">
            <!-- 1. CASH TAB -->
            <div v-if="paymentMethod === 'cash'" class="space-y-4">
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Uang Fisik Diterima</label>
                  <input
                    type="number"
                    v-model="amountPaid"
                    class="w-full p-3 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-500 font-mono font-bold text-lg text-slate-800"
                  />
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Kembalian Uang</label>
                  <div class="w-full p-3 rounded-xl bg-teal-50 border border-teal-100 font-mono font-black text-lg text-teal-700">
                    Rp {{ cashChange.toLocaleString('id-ID') }}
                  </div>
                </div>
              </div>

              <!-- Quick cash buttons -->
              <div class="flex flex-wrap gap-2 pt-1">
                <button
                  v-for="cashOption in [Math.ceil(grandTotal), 10000, 20000, 50000, 100000, 200000]"
                  :key="cashOption"
                  @click="selectQuickCash(cashOption)"
                  class="bg-white hover:bg-slate-100 border border-slate-200 px-3.5 py-1.5 rounded-lg text-xs font-bold text-slate-700 transition shadow-sm cursor-pointer"
                >
                  Rp {{ cashOption.toLocaleString('id-ID') }}
                </button>
              </div>
            </div>

            <!-- 2. QRIS TAB -->
            <div v-if="paymentMethod === 'qris'" class="flex items-center gap-6 justify-center">
              <div class="w-28 h-28 bg-white border border-slate-200 rounded-xl p-2 flex items-center justify-center shadow-sm">
                <!-- Mock static/dynamic QR code -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" class="w-full h-full text-slate-900">
                  <path fill="currentColor" d="M0,0h40v40H0V0z M10,10v20h20V10H10z M60,0h40v40H60V0z M70,10v20h20V10H70z M0,60h40v40H0V60z M10,70v20h20V70H10z M50,50h10v10H50V50z M70,60h10v10H70V60z M90,50h10v20H90V50z M60,80h20v10H60V80z M80,90h20v10H80V90z" />
                </svg>
              </div>
              <div class="space-y-1 max-w-[280px]">
                <h4 class="font-bold text-slate-800 text-sm">Simulasi QRIS POS Dinamis</h4>
                <p class="text-xs text-slate-500 leading-relaxed">Scan kode QR di atas menggunakan aplikasi OVO, GoPay, ShopeePay atau e-Wallet lainnya senilai nominal tagihan.</p>
              </div>
            </div>

            <!-- 3. CREDIT CARD TAB -->
            <div v-if="paymentMethod === 'card'" class="space-y-4">
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Nomor Kartu Debit/Kredit</label>
                  <input
                    type="text"
                    v-model="cardNumber"
                    placeholder="4111 1111 1111 1111"
                    class="w-full p-2.5 rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-500 text-xs font-mono text-slate-700"
                  />
                </div>
                <!-- Interactive error toggle for gateway test simulation -->
                <div class="flex items-center pl-2">
                  <label class="flex items-center gap-2 cursor-pointer pt-4">
                    <input
                      type="checkbox"
                      v-model="simulatePaymentFail"
                      class="rounded border-slate-300 text-rose-600 focus:ring-rose-500 w-4.5 h-4.5 cursor-pointer"
                    />
                    <span class="text-xs text-rose-500 font-bold">Simulasikan Gagal Bayar</span>
                  </label>
                </div>
              </div>
              <p class="text-[11px] text-slate-400 leading-normal">
                *Masukkan nomor kartu di atas. Centang kotak simulasi gagal jika Anda ingin menguji rollback transaksi database, error logging, dan custom exceptions.
              </p>
            </div>
          </div>

          <!-- Checkout Action -->
          <button
            @click="handleCheckout"
            class="w-full bg-gradient-to-r from-teal-600 to-emerald-600 hover:from-teal-700 hover:to-emerald-700 active:scale-[0.98] text-white font-bold py-4 rounded-xl shadow-lg transition-all duration-200 cursor-pointer flex items-center justify-center gap-2"
            :disabled="submitting"
          >
            <span v-if="submitting" class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            <span v-else>Konfirmasi & Bayar Selesai</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes scale-up {
  from {
    transform: scale(0.95);
    opacity: 0;
  }
  to {
    transform: scale(1);
    opacity: 1;
  }
}
.animate-scale-up {
  animation: scale-up 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
.animate-pulse-subtle {
  animation: pulse-subtle 2s infinite ease-in-out;
}
@keyframes pulse-subtle {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.8;
  }
}
</style>
