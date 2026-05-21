<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import api from '../../../services/api'
import { useToast } from 'vue-toastification'

const toast = useToast()

// States
const products = ref([])
const racks = ref([])
const loading = ref(false)
const generating = ref(false)

// Selectors / Form Config
const selectionMode = ref('product') // 'product' or 'rack'
const selectedProductIds = ref([]) // Array of selected product IDs
const selectedRackId = ref('') // Selected Rack ID
const globalSize = ref('sedang') // Default size: 'besar', 'sedang', 'kecil'

// Active Promotion visual mock data (matches seeder GRANDOPENING26)
const activePromotion = ref({
  code: 'GRANDOPENING26',
  name: 'Grand Opening Diskon 20%',
  is_active: true,
  type: 'percentage',
  value: 20
})

// Printing Queue (CRUD State)
const printQueue = ref([])

// Error States
const errors = ref({
  selection: '',
  queue: ''
})

// Search query inside selectors
const productSearch = ref('')
const rackSearch = ref('')

// Filter products based on search
const filteredProductsList = computed(() => {
  if (!productSearch.value) return products.value
  const q = productSearch.value.toLowerCase().trim()
  return products.value.filter(p => 
    p.name.toLowerCase().includes(q) || 
    p.code.toLowerCase().includes(q) ||
    (p.sku && p.sku.toLowerCase().includes(q))
  )
})

// Fetch products & racks on mount
const fetchData = async () => {
  loading.value = true
  try {
    const [productsRes, racksRes] = await Promise.all([
      api.get('/products'),
      api.get('/racks')
    ])
    
    // Parse products
    if (productsRes.data && productsRes.data.data) {
      products.value = productsRes.data.data
    } else {
      products.value = productsRes.data || []
    }

    // Parse racks
    if (racksRes.data && racksRes.data.data) {
      racks.value = racksRes.data.data
    } else {
      racks.value = racksRes.data || []
    }
  } catch (error) {
    console.error('Error fetching Price Tag base data:', error)
    toast.error('Gagal memuat data dasar produk atau rak planogram.')
  } finally {
    loading.value = false
  }
}

// Helper to format currency
const formatCurrency = (val) => {
  if (val === null || val === undefined || val === '') return '-'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val)
}

// Add item(s) to printing queue (CREATE operation in queue CRUD)
const handleAddToQueue = () => {
  errors.value.selection = ''
  
  if (selectionMode.value === 'product') {
    if (selectedProductIds.value.length === 0) {
      errors.value.selection = 'Pilih minimal satu produk untuk dimasukkan ke antrean.'
      return
    }

    selectedProductIds.value.forEach(id => {
      const product = products.value.find(p => p.id === id)
      if (product) {
        // Avoid duplicate in queue, increment quantity if exists
        const existing = printQueue.value.find(item => item.product_id === product.id)
        if (existing) {
          existing.quantity += 1
        } else {
          printQueue.value.push({
            product_id: product.id,
            product_code: product.code,
            product_name: product.name,
            barcode: product.barcode || product.code,
            category_name: product.category?.name || 'Retail',
            rack_code: product.racks?.[0]?.code || 'DISPLAY',
            normal_price: parseFloat(product.price || 0),
            size: globalSize.value, // uses current selected global default size
            quantity: 1
          })
        }
      }
    })
    
    toast.success(`${selectedProductIds.value.length} produk ditambahkan ke antrean cetak.`)
    selectedProductIds.value = [] // Reset selection
  } else {
    // Adding by rack (Fetches all products inside the chosen rack)
    if (!selectedRackId.value) {
      errors.value.selection = 'Pilih rak planogram terlebih dahulu.'
      return
    }

    const rack = racks.value.find(r => r.id === parseInt(selectedRackId.value))
    if (!rack) return

    // Find products linked to this rack
    const rackProducts = products.value.filter(p => 
      p.racks && p.racks.some(r => r.id === rack.id)
    )

    if (rackProducts.length === 0) {
      toast.warning(`Tidak ada produk terpetakan pada rak ${rack.name} di planogram.`)
      return
    }

    let addedCount = 0
    rackProducts.forEach(product => {
      const existing = printQueue.value.find(item => item.product_id === product.id)
      if (existing) {
        existing.quantity += 1
      } else {
        printQueue.value.push({
          product_id: product.id,
          product_code: product.code,
          product_name: product.name,
          barcode: product.barcode || product.code,
          category_name: product.category?.name || 'Retail',
          rack_code: rack.code,
          normal_price: parseFloat(product.price || 0),
          size: globalSize.value,
          quantity: 1
        })
      }
      addedCount++
    })

    toast.success(`Berhasil menambahkan ${addedCount} produk dari Rak ${rack.code} ke antrean.`)
    selectedRackId.value = '' // Reset
  }
}

