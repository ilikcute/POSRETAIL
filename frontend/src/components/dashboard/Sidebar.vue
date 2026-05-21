<script setup>
import { useAuth } from '../../store/auth'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'

const props = defineProps({
  activeTab: {
    type: String,
    required: true
  },
  collapsed: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:activeTab', 'update:collapsed'])

const { currentUser, logout } = useAuth()
const router = useRouter()
const toast = useToast()

const tabs = [
  { id: 'overview', icon: 'home', title: 'Sales Overview' },
  { id: 'store', icon: 'store', title: 'Store Analysis' },
  { id: 'item', icon: 'bag', title: 'Item Analysis' },
  { id: 'void', icon: 'document', title: 'Voided Transactions' }
]

const setTab = (tabId) => {
  emit('update:activeTab', tabId)
}

const toggleCollapse = () => {
  emit('update:collapsed', !props.collapsed)
}

const handleLogout = async () => {
  if (confirm('Apakah Anda yakin ingin keluar dari aplikasi?')) {
    await logout()
    toast.success('Berhasil logout!')
    router.push('/login')
  }
}
</script>

<template>
  <aside
    class="flex flex-col items-center py-5 flex-shrink-0 transition-all duration-300 ease-in-out relative z-30 overflow-hidden"
    :class="collapsed ? 'w-[60px]' : 'w-[72px] md:w-[80px]'"
    style="background: linear-gradient(180deg, #0d3b66 0%, #0e4d6e 30%, #147a83 60%, #1a9e8f 100%);"
  >
    <!-- Toggle Button (top) -->
    <button
      @click="toggleCollapse"
      class="mb-5 w-9 h-9 flex items-center justify-center text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200 cursor-pointer"
      :title="collapsed ? 'Expand Sidebar' : 'Collapse Sidebar'"
    >
      <!-- Filter/Funnel icon matching ref -->
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.59l-5.432 5.432a2.25 2.25 0 00-.659 1.59v3.492a2.25 2.25 0 01-1.07 1.916l-3 1.8a.75.75 0 01-1.13-.643v-6.565a2.25 2.25 0 00-.659-1.59L3.659 7.408a2.25 2.25 0 01-.659-1.59V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
      </svg>
    </button>

    <!-- Navigation Tabs -->
    <nav class="flex-1 flex flex-col space-y-3 w-full items-center">
      <div
        v-for="tab in tabs"
        :key="tab.id"
        class="relative w-full flex justify-center py-1.5 group cursor-pointer"
        @click="setTab(tab.id)"
        :title="tab.title"
      >
        <!-- Active Indicator (Left green bar) -->
        <Transition name="indicator-slide">
          <div
            v-if="activeTab === tab.id"
            class="absolute left-0 top-1/2 -translate-y-1/2 w-[4px] h-9 bg-emerald-400 rounded-r-full shadow-[0_0_10px_rgba(52,211,153,0.6)]"
          ></div>
        </Transition>

        <!-- Icon Container -->
        <div
          class="w-10 h-10 rounded-xl flex items-center justify-center transition-all duration-200"
          :class="[
            activeTab === tab.id
              ? 'bg-white/20 text-white shadow-lg'
              : 'text-white/60 hover:text-white hover:bg-white/10'
          ]"
        >
          <!-- Icon: Home -->
          <svg v-if="tab.icon === 'home'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
          </svg>

          <!-- Icon: Store -->
          <svg v-if="tab.icon === 'store'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.15c0 .415.336.75.75.75z" />
          </svg>

          <!-- Icon: Bag -->
          <svg v-if="tab.icon === 'bag'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
          </svg>

          <!-- Icon: Document -->
          <svg v-if="tab.icon === 'document'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
          </svg>
        </div>
      </div>
    </nav>

    <!-- Bottom: Lightbulb & Logout -->
    <div class="mt-auto flex flex-col items-center space-y-4">
      <!-- Lightbulb Decoration -->
      <div class="text-white/40 hover:text-white/80 transition-colors duration-200 cursor-pointer">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
        </svg>
      </div>

      <!-- Logout -->
      <button
        @click="handleLogout"
        class="w-9 h-9 rounded-xl bg-white/10 flex items-center justify-center text-white/60 hover:text-red-300 hover:bg-red-500/20 transition-all duration-200 cursor-pointer"
        title="Logout"
      >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3-3l3-3m0 0l-3-3m3 3H9" />
        </svg>
      </button>
    </div>
  </aside>
</template>

<style scoped>
.indicator-slide-enter-active,
.indicator-slide-leave-active {
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.indicator-slide-enter-from,
.indicator-slide-leave-to {
  transform: translateY(-50%) scaleY(0);
  opacity: 0;
}
</style>
