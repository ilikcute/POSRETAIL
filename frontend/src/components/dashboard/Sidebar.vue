<script setup>
import { ref, computed, watch } from 'vue'
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

const { logout, hasRole, hasPermission } = useAuth()
const router = useRouter()
const toast = useToast()

const isSuperAdmin = computed(() => hasRole('super_admin'))

// Menu structure with groups and customized icon mapping matching each feature meaning
const menuGroups = [
  // Dashboard group removed: navigation now handled by horizontal tabs

  {
    name: 'Master',
    icon: 'database',
    items: [
      { id: 'Brand', icon: 'check-badge', title: 'Brand' },
      { id: 'Category', icon: 'tag', title: 'Category' },
      { id: 'Customer', icon: 'user', title: 'Customer' },
      { id: 'Price Tag', icon: 'ticket', title: 'Price Tag' },
      { id: 'Product', icon: 'cube', title: 'Product' },
      { id: 'product Variance', icon: 'adjustments', title: 'Product Variance' },
      { id: 'Unit', icon: 'scale', title: 'Unit' },
      { id: 'Supplier', icon: 'truck', title: 'Supplier' },
      { id: 'Stations', icon: 'computer', title: 'Station' },
      { id: 'Rack', icon: 'list-bullet', title: 'Rack' },
      { id: 'Store', icon: 'store', title: 'Store' },
      { id: 'Warehouse', icon: 'building-office', title: 'Warehouse' }
    ]
  },
  {
    name: 'Sales',
    icon: 'shopping-cart',
    items: [
      { id: 'Shift', icon: 'clock', title: 'Shift' },
      { id: 'Cash Pull', icon: 'wallet', title: 'Cash Pull' },
      { id: 'Daily CLose', icon: 'lock-closed', title: 'Daily Close' },
      { id: 'Loyality Transaksi', icon: 'gift', title: 'Loyalty Transaksi' },
      { id: 'Pricing Safe guard', icon: 'shield', title: 'Pricing Safeguard' },
      { id: 'Promotion', icon: 'megaphone', title: 'Promotion' },
      { id: 'Sales', icon: 'shopping-cart', title: 'Sales' },
      { id: 'Station Remittance', icon: 'arrow-right-left', title: 'Station Remittance' }
    ] 
  },
  {
    name: 'Purchases',
    icon: 'bag',
    items: [
      { id: 'Purchases', icon: 'inbox-arrow-down', title: 'Purchases' }
    ]
  },
  {
    name: 'Inventory',
    icon: 'cube',
    items: [
      { id: 'Inventory', icon: 'archive-box', title: 'Inventory' },
      { id: 'Stock Opname', icon: 'clipboard', title: 'Stock Opname' },
      { id: 'Stock Disposal', icon: 'trash', title: 'Stock Disposal' },
      { id: 'Auto Replenishment', icon: 'arrow-path', title: 'Auto Replenishment' },
      { id: 'Inventory Intelligent', icon: 'light-bulb', title: 'Inventory Intelligent' },
      { id: 'Product Stock', icon: 'chart-bar', title: 'Product Stock' }
    ]
  },
  {
    name: 'Finance',
    icon: 'banknotes',
    items: [
      { id: 'Month End', icon: 'calendar', title: 'Month End' },
      { id: 'Account', icon: 'folder-open', title: 'Account' },
      { id: 'Cash Transaction', icon: 'banknotes', title: 'Cash Transaction' },
      { id: 'Consigment Tax', icon: 'calculator', title: 'Consigment Tax' },
      { id: 'Financial Report', icon: 'presentation-chart-line', title: 'Financial Report' },
      { id: 'Jurnal Entry', icon: 'pencil-square', title: 'Jurnal Entry' },
      { id: 'Debt Ledger', icon: 'credit-card', title: 'Debt Ledger' }
    ]
  },
  {
    name: 'Akun',
    icon: 'user',
    items: [
      { id: 'Profile', icon: 'user-circle', title: 'Profil Saya' }
    ]
  },
  {
    name: 'Setting',
    icon: 'cog',
    items: [
      { id: 'User Management', icon: 'user', title: 'User Management', permission: 'view users' },
      { id: 'Role Management', icon: 'shield', title: 'Role Management', permission: 'view roles' },
      { id: 'Theme Settings', icon: 'palette', title: 'Theme Settings', permission: 'view settings' },
      { id: 'Language Settings', icon: 'globe-alt', title: 'Language Settings', permission: 'view settings' },
      { id: 'Currency Settings', icon: 'currency-dollar', title: 'Currency Settings', permission: 'view settings' }
    ]
  }
]

// Only expose groups and items the current user is allowed to see
const visibleMenuGroups = computed(() => {
  return menuGroups
    .map(group => {
      const filteredItems = group.items.filter(item => {
        if (item.permission && !hasPermission(item.permission)) return false
        return true
      })
      return { ...group, items: filteredItems }
    })
    .filter(group => group.items.length > 0)
})

