<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../../store/auth'
import { useToast } from 'vue-toastification'

// Import Dashboard Components
import Sidebar from '../../components/dashboard/Sidebar.vue'
import Header from '../../components/dashboard/Header.vue'
import Footer from '../../components/dashboard/Footer.vue'

// Import Tab Components
import SalesOverview from './tabs/SalesOverview.vue'
import StoreAnalysis from './tabs/StoreAnalysis.vue'
import ItemAnalysis from './tabs/ItemAnalysis.vue'
import VoidTransactions from './tabs/VoidTransactions.vue'
import BrandTab from './tabs/BrandTab.vue'
import CategoryTab from './tabs/CategoryTab.vue'
import CustomerTab from './tabs/CustomerTab.vue'
import UnitTab from './tabs/UnitTab.vue'
import SupplierTab from './tabs/SupplierTab.vue'
import StationTab from './tabs/StationTab.vue'
import WarehouseTab from './tabs/WarehouseTab.vue'
import StoreTab from './tabs/StoreTab.vue'
import RackTab from './tabs/RackTab.vue'
import ProductTab from './tabs/ProductTab.vue'
import ProductVariantTab from './tabs/ProductVariantTab.vue'
import PriceTagTab from './tabs/PriceTagTab.vue'
import InventoryTab from './tabs/InventoryTab.vue'
import InventoryIntelligenceTab from './tabs/InventoryIntelligenceTab.vue'
import AutoReplenishmentTab from './tabs/AutoReplenishmentTab.vue'
import StockDisposalTab from './tabs/StockDisposalTab.vue'
import ProductStockTab from './tabs/ProductStockTab.vue'
import PurchaseTab from './tabs/PurchaseTab.vue'
import ShiftTab from './tabs/ShiftTab.vue'
import DailyCloseTab from './tabs/DailyCloseTab.vue'
import PromotionTab from './tabs/PromotionTab.vue'
import CashPullTab from './tabs/CashPullTab.vue'
import LoyaltyTransactionTab from './tabs/LoyaltyTransactionTab.vue'
import PricingSafeguardTab from './tabs/PricingSafeguardTab.vue'
import SalesTab from './tabs/SalesTab.vue'
import StationRemittanceTab from './tabs/StationRemittanceTab.vue'


const router = useRouter()
const toast = useToast()
const { currentUser } = useAuth()

const activeTab = ref('overview')
const sidebarCollapsed = ref(false)

const currentTabComponent = computed(() => {
  switch (activeTab.value) {
    case 'overview':
      return SalesOverview
    case 'store':
      return StoreAnalysis
    case 'item':
      return ItemAnalysis
    case 'void':
      return VoidTransactions
    case 'Brand':
      return BrandTab
    case 'Category':
      return CategoryTab
    case 'Customer':
      return CustomerTab
    case 'Unit':
      return UnitTab
    case 'Supplier':
      return SupplierTab
    case 'Stations':
      return StationTab
    case 'Warehouse':
      return WarehouseTab
    case 'Store':
      return StoreTab
    case 'Rack':
      return RackTab
    case 'Product':
      return ProductTab
    case 'product Variance':
      return ProductVariantTab
    case 'Price Tag':
      return PriceTagTab
    case 'Inventory':
      return InventoryTab
    case 'Stock Disposal':
      return StockDisposalTab
    case 'Auto Replenishment':
      return AutoReplenishmentTab
    case 'Inventory Intelligent':
      return InventoryIntelligenceTab
    case 'Product Stock':
      return ProductStockTab
    case 'Shift':
      return ShiftTab
    case 'Daily CLose':
      return DailyCloseTab
    case 'Promotion':
      return PromotionTab
    case 'Cash Pull':
      return CashPullTab
    case 'Loyality Transaksi':
      return LoyaltyTransactionTab
    case 'Pricing Safe guard':
      return PricingSafeguardTab
    case 'Sales':
      return SalesTab
    case 'Purchases':
      return PurchaseTab
    case 'Station Remittance':
      return StationRemittanceTab
    default:
      return SalesOverview
  }
})

</script>

<template>
  <div class="h-screen bg-[#eaf6f6] flex flex-row font-sans overflow-hidden">
    <!-- Sidebar Navigation (collapsible) -->
    <Sidebar
      :active-tab="activeTab"
      :collapsed="sidebarCollapsed"
      @update:active-tab="activeTab = $event"
      @update:collapsed="sidebarCollapsed = $event"
    />
    
    <!-- Right Main Area -->
    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
      <!-- Header -->
      <Header :active-tab="activeTab" />

      <!-- Main Content -->
      <main class="flex-1 p-5 md:p-7 space-y-5">
        <!-- Tab Content with Transitions -->
        <Transition name="fade" mode="out-in">
          <component :is="currentTabComponent" />
        </Transition>
      </main>

      <!-- Footer -->
      <Footer />
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.fade-enter-from {
  opacity: 0;
  transform: translateY(8px);
}
.fade-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
