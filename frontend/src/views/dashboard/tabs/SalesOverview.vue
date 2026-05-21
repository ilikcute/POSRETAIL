<script setup>
import { ref } from 'vue'
import KpiCard from '../../../components/dashboard/KpiCard.vue'
import SvgBarChart from '../../../components/dashboard/charts/SvgBarChart.vue'
import SvgDonutChart from '../../../components/dashboard/charts/SvgDonutChart.vue'

// Data matching Reference Image 1
const monthlySalesTrend = ref([
  { label: 'Jan', value: 7200, secondaryValue: 0 },
  { label: 'Feb', value: 8500, secondaryValue: 0 },
  { label: 'Mar', value: 6100, secondaryValue: 0 },
  { label: 'Apr', value: 7800, secondaryValue: 0 },
  { label: 'May', value: 7500, secondaryValue: 0 },
  { label: 'Jun', value: 6800, secondaryValue: 0 },
  { label: 'Jul', value: 9200, secondaryValue: 0 },
  { label: 'Aug', value: 13500, secondaryValue: 0 },
  { label: 'Sep', value: 16000, secondaryValue: 0 },
  { label: 'Oct', value: 12200, secondaryValue: 0 }
])

const monthlyPerformance = ref([
  { label: 'Jan', value: 7200 },
  { label: 'Feb', value: 8500 },
  { label: 'Mar', value: 6100 },
  { label: 'Apr', value: 7800 },
  { label: 'May', value: 7500 },
  { label: 'Jun', value: 6800 },
  { label: 'Jul', value: 9200 },
  { label: 'Aug', value: 13500 },
  { label: 'Sep', value: 16000 },
  { label: 'Oct', value: 12200 }
])

const topStores = ref([
  { label: 'S0003 - Cronus Fashion North', value: 62490 },
  { label: 'S0004 - Cronus Fashion South', value: 14751 },
  { label: 'S0002 - Cronus Food Market North', value: 1818 },
  { label: 'S0001 - Cronus Super Market South', value: 1780 },
  { label: 'S0005 - Cronus Restaurant', value: 29 }
])

const salesByTender = ref([
  { label: 'Cash', value: 53240, color: '#66bb6a' },
  { label: 'Customer Account', value: 6660, color: '#ab47bc' },
  { label: 'Cards', value: 20970, color: '#ffca28' }
])

const topItems = ref([
  { label: '40060 - Jacket Davi-s Professional', value: 16000 },
  { label: '40110 - Coat Davi-s Professional W..', value: 13000 },
  { label: '40050 - Jacket Linda Casual Wear', value: 10000 },
  { label: '40020 - Skirt Linda Professional Wear', value: 9000 },
  { label: '41030 - Boys Sweater', value: 6000 }
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
      <KpiCard label="Avg. Sales" value="379,46" type="special" />
    </div>

    <!-- Row 2: Monthly Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <!-- Monthly Sales Trend -->
      <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm">
        <div class="flex items-center justify-between mb-5">
          <div>
            <h3 class="text-sm font-bold text-gray-800">Monthly Sales Trend</h3>
            <div class="flex items-center space-x-3 mt-1 text-[9px] font-bold text-gray-400 uppercase tracking-wider">
              <span class="flex items-center"><span class="w-2 h-2 rounded-full bg-[#66bb6a] mr-1.5"></span>Current Year</span>
              <span class="flex items-center"><span class="w-2 h-2 rounded-full bg-gray-300 mr-1.5"></span>Last Year</span>
            </div>
          </div>
          <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Compare to Voided Amount</span>
        </div>
        <SvgBarChart :data="monthlySalesTrend" type="vertical" :height="170" />
      </div>

      <!-- Monthly Performance -->
      <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm">
        <div class="flex items-center justify-between mb-5">
          <div>
            <h3 class="text-sm font-bold text-gray-800">Monthly Performance</h3>
            <div class="flex items-center space-x-3 mt-1 text-[9px] font-bold text-gray-400 uppercase tracking-wider">
              <span class="flex items-center"><span class="w-2 h-2 rounded-full bg-[#66bb6a] mr-1.5"></span>Sales</span>
              <span class="flex items-center"><span class="w-2 h-2 rounded-full bg-[#ffca28] mr-1.5"></span>Cost</span>
              <span class="flex items-center"><span class="w-2 h-2 rounded-full bg-[#26a69a] mr-1.5"></span>Profit</span>
            </div>
          </div>
          <div class="text-right">
            <span class="block text-[10px] text-gray-400 font-bold uppercase tracking-wider">Profit %</span>
            <span class="text-base font-black text-[#26a69a]">100,00%</span>
          </div>
        </div>
        <SvgBarChart :data="monthlyPerformance" type="vertical" :height="170" />
      </div>
    </div>

    <!-- Row 3: Detail Charts -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
      <!-- Top 5 Stores -->
      <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm flex flex-col justify-between">
        <h3 class="text-sm font-bold text-gray-800 mb-5">Top 5 Stores by Sales</h3>
        <SvgBarChart :data="topStores" type="horizontal" />
      </div>

      <!-- Tender Type -->
      <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm flex flex-col justify-between">
        <h3 class="text-sm font-bold text-gray-800 mb-3">Sales by Tender Type</h3>
        <SvgDonutChart :data="salesByTender" />
      </div>

      <!-- Top 5 Items -->
      <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm flex flex-col justify-between">
        <h3 class="text-sm font-bold text-gray-800 mb-5">Top 5 Items by Sales</h3>
        <SvgBarChart :data="topItems" type="horizontal" />
      </div>
    </div>
  </div>
</template>
