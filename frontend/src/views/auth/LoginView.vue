<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../../store/auth'
import { useToast } from 'vue-toastification'
import AuthCard from '../../components/ui/AuthCard.vue'
import BaseInput from '../../components/ui/BaseInput.vue'
import BaseButton from '../../components/ui/BaseButton.vue'

const router = useRouter()
const toast = useToast()
const { login, isAuthLoading } = useAuth()

const email = ref('')
const password = ref('')

const emailError = ref('')
const passwordError = ref('')

const validateForm = () => {
  let isValid = true
  emailError.value = ''
  passwordError.value = ''

  if (!email.value) {
    emailError.value = 'Email wajib diisi'
    isValid = false
  } else if (!/\S+@\S+\.\S+/.test(email.value)) {
    emailError.value = 'Format email tidak valid'
    isValid = false
  }

  if (!password.value) {
    passwordError.value = 'Password wajib diisi'
    isValid = false
  } else if (password.value.length < 6) {
    passwordError.value = 'Password minimal 6 karakter'
    isValid = false
  }

  return isValid
}

const handleLogin = async () => {
  if (!validateForm()) return

  const result = await login(email.value, password.value)

  if (result.success) {
    toast.success('Login berhasil! Selamat bekerja.')
    router.push('/dashboard')
  } else {
    toast.error(result.message || 'Kredensial tidak valid.')
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center p-6 relative overflow-hidden font-sans" style="background: linear-gradient(135deg, #eaf6f6 0%, #d4efed 30%, #c8e6e3 60%, #b8ddd9 100%);">
    <!-- Ambient decorative blobs -->
    <div class="absolute top-1/4 left-1/4 w-[500px] h-[500px] bg-[#1a9e8f]/8 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute bottom-1/4 right-1/4 w-[500px] h-[500px] bg-[#0d3b66]/5 rounded-full blur-[120px] pointer-events-none"></div>
    
    <!-- Subtle grid pattern -->
    <div class="absolute inset-0 bg-[linear-gradient(to_right,#0d3b66_1px,transparent_1px),linear-gradient(to_bottom,#0d3b66_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_50%,#000_70%,transparent_100%)] opacity-[0.03]"></div>

    <AuthCard
      title="Login Kasir"
      subtitle="Selamat datang di Restoku POS. Silakan masuk untuk mengelola transaksi."
    >
      <form @submit.prevent="handleLogin" class="space-y-1">
        <!-- Input Email -->
        <BaseInput
          v-model="email"
          type="email"
          label="Alamat Email"
          placeholder=" "
          required
          :error="emailError"
          :disabled="isAuthLoading"
        />

        <!-- Input Password -->
        <BaseInput
          v-model="password"
          type="password"
          label="Password"
          placeholder=" "
          required
          :error="passwordError"
          :disabled="isAuthLoading"
        />

        <!-- Remember Me & Forgot Password link -->
        <div class="flex items-center justify-between pb-4 pt-1">
          <label class="flex items-center cursor-pointer select-none text-xs text-gray-500 hover:text-gray-700">
            <input
              type="checkbox"
              class="w-4 h-4 rounded border-gray-300 bg-gray-50 text-[#1a9e8f] focus:ring-[#1a9e8f]/30 focus:ring-2 focus:ring-offset-0 mr-2 cursor-pointer accent-[#1a9e8f]"
            />
            Ingat Saya
          </label>
          <router-link
            to="/forgot-password"
            class="text-xs font-semibold text-[#1a9e8f] hover:text-[#147a83] transition-colors duration-200"
          >
            Lupa Password?
          </router-link>
        </div>

        <!-- Submit Button -->
        <BaseButton type="submit" :loading="isAuthLoading">
          Masuk ke Aplikasi
        </BaseButton>

        <!-- Redirect to Register -->
        <p class="mt-6 text-center text-xs text-gray-500">
          Belum terdaftar? 
          <router-link
            to="/register"
            class="font-semibold text-[#1a9e8f] hover:text-[#147a83] transition-colors duration-200 ml-1"
          >
            Daftar Akun Baru
          </router-link>
        </p>
      </form>
    </AuthCard>
  </div>
</template>
