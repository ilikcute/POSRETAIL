<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import {
  getRoles, getRole, createRole, updateRole, deleteRole,
  getAllPermissions, syncRolePermissions
} from '../../../services/userManagement'

const toast = useToast()

// ── State ─────────────────────────────────────────────────────────────────────
const roles = ref([])
const permissionsGrouped = ref({})
const loading = ref(false)
const showModal = ref(false)
const showDeleteModal = ref(false)
const showPermissionModal = ref(false)
const isEditing = ref(false)
const deletingRole = ref(null)
const managingRole = ref(null)
const syncLoading = ref(false)

const form = reactive({ name: '', permissions: [] })
const formErrors = reactive({})
const formLoading = ref(false)
const editingId = ref(null)

// Permission modal state
const selectedPermissions = ref([])
const permissionSearch = ref('')

// ── Computed ──────────────────────────────────────────────────────────────────
const filteredPermissionsGrouped = computed(() => {
  if (!permissionSearch.value) return permissionsGrouped.value
  const q = permissionSearch.value.toLowerCase()
  const result = {}
  for (const [module, perms] of Object.entries(permissionsGrouped.value)) {
    const filtered = perms.filter(p => p.toLowerCase().includes(q) || module.toLowerCase().includes(q))
    if (filtered.length) result[module] = filtered
  }
  return result
})

const allGroupPermissions = computed(() => {
  const all = []
  for (const perms of Object.values(permissionsGrouped.value)) all.push(...perms)
  return all
})

const isAllSelected = computed(() =>
  allGroupPermissions.value.length > 0 &&
  allGroupPermissions.value.every(p => selectedPermissions.value.includes(p))
)

const isGroupSelected = (module) => {
  const perms = permissionsGrouped.value[module] ?? []
  return perms.length > 0 && perms.every(p => selectedPermissions.value.includes(p))
}

