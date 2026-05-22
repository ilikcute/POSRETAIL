import { reactive, computed } from 'vue'
import api from '../services/api'

// Ambil state awal dari localStorage jika tersedia
const initialToken = localStorage.getItem('auth_token') || null
let initialUser = null
try {
  const userStr = localStorage.getItem('auth_user')
  if (userStr) {
    initialUser = JSON.parse(userStr)
  }
} catch (e) {
  localStorage.removeItem('auth_user')
}

// State Reaktif Autentikasi
const state = reactive({
  token: initialToken,
  user: initialUser,
  loading: false,
  error: null
})

// Getters
const isAuthenticated = computed(() => !!state.token)
const currentUser = computed(() => state.user)
const authError = computed(() => state.error)
const isAuthLoading = computed(() => state.loading)

// Helper untuk mengecek role Spatie
const hasRole = (roleName) => {
  if (!state.user || !state.user.roles) return false
  return state.user.roles.some(role => role.name === roleName)
}

// Helper untuk mengecek permission Spatie
const hasPermission = (permissionName) => {
  if (!state.user) return false
  
  // Super Admin bypass
  if (hasRole('super_admin')) return true
  
  // Cek dari permissions di dalam roles
  if (state.user.roles) {
    const hasIt = state.user.roles.some(role => 
      role.permissions && role.permissions.some(p => p.name === permissionName)
    )
    if (hasIt) return true
  }
  
  // Cek dari direct permissions milik user (jika ada)
  if (state.user.permissions) {
    return state.user.permissions.some(p => p.name === permissionName)
  }
  
  return false
}

// Actions
const login = async (email, password) => {
  state.loading = true
  state.error = null
  try {
    const response = await api.post('/login', { email, password })
    const { access_token, user } = response.data.data
    
    state.token = access_token
    state.user = user
    
    localStorage.setItem('auth_token', access_token)
    localStorage.setItem('auth_user', JSON.stringify(user))
    
    return { success: true }
  } catch (error) {
    state.error = error.response?.data?.message || 'Login gagal. Silakan coba lagi.'
    return { success: false, message: state.error }
  } finally {
    state.loading = false
  }
}

const register = async (name, email, password, password_confirmation) => {
  state.loading = true
  state.error = null
  try {
    const response = await api.post('/register', {
      name,
      email,
      password,
      password_confirmation
    })
    const { access_token, user } = response.data.data
    
    state.token = access_token
    state.user = user
    
    localStorage.setItem('auth_token', access_token)
    localStorage.setItem('auth_user', JSON.stringify(user))
    
    return { success: true }
  } catch (error) {
    state.error = error.response?.data?.message || 'Registrasi gagal. Silakan coba lagi.'
    return { success: false, message: state.error }
  } finally {
    state.loading = false
  }
}

const logout = async () => {
  state.loading = true
  try {
    await api.post('/logout')
  } catch (error) {
    console.error('Logout error on backend:', error)
  } finally {
    state.token = null
    state.user = null
    localStorage.removeItem('auth_token')
    localStorage.removeItem('auth_user')
    state.loading = false
  }
}

const forgotPassword = async (email) => {
  state.loading = true
  state.error = null
  try {
    const response = await api.post('/forgot-password', { email })
    return { success: true, message: response.data.message }
  } catch (error) {
    state.error = error.response?.data?.message || 'Gagal mengirim email reset password.'
    return { success: false, message: state.error }
  } finally {
    state.loading = false
  }
}

const resetPassword = async (email, password, password_confirmation, token) => {
  state.loading = true
  state.error = null
  try {
    const response = await api.post('/reset-password', {
      email,
      password,
      password_confirmation,
      token
    })
    return { success: true, message: response.data.message }
  } catch (error) {
    state.error = error.response?.data?.message || 'Gagal mengatur ulang password.'
    return { success: false, message: state.error }
  } finally {
    state.loading = false
  }
}

const fetchUser = async () => {
  if (!state.token) return null
  try {
    const response = await api.get('/me')
    const user = response.data.data
    state.user = user
    localStorage.setItem('auth_user', JSON.stringify(user))
    return user
  } catch (error) {
    // 401 interceptor otomatis menangani jika token expired
    return null
  }
}

export const useAuth = () => {
  return {
    state,
    isAuthenticated,
    currentUser,
    authError,
    isAuthLoading,
    hasRole,
    hasPermission,
    login,
    register,
    logout,
    forgotPassword,
    resetPassword,
    fetchUser
  }
}
