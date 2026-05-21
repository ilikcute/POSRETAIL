<script setup>
import { ref } from 'vue'
import { useAuth } from '../../store/auth'
import { useToast } from 'vue-toastification'
import AuthCard from '../../components/ui/AuthCard.vue'
import BaseInput from '../../components/ui/BaseInput.vue'
import BaseButton from '../../components/ui/BaseButton.vue'

const toast = useToast()
const { forgotPassword, isAuthLoading } = useAuth()

const email = ref('')
const emailError = ref('')
const isSubmitted = ref(false)

const validateForm = () => {
  emailError.value = ''
  
  if (!email.value) {
    emailError.value = 'Email wajib diisi'
    return false
  } else if (!/\S+@\S+\.\S+/.test(email.value)) {
    emailError.value = 'Format email tidak valid'
    return false
  }
  
  return true
}

const handleSubmit = async () => {
  if (!validateForm()) return

  const result = await forgotPassword(email.value)

  if (result.success) {
    isSubmitted.value = true
    toast.success('Link reset password berhasil dikirim ke email Anda.')
  } else {
    toast.error(result.message || 'Gagal mengirim link reset password.')
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
      title="Lupa Password?"
      :subtitle="isSubmitted ? 'Instruksi terkirim!' : 'Masukkan alamat email Anda untuk mendapatkan tautan atur ulang kata sandi.'"
    >
      <!-- Success State -->
      <div v-if="isSubmitted" class="text-center space-y-6">
        <div class="w-16 h-16 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-full flex items-center justify-center mx-auto animate-bounce">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="2"
            stroke="currentColor"
            class="w-8 h-8"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
          </svg>
        </div>
        
        <p class="text-sm text-slate-300 leading-relaxed">
          Kami telah mengirimkan instruksi pengaturan ulang kata sandi ke <span class="text-emerald-400 font-semibold">{{ email }}</span>. Silakan periksa kotak masuk atau spam email Anda.
        </p>

        <router-link
          to="/login"
          class="inline-block font-semibold text-emerald-400 hover:text-emerald-300 transition-colors duration-200 text-sm"
        >
          Kembali ke Login
        </router-link>
      </div>

      <!-- Form State -->
      <form v-else @submit.prevent="handleSubmit" class="space-y-4">
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

        <!-- Submit Button -->
        <div class="pt-2">
          <BaseButton type="submit" :loading="isAuthLoading">
            Kirim Link Reset Password
          </BaseButton>
        </div>

        <!-- Redirect to Login -->
        <p class="mt-6 text-center text-xs text-slate-400">
          Ingat kata sandi Anda? 
          <router-link
            to="/login"
            class="font-semibold text-emerald-400 hover:text-emerald-300 transition-colors duration-200 ml-1"
          >
            Kembali ke Login
          </router-link>
        </p>
      </form>
    </AuthCard>
  </div>
</template>
