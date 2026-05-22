<script setup>
import { ref, reactive, computed } from 'vue'
import { useAuth } from '@/store/auth'
import axios from 'axios'
import { useToast } from 'vue-toastification'

const { currentUser, fetchUser } = useAuth()
const toast = useToast()

const form = reactive({
  name: currentUser.value?.name || '',
  email: currentUser.value?.email || '',
  password: '',
  password_confirmation: ''
})

const errors = reactive({
  name: null,
  email: null,
  password: null,
  password_confirmation: null
})

const validate = () => {
  let valid = true
  if (!form.name.trim()) { errors.name = 'Nama tidak boleh kosong.'; valid = false } else { errors.name = null }
  const emailRegex = /^[^@\s]+@[^@\s]+\.[^@\s]+$/
  if (!form.email.trim()) { errors.email = 'Email tidak boleh kosong.'; valid = false }
  else if (!emailRegex.test(form.email)) { errors.email = 'Format email tidak valid.'; valid = false }
  else { errors.email = null }
  if (form.password) {
    if (form.password.length < 8) { errors.password = 'Password minimal 8 karakter.'; valid = false } else { errors.password = null }
    if (form.password !== form.password_confirmation) { errors.password_confirmation = 'Konfirmasi password tidak cocok.'; valid = false } else { errors.password_confirmation = null }
  } else { errors.password = null; errors.password_confirmation = null }
  return valid
}

const submitting = ref(false)

const submit = async () => {
  if (!validate()) return
  submitting.value = true
  try {
    const payload = { name: form.name, email: form.email }
    if (form.password) {
      payload.password = form.password
      payload.password_confirmation = form.password_confirmation
    }
    await axios.put(`/api/users/${currentUser.value.id}`, payload)
    toast.success('Profil berhasil diperbarui')
    await fetchUser()
    form.name = currentUser.value.name
    form.email = currentUser.value.email
    form.password = ''
    form.password_confirmation = ''
  } catch (e) {
    if (e.response && e.response.data && e.response.data.errors) {
      const apiErrors = e.response.data.errors
      Object.keys(apiErrors).forEach(k => { errors[k] = apiErrors[k][0] })
    } else {
      toast.error('Terjadi kesalahan saat memperbarui profil')
    }
  } finally {
    submitting.value = false
  }
}

const roleBadgeColor = computed(() => {
  const role = currentUser.value?.roles?.[0]?.name ?? ''
  switch (role) {
    case 'super_admin': return 'bg-red-500'
    case 'manager': return 'bg-blue-500'
    case 'cashier': return 'bg-green-500'
    default: return 'bg-gray-500'
  }
})
</script>

<template>
  <div class="p-6 max-w-2xl mx-auto">
    <div class="bg-gray-800/70 backdrop-blur-lg rounded-xl shadow-xl p-8 space-y-6">
      <div class="flex items-center space-x-4">
        <div class="w-16 h-16 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-2xl font-bold">
          {{ (form.name?.charAt(0) || 'U').toUpperCase() }}
        </div>
        <div>
          <h2 class="text-xl font-semibold text-white">{{ form.name }}</h2>
          <span :class="['px-2 py-1 rounded text-xs font-medium', roleBadgeColor]">
            {{ currentUser.value?.roles?.[0]?.display_name || currentUser.value?.roles?.[0]?.name }}
          </span>
        </div>
      </div>
      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-200 mb-1">Nama</label>
          <input v-model="form.name" type="text" class="w-full rounded-md bg-gray-700/30 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-indigo-400 px-3 py-2" />
          <p v-if="errors.name" class="mt-1 text-xs text-red-400">{{ errors.name }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-200 mb-1">Email</label>
          <input v-model="form.email" type="email" class="w-full rounded-md bg-gray-700/30 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-indigo-400 px-3 py-2" />
          <p v-if="errors.email" class="mt-1 text-xs text-red-400">{{ errors.email }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-200 mb-1">Password Baru (opsional)</label>
          <input v-model="form.password" type="password" autocomplete="new-password" class="w-full rounded-md bg-gray-700/30 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-indigo-400 px-3 py-2" />
          <p v-if="errors.password" class="mt-1 text-xs text-red-400">{{ errors.password }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-200 mb-1">Konfirmasi Password</label>
          <input v-model="form.password_confirmation" type="password" autocomplete="new-password" class="w-full rounded-md bg-gray-700/30 text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-indigo-400 px-3 py-2" />
          <p v-if="errors.password_confirmation" class="mt-1 text-xs text-red-400">{{ errors.password_confirmation }}</p>
        </div>
        <div class="flex justify-end">
          <button type="submit" :disabled="submitting" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded transition-colors disabled:opacity-50">
            {{ submitting ? 'Menyimpan…' : 'Simpan Perubahan' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
/* No extra CSS needed – Tailwind handles styling */
</style>
