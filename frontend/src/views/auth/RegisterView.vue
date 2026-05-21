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
const { register, isAuthLoading } = useAuth()

const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')

const nameError = ref('')
const emailError = ref('')
const passwordError = ref('')
const confirmError = ref('')

const validateForm = () => {
  let isValid = true
  nameError.value = ''
  emailError.value = ''
  passwordError.value = ''
  confirmError.value = ''

  if (!name.value) {
    nameError.value = 'Nama lengkap wajib diisi'
    isValid = false
  } else if (name.value.length < 3) {
    nameError.value = 'Nama minimal 3 karakter'
    isValid = false
  }

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

const handleRegister = async () => {
  if (!validateForm()) return

  const result = await register(
    name.value,
    email.value,
    password.value,
    passwordConfirmation.value
  )

  if (result.success) {
    toast.success('Registrasi berhasil! Akun Anda aktif sebagai cashier.')
    router.push('/dashboard')
  } else {
    toast.error(result.message || 'Registrasi gagal. Coba lagi.')
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
      title="Daftar Kasir Baru"
      subtitle="Buat akun kasir Anda untuk memulai operasional POS."
    >
      <form @submit.prevent="handleRegister" class="space-y-1">
        <!-- Input Nama -->
        <BaseInput
          v-model="name"
          type="text"
          label="Nama Lengkap"
          placeholder=" "
          required
          :error="nameError"
          :disabled="isAuthLoading"
        />

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
          label="Password (min. 8 karakter)"
          placeholder=" "
          required
          :error="passwordError"
          :disabled="isAuthLoading"
        />

        <!-- Konfirmasi Password -->
        <BaseInput
          v-model="passwordConfirmation"
          type="password"
          label="Konfirmasi Password"
          placeholder=" "
          required
          :error="confirmError"
          :disabled="isAuthLoading"
        />

        <!-- Submit Button -->
        <div class="pt-3">
          <BaseButton type="submit" :loading="isAuthLoading">
            Daftar Sekarang
          </BaseButton>
        </div>

        <!-- Redirect to Login -->
        <p class="mt-6 text-center text-xs text-gray-500">
          Sudah punya akun? 
          <router-link
            to="/login"
            class="font-semibold text-[#1a9e8f] hover:text-[#147a83] transition-colors duration-200 ml-1"
          >
            Masuk Sekarang
          </router-link>
        </p>
      </form>
    </AuthCard>
  </div>
</template>