// Remove individual item from printing queue (DELETE operation in queue CRUD)
const handleRemoveFromQueue = (productId) => {
  printQueue.value = printQueue.value.filter(item => item.product_id !== productId)
  toast.info('Produk dihapus dari antrean cetak.')
}

// Update properties in queue directly (UPDATE operation in queue CRUD)
const updateQueueItem = (productId, field, value) => {
  const item = printQueue.value.find(q => q.product_id === productId)
  if (item) {
    if (field === 'quantity') {
      const val = parseInt(value)
      item.quantity = isNaN(val) || val < 1 ? 1 : val
    } else if (field === 'size') {
      item.size = value
    }
  }
}

// Clear all items in queue
const clearQueue = () => {
  if (printQueue.value.length === 0) return
  if (confirm('Apakah Anda yakin ingin mengosongkan seluruh antrean cetak Price Tag?')) {
    printQueue.value = []
    toast.info('Antrean cetak berhasil dikosongkan.')
  }
}

// Helper to calculate promotional price dynamically in preview
const getPromoDetails = (item) => {
  let isPromoActive = false
  let promoPrice = null
  let promoDiscountText = null

  // Special visual demo for Chitato matching database controller
  const isChitato = item.product_name.toLowerCase().includes('chitato')
  
  if (activePromotion.value && activePromotion.value.is_active) {
    // If it is Chitato or we simulate promotion, apply 20%
    if (isChitato || item.product_id % 3 === 0) {
      isPromoActive = true
      const discount = activePromotion.value.value
      promoPrice = item.normal_price * (1 - (discount / 100))
      promoDiscountText = `DISC ${discount}%`
    }
  }

  return {
    isPromoActive,
    promoPrice,
    promoDiscountText
  }
}

// Client-side print validation
const validatePrint = () => {
  errors.value.queue = ''
  if (printQueue.value.length === 0) {
    errors.value.queue = 'Antrean cetak kosong! Tambahkan beberapa produk terlebih dahulu.'
    return false
  }
  return true
}

// Axios Submit & Launch printable window
const triggerPrint = () => {
  if (!validatePrint()) return

  generating.value = true

  // Group queue by size to open separate printable tabs for clean A4 alignment
  const sizesInQueue = [...new Set(printQueue.value.map(item => item.size))]

  try {
    sizesInQueue.forEach(size => {
      // Filter items belonging to this size
      const sizeItems = printQueue.value.filter(item => item.size === size)
      
      // Map to comma-separated product ids, respecting duplicates by sending repeated IDs or using quantity
      const ids = []
      sizeItems.forEach(item => {
        for (let i = 0; i < item.quantity; i++) {
          ids.push(item.product_id)
        }
      })

      const productIdsParam = ids.join(',')
      
      // Open Laravel print route in a new window/tab
      const printUrl = `/price-tags/print?product_ids=${productIdsParam}&size=${size}`
      window.open(printUrl, '_blank')
    })
    
    toast.success('Halaman cetak berhasil dibuka di tab baru!')
  } catch (error) {
    console.error('Error generating print tags:', error)
    toast.error('Gagal memproses antrean cetak.')
  } finally {
    generating.value = false
  }
}

