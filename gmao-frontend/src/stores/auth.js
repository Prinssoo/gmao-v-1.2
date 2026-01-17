import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'
import { getCsrfCookie } from '@/services/csrf'
import { updateAbility, resetAbility } from '@/services/ability'

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref(null)
  const isLoading = ref(false)
  const isAuthenticated = computed(() => !!user.value)

  // Getters
  const currentSite = computed(() => user.value?.current_site)
  const roles = computed(() => user.value?.roles || [])
  const permissions = computed(() => user.value?.permissions || [])

  // Actions
  async function login(email, password) {
    isLoading.value = true
    try {
      await getCsrfCookie()
      const response = await api.post('/login', { email, password })
      user.value = response.data.user
      updateAbility(response.data.user.permissions)
      return { success: true }
    } catch (error) {
      console.error('Login error:', error)
      return {
        success: false,
        message: error.response?.data?.message || 'Erreur de connexion',
      }
    } finally {
      isLoading.value = false
    }
  }

  async function logout() {
    try {
      await api.post('/logout')
    } catch (error) {
      console.error('Erreur logout:', error)
    } finally {
      user.value = null
      resetAbility()
    }
  }

  async function fetchUser() {
    // Ne pas appeler si déjà en chargement
    if (isLoading.value) return
    
    isLoading.value = true
    try {
      await getCsrfCookie()
      const response = await api.get('/user')
      user.value = response.data.user
      updateAbility(response.data.user.permissions)
    } catch (error) {
      // Silencieux - l'utilisateur n'est simplement pas connecté
      user.value = null
      resetAbility()
    } finally {
      isLoading.value = false
    }
  }

  async function switchSite(siteId) {
    try {
      const response = await api.post('/switch-site', { site_id: siteId })
      user.value = response.data.user
      updateAbility(response.data.user.permissions)
      return { success: true }
    } catch (error) {
      return {
        success: false,
        message: error.response?.data?.message || 'Erreur lors du changement de site',
      }
    }
  }

  function hasRole(roleName) {
    return roles.value.includes(roleName)
  }

  function hasPermission(permissionName) {
    return permissions.value.includes(permissionName)
  }

  return {
    // State
    user,
    isLoading,
    isAuthenticated,
    // Getters
    currentSite,
    roles,
    permissions,
    // Actions
    login,
    logout,
    fetchUser,
    switchSite,
    hasRole,
    hasPermission,
  }
})
