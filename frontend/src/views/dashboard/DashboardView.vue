<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../../store/auth'
import { useToast } from 'vue-toastification'

// Import Dashboard Components
import Sidebar from '../../components/dashboard/Sidebar.vue'
// Header component removed; using HorizontalTabBar as main navigation
import Footer from '../../components/dashboard/Footer.vue'
import HorizontalTabBar from '../../components/dashboard/HorizontalTabBar.vue'

// Import Tab Components
import SalesOverview from './tabs/SalesOverview.vue'
import StoreAnalysis from './tabs/StoreAnalysis.vue'
import ItemAnalysis from './tabs/ItemAnalysis.vue'
import VoidTransactions from './tabs/VoidTransactions.vue'
// Additional tabs may be imported as needed
// Other tab imports remain unchanged
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
import AccountTab from './tabs/AccountTab.vue'
import CashTransactionTab from './tabs/CashTransactionTab.vue'
import ConsignmentTaxTab from './tabs/ConsignmentTaxTab.vue'
import DebtLedgerTab from './tabs/DebtLedgerTab.vue'
import FinancialReportTab from './tabs/FinancialReportTab.vue'
import JournalEntryTab from './tabs/JournalEntryTab.vue'
import MonthEndTab from './tabs/MonthEndTab.vue'
import StockOpnameTab from './tabs/StockOpnameTab.vue'
import ProfileTab from './tabs/ProfileTab.vue'
import UserManagementTab from './tabs/UserManagementTab.vue'
import RoleManagementTab from './tabs/RoleManagementTab.vue'
const router = useRouter()
const toast = useToast()
const { currentUser } = useAuth()

const activeTab = ref('overview') // default to SalesOverview
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
    case 'Account':
      return AccountTab
    case 'Cash Transaction':
      return CashTransactionTab
    case 'Consigment Tax':
      return ConsignmentTaxTab
    case 'Debt Ledger':
      return DebtLedgerTab
    case 'Financial Report':
      return FinancialReportTab
    case 'Jurnal Entry':
      return JournalEntryTab
    case 'Month End':
      return MonthEndTab
    case 'Stock Opname':
      return StockOpnameTab
    case 'Profile':
      return ProfileTab
    case 'Role Management':
      return RoleManagementTab
    case 'Theme Settings':
    case 'Language Settings':
    case 'Currency Settings':
    case 'Company Settings':
    case 'Outlet Settings':
    case 'Branch Settings':
    case 'Company Profile':
    case 'Outlet Profile':
    case 'Department Profile':
    case 'Employee Profile':
      return SettingTab
    default:
      return SalesOverview
  }
})

</script>

<template>
  <div class="h-screen bg-[#eaf6f6] flex flex-col font-sans overflow-hidden">
  <!-- Main Layout with Sidebar, Vertical Tab Bar, and Content -->
  <div class="flex flex-1 overflow-hidden">
    <!-- Sidebar Navigation -->
    <Sidebar :active-tab="activeTab" :collapsed="sidebarCollapsed" @update:active-tab="activeTab = $event" @update:collapsed="sidebarCollapsed = $event" />

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
      <!-- Header (restricted to main area) -->
        <HorizontalTabBar :active-tab="activeTab" @update:activeTab="activeTab = $event" />

      <!-- Main Content -->
      <main class="flex-1 p-5 md:p-7 space-y-5">
        <Transition name="fade" mode="out-in">
          <component :is="currentTabComponent" :active-tab="activeTab" />
        </Transition>
      </main>
      <Footer />
    </div>
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
