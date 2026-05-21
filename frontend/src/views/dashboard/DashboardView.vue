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
    default:
      return SalesOverview
  }
})
</script>

<template>
  <div class="min-h-screen bg-[#eaf6f6] flex flex-row font-sans overflow-hidden">
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
