<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '../../store/auth'
import { useToast } from 'vue-toastification'
import AuthCard from '../../components/ui/AuthCard.vue'
import BaseInput from '../../components/ui/BaseInput.vue'
import BaseButton from '../../components/ui/BaseButton.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const { resetPassword, isAuthLoading } = useAuth()

const email = ref('')
const token = ref('')
const password = ref('')
const passwordConfirmation = ref('')

const passwordError = ref('')
const confirmError = ref('')
const isSubmitted = ref(false)

onMounted(() => {
  // Ambil parameter email dan token dari URL query string
  email.value = route.query.email || ''
  token.value = route.query.token || ''

  if (!token.value || !email.value) {
    toast.error('Token atau email reset password tidak valid. Silakan lakukan request ulang.')
  }
})

const validateForm = () => {
  let isValid = true
  passwordError.value = ''
  confirmError.value = ''

  if (!password.value) {
    passwordError.value = 'Password baru wajib diisi'
    isValid = false
  } else if (password.value.length < 8) {
    passwordError.value = 'Password minimal 8 karakter'
    isValid = false
  }

  if (!passwordConfirmation.value) {
    confirmError.value = 'Konfirmasi password wajib diisi'
    isValid = false
  } else if (password.value !== passwordConfirmation.value) {
    confirmError.value = 'Konfirmasi password tidak cocok'
    isValid = false
  }

  return isValid
}

const handleSubmit = async () => {
  if (!validateForm()) return
  if (!token.value || !email.value) {
    toast.error('Gagal memproses reset: parameter tidak lengkap.')
    return
  }

  const result = await resetPassword(
    email.value,
    password.value,
    passwordConfirmation.value,
    token.value
  )

  if (result.success) {
    isSubmitted.value = true
    toast.success('Password Anda berhasil diperbarui! Silakan login kembali.')
    
    // Redirect ke login setelah 3 detik
    setTimeout(() => {
      router.push('/login')
    }, 3000)
  } else {
    toast.error(result.message || 'Gagal mengubah password.')
  }
}
</script>

<template>
  <div class="min-h-screen bg-[#0B0F19] flex items-center justify-center p-6 relative overflow-hidden font-sans">
    <!-- Ambient mesh background gradients -->
    <div class="absolute top-1/4 left-1/4 w-[500px] h-[500px] bg-emerald-500/5 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-1/4 right-1/4 w-[500px] h-[500px] bg-teal-500/5 rounded-full blur-[120px] pointer-events-none"></div>
    
    <!-- Cyberpunk grid background lines -->
    <div class="absolute inset-0 bg-[linear-gradient(to_right,#1e293b_1px,transparent_1px),linear-gradient(to_bottom,#1e293b_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_50%,#000_70%,transparent_100%)] opacity-[0.15]"></div>

    <AuthCard
      title="Reset Password"
      :subtitle="isSubmitted ? 'Password berhasil diperbarui!' : 'Masukkan kata sandi baru Anda.'"
    >
      <!-- Success State -->
      <div v-if="isSubmitted" class="text-center space-y-6">
        <div class="w-16 h-16 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-full flex items-center justify-center mx-auto">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="2.5"
            stroke="currentColor"
            class="w-8 h-8"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
          </svg>
        </div>
        
        <p class="text-sm text-slate-300 leading-relaxed">
          Atur ulang kata sandi berhasil. Anda akan dialihkan ke halaman login secara otomatis dalam beberapa detik.
        </p>

        <router-link
          to="/login"
          class="inline-block font-semibold text-emerald-400 hover:text-emerald-300 transition-colors duration-200 text-sm"
        >
          Masuk Sekarang (Manual)
        </router-link>
      </div>

      <!-- Form State -->
      <form v-else @submit.prevent="handleSubmit" class="space-y-1">
        <!-- Tampilkan email terkunci untuk info kasir -->
        <div class="mb-4 p-3 bg-slate-900/60 border border-slate-800 rounded-xl flex items-center justify-between text-xs">
          <span class="text-slate-400">Email Kasir:</span>
          <span class="text-slate-200 font-semibold">{{ email || 'Mengambil data...' }}</span>
        </div>

        <!-- Input Password Baru -->
        <BaseInput
          v-model="password"
          type="password"
          label="Password Baru"
          placeholder=" "
          required
          :error="passwordError"
          :disabled="isAuthLoading || !token"
        />

        <!-- Konfirmasi Password -->
        <BaseInput
          v-model="passwordConfirmation"
          type="password"
          label="Konfirmasi Password Baru"
          placeholder=" "
          required
          :error="confirmError"
          :disabled="isAuthLoading || !token"
        />

        <!-- Submit Button -->
        <div class="pt-3">
          <BaseButton type="submit" :loading="isAuthLoading" :disabled="!token">
            Simpan Password Baru
          </BaseButton>
        </div>
      </form>
    </AuthCard>
  </div>
</template>