const isGroupIndeterminate = (module) => {
  const perms = permissionsGrouped.value[module] ?? []
  const selected = perms.filter(p => selectedPermissions.value.includes(p))
  return selected.length > 0 && selected.length < perms.length
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const protectedRoles = ['super_admin']

const getRoleColor = (name) => {
  const map = {
    super_admin: 'from-purple-600 to-indigo-700',
    manager: 'from-blue-600 to-cyan-700',
    cashier: 'from-emerald-600 to-teal-700'
  }
  return map[name] ?? 'from-slate-600 to-slate-700'
}

const formatModuleName = (module) =>
  module.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ')

// ── Methods ───────────────────────────────────────────────────────────────────
const fetchRoles = async () => {
  loading.value = true
  try {
    const res = await getRoles()
    roles.value = res.data.data
  } catch {
    toast.error('Gagal memuat data role')
  } finally {
    loading.value = false
  }
}

const fetchPermissions = async () => {
  try {
    const res = await getAllPermissions()
    permissionsGrouped.value = res.data.data
  } catch {
    console.error('Failed to load permissions')
  }
}

const openCreate = () => {
  isEditing.value = false
  editingId.value = null
  form.name = ''
  form.permissions = []
  Object.keys(formErrors).forEach(k => delete formErrors[k])
  showModal.value = true
}

const openEdit = async (role) => {
  isEditing.value = true
  editingId.value = role.id
  try {
    const res = await getRole(role.id)
    const r = res.data.data
    form.name = r.name
    form.permissions = r.permissions?.map(p => p.name) ?? []
  } catch {
    form.name = role.name
    form.permissions = []
  }
  Object.keys(formErrors).forEach(k => delete formErrors[k])
  showModal.value = true
}

const validateForm = () => {
  Object.keys(formErrors).forEach(k => delete formErrors[k])
  if (!form.name.trim()) formErrors.name = 'Nama role wajib diisi'
  else if (!/^[a-z0-9_]+$/.test(form.name)) formErrors.name = 'Nama hanya boleh huruf kecil, angka, dan underscore'
  if (!form.permissions.length) formErrors.permissions = 'Pilih minimal satu permission'
  return Object.keys(formErrors).length === 0
}

const submitForm = async () => {
  if (!validateForm()) return
  formLoading.value = true
  try {
    if (isEditing.value) {
      await updateRole(editingId.value, { name: form.name, permissions: form.permissions })
      toast.success('Role berhasil diperbarui')
    } else {
      await createRole({ name: form.name, permissions: form.permissions })
      toast.success('Role berhasil dibuat')
    }
    showModal.value = false
    fetchRoles()
  } catch (e) {
    if (e.response?.data?.errors) Object.assign(formErrors, e.response.data.errors)
    else toast.error(e.response?.data?.message ?? 'Terjadi kesalahan')
  } finally {
    formLoading.value = false
  }
}

const confirmDelete = (role) => {
  deletingRole.value = role
  showDeleteModal.value = true
}

const executeDelete = async () => {
  if (!deletingRole.value) return
  try {
    await deleteRole(deletingRole.value.id)
    toast.success('Role berhasil dihapus')
    showDeleteModal.value = false
    deletingRole.value = null
    fetchRoles()
  } catch (e) {
    toast.error(e.response?.data?.message ?? 'Gagal menghapus role')
  }
}

const openPermissionManager = async (role) => {
  managingRole.value = role
  permissionSearch.value = ''
  try {
    const res = await getRole(role.id)
    selectedPermissions.value = res.data.data.permissions?.map(p => p.name) ?? []
  } catch {
    selectedPermissions.value = []
  }
  showPermissionModal.value = true
}

const togglePermission = (perm) => {
  const idx = selectedPermissions.value.indexOf(perm)
  if (idx >= 0) selectedPermissions.value.splice(idx, 1)
  else selectedPermissions.value.push(perm)
}

const toggleGroup = (module) => {
  const perms = permissionsGrouped.value[module] ?? []
  const allSelected = perms.every(p => selectedPermissions.value.includes(p))
  if (allSelected) {
    selectedPermissions.value = selectedPermissions.value.filter(p => !perms.includes(p))
  } else {
    perms.forEach(p => { if (!selectedPermissions.value.includes(p)) selectedPermissions.value.push(p) })
  }
}

const toggleAll = () => {
  if (isAllSelected.value) {
    selectedPermissions.value = []
  } else {
    selectedPermissions.value = [...allGroupPermissions.value]
  }
}

const savePermissions = async () => {
  if (!managingRole.value) return
  syncLoading.value = true
  try {
    await syncRolePermissions(managingRole.value.id, selectedPermissions.value)
    toast.success('Permission role berhasil diperbarui')
    showPermissionModal.value = false
    fetchRoles()
  } catch (e) {
    toast.error(e.response?.data?.message ?? 'Gagal menyimpan permission')
  } finally {
    syncLoading.value = false
  }
}

const toggleFormPermission = (perm) => {
  const idx = form.permissions.indexOf(perm)
  if (idx >= 0) form.permissions.splice(idx, 1)
  else form.permissions.push(perm)
}

const toggleFormGroup = (module) => {
  const perms = permissionsGrouped.value[module] ?? []
  const allSelected = perms.every(p => form.permissions.includes(p))
  if (allSelected) {
    form.permissions = form.permissions.filter(p => !perms.includes(p))
  } else {
    perms.forEach(p => { if (!form.permissions.includes(p)) form.permissions.push(p) })
  }
}

const isFormGroupSelected = (module) => {
  const perms = permissionsGrouped.value[module] ?? []
  return perms.length > 0 && perms.every(p => form.permissions.includes(p))
}

const isFormGroupIndeterminate = (module) => {
  const perms = permissionsGrouped.value[module] ?? []
  const selected = perms.filter(p => form.permissions.includes(p))
  return selected.length > 0 && selected.length < perms.length
}

onMounted(() => { fetchRoles(); fetchPermissions() })
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h2 class="text-2xl font-bold text-white">Role & Permission</h2>
        <p class="text-slate-400 text-sm mt-1">Kelola role dan hak akses sistem</p>
      </div>
      <button
        id="btn-create-role"
        @click="openCreate"
        class="flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl font-medium transition-all duration-200 shadow-lg shadow-indigo-600/25"
      >
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Tambah Role
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <div class="animate-spin w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full"></div>
    </div>

    <!-- Role Cards Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <div
        v-for="role in roles" :key="role.id"
        class="bg-slate-800/40 border border-slate-700/50 rounded-2xl overflow-hidden hover:border-slate-600/60 transition-all duration-200 group"
      >
        <!-- Card Header -->
        <div :class="['bg-gradient-to-r p-5', getRoleColor(role.name)]">
          <div class="flex items-start justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
              </div>
              <div>
                <h3 class="text-white font-bold text-base capitalize">{{ role.name.replace('_', ' ') }}</h3>
                <p class="text-white/70 text-xs mt-0.5">{{ role.permissions_count ?? 0 }} permission</p>
              </div>
            </div>
            <div v-if="protectedRoles.includes(role.name)" class="flex items-center gap-1 px-2 py-1 bg-white/20 rounded-full">
              <svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
              </svg>
              <span class="text-white text-xs font-medium">Protected</span>
            </div>
          </div>
        </div>

        <!-- Card Footer -->
        <div class="p-4 flex items-center justify-between">
          <button
            @click="openPermissionManager(role)"
            class="flex items-center gap-2 text-sm text-indigo-400 hover:text-indigo-300 font-medium transition-colors"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
            </svg>
            Kelola Permission
          </button>
          <div class="flex items-center gap-2">
            <button
              @click="openEdit(role)"
              class="p-1.5 rounded-lg bg-slate-700 hover:bg-indigo-600 text-slate-400 hover:text-white transition-all"
              title="Edit role"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
              </svg>
            </button>
            <button
              v-if="!protectedRoles.includes(role.name)"
              @click="confirmDelete(role)"
              class="p-1.5 rounded-lg bg-slate-700 hover:bg-red-600 text-slate-400 hover:text-white transition-all"
              title="Hapus role"
            >
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="roles.length === 0" class="col-span-full flex flex-col items-center justify-center py-20 text-slate-500">
        <svg class="w-12 h-12 mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
        </svg>
        <p>Belum ada role yang dibuat</p>
      </div>
    </div>

    <!-- ── Create/Edit Role Modal ──────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="showModal = false"></div>
          <div class="relative bg-slate-900 border border-slate-700/60 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-5 border-b border-slate-700/50 flex-shrink-0">
              <h3 class="text-lg font-bold text-white">{{ isEditing ? 'Edit Role' : 'Buat Role Baru' }}</h3>
              <button @click="showModal = false" class="p-2 rounded-lg hover:bg-slate-800 text-slate-400 hover:text-white transition-all">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
            <!-- Body -->
            <div class="flex-1 overflow-y-auto p-6 space-y-5">
              <!-- Name -->
              <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Nama Role</label>
                <input
                  v-model="form.name"
                  type="text"
                  id="role-name"
                  placeholder="contoh: warehouse_staff"
                  :class="['w-full px-4 py-2.5 bg-slate-800/80 border rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all', formErrors.name ? 'border-red-500 focus:ring-red-500/50' : 'border-slate-700/50 focus:border-indigo-500 focus:ring-indigo-500/50']"
                />
                <p class="mt-1 text-xs text-slate-500">Gunakan huruf kecil dan underscore, contoh: <code class="text-indigo-400">store_manager</code></p>
                <p v-if="formErrors.name" class="mt-1 text-xs text-red-400">{{ formErrors.name }}</p>
              </div>

              <!-- Permissions Matrix -->
              <div>
                <div class="flex items-center justify-between mb-3">
                  <label class="text-sm font-medium text-slate-300">Permissions</label>
                  <label class="flex items-center gap-2 text-sm text-slate-400 cursor-pointer hover:text-white transition-colors">
                    <input
                      type="checkbox"
                      :checked="Object.values(permissionsGrouped).flat().every(p => form.permissions.includes(p))"
                      @change="Object.values(permissionsGrouped).flat().every(p => form.permissions.includes(p))
                        ? form.permissions = []
                        : form.permissions = Object.values(permissionsGrouped).flat()"
                      class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-indigo-500 cursor-pointer"
                    />
                    Pilih Semua
                  </label>
                </div>
                <p v-if="formErrors.permissions" class="mb-2 text-xs text-red-400">{{ formErrors.permissions }}</p>
                <div class="space-y-3 max-h-72 overflow-y-auto pr-1">
                  <div
                    v-for="(perms, module) in permissionsGrouped" :key="module"
                    class="bg-slate-800/50 border border-slate-700/30 rounded-xl overflow-hidden"
                  >
                    <div class="flex items-center gap-3 px-4 py-2.5 border-b border-slate-700/30 bg-slate-700/20">
                      <input
                        type="checkbox"
                        :checked="isFormGroupSelected(module)"
                        :indeterminate="isFormGroupIndeterminate(module)"
                        @change="toggleFormGroup(module)"
                        class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-indigo-500 cursor-pointer"
                      />
                      <span class="text-sm font-semibold text-slate-200">{{ formatModuleName(module) }}</span>
                      <span class="ml-auto text-xs text-slate-500">{{ perms.filter(p => form.permissions.includes(p)).length }}/{{ perms.length }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-1 p-3">
                      <label
                        v-for="perm in perms" :key="perm"
                        class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-slate-700/30 cursor-pointer group/perm"
                      >
                        <input
                          type="checkbox"
                          :value="perm"
                          :checked="form.permissions.includes(perm)"
                          @change="toggleFormPermission(perm)"
                          class="w-3.5 h-3.5 rounded border-slate-600 bg-slate-800 text-indigo-500 cursor-pointer"
                        />
                        <span class="text-xs text-slate-400 group-hover/perm:text-slate-200 transition-colors capitalize">
                          {{ perm.split(' ')[0] }}
                        </span>
                      </label>
                    </div>
                  </div>
                </div>
                <p class="mt-2 text-xs text-slate-500">
                  <span class="text-indigo-400 font-medium">{{ form.permissions.length }}</span> permission dipilih
                </p>
              </div>
            </div>
            <!-- Footer -->
            <div class="flex gap-3 px-6 py-5 border-t border-slate-700/50 flex-shrink-0">
              <button @click="showModal = false" class="flex-1 px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl font-medium transition-all">Batal</button>
              <button
                @click="submitForm"
                :disabled="formLoading"
                class="flex-1 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl font-medium transition-all flex items-center justify-center gap-2"
              >
                <div v-if="formLoading" class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></div>
                {{ isEditing ? 'Simpan Perubahan' : 'Buat Role' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ── Permission Manager Modal ───────────────────────────────────────── -->
    <Teleport to="body">
      <Transition name="modal">
        <div v-if="showPermissionModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="showPermissionModal = false"></div>
          <div class="relative bg-slate-900 border border-slate-700/60 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-5 border-b border-slate-700/50 flex-shrink-0">
              <div>
                <h3 class="text-lg font-bold text-white">Kelola Permission</h3>
                <p class="text-slate-400 text-xs mt-0.5 capitalize">Role: <span class="text-indigo-400 font-semibold">{{ managingRole?.name?.replace('_', ' ') }}</span></p>
              </div>
              <button @click="showPermissionModal = false" class="p-2 rounded-lg hover:bg-slate-800 text-slate-400 hover:text-white transition-all">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>
            <!-- Search + Select All -->
            <div class="px-6 py-4 border-b border-slate-700/30 flex-shrink-0 space-y-3">
              <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input
                  v-model="permissionSearch"
                  type="text"
                  placeholder="Cari permission atau modul..."
                  class="w-full pl-10 pr-4 py-2 bg-slate-800/60 border border-slate-700/50 rounded-xl text-white placeholder-slate-400 text-sm focus:outline-none focus:border-indigo-500 transition-all"
                />
              </div>
              <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-slate-400 cursor-pointer hover:text-white transition-colors">
                  <input
                    type="checkbox"
                    :checked="isAllSelected"
                    @change="toggleAll"
                    class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-indigo-500 cursor-pointer"
                  />
                  Pilih Semua Permission
                </label>
                <span class="text-xs text-slate-500">
                  <span class="text-indigo-400 font-semibold">{{ selectedPermissions.length }}</span> / {{ allGroupPermissions.length }} dipilih
                </span>
              </div>
            </div>
            <!-- Permission Grid -->
            <div class="flex-1 overflow-y-auto p-6 space-y-3">
              <div
                v-for="(perms, module) in filteredPermissionsGrouped" :key="module"
                class="bg-slate-800/50 border border-slate-700/30 rounded-xl overflow-hidden"
              >
                <div class="flex items-center gap-3 px-4 py-2.5 bg-slate-700/20 border-b border-slate-700/30">
                  <input
                    type="checkbox"
                    :checked="isGroupSelected(module)"
                    :indeterminate="isGroupIndeterminate(module)"
                    @change="toggleGroup(module)"
                    class="w-4 h-4 rounded border-slate-600 bg-slate-800 text-indigo-500 cursor-pointer"
                  />
                  <span class="text-sm font-semibold text-slate-200">{{ formatModuleName(module) }}</span>
                  <div class="ml-auto flex items-center gap-2">
                    <div class="h-1.5 bg-slate-700 rounded-full w-16 overflow-hidden">
                      <div
                        class="h-full bg-indigo-500 rounded-full transition-all"
                        :style="{ width: `${(perms.filter(p => selectedPermissions.includes(p)).length / perms.length) * 100}%` }"
                      ></div>
                    </div>
                    <span class="text-xs text-slate-500">{{ perms.filter(p => selectedPermissions.includes(p)).length }}/{{ perms.length }}</span>
                  </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-1 p-3">
                  <label
                    v-for="perm in perms" :key="perm"
                    class="flex items-center gap-2 px-2.5 py-2 rounded-lg hover:bg-slate-700/40 cursor-pointer transition-colors group/item"
                  >
                    <input
                      type="checkbox"
                      :value="perm"
                      v-model="selectedPermissions"
                      class="w-3.5 h-3.5 rounded border-slate-600 bg-slate-800 text-indigo-500 cursor-pointer"
                    />
                    <span class="text-xs text-slate-400 group-hover/item:text-slate-200 transition-colors capitalize">
                      {{ perm.split(' ')[0] }}
                    </span>
                  </label>
                </div>
              </div>

              <div v-if="Object.keys(filteredPermissionsGrouped).length === 0" class="text-center py-10 text-slate-500">
                <p>Tidak ada permission ditemukan untuk "<span class="text-slate-300">{{ permissionSearch }}</span>"</p>
              </div>
            </div>
            <!-- Footer -->
            <div class="flex gap-3 px-6 py-5 border-t border-slate-700/50 flex-shrink-0">
              <button @click="showPermissionModal = false" class="flex-1 px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl font-medium transition-all">Batal</button>
              <button
                @click="savePermissions"
                :disabled="syncLoading"
                class="flex-1 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white rounded-xl font-medium transition-all flex items-center justify-center gap-2"
              >
                <div v-if="syncLoading" class="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full"></div>
                Simpan Permission
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Delete Modal -->
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
                <h3 class="text-white font-semibold">Hapus Role</h3>
                <p class="text-slate-400 text-sm mt-0.5">Pastikan tidak ada user yang memakai role ini</p>
              </div>
            </div>
            <p class="text-slate-300 text-sm mb-6">
              Yakin menghapus role <span class="text-white font-semibold capitalize">{{ deletingRole?.name?.replace('_', ' ') }}</span>?
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
</style>
