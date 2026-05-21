<script setup>
import { ref } from 'vue'
import KpiCard from '../../../components/dashboard/KpiCard.vue'
import SvgBarChart from '../../../components/dashboard/charts/SvgBarChart.vue'
import SvgLineChart from '../../../components/dashboard/charts/SvgLineChart.vue'

// Voided Sales Over Time (vertical bars with secondary: amount green + receipt pink line)
const voidedOverTime = ref([
  { label: 'Jan', value: 500, secondaryValue: 20 },
  { label: 'Feb', value: 100000, secondaryValue: 80 },
  { label: 'Mar', value: 8000, secondaryValue: 30 },
  { label: 'Apr', value: 5000, secondaryValue: 25 },
  { label: 'May', value: 3000, secondaryValue: 15 },
  { label: 'Jun', value: 4000, secondaryValue: 20 },
  { label: 'Jul', value: 3000, secondaryValue: 15 },
  { label: 'Aug', value: 5000, secondaryValue: 20 },
  { label: 'Sep', value: 2000, secondaryValue: 10 },
  { label: 'Oct', value: 3000, secondaryValue: 15 },
  { label: 'Nov', value: 2000, secondaryValue: 8 }
])

// Amount by Item (horizontal bar)
const amountByItem = ref([
  { label: '10050 - Gouda Cheese', value: 101000 },
  { label: '40050 - Jacket Linda Casual Wear', value: 4000 },
  { label: '40110 - Coat Davi-s Professional W...', value: 3000 },
  { label: '50000 - Briefcase, Leather', value: 3000 },
  { label: '40090 - Coat Tim-n Tina Wear', value: 2000 }
])

// Average Voided Sales Comparison (Line Chart)
const avgVoidTrend = ref([
  { label: 'Jan', value: 500 },
  { label: 'Feb', value: 2000 },
  { label: 'Mar', value: 800 },
  { label: 'Apr', value: 600 },
  { label: 'May', value: 500 },
  { label: 'Jun', value: 550 },
  { label: 'Jul', value: 400 },
  { label: 'Aug', value: 500 },
  { label: 'Sep', value: 350 },
  { label: 'Oct', value: 400 },
  { label: 'Nov', value: 300 }
])

// Detailed Voided Table
const voidDetails = ref([
  { item: '10050 - Gouda Cheese', sales: 101027, qty: 40411, receipt: 18, transaction: 18 },
  { item: '40050 - Jacket Linda Casual Wear', sales: 4320, qty: 27, receipt: 18, transaction: 18 },
  { item: '40110 - Coat Davi-s Professional W.', sales: 3240, qty: 36, receipt: 36, transaction: 36 },
  { item: '50000 - Briefcase, Leather', sales: 2880, qty: 36, receipt: 36, transaction: 36 },
  { item: '40090 - Coat Tim-n Tina Wear', sales: 2016, qty: 36, receipt: 36, transaction: 36 },
  { item: '40020 - Skirt Linda Professional Wear', sales: 1008, qty: 18, receipt: 18, transaction: 18 },
  { item: '40040 - Blouse Linda Professional Wear', sales: 693, qty: 63, receipt: 63, transaction: 63 }
])
</script>

<template>
  <div class="space-y-5">
    <!-- Row 1: KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
      <KpiCard label="Receipt" value="495" type="receipt" />
      <KpiCard label="Amount" value="116.906" type="amount" />
      <KpiCard label="Quantity" value="40.699" type="quantity" />
      <KpiCard label="Cost" value="86.148" type="cost" />
      <KpiCard label="Transactions" value="495" type="transactions" />
      <KpiCard label="Transactions %" value="26,37%" type="special" />
    </div>

    <!-- Row 2: Voided Sales Over Time + Amount by Item -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <!-- Voided Sales Over Time -->
      <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-bold text-gray-800">Voided Sales Over Time</h3>
          <div class="flex items-center space-x-3 text-[9px] font-bold text-gray-400 uppercase tracking-wider">
            <span class="flex items-center"><span class="w-2 h-2 rounded-full bg-[#66bb6a] mr-1.5"></span>Amount</span>
            <span class="flex items-center"><span class="w-2 h-2 rounded-full bg-[#66bb6a] mr-1.5"></span>Quantity</span>
            <span class="flex items-center"><span class="w-2 h-2 rounded-full bg-[#ef9a9a] mr-1.5"></span>Receipt</span>
          </div>
        </div>
        <SvgBarChart :data="voidedOverTime" type="vertical" :height="170" :has-secondary="true" />
      </div>

      <!-- Amount by Item -->
      <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm flex flex-col">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-bold text-gray-800">Amount by Item</h3>
          <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Change to Store</span>
        </div>
        <SvgBarChart :data="amountByItem" type="horizontal" />
      </div>
    </div>

    <!-- Row 3: Average Voided Sales Comparison + Detail Table -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <!-- Average Voided Sales Comparison -->
      <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm flex flex-col">
        <h3 class="text-sm font-bold text-gray-800 mb-4">Average Voided Sales Comparison</h3>
        <div class="flex-1 flex items-end">
          <SvgLineChart :data="avgVoidTrend" mode="area" color="#26a69a" gradient-id="void-avg" :height="160" />
        </div>
      </div>

      <!-- Void Detail Table -->
      <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse text-[11px] font-semibold text-gray-600">
            <thead>
              <tr class="border-b border-gray-200 text-gray-400 uppercase tracking-wider text-[9px]">
                <th class="pb-2">Item</th>
                <th class="pb-2 text-right">Sales</th>
                <th class="pb-2 text-right">Qty</th>
                <th class="pb-2 text-right">Receipt</th>
                <th class="pb-2 text-right">Transaction</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="row in voidDetails" :key="row.item" class="hover:bg-gray-50 transition-colors">
                <td class="py-2 text-gray-700 max-w-[160px] truncate">{{ row.item }}</td>
                <td class="py-2 text-right">
                  <span class="bg-[#66bb6a] text-white text-[10px] font-bold px-1.5 py-0.5 rounded">{{ row.sales.toLocaleString() }}</span>
                </td>
                <td class="py-2 text-right">
                  <span class="bg-[#26a69a] text-white text-[10px] font-bold px-1.5 py-0.5 rounded">{{ row.qty.toLocaleString() }}</span>
                </td>
                <td class="py-2 text-right">
                  <span class="bg-[#ef9a9a] text-white text-[10px] font-bold px-1.5 py-0.5 rounded">{{ row.receipt }}</span>
                </td>
                <td class="py-2 text-right">
                  <span class="bg-[#90caf9] text-white text-[10px] font-bold px-1.5 py-0.5 rounded">{{ row.transaction }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