// Synchronize global size toggles to all queued items for fast batch updates
const syncGlobalSizeToQueue = () => {
  printQueue.value.forEach(item => {
    item.size = globalSize.value
  })
  toast.success(`Ukuran seluruh item di antrean disesuaikan menjadi: ${globalSize.value.toUpperCase()}`)
}

onMounted(() => {
  fetchData()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header Summary Card -->
    <div class="bg-white/80 backdrop-blur-md rounded-2xl p-6 shadow-sm border border-slate-100 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h2 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-indigo-500">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-12v.75m0 3v.75m0 3v.75m0 3V18M3 8.25a2.25 2.25 0 0 1 2.25-2.25h13.5a2.25 2.25 0 0 1 2.25 2.25v7.5a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15.75V8.25z" />
          </svg>
          Master Price Tag Generator
        </h2>
        <p class="text-sm text-slate-500 mt-1">
          Buat dan cetak label harga (shelf talkers) interaktif berukuran Besar (Self-Taker), Sedang (Rak Besar), atau Kecil (Rak Kecil) berdasarkan produk dan lokasi planogram rak.
        </p>
      </div>

      <!-- Quick metadata stats -->
      <div class="flex gap-4">
        <div class="bg-indigo-50 border border-indigo-100/50 rounded-xl px-4 py-2 text-center min-w-[100px]">
          <div class="text-xs text-indigo-600 font-medium uppercase tracking-wider">Antrean Cetak</div>
          <div class="text-lg font-bold text-indigo-800">{{ printQueue.length }} Item</div>
        </div>
        <div class="bg-amber-50 border border-amber-100/50 rounded-xl px-4 py-2 text-center min-w-[100px]">
          <div class="text-xs text-amber-600 font-medium uppercase tracking-wider">Promo Aktif</div>
          <div class="text-xs font-bold text-amber-800 mt-1 truncate max-w-[120px]">{{ activePromotion.code }}</div>
        </div>
      </div>
    </div>

    <!-- Main Workspace Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
      
      <!-- Left Panel: Configurations & Queue CRUD Table (lg:col-span-7) -->
      <div class="lg:col-span-7 space-y-6">
        
        <!-- Queue Adder Form Card -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 space-y-4">
          <h3 class="text-base font-semibold text-slate-800 flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
            <span>Konfigurasi Label & Penambahan Antrean</span>
          </h3>

          <!-- Selection Mode Switcher -->
          <div class="grid grid-cols-2 gap-2 bg-slate-50 p-1 rounded-xl">
            <button
              type="button"
              @click="selectionMode = 'product'"
              class="py-2 text-xs font-bold rounded-lg transition-all cursor-pointer text-center"
              :class="selectionMode === 'product' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
            >
              Pilih per Produk
            </button>
            <button
              type="button"
              @click="selectionMode = 'rack'"
              class="py-2 text-xs font-bold rounded-lg transition-all cursor-pointer text-center"
              :class="selectionMode === 'rack' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
            >
              Pilih per Rak (Planogram)
            </button>
          </div>

          <!-- Product Multiselect Dropdown Mode -->
          <div v-if="selectionMode === 'product'" class="space-y-2">
            <label class="block text-xs font-semibold text-slate-600">Pilih Produk Ritel</label>
            <div class="flex gap-2">
              <div class="relative flex-1">
                <select
                  v-model="selectedProductIds"
                  multiple
                  class="w-full text-sm bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-3 text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all min-h-[120px]"
                >
                  <option v-for="prod in filteredProductsList" :key="prod.id" :value="prod.id">
                    {{ prod.code }} - {{ prod.name }} ({{ formatCurrency(prod.price) }})
                  </option>
                </select>
              </div>

              <!-- Quick Search Filter panel -->
              <div class="w-48 space-y-2">
                <input
                  v-model="productSearch"
                  type="text"
                  placeholder="Cari produk..."
                  class="w-full text-xs bg-slate-50 border border-slate-200 px-3 py-2.5 rounded-xl text-slate-700 placeholder:text-slate-400 focus:outline-none focus:border-indigo-500"
                />
                <div class="text-[10px] text-slate-400 bg-slate-50 p-2 rounded-lg border border-slate-100">
                  <span class="font-bold">Tips:</span> Tekan <kbd class="bg-white border px-1 rounded">Ctrl</kbd> untuk memilih beberapa produk sekaligus.
                </div>
              </div>
            </div>
            <p v-if="errors.selection" class="text-[11px] text-red-500 font-medium">{{ errors.selection }}</p>
          </div>

          <!-- Rack Selector Mode -->
          <div v-else class="space-y-2">
            <label class="block text-xs font-semibold text-slate-600">Pilih Rak Planogram Utama</label>
            <div class="flex gap-2">
              <select
                v-model="selectedRackId"
                class="flex-1 text-sm bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-3 text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all cursor-pointer"
              >
                <option value="" disabled>-- Pilih Rak Lokasi Produk --</option>
                <option v-for="rack in racks" :key="rack.id" :value="rack.id">
                  {{ rack.code }} - {{ rack.name }} (Gudang: {{ rack.warehouse?.name || 'Unknown' }})
                </option>
              </select>
            </div>
            <p v-if="errors.selection" class="text-[11px] text-red-500 font-medium">{{ errors.selection }}</p>
          </div>

          <!-- Global Size Preset & Insertion Controls -->
          <div class="flex flex-wrap items-center justify-between gap-4 pt-2 border-t border-slate-100">
            <!-- Selector Default Size -->
            <div class="flex items-center gap-3">
              <span class="text-xs font-semibold text-slate-600">Ukuran Standar:</span>
              <div class="flex bg-slate-100 p-1 rounded-lg">
                <button
                  v-for="sz in ['besar', 'sedang', 'kecil']"
                  :key="sz"
                  type="button"
                  @click="globalSize = sz"
                  class="px-3 py-1 text-[10px] font-bold rounded uppercase transition-all cursor-pointer"
                  :class="globalSize === sz ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-400 hover:text-slate-600'"
                >
                  {{ sz }}
                </button>
              </div>
            </div>

            <!-- Action Buttons to Insert -->
            <div class="flex gap-2">
              <button
                v-if="printQueue.length > 0"
                type="button"
                @click="syncGlobalSizeToQueue"
                class="text-indigo-600 hover:text-indigo-700 bg-indigo-50 hover:bg-indigo-100/80 px-4 py-2 rounded-xl text-xs font-semibold transition-all cursor-pointer"
                title="Terapkan ukuran terpilih ke semua item di antrean"
              >
                Sesuaikan Semua
              </button>
              <button
                type="button"
                @click="handleAddToQueue"
                class="bg-indigo-500 hover:bg-indigo-600 text-white px-5 py-2.5 rounded-xl text-xs font-bold shadow-md shadow-indigo-100 hover:shadow-lg transition-all cursor-pointer flex items-center gap-1.5"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambahkan Ke Antrean
              </button>
            </div>
          </div>
        </div>

        <!-- Printing Queue CRUD Table -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-800 flex items-center gap-2">
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
              <span>Daftar Antrean Cetak Price Tag (CRUD)</span>
            </h3>
            <button
              v-if="printQueue.length > 0"
              type="button"
              @click="clearQueue"
              class="text-red-500 hover:text-red-600 hover:bg-red-50 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer"
            >
              Kosongkan Antrean
            </button>
          </div>

          <!-- Queue Table Layout -->
          <div class="overflow-x-auto border border-slate-100 rounded-xl">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-slate-50/50 text-slate-600 border-b border-slate-100 text-[10px] uppercase font-bold tracking-wider">
                  <th class="p-3">Info Produk</th>
                  <th class="p-3 text-center">Ukuran Label</th>
                  <th class="p-3 text-center">Jumlah Cetak</th>
                  <th class="p-3 text-right">Harga Normal</th>
                  <th class="p-3 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 text-slate-700 text-xs">
                <tr v-if="printQueue.length === 0" class="hover:bg-slate-50/20">
                  <td colspan="5" class="p-10 text-center text-slate-400 italic">
                    Belum ada produk dalam antrean cetak label harga. Pilih produk di atas lalu klik "Tambahkan ke Antrean".
                  </td>
                </tr>
                <tr v-else v-for="item in printQueue" :key="item.product_id" class="hover:bg-slate-50/25 transition-colors">
                  <!-- Product Profile details -->
                  <td class="p-3">
                    <div class="font-semibold text-slate-800 text-xs">{{ item.product_name }}</div>
                    <div class="text-[10px] text-indigo-600 font-medium mt-0.5">
                      {{ item.product_code }} | Rak: {{ item.rack_code }}
                    </div>
                  </td>

                  <!-- Size Form Input Dropdown -->
                  <td class="p-3 text-center">
                    <select
                      :value="item.size"
                      @change="updateQueueItem(item.product_id, 'size', $event.target.value)"
                      class="bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-slate-700 cursor-pointer focus:outline-none focus:border-indigo-500 text-xs font-semibold"
                    >
                      <option value="besar">BESAR (Self Taker)</option>
                      <option value="sedang">SEDANG (Rak Besar)</option>
                      <option value="kecil">KECIL (Rak Kecil)</option>
                    </select>
                  </td>

                  <!-- Quantity input (Update operation) -->
                  <td class="p-3 text-center">
                    <div class="flex items-center justify-center gap-1">
                      <button
                        type="button"
                        @click="updateQueueItem(item.product_id, 'quantity', item.quantity - 1)"
                        class="w-5 h-5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-bold text-xs"
                      >
                        -
                      </button>
                      <input
                        type="number"
                        :value="item.quantity"
                        @change="updateQueueItem(item.product_id, 'quantity', $event.target.value)"
                        class="w-10 text-center text-xs bg-slate-50 border border-slate-200 rounded py-0.5 text-slate-700 font-semibold"
                        min="1"
                      />
                      <button
                        type="button"
                        @click="updateQueueItem(item.product_id, 'quantity', item.quantity + 1)"
                        class="w-5 h-5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center font-bold text-xs"
                      >
                        +
                      </button>
                    </div>
                  </td>

                  <!-- Normal base price -->
                  <td class="p-3 text-right font-medium text-slate-800">
                    {{ formatCurrency(item.normal_price) }}
                  </td>

                  <!-- Delete queue item (Delete operation) -->
                  <td class="p-3 text-center">
                    <button
                      type="button"
                      @click="handleRemoveFromQueue(item.product_id)"
                      class="p-1 rounded-lg border border-slate-200 text-slate-400 hover:text-red-600 hover:border-red-200 hover:bg-red-50 transition-all cursor-pointer"
                      title="Hapus dari antrean"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                      </svg>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Bottom trigger and feedback warnings -->
          <div class="space-y-2 pt-2">
            <p v-if="errors.queue" class="text-xs text-red-500 font-semibold bg-red-50 p-2 rounded-lg border border-red-100">{{ errors.queue }}</p>
            
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
              <div class="text-xs text-slate-500">
                <span v-if="printQueue.length > 0">
                  Akan mencetak total <span class="font-bold text-slate-700">{{ printQueue.reduce((acc, curr) => acc + curr.quantity, 0) }} salinan</span> label harga.
                </span>
              </div>
              <button
                type="button"
                @click="triggerPrint"
                :disabled="generating || printQueue.length === 0"
                class="w-full sm:w-auto bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-600 hover:to-teal-700 text-white px-7 py-3 rounded-xl text-xs font-bold shadow-md shadow-emerald-100 hover:shadow-lg transition-all cursor-pointer flex items-center justify-center gap-2"
                :class="{ 'opacity-50 cursor-not-allowed': printQueue.length === 0 }"
              >
                <svg v-if="generating" class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.865 48.865 0 0 0-14.325 0C3.768 7.44 3 8.375 3 9.456v6.294a2.25 2.25 0 0 0 2.24 2.25h1.092m12-10.5-1.21-2.903A1.125 1.125 0 0 0 16.083 4.5H7.917a1.125 1.125 0 0 0-1.017.677L5.69 8.083m12.62 0L17.25 9.456M5.69 8.083 6.75 9.456M8.25 10.5h8.25" />
                </svg>
                <span>GENERATE & CETAK SEKARANG</span>
              </button>
            </div>
          </div>
        </div>

      </div>

      <!-- Right Panel: Live Visual A4 Sheet Layout Preview (lg:col-span-5) -->
      <div class="lg:col-span-5 space-y-6">
        
        <!-- Live Layout Preview Wrapper -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-slate-800 flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-indigo-500 animate-pulse"></span>
              <span>Visual A4 Sheet Preview (Kertas Label)</span>
            </h3>
            <span class="text-[10px] bg-slate-100 text-slate-600 px-2 py-0.5 rounded font-bold uppercase">A4 Paper Grid</span>
          </div>

          <p class="text-xs text-slate-500">
            Pratinjau skala label harga secara real-time di bawah. Penempatan layout kolom otomatis menyesuaikan skala ukuran yang dipilih.
          </p>

          <!-- Interactive preview canvas simulating the exact printable page -->
          <div class="bg-[#f8fafc] border border-slate-200 rounded-2xl p-5 shadow-inner min-h-[400px] flex flex-col justify-start">
            
            <div v-if="printQueue.length === 0" class="flex-1 flex flex-col items-center justify-center text-center p-8 space-y-3">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 text-slate-300">
                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1.75 0Z" />
              </svg>
              <div class="text-xs text-slate-400 font-medium">Antrean kosong. Masukkan item untuk melihat simulasi cetak label.</div>
            </div>

            <!-- Dynamic grid container based on global default size size preview -->
            <div v-else class="space-y-4 w-full">
              <!-- Header tag indicating size sheet style -->
              <div class="text-[10px] text-slate-400 font-bold uppercase tracking-wider text-center border-b border-dashed border-slate-200 pb-2">
                Tata Letak Kolom: {{ globalSize === 'besar' ? '1 Kolom (Besar)' : globalSize === 'sedang' ? '2 Kolom (Sedang)' : '3 Kolom (Kecil)' }}
              </div>

              <!-- Simulating layout grids -->
              <div 
                class="grid gap-3"
                :class="[
                  globalSize === 'besar' ? 'grid-cols-1' :
                  globalSize === 'sedang' ? 'grid-cols-2' :
                  'grid-cols-3'
                ]"
              >
                <!-- Render simulated price cards -->
                <div
                  v-for="item in printQueue"
                  :key="item.product_id"
                  class="bg-white border rounded-xl shadow-sm relative overflow-hidden flex flex-col justify-between transition-all duration-200"
                  :class="[
                    getPromoDetails(item).isPromoActive ? 'border-red-500 bg-amber-50/20' : 'border-slate-200',
                    globalSize === 'besar' ? 'p-5 min-h-[160px]' :
                    globalSize === 'sedang' ? 'p-3.5 min-h-[120px]' :
                    'p-2.5 min-h-[90px]'
                  ]"
                >
                  <!-- Active Promo Red Banner tag -->
                  <div
                    v-if="getPromoDetails(item).isPromoActive"
                    class="absolute top-0 left-0 right-0 bg-red-500 text-white font-extrabold text-center uppercase tracking-widest"
                    :class="[
                      globalSize === 'besar' ? 'text-[10px] py-1' :
                      globalSize === 'sedang' ? 'text-[8px] py-0.5' :
                      'text-[6px] py-0.5'
                    ]"
                  >
                    {{ getPromoDetails(item).promoDiscountText }}
                  </div>

                  <!-- Tag Category & Unit Header -->
                  <div 
                    class="flex justify-between items-center text-slate-400 uppercase font-bold"
                    :class="[
                      globalSize === 'besar' ? 'text-[10px] mt-3' :
                      globalSize === 'sedang' ? 'text-[8px] mt-2' :
                      'text-[7px]'
                    ]"
                  >
                    <span>{{ item.category_name }}</span>
                    <span>/ {{ item.size.toUpperCase() }}</span>
                  </div>

                  <!-- Product Name -->
                  <div 
                    class="font-bold text-slate-800 leading-tight truncate mt-1"
                    :class="[
                      globalSize === 'besar' ? 'text-sm' :
                      globalSize === 'sedang' ? 'text-xs' :
                      'text-[10px]'
                    ]"
                    :title="item.product_name"
                  >
                    {{ item.product_name }}
                  </div>

                  <!-- Pricing display (Normal vs Promo active) -->
                  <div class="mt-1 flex flex-col justify-center">
                    <div v-if="getPromoDetails(item).isPromoActive" class="flex flex-col">
                      <span 
                        class="text-slate-400 line-through font-semibold leading-none"
                        :class="[
                          globalSize === 'besar' ? 'text-xs' :
                          globalSize === 'sedang' ? 'text-[9px]' :
                          'text-[8px]'
                        ]"
                      >
                        {{ formatCurrency(item.normal_price) }}
                      </span>
                      <span 
                        class="text-red-500 font-extrabold leading-tight mt-0.5"
                        :class="[
                          globalSize === 'besar' ? 'text-xl' :
                          globalSize === 'sedang' ? 'text-sm' :
                          'text-xs'
                        ]"
                      >
                        {{ formatCurrency(getPromoDetails(item).promoPrice) }}
                      </span>
                    </div>
                    <span 
                      v-else 
                      class="text-slate-900 font-bold leading-tight"
                      :class="[
                        globalSize === 'besar' ? 'text-lg' :
                        globalSize === 'sedang' ? 'text-sm' :
                        'text-xs'
                      ]"
                    >
                      {{ formatCurrency(item.normal_price) }}
                    </span>
                  </div>

                  <!-- Footer: Barcodes & Shelf Location badges -->
                  <div 
                    class="border-t border-dashed border-slate-100 pt-1.5 mt-2 flex justify-between items-center"
                    :class="[
                      globalSize === 'besar' ? 'mt-3' :
                      globalSize === 'sedang' ? 'mt-2' :
                      'mt-1'
                    ]"
                  >
                    <!-- Simulated Barcode Lines -->
                    <div class="flex flex-col items-start">
                      <div 
                        class="bg-slate-800"
                        :class="[
                          globalSize === 'besar' ? 'w-16 h-4' :
                          globalSize === 'sedang' ? 'w-10 h-3' :
                          'w-8 h-2.5'
                        ]"
                        style="background: repeating-linear-gradient(90deg, #1e293b, #1e293b 2px, transparent 2px, transparent 4px);"
                      ></div>
                      <span 
                        class="text-slate-500 font-mono scale-90 origin-left"
                        :class="[
                          globalSize === 'besar' ? 'text-[8px] mt-0.5' :
                          globalSize === 'sedang' ? 'text-[7px]' :
                          'text-[6px]'
                        ]"
                      >
                        {{ item.barcode }}
                      </span>
                    </div>

                    <!-- Shelf code badge -->
                    <span 
                      class="bg-slate-700 text-white font-bold rounded uppercase text-center"
                      :class="[
                        globalSize === 'besar' ? 'text-[9px] px-2 py-0.5' :
                        globalSize === 'sedang' ? 'text-[7px] px-1.5 py-0.5' :
                        'text-[6px] px-1 py-0.25'
                      ]"
                    >
                      {{ item.rack_code }}
                    </span>
                  </div>

                </div>
              </div>
            </div>

          </div>
        </div>

      </div>

    </div>
  </div>
</template>

<style scoped>
/* Standard print custom styles overrides */
input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
  -webkit-appearance: none;
  margin: 0;
}
input[type="number"] {
  -moz-appearance: textfield;
}
</style>
