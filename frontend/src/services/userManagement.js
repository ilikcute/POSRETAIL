import api from './api'

// ── User Management ───────────────────────────────────────────────────────────

export const getUsers = (params = {}) => api.get('/users', { params })
export const getUser = (id) => api.get(`/users/${id}`)
export const createUser = (data) => api.post('/users', data)
export const updateUser = (id, data) => api.put(`/users/${id}`, data)
export const deleteUser = (id) => api.delete(`/users/${id}`)
export const toggleUserActive = (id) => api.patch(`/users/${id}/toggle-active`)
export const syncUserRoles = (id, roles) => api.post(`/users/${id}/sync-roles`, { roles })

// ── Role Management ───────────────────────────────────────────────────────────

export const getRoles = () => api.get('/roles')
export const getRole = (id) => api.get(`/roles/${id}`)
export const createRole = (data) => api.post('/roles', data)
export const updateRole = (id, data) => api.put(`/roles/${id}`, data)
export const deleteRole = (id) => api.delete(`/roles/${id}`)
export const getAllPermissions = () => api.get('/roles/permissions/all')
export const syncRolePermissions = (id, permissions) =>
  api.post(`/roles/${id}/sync-permissions`, { permissions })
