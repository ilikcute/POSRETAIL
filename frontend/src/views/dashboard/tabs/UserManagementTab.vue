<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useToast } from 'vue-toastification'
import {
  getUsers, getUser, createUser, updateUser, deleteUser,
  toggleUserActive, getRoles
} from '../../../services/userManagement'

const toast = useToast()

// ── State ─────────────────────────────────────────────────────────────────────
const users = ref([])
const roles = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0, per_page: 15 })
const loading = ref(false)
const showModal = ref(false)
const showDeleteModal = ref(false)
const isEditing = ref(false)
const deletingUser = ref(null)

const filters = reactive({ search: '', role: '', is_active: '', per_page: 15 })
const currentPage = ref(1)

const form = reactive({
  name: '', email: '', password: '', password_confirmation: '',
  role: '', station_id: null, is_active: true
})
const formErrors = reactive({})
const formLoading = ref(false)
const editingId = ref(null)

// ── Computed ──────────────────────────────────────────────────────────────────
const pageNumbers = computed(() => {
  const pages = []
  for (let i = 1; i <= Math.min(meta.value.last_page, 10); i++) pages.push(i)
  return pages
})

// ── Methods ───────────────────────────────────────────────────────────────────
const fetchUsers = async () => {
  loading.value = true
  try {
    const params = { page: currentPage.value, per_page: filters.per_page }
    if (filters.search) params.search = filters.search
    if (filters.role) params.role = filters.role
    if (filters.is_active !== '') params.is_active = filters.is_active
    const res = await getUsers(params)
    users.value = res.data.data.data ?? res.data.data
    if (res.data.data.meta) meta.value = res.data.data.meta
    else if (res.data.data.current_page) {
      meta.value = {
        current_page: res.data.data.current_page,
        last_page: res.data.data.last_page,
        total: res.data.data.total,
        per_page: res.data.data.per_page
      }
    }
  } catch (e) {
    toast.error('Gagal memuat data user')
  } finally {
    loading.value = false
  }
}

const fetchRoles = async () => {
  try {
    const res = await getRoles()
    roles.value = res.data.data
  } catch (e) {
    console.error('Failed to load roles', e)
  }
}

const openCreate = () => {
  isEditing.value = false
  editingId.value = null
  Object.assign(form, { name: '', email: '', password: '', password_confirmation: '', role: '', station_id: null, is_active: true })
  Object.keys(formErrors).forEach(k => delete formErrors[k])
  showModal.value = true
}

const openEdit = async (user) => {
  isEditing.value = true
  editingId.value = user.id
  try {
    const res = await getUser(user.id)
    const u = res.data.data
    Object.assign(form, {
      name: u.name, email: u.email,
      password: '', password_confirmation: '',
      role: u.roles?.[0]?.name ?? '',
      station_id: u.station_id,
      is_active: u.is_active
    })
  } catch {
    Object.assign(form, { name: user.name, email: user.email, password: '', password_confirmation: '', role: user.roles?.[0]?.name ?? '', station_id: user.station_id, is_active: user.is_active })
  }
  Object.keys(formErrors).forEach(k => delete formErrors[k])
  showModal.value = true
}

const validateForm = () => {
  Object.keys(formErrors).forEach(k => delete formErrors[k])
  if (!form.name) formErrors.name = 'Nama wajib diisi'
  if (!form.email) formErrors.email = 'Email wajib diisi'
  else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) formErrors.email = 'Format email tidak valid'
  if (!isEditing.value && !form.password) formErrors.password = 'Password wajib diisi'
  if (form.password && form.password.length < 8) formErrors.password = 'Password minimal 8 karakter'
  if (form.password && form.password !== form.password_confirmation) formErrors.password_confirmation = 'Konfirmasi password tidak cocok'
  if (!form.role) formErrors.role = 'Role wajib dipilih'
  return Object.keys(formErrors).length === 0
}

