import axios from 'axios'

// Configuration de base
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
  withCredentials: true,
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

// Intercepteur de requête
api.interceptors.request.use(
  (config) => {
    // Ajouter le token d'authentification
    const token = localStorage.getItem('auth_token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    
    // Ajouter un timestamp pour éviter le cache sur les GET
    if (config.method === 'get') {
      config.params = {
        ...config.params,
        _t: Date.now()
      }
    }
    
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Intercepteur de réponse
api.interceptors.response.use(
  (response) => {
    return response
  },
  async (error) => {
    const originalRequest = error.config
    
    // Gestion du 401 (non authentifié)
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true
      
      // Nettoyer le storage et rediriger
      localStorage.removeItem('auth_token')
      localStorage.removeItem('user')
      
      // Ne pas rediriger si déjà sur la page login
      if (!window.location.pathname.includes('/login')) {
        window.location.href = '/login'
      }
      
      return Promise.reject(error)
    }
    
    // Gestion du 403 (interdit)
    if (error.response?.status === 403) {
      console.warn('Accès refusé:', error.response?.data?.message)
    }
    
    // Gestion du 404
    if (error.response?.status === 404) {
      console.warn('Ressource non trouvée:', originalRequest.url)
    }
    
    // Gestion du 422 (erreurs de validation)
    if (error.response?.status === 422) {
      // Les erreurs de validation sont gérées par le composant
      return Promise.reject(error)
    }
    
    // Gestion du 500 (erreur serveur)
    if (error.response?.status >= 500) {
      console.error('Erreur serveur:', error.response?.data)
    }
    
    // Gestion du timeout
    if (error.code === 'ECONNABORTED') {
      error.message = 'La requête a expiré. Veuillez réessayer.'
    }
    
    // Gestion de l'absence de connexion
    if (!error.response) {
      error.message = 'Impossible de se connecter au serveur. Vérifiez votre connexion.'
    }
    
    return Promise.reject(error)
  }
)

// Fonctions utilitaires
export const setAuthToken = (token) => {
  if (token) {
    localStorage.setItem('auth_token', token)
    api.defaults.headers.common['Authorization'] = `Bearer ${token}`
  } else {
    localStorage.removeItem('auth_token')
    delete api.defaults.headers.common['Authorization']
  }
}

export const getAuthToken = () => {
  return localStorage.getItem('auth_token')
}

export const clearAuth = () => {
  localStorage.removeItem('auth_token')
  localStorage.removeItem('user')
  delete api.defaults.headers.common['Authorization']
}

export default api
