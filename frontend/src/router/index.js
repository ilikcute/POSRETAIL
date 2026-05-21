import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '../store/auth'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('../views/auth/LoginView.vue'),
    meta: { guest: true }
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('../views/auth/RegisterView.vue'),
    meta: { guest: true }
  },
  {
    path: '/forgot-password',
    name: 'forgot-password',
    component: () => import('../views/auth/ForgotPasswordView.vue'),
    meta: { guest: true }
  },
  {
    path: '/reset-password',
    name: 'reset-password',
    component: () => import('../views/auth/ResetPasswordView.vue'),
    meta: { guest: true }
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('../views/dashboard/DashboardView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/dashboard'
  }
]

const router = createRouter({
  history: createWebHistory('/app/'), // Base path diatur ke /app/ sesuai dengan deployment
  routes
})

// Navigation Guards
router.beforeEach(async (to, from, next) => {
  const { isAuthenticated, currentUser, fetchUser } = useAuth()
  
  // Jika token terdeteksi di localStorage tapi state user masih kosong (misal setelah refresh), ambil data user dulu
  if (localStorage.getItem('auth_token') && !currentUser.value) {
    await fetchUser()
  }

  const isAuth = isAuthenticated.value

  if (to.matched.some(record => record.meta.requiresAuth)) {
    if (!isAuth) {
      // Belum login, arahkan ke login
      next({ name: 'login' })
    } else {
      next()
    }
  } else if (to.matched.some(record => record.meta.guest)) {
    if (isAuth) {
      // Sudah login, cegah masuk ke halaman login/register dan lempar ke dashboard
      next({ name: 'dashboard' })
    } else {
      next()
    }
  } else {
    next()
  }
})

export default router