const submitForm = async () => {
  if (!validateForm()) return
  formLoading.value = true
  try {
    const payload = { name: form.name, email: form.email, role: form.role, is_active: form.is_active }
    if (form.password) { payload.password = form.password; payload.password_confirmation = form.password_confirmation }
    if (form.station_id) payload.station_id = form.station_id

    if (isEditing.value) {
      await updateUser(editingId.value, payload)
      toast.success('User berhasil diperbarui')
    } else {
      await createUser({ ...payload, password_confirmation: form.password_confirmation })
      toast.success('User berhasil dibuat')
    }
    showModal.value = false
    fetchUsers()
  } catch (e) {
    if (e.response?.data?.errors) {
      Object.assign(formErrors, e.response.data.errors)
    } else {
      toast.error(e.response?.data?.message ?? 'Terjadi kesalahan')
    }
  } finally {
    formLoading.value = false
  }
}

const confirmDelete = (user) => {
  deletingUser.value = user
  showDeleteModal.value = true
}

const executeDelete = async () => {
  if (!deletingUser.value) return
  try {
    await deleteUser(deletingUser.value.id)
    toast.success('User berhasil dihapus')
    showDeleteModal.value = false
    deletingUser.value = null
    fetchUsers()
  } catch (e) {
    toast.error(e.response?.data?.message ?? 'Gagal menghapus user')
  }
}

const handleToggleActive = async (user) => {
  try {
    const res = await toggleUserActive(user.id)
    const isActive = res.data.data.is_active
    user.is_active = isActive
    toast.success(isActive ? `${user.name} diaktifkan` : `${user.name} dinonaktifkan`)
  } catch (e) {
    toast.error(e.response?.data?.message ?? 'Gagal mengubah status user')
  }
}

const getRoleBadgeClass = (roleName) => {
  const map = {
    super_admin: 'bg-purple-500/20 text-purple-300 border-purple-500/30',
    manager: 'bg-blue-500/20 text-blue-300 border-blue-500/30',
    cashier: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30'
  }
  return map[roleName] ?? 'bg-slate-500/20 text-slate-300 border-slate-500/30'
}

let searchTimeout = null
watch(() => filters.search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => { currentPage.value = 1; fetchUsers() }, 400)
})
watch([() => filters.role, () => filters.is_active], () => { currentPage.value = 1; fetchUsers() })
watch(currentPage, fetchUsers)

