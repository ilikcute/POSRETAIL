<script setup>
import { ref } from 'vue'
import KpiCard from '../../../components/dashboard/KpiCard.vue'
import SvgBarChart from '../../../components/dashboard/charts/SvgBarChart.vue'
import SvgDonutChart from '../../../components/dashboard/charts/SvgDonutChart.vue'

// Top Selling Items (15 items horizontal bar)
const topItemsSales = ref([
  { label: '40060 - Jacket Davi-s Profession...', value: 16000 },
  { label: '40110 - Coat Davi-s Professional...', value: 13000 },
  { label: '40050 - Jacket Linda Casual Wear', value: 10000 },
  { label: '40020 - Skirt Linda Professional ...', value: 9000 },
  { label: '41030 - Boys Sweater', value: 6000 },
  { label: '40130 - Velvet Jacket Tim-n Tina ...', value: 4000 },
  { label: '40070 - Pants Davi-s Professiona...', value: 4000 },
  { label: '42030 - Girls Sweater', value: 3000 },
  { label: '40090 - Coat Tim-n Tina Wear', value: 3000 },
  { label: '40080 - Pants Boys Tim-n Tina W...', value: 2000 },
  { label: '43120 - Moccasins Davi-s Casual', value: 2000 },
  { label: '10080 - Yogurt Lowfat Strawberry', value: 2000 },
  { label: '40140 - Shorts Davi-s Profession...', value: 2000 }
])

// Sales by Item Category (horizontal bar)
const categorySales = ref([
  { label: 'Clothing', value: 77000 },
  { label: 'Dairy Products', value: 2000 },
  { label: 'Office Furniture', value: 2000 },
  { label: 'Stationary', value: 0 },
  { label: 'Food', value: 0 },
  { label: 'Beverages', value: 0 }
])

// Sales by Product Group (horizontal bar)
const productGroupSales = ref([
  { label: "Men-s clothing", value: 34000 },
  { label: "Women-s Clothi...", value: 20000 },
  { label: "Children-s Clot...", value: 18000 },
  { label: "Shoes", value: 3000 },
  { label: "Accessories", value: 2000 },
  { label: "Yogurt", value: 2000 }
])

// Sales by Division (Donut)
const divisionSales = ref([
  { label: 'Food Items', value: 79010, color: '#42a5f5' },
  { label: 'Nonfood Items', value: 1860, color: '#ef5350' }
])

// Unsold Items List
const unsoldItems = ref([
  { id: '1001', name: 'Touring Bicycle' },
  { id: '40000', name: 'Jeans Linda line' }
])
</script>

<template>
  <div class="space-y-5">
    <!-- Row 1: KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
      <KpiCard label="Receipt" value="213" type="receipt" />
      <KpiCard label="Sales" value="80.868" type="sales" />
      <KpiCard label="Quantity" value="1.369" type="quantity" />
      <KpiCard label="Cost" value="0" type="cost" />
      <KpiCard label="Profit" value="80.868" type="profit" />
      <KpiCard label="Unsold Items %" value="5,71%" type="special" />
    </div>

    <!-- Row 2: Sales by Item, Category, Product Group -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- Sales by Item (Main tall list) -->
      <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm flex flex-col">
        <h3 class="text-sm font-bold text-gray-800 mb-4">Sales by Item</h3>
        <SvgBarChart :data="topItemsSales" type="horizontal" />
      </div>

      <!-- Sales by Item Category -->
      <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm flex flex-col">
        <h3 class="text-sm font-bold text-gray-800 mb-4">Sales by Item Category</h3>
        <SvgBarChart :data="categorySales" type="horizontal" />
      </div>

      <!-- Sales by Product Group -->
      <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm flex flex-col">
        <h3 class="text-sm font-bold text-gray-800 mb-4">Sales by Product Group</h3>
        <SvgBarChart :data="productGroupSales" type="horizontal" />
      </div>
    </div>

    <!-- Row 3: Division Donut + Unsold Items -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <!-- Sales by Division -->
      <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm flex flex-col">
        <h3 class="text-sm font-bold text-gray-800 mb-3">Sales by Division</h3>
        <div class="flex items-center space-x-3 mb-3 text-[9px] font-bold text-gray-400 uppercase tracking-wider">
          <span class="flex items-center"><span class="w-2 h-2 rounded-full bg-[#42a5f5] mr-1.5"></span>Food Items</span>
          <span class="flex items-center"><span class="w-2 h-2 rounded-full bg-[#ef5350] mr-1.5"></span>Nonfood Items</span>
        </div>
        <div class="flex-1 flex items-center justify-center">
          <SvgDonutChart :data="divisionSales" :size="140" />
        </div>
      </div>

      <!-- Unsold Items -->
      <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm flex flex-col">
        <h3 class="text-sm font-bold text-gray-800 mb-1">Unsold Items</h3>
        <p class="text-xs text-gray-400 mb-4">2 out of 35 items are unsold</p>
        <div class="space-y-3">
          <div v-for="item in unsoldItems" :key="item.id" class="flex items-center space-x-3 p-3 bg-gray-50 rounded-xl border border-gray-100">
            <div class="w-8 h-8 bg-amber-100 text-amber-600 rounded-lg flex items-center justify-center flex-shrink-0">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
              </svg>
            </div>
            <div>
              <p class="text-sm font-bold text-gray-700">{{ item.id }} - {{ item.name }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