// State for expanded/collapsed groups (only used when sidebar is expanded)
const expandedGroups = ref({
  Dashboard: true,
  Master: false,
  Sales: false,
  Purchases: false,
  Inventory: false,
  Finance: false,
  Akun: false,
  Setting: false
})

// Flatten all leaf items for collapsed mode (respects visibility rules)
const flatLeafItems = computed(() => {
  return visibleMenuGroups.value.flatMap(group => group.items)
})


// Automatically expand the group containing the active tab
watch(
  () => props.activeTab,
  (newActiveTab) => {
    const activeGroup = menuGroups.find(group => 
      group.items.some(item => item.id === newActiveTab)
    )
    if (activeGroup) {
      expandedGroups.value[activeGroup.name] = true
    }
  },
  { immediate: true }
)

const toggleGroup = (groupName) => {
  if (props.collapsed) return // No group toggling when collapsed
  expandedGroups.value[groupName] = !expandedGroups.value[groupName]
}

const setActiveTab = (tabId) => {
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

// Highly optimized inline SVG path repository mapping standard outline Heroicons
const defaultIcon = '<path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 21m0 0l-.813-5.096M9 21h3.375c1.035 0 1.875-.84 1.875-1.875v-1.125a1.875 1.875 0 011.875-1.875h.375M16.5 13.5v-2.25a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0110.5 6.75V5.625c0-.621-.504-1.125-1.125-1.125H4.125C3.504 4.5 3 5.004 3 5.625V18.75c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125v-2.25z" />'

const iconPaths = {
  'home': '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />',
  'chart-pie': '<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a7.5 7.5 0 107.5 7.5h-7.5V6z" /><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5H21A7.5 7.5 0 0013.5 3v7.5z" />',
  'store': '<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.15c0 .415.336.75.75.75z" />',
  'bag': '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />',
  'x-circle': '<path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
  'database': '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5c0 2.21-3.694 4-8.25 4S3.75 9.71 3.75 7.5m16.5 0c0-2.21-3.694-4-8.25-4S3.75 5.29 3.75 7.5m16.5 0v5.25c0 2.21-3.694 4-8.25 4s-8.25-1.79-8.25-4V7.5m16.5 5.25v5.25c0 2.21-3.694 4-8.25 4s-8.25-1.79-8.25-4v-5.25" />',
  'bookmark': '<path stroke-linecap="round" stroke-linejoin="round" d="M17.598 19.142l-5.322-2.836a1.125 1.125 0 00-1.053 0l-5.322 2.836A.75.75 0 015.25 18.5V4.5a2.25 2.25 0 012.25-2.25h9a2.25 2.25 0 012.25 2.25v14a.75.75 0 01-1.152.642z" />',
  'tag': '<path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />',
  'user': '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />',
  'cube': '<path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />',
  'scale': '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18M3 8.25h18M5.25 15.75a3 3 0 106 0v-7.5h-6v7.5zm10.5 0a3 3 0 106 0v-7.5h-6v7.5z" />',
  'truck': '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.129-1.125v-3.026a2.99 2.99 0 0 0-.75-1.985l-2.717-2.717a2.99 2.99 0 0 0-1.985-.75H12m0 12V7.5m0 0H7.5m4.5 0H18M12 14.25h7.5" />',
  'computer': '<path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" />',
  'archive-box': '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />',
  'arrow-down-tray': '<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />',
  'lock-closed': '<path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />',
  'gift': '<path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.071-1.679-3.75-3.75-3.75-1.31 0-2.462.67-3.13 1.693-.668-1.023-1.82-1.693-3.13-1.693C9.079 4.5 7.4 6.179 7.4 8.25c0 .326.042.643.12.945M21 8.25c0 .326-.042.643-.12.945M21 8.25h-3.75m-9 0H4.5m.75 0a2.25 2.25 0 002.25 2.25h12a2.25 2.25 0 002.25-2.25M12 4.5v16.5m-9-9h18M7.5 12v6.75c0 .621.504 1.125 1.125 1.125h6.75c.621 0 1.125-.504 1.125-1.125V12" />',
  'shield': '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />',
  'megaphone': '<path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 010 12.728M16.463 8.288a5.25 5.25 0 010 7.424M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z" />',
  'shopping-cart': '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />',
  'arrow-right-left': '<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />',
  'pause-circle': '<path stroke-linecap="round" stroke-linejoin="round" d="M14.25 9v6m-4.5-6v6M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
  'clipboard': '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />',
  'arrow-path': '<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />',
  'light-bulb': '<path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.383a14.406 14.406 0 01-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 10-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />',
  'folder-open': '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0-.621.504-1.125 1.125-1.125h3.375c.621 0 1.125.504 1.125 1.125v.75H18.75a2.25 2.25 0 012.25 2.25v9a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18.75V6.75z" />',
  'banknotes': '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5h16.5a1.5 1.5 0 011.5 1.5v12a1.5 1.5 0 01-1.5 1.5H3.75a1.5 1.5 0 01-1.5-1.5v-12a1.5 1.5 0 011.5-1.5zM12 12.75a2.25 2.25 0 100-4.5 2.25 2.25 0 000 4.5z" />',
  'calculator': '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 15.75h.008v.008H15.75v-.008zm0-2.25h.008v.008H15.75v-.008zm0-2.25h.008v.008H15.75v-.008zm-3 4.5h.008v.008h-.008v-.008zm0-2.25h.008v.008h-.008v-.008zm0-2.25h.008v.008h-.008v-.008zm-3 4.5h.008v.008H9.75v-.008zm0-2.25h.008v.008H9.75v-.008zm0-2.25h.008v.008H9.75v-.008zm-3 4.5H5.25v-.75h1.5v.75zm0-2.25H5.25v-.75h1.5v.75zm0-2.25H5.25v-.75h1.5v.75zM3 5.25a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 5.25v13.5A2.25 2.25 0 0118.75 21H5.25A2.25 2.25 0 013 18.75V5.25zM6 7.5h12v2.25H6V7.5z" />',
  'presentation-chart-line': '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0h.008v.008h-.008v-.008zm-12 0h.008v.008h-.008v-.008zM6 9l4.5-4.5 3 3L18 3" />',
  'pencil-square': '<path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />',
  'credit-card': '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-6.188-12h12.375c1.036 0 1.875.84 1.875 1.875v11.25c0 1.035-.84 1.875-1.875 1.875H3.062A1.875 1.875 0 011.187 18V5.625c0-1.036.84-1.875 1.875-1.875z" />',
  'document-text': '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 3h6m-6 3h3" />',
  'document-chart': '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 6.75h.007m-.007 4.5h.007M9 9h.007M9 12h.007M9 15h.007M10.5 9h.007M10.5 12h.007M10.5 15h.007M12 9h.007M12 12h.007M12 15h.007M16.5 9h.007M16.5 12h.007M16.5 15h.007" />',
  'user-group': '<path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m-4.674-6.13a3 3 0 11-2.77-4.21 3 3 0 012.77 4.21z" />',
  'user-circle': '<path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" />',
  'palette': '<path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 005.304 0l6.401-6.402M6.75 21A3.75 3.75 0 013 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 003.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008z" />',
  'globe-alt': '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />',
  'currency-dollar': '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />',
  'cog': '<path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.43.992a6.759 6.759 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.87l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.991l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.645-.87l.213-1.281z" />',
  'adjustments': '<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />',
  'check-badge': '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.746 3.746 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" />',
  'ticket': '<path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-12v.75m0 3v.75m0 3v.75m0 3V18M3 8.25a2.25 2.25 0 012.25-2.25h13.5a2.25 2.25 0 012.25 2.25v7.5a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15.75V8.25z" />',
  'list-bullet': '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />',
  'building-office': '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />',
  'wallet': '<path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18-3a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3" />',
  'inbox-arrow-down': '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.008 1.24l.885 1.77a2.25 2.25 0 002.007 1.24h1.98a2.25 2.25 0 002.007-1.24l.885-1.77a2.25 2.25 0 012.007-1.24h3.86m-18 0h18M12 4.5v10.5m0 0l-3-3m3 3l3-3" />',
  'trash': '<path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />',
  'chart-bar': '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />',
  'calendar': '<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />'
}
</script>

<template>
  <aside
    class="flex flex-col py-5 h-screen flex-shrink-0 transition-all duration-300 ease-in-out relative z-30 overflow-hidden"
    :class="collapsed ? 'w-[80px]' : 'w-[280px]'"
    style="background: linear-gradient(180deg, #0d3b66 0%, #0e4d6e 30%, #147a83 60%, #1a9e8f 100%);"
  >
    <!-- Toggle Button -->
    <button
      @click="toggleCollapse"
      class="mb-5 w-9 h-9 flex items-center justify-center text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition-all duration-200 cursor-pointer mx-auto"
      :title="collapsed ? 'Expand Sidebar' : 'Collapse Sidebar'"
    >
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="w-5 h-5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
      </svg>
    </button>

    <!-- Navigation: Collapsed Mode (icons only) -->
    <nav v-if="collapsed" class="flex-1 flex flex-col space-y-3 w-full items-center overflow-y-auto">
      <div
        v-for="item in flatLeafItems"
        :key="item.id"
        class="relative w-full flex justify-center py-1.5 group cursor-pointer"
        @click="setActiveTab(item.id)"
        :title="item.title"
      >
        <!-- Active Indicator -->
        <Transition name="indicator-slide">
          <div
            v-if="activeTab === item.id"
            class="absolute left-0 top-1/2 -translate-y-1/2 w-[4px] h-9 bg-emerald-400 rounded-r-full shadow-[0_0_10px_rgba(52,211,153,0.6)]"
          ></div>
        </Transition>

        <!-- Icon Container -->
        <div
          class="w-10 h-10 rounded-xl flex items-center justify-center transition-all duration-200"
          :class="[
            activeTab === item.id
              ? 'bg-white/20 text-white shadow-lg'
              : 'text-white/60 hover:text-white hover:bg-white/10'
          ]"
        >
          <!-- Dynamic High-Performance SVG Icon -->
          <svg 
            xmlns="http://www.w3.org/2000/svg" 
            fill="none" 
            viewBox="0 0 24 24" 
            stroke-width="1.8" 
            stroke="currentColor" 
            class="w-5 h-5"
            v-html="iconPaths[item.icon] || defaultIcon"
          ></svg>
        </div>
      </div>
    </nav>

    <!-- Navigation: Expanded Mode (with groups) -->
    <nav v-else class="flex-1 flex flex-col w-full overflow-y-auto px-3">
      <div v-for="group in visibleMenuGroups" :key="group.name" class="mb-2">
        <!-- Group Header -->
        <div
          @click="toggleGroup(group.name)"
          class="flex items-center justify-between px-2 py-2 rounded-lg cursor-pointer text-white/70 hover:text-white hover:bg-white/10 transition-all duration-200"
        >
          <div class="flex items-center gap-3">
            <!-- Group Icon -->
            <div class="w-8 h-8 flex items-center justify-center">
              <svg 
                xmlns="http://www.w3.org/2000/svg" 
                fill="none" 
                viewBox="0 0 24 24" 
                stroke-width="1.8" 
                stroke="currentColor" 
                class="w-5 h-5"
                v-html="iconPaths[group.icon] || defaultIcon"
              ></svg>
            </div>
            <span class="text-sm font-medium">{{ group.name }}</span>
          </div>
          <!-- Chevron -->
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': expandedGroups[group.name] }">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
          </svg>
        </div>

        <!-- Group Items (Submenu) -->
        <Transition name="group-slide">
          <div v-if="expandedGroups[group.name]" class="ml-4 mt-1 space-y-1">
            <div
              v-for="item in group.items"
              :key="item.id"
              class="relative flex items-center gap-3 px-2 py-2 rounded-lg cursor-pointer transition-all duration-200"
              :class="[
                activeTab === item.id
                  ? 'bg-white/15 text-white'
                  : 'text-white/60 hover:text-white hover:bg-white/10'
              ]"
              @click="setActiveTab(item.id)"
            >
              <!-- Active Indicator for submenu -->
              <Transition name="indicator-slide">
                <div
                  v-if="activeTab === item.id"
                  class="absolute left-0 top-1/2 -translate-y-1/2 w-[3px] h-6 bg-emerald-400 rounded-r-full shadow-[0_0_8px_rgba(52,211,153,0.5)]"
                ></div>
              </Transition>
              
              <!-- Item Icon -->
              <div class="w-7 h-7 flex items-center justify-center">
                <svg 
                  xmlns="http://www.w3.org/2000/svg" 
                  fill="none" 
                  viewBox="0 0 24 24" 
                  stroke-width="1.8" 
                  stroke="currentColor" 
                  class="w-4 h-4"
                  v-html="iconPaths[item.icon] || defaultIcon"
                ></svg>
              </div>
              
              <!-- Item Title -->
              <span class="text-sm">{{ item.title }}</span>
            </div>
          </div>
        </Transition>
      </div>
    </nav>

    <!-- Bottom: Lightbulb & Logout -->
    <div class="mt-auto flex flex-col items-center space-y-4 pt-4">
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
/* Custom premium scrollbar for sidebar nav */
nav::-webkit-scrollbar {
  width: 5px;
}
nav::-webkit-scrollbar-track {
  background: transparent;
}
nav::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.18);
  border-radius: 9999px;
}
nav::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.35);
}
nav {
  scrollbar-width: thin;
  scrollbar-color: rgba(255, 255, 255, 0.18) transparent;
}

.indicator-slide-enter-active,
.indicator-slide-leave-active {
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.indicator-slide-enter-from,
.indicator-slide-leave-to {
  transform: translateY(-50%) scaleY(0);
  opacity: 0;
}

.group-slide-enter-active,
.group-slide-leave-active {
  transition: all 0.25s ease-out;
}
.group-slide-enter-from,
.group-slide-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>