onMounted(() => { fetchUsers(); fetchRoles() })
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h2 class="text-2xl font-bold text-white">User Management</h2>
        <p class="text-slate-400 text-sm mt-1">Kelola pengguna dan hak akses mereka</p>
      </div>
      <button
        id="btn-create-user"
        @click="openCreate"
        class="flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-medium transition-all duration-200 shadow-lg shadow-indigo-600/25"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Tambah User
      </button>
    </div>

    <!-- Filters -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
      <div class="relative">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input
          v-model="filters.search"
          type="text"
          placeholder="Cari nama atau email..."
          class="w-full pl-10 pr-4 py-2.5 bg-slate-800/60 border border-slate-700/50 rounded-xl text-white placeholder-slate-400 text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/50 transition-all"
        />
      </div>
      <select
        v-model="filters.role"
        class="px-3 py-2.5 bg-slate-800/60 border border-slate-700/50 rounded-xl text-white text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/50 transition-all"
      >
        <option value="">Semua Role</option>
        <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
      </select>
      <select
        v-model="filters.is_active"
        class="px-3 py-2.5 bg-slate-800/60 border border-slate-700/50 rounded-xl text-white text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/50 transition-all"
      >
        <option value="">Semua Status</option>
        <option value="1">Aktif</option>
        <option value="0">Nonaktif</option>
      </select>
    </div>

    <!-- Table -->
    <div class="bg-slate-800/40 rounded-2xl border border-slate-700/50 overflow-hidden">
      <div v-if="loading" class="flex items-center justify-center py-20">
        <div class="animate-spin w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full"></div>
      </div>

      <div v-else-if="users.length === 0" class="flex flex-col items-center justify-center py-20 text-slate-500">
        <svg class="w-12 h-12 mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        <p class="font-medium">Tidak ada user ditemukan</p>
      </div>

      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-700/50">
            <th class="text-left px-6 py-4 text-slate-400 font-medium">User</th>
            <th class="text-left px-6 py-4 text-slate-400 font-medium hidden md:table-cell">Role</th>
            <th class="text-left px-6 py-4 text-slate-400 font-medium hidden sm:table-cell">Login Terakhir</th>
            <th class="text-center px-6 py-4 text-slate-400 font-medium">Status</th>
            <th class="text-right px-6 py-4 text-slate-400 font-medium">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-700/30">
          <tr v-for="user in users" :key="user.id" class="hover:bg-slate-700/20 transition-colors group">
            <td class="px-6 py-4">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center font-bold text-white text-sm flex-shrink-0">
                  {{ user.name?.charAt(0)?.toUpperCase() }}
                </div>
                <div>
                  <p class="text-white font-medium">{{ user.name }}</p>
                  <p class="text-slate-400 text-xs">{{ user.email }}</p>
                </div>
              </div>
            </td>
            <td class="px-6 py-4 hidden md:table-cell">
              <span
                v-for="role in user.roles" :key="role.id"
                :class="['inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium border', getRoleBadgeClass(role.name)]"
              >
                {{ role.name }}
              </span>
              <span v-if="!user.roles?.length" class="text-slate-500 text-xs">-</span>
            </td>
            <td class="px-6 py-4 hidden sm:table-cell text-slate-400 text-xs">
              {{ user.last_login_at ? new Date(user.last_login_at).toLocaleDateString('id-ID') : 'Belum pernah' }}
            </td>
            <td class="px-6 py-4 text-center">
              <button
                @click="handleToggleActive(user)"
                :class="['relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full transition-colors duration-200', user.is_active ? 'bg-emerald-500' : 'bg-slate-600']"
              >
                <span :class="['inline-block h-4 w-4 mt-0.5 ml-0.5 transform rounded-full bg-white shadow transition-transform duration-200', user.is_active ? 'translate-x-4' : 'translate-x-0']"></span>
              </button>
            </td>
            <td class="px-6 py-4 text-right">
              <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                <button
                  @click="openEdit(user)"
                  class="p-1.5 rounded-lg bg-slate-700 hover:bg-indigo-600 text-slate-300 hover:text-white transition-all"
                  title="Edit"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                  </svg>
                </button>
                <button
                  @click="confirmDelete(user)"
                  class="p-1.5 rounded-lg bg-slate-700 hover:bg-red-600 text-slate-300 hover:text-white transition-all"
                  title="Hapus"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="meta.last_page > 1" class="flex items-center justify-between">
      <p class="text-sm text-slate-400">
        Total <span class="text-white font-medium">{{ meta.total }}</span> user
      </p>
      <div class="flex gap-1">
        <button
          v-for="page in pageNumbers" :key="page"
          @click="currentPage = page"
          :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition-all', currentPage === page ? 'bg-indigo-600 text-white' : 'bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white']"
        >{{ page }}</button>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="showModal = false"></div>
          <div class="relative bg-slate-900 border border-slate-700/60 rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-slate-700/50">
              <h3 class="text-lg font-bold text-white">{{ isEditing ? 'Edit User' : 'Tambah User Baru' }}</h3>
              <button @click="showModal = false" class="p-2 rounded-lg hover:bg-slate-800 text-slate-400 hover:text-white transition-all">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
            <!-- Modal Body -->
            <div class="p-6 space-y-5">
              <!-- Name -->
              <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Nama Lengkap</label>
                <input
                  v-model="form.name"
                  type="text"
                  id="user-name"
                  placeholder="Nama lengkap user"
                  :class="['w-full px-4 py-2.5 bg-slate-800/80 border rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all', formErrors.name ? 'border-red-500 focus:ring-red-500/50' : 'border-slate-700/50 focus:border-indigo-500 focus:ring-indigo-500/50']"
                />
                <p v-if="formErrors.name" class="mt-1 text-xs text-red-400">{{ formErrors.name }}</p>
              </div>
              <!-- Email -->
              <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Email</label>
                <input
                  v-model="form.email"
                  type="email"
                  id="user-email"
                  placeholder="email@example.com"
                  :class="['w-full px-4 py-2.5 bg-slate-800/80 border rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all', formErrors.email ? 'border-red-500 focus:ring-red-500/50' : 'border-slate-700/50 focus:border-indigo-500 focus:ring-indigo-500/50']"
                />
                <p v-if="formErrors.email" class="mt-1 text-xs text-red-400">{{ formErrors.email }}</p>
              </div>
              <!-- Password -->
              <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">
                  Password <span v-if="isEditing" class="text-slate-500 text-xs">(kosongkan jika tidak diubah)</span>
                </label>
                <input
                  v-model="form.password"
                  type="password"
                  id="user-password"
                  placeholder="Min. 8 karakter"
                  :class="['w-full px-4 py-2.5 bg-slate-800/80 border rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all', formErrors.password ? 'border-red-500 focus:ring-red-500/50' : 'border-slate-700/50 focus:border-indigo-500 focus:ring-indigo-500/50']"
                />
                <p v-if="formErrors.password" class="mt-1 text-xs text-red-400">{{ formErrors.password }}</p>
              </div>
              <!-- Confirm Password -->
              <div v-if="form.password">
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Konfirmasi Password</label>
                <input
                  v-model="form.password_confirmation"
                  type="password"
                  id="user-password-confirm"
                  placeholder="Ulangi password"
                  :class="['w-full px-4 py-2.5 bg-slate-800/80 border rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all', formErrors.password_confirmation ? 'border-red-500 focus:ring-red-500/50' : 'border-slate-700/50 focus:border-indigo-500 focus:ring-indigo-500/50']"
                />
                <p v-if="formErrors.password_confirmation" class="mt-1 text-xs text-red-400">{{ formErrors.password_confirmation }}</p>
              </div>
              <!-- Role -->
              <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Role</label>
                <select
                  v-model="form.role"
                  id="user-role"
                  :class="['w-full px-4 py-2.5 bg-slate-800/80 border rounded-xl text-white text-sm focus:outline-none focus:ring-1 transition-all', formErrors.role ? 'border-red-500 focus:ring-red-500/50' : 'border-slate-700/50 focus:border-indigo-500 focus:ring-indigo-500/50']"
                >
                  <option value="" disabled>Pilih role...</option>
                  <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
                </select>
                <p v-if="formErrors.role" class="mt-1 text-xs text-red-400">{{ formErrors.role }}</p>
              </div>
              <!-- Status -->
              <div class="flex items-center justify-between p-4 bg-slate-800/50 rounded-xl border border-slate-700/30">
                <div>
                  <p class="text-sm font-medium text-slate-300">Status Akun</p>
                  <p class="text-xs text-slate-500 mt-0.5">User aktif dapat login ke sistem</p>
                </div>
                <button
                  type="button"
                  @click="form.is_active = !form.is_active"
                  :class="['relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200', form.is_active ? 'bg-emerald-500' : 'bg-slate-600']"
                >
                  <span :class="['inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform duration-200', form.is_active ? 'translate-x-6' : 'translate-x-1']"></span>
                </button>
              </div>
            </div>
            <!-- Modal Footer -->
            <div class="flex gap-3 p-6 border-t border-slate-700/50">
              <button
                @click="showModal = false"
                class="flex-1 px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl font-medium transition-all"
              >
                Batal
              </button>
              <button
                @click="submitForm"
                :disabled="formLoading"
                class="flex-1 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl font-medium transition-all flex items-center justify-center gap-2"
              >
                <div v-if="formLoading" class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></div>
                {{ isEditing ? 'Simpan Perubahan' : 'Buat User' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Delete Confirmation Modal -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="showDeleteModal = false"></div>
          <div class="relative bg-slate-900 border border-slate-700/60 rounded-2xl shadow-2xl w-full max-w-sm p-6">
            <div class="flex items-center gap-4 mb-4">
              <div class="w-12 h-12 rounded-full bg-red-500/20 flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
              </div>
              <div>
                <h3 class="text-white font-semibold">Hapus User</h3>
                <p class="text-slate-400 text-sm mt-0.5">Tindakan ini tidak bisa dibatalkan</p>
              </div>
            </div>
            <p class="text-slate-300 text-sm mb-6">
              Yakin ingin menghapus user <span class="text-white font-semibold">{{ deletingUser?.name }}</span>?
            </p>
            <div class="flex gap-3">
              <button @click="showDeleteModal = false" class="flex-1 px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl font-medium transition-all">Batal</button>
              <button @click="executeDelete" class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-500 text-white rounded-xl font-medium transition-all">Hapus</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: all 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .relative, .modal-leave-to .relative { transform: scale(0.95); }
</style>
