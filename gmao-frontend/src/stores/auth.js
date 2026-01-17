import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api, { setAuthToken, clearAuth } from '@/services/api'
import { getCsrfCookie } from '@/services/csrf'
import { updateAbility, resetAbility } from '@/services/ability'

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref(null)
  const token = ref(localStorage.getItem('auth_token') || null)
  const isLoading = ref(false)
  const isInitialized = ref(false)

  // Getters
  const isAuthenticated = computed(() => !!user.value && !!token.value)
  const currentSite = computed(() => user.value?.current_site)
  const currentSiteId = computed(() => user.value?.current_site_id)
  const roles = computed(() => user.value?.roles || [])
  const permissions = computed(() => user.value?.permissions || [])
  const userName = computed(() => user.value?.name || '')
  const userEmail = computed(() => user.value?.email || '')

  // Actions
  async function login(email, password) {
    isLoading.value = true
    try {
      await getCsrfCookie()
      const response = await api.post('/login', { email, password })
      
      const { user: userData, token: authToken } = response.data
      
      // Sauvegarder le token
      if (authToken) {
        token.value = authToken
        setAuthToken(authToken)
      }
      
      // Sauvegarder l'utilisateur
      user.value = userData
      localStorage.setItem('user', JSON.stringify(userData))
      
      // Mettre à jour les permissions
      updateAbility(userData.permissions)
      
      return { success: true, user: userData }
    } catch (error) {
      console.error('Login error:', error)
      return {
        success: false,
        message: error.response?.data?.message || 'Identifiants incorrects',
        errors: error.response?.data?.errors
      }
    } finally {
      isLoading.value = false
    }
  }

  async function logout() {
    try {
      await api.post('/logout')
    } catch (error) {
      console.error('Logout error:', error)
    } finally {
      // Toujours nettoyer localement
      user.value = null
      token.value = null
      clearAuth()
      resetAbility()
    }
  }

  async function fetchUser() {
    if (isLoading.value) return
    if (!token.value) {
      isInitialized.value = true
      return
    }
    
    isLoading.value = true
    try {
      await getCsrfCookie()
      const response = await api.get('/user')
      
      user.value = response.data.user || response.data
      localStorage.setItem('user', JSON.stringify(user.value))
      updateAbility(user.value.permissions)
    } catch (error) {
      // Token invalide ou expiré
      user.value = null
      token.value = null
      clearAuth()
      resetAbility()
    } finally {
      isLoading.value = false
      isInitialized.value = true
    }
  }

  async function switchSite(siteId) {
    try {
      const response = await api.post('/switch-site', { site_id: siteId })
      
      user.value = response.data.user
      localStorage.setItem('user', JSON.stringify(user.value))
      updateAbility(user.value.permissions)
      
      return { success: true, site: user.value.current_site }
    } catch (error) {
      return {
        success: false,
        message: error.response?.data?.message || 'Erreur lors du changement de site'
      }
    }
  }

  function hasRole(roleName) {
    if (Array.isArray(roleName)) {
      return roleName.some(r => roles.value.includes(r))
    }
    return roles.value.includes(roleName)
  }

  function hasPermission(permissionName) {
    if (Array.isArray(permissionName)) {
      return permissionName.some(p => permissions.value.includes(p))
    }
    return permissions.value.includes(permissionName)
  }

  function hasAnyPermission(permissionNames) {
    return permissionNames.some(p => permissions.value.includes(p))
  }

  function hasAllPermissions(permissionNames) {
    return permissionNames.every(p => permissions.value.includes(p))
  }

  function isSuperAdmin() {
    return hasRole(['SuperAdmin', 'super-admin'])
  }

  function canAccessSite(siteId) {
    return isSuperAdmin() || currentSiteId.value === siteId
  }

  // Initialiser depuis le localStorage
  function initFromStorage() {
    const storedUser = localStorage.getItem('user')
    if (storedUser && token.value) {
      try {
        user.value = JSON.parse(storedUser)
        updateAbility(user.value.permissions)
      } catch (e) {
        localStorage.removeItem('user')
      }
    }
  }

  // Appeler au chargement
  initFromStorage()

  return {
    // State
    user,
    token,
    isLoading,
    isInitialized,
    // Getters
    isAuthenticated,
    currentSite,
    currentSiteId,
    roles,
    permissions,
    userName,
    userEmail,
    // Actions
    login,
    logout,
    fetchUser,
    switchSite,
    hasRole,
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,
    isSuperAdmin,
    canAccessSite,
    initFromStorage
  }
})
