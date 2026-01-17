import { ref } from 'vue'
import api from '@/services/api'
import { useToast } from './useToast'

export function useApi() {
  const loading = ref(false)
  const error = ref(null)
  const data = ref(null)
  const toast = useToast()

  const handleError = (err, showToast = true) => {
    const message = err.response?.data?.message 
      || err.response?.data?.error 
      || err.message 
      || 'Une erreur est survenue'
    
    error.value = message
    
    if (showToast) {
      toast.error(message)
    }
    
    // Log pour debug en développement
    if (import.meta.env.DEV) {
      console.error('API Error:', err)
    }
    
    return message
  }

  const request = async (method, url, payload = null, options = {}) => {
    loading.value = true
    error.value = null
    
    try {
      const config = { ...options }
      let response
      
      if (['get', 'delete'].includes(method)) {
        response = await api[method](url, config)
      } else {
        response = await api[method](url, payload, config)
      }
      
      data.value = response.data
      return { success: true, data: response.data }
    } catch (err) {
      const message = handleError(err, options.showToast !== false)
      return { success: false, error: message }
    } finally {
      loading.value = false
    }
  }

  const get = (url, options) => request('get', url, null, options)
  const post = (url, payload, options) => request('post', url, payload, options)
  const put = (url, payload, options) => request('put', url, payload, options)
  const patch = (url, payload, options) => request('patch', url, payload, options)
  const del = (url, options) => request('delete', url, null, options)

  // Fonction pour les listes avec pagination
  const fetchList = async (url, params = {}) => {
    const queryString = new URLSearchParams(params).toString()
    const fullUrl = queryString ? `${url}?${queryString}` : url
    return get(fullUrl)
  }

  // Fonction pour créer avec message de succès
  const create = async (url, payload, successMessage = 'Créé avec succès') => {
    const result = await post(url, payload)
    if (result.success && successMessage) {
      toast.success(successMessage)
    }
    return result
  }

  // Fonction pour mettre à jour avec message de succès
  const update = async (url, payload, successMessage = 'Mis à jour avec succès') => {
    const result = await put(url, payload)
    if (result.success && successMessage) {
      toast.success(successMessage)
    }
    return result
  }

  // Fonction pour supprimer avec message de succès
  const remove = async (url, successMessage = 'Supprimé avec succès') => {
    const result = await del(url)
    if (result.success && successMessage) {
      toast.success(successMessage)
    }
    return result
  }

  return {
    loading,
    error,
    data,
    get,
    post,
    put,
    patch,
    del,
    fetchList,
    create,
    update,
    remove,
    handleError
  }
}
