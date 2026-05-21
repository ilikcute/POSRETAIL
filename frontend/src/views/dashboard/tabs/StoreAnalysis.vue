<script setup>
import { ref } from 'vue'
import KpiCard from '../../../components/dashboard/KpiCard.vue'
import SvgBarChart from '../../../components/dashboard/charts/SvgBarChart.vue'
import SvgDonutChart from '../../../components/dashboard/charts/SvgDonutChart.vue'
import SvgLineChart from '../../../components/dashboard/charts/SvgLineChart.vue'

// Top Stores Data (horizontal bar)
const topStores = ref([
  { label: 'S0003 - Cronus Fashion Store N...', value: 62490 },
  { label: 'S0004 - Cronus Fashion Store S...', value: 14751 },
  { label: 'S0002 - Cronus Food Market No...', value: 1818 },
  { label: 'S0001 - Cronus Super Market S...', value: 1780 },
  { label: 'S0005 - Cronus Restaurant', value: 29 }
])

// Table Data
const storeDetails = ref([
  { id: 'S0003', name: 'Cronus Fashion Store North', sales: 62490, qty: 1040, receipt: 163 },
  { id: 'S0004', name: 'Cronus Fashion Store South', sales: 14751, qty: 307, receipt: 46 },
  { id: 'S0002', name: 'Cronus Food Market North SM', sales: 1818, qty: 1, receipt: 0 },
  { id: 'S0001', name: 'Cronus Super Market South', sales: 1780, qty: 16, receipt: 3 },
  { id: 'S0005', name: 'Cronus Restaurant', sales: 29, qty: 5, receipt: 1 }
])

// Weekly (Step Chart)
const weeklyTrend = ref([
  { label: 'Mon', value: 10000 },
  { label: 'Tue', value: 12000 },
  { label: 'Wed', value: 8000 },
  { label: 'Thu', value: 15000 },
  { label: 'Fri', value: 20000 },
  { label: 'Sat', value: 18000 },
  { label: 'Sun', value: 14000 }
])

// Hourly (Area)
const hourlyTrend = ref([
  { label: '09', value: 5000 },
  { label: '10', value: 8000 },
  { label: '11', value: 12000 },
  { label: '13', value: 20000 },
  { label: '14', value: 15000 },
  { label: '15', value: 12000 },
  { label: '16', value: 10000 }
])

// Sales Type Donut
const salesTypes = ref([
  { label: 'RESTAURANT', value: 2500, color: '#ab47bc' },
  { label: 'POS', value: 26880, color: '#66bb6a' }
])

// City Sales
const citySales = ref([
  { label: 'Bedford', value: 64000 },
  { label: 'London', value: 17000 }
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
      <KpiCard label="Stores" value="5" type="special" />
    </div>

    <!-- Row 2: Sales by Store + Weekly + Hourly -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- Sales by Store (spans 1) -->
      <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm flex flex-col">
        <h3 class="text-sm font-bold text-gray-800 mb-4">Sales by Store</h3>
        <SvgBarChart :data="topStores" type="horizontal" />
      </div>

      <!-- Weekly Sales Trend -->
      <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm flex flex-col">
        <h3 class="text-sm font-bold text-gray-800 mb-4">Weekly Sales Trend</h3>
        <div class="flex-1 flex items-end">
          <SvgLineChart :data="weeklyTrend" mode="step" color="#66bb6a" gradient-id="weekly-step" :height="150" />
        </div>
      </div>

      <!-- Hourly Sales Trend -->
      <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm flex flex-col">
        <h3 class="text-sm font-bold text-gray-800 mb-4">Hourly Sales Trend</h3>
        <div class="flex-1 flex items-end">
          <SvgLineChart :data="hourlyTrend" mode="area" color="#26a69a" gradient-id="hourly-area" :height="150" />
        </div>
      </div>
    </div>

    <!-- Row 3: Table + Sales Type + City -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- Store Detail Table -->
      <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-[11px] font-semibold text-gray-600">
            <thead>
              <tr class="border-b border-gray-200 text-gray-400 uppercase tracking-wider text-[9px]">
                <th class="pb-2">Store</th>
                <th class="pb-2 text-right">Sales</th>
                <th class="pb-2 text-right">Qty</th>
                <th class="pb-2 text-right">Receipt</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="store in storeDetails" :key="store.id" class="hover:bg-gray-50 transition-colors">
                <td class="py-2 text-gray-700">{{ store.id }} - {{ store.name }}</td>
                <td class="py-2 text-right">
                  <span class="bg-[#66bb6a] text-white text-[10px] font-bold px-2 py-0.5 rounded">{{ store.sales.toLocaleString() }}</span>
                </td>
                <td class="py-2 text-right">
                  <span class="bg-[#26a69a] text-white text-[10px] font-bold px-2 py-0.5 rounded">{{ store.qty }}</span>
                </td>
                <td class="py-2 text-right">
                  <span class="bg-[#ef9a9a] text-white text-[10px] font-bold px-2 py-0.5 rounded">{{ store.receipt }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Sales by Sales Type -->
      <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm flex flex-col">
        <h3 class="text-sm font-bold text-gray-800 mb-3">Sales by Sales Type</h3>
        <div class="flex-1 flex items-center justify-center">
          <SvgDonutChart :data="salesTypes" :size="120" />
        </div>
      </div>

      <!-- Sales by City -->
      <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm flex flex-col">
        <h3 class="text-sm font-bold text-gray-800 mb-4">Sales by City</h3>
        <div class="flex-1 flex items-end">
          <SvgBarChart :data="citySales" type="vertical" :height="140" />
        </div>
      </div>
    </div>
  </div>
</template>
