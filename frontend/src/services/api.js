import axios from 'axios'

// Konfigurasi dinamis untuk base URL API
// Pada masa development (Vite port 5173), request diarahkan ke Laravel dev server (port 8000)
// Pada masa production, request menggunakan path relatif /api karena frontend berada di domain yang sama
const api = axios.create({
  baseURL: import.meta.env.DEV ? 'http://localhost:8000/api' : '/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// Request Interceptor untuk melampirkan Bearer Token
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Response Interceptor untuk mendeteksi token expired / 401 Unauthorized
api.interceptors.response.use(
  (response) => {
    return response
  },
  (error) => {
    if (error.response && error.response.status === 401) {
      // Hapus session jika tidak terotorisasi
      localStorage.removeItem('auth_token')
      localStorage.removeItem('auth_user')
      
      // Redirect ke login jika bukan di halaman auth
      const currentPath = window.location.pathname
      if (!currentPath.includes('/login') && !currentPath.includes('/register') && !currentPath.includes('/forgot-password') && !currentPath.includes('/reset-password')) {
        window.location.href = '/app/login'
      }
    }
    return Promise.reject(error)
  }
)

export default api
