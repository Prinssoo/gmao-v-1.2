import { ref, computed, watch } from 'vue'

export function usePagination(fetchFunction, options = {}) {
  const items = ref([])
  const loading = ref(false)
  const error = ref(null)
  
  const currentPage = ref(1)
  const perPage = ref(options.perPage || 20)
  const total = ref(0)
  const lastPage = ref(1)
  
  const hasNextPage = computed(() => currentPage.value < lastPage.value)
  const hasPrevPage = computed(() => currentPage.value > 1)
  
  const pageInfo = computed(() => {
    const start = (currentPage.value - 1) * perPage.value + 1
    const end = Math.min(currentPage.value * perPage.value, total.value)
    return { start, end, total: total.value }
  })

  const fetch = async (params = {}) => {
    loading.value = true
    error.value = null
    
    try {
      const response = await fetchFunction({
        page: currentPage.value,
        per_page: perPage.value,
        ...params
      })
      
      if (response.success) {
        items.value = response.data.data || response.data
        total.value = response.data.total || items.value.length
        lastPage.value = response.data.last_page || 1
        currentPage.value = response.data.current_page || 1
      } else {
        error.value = response.error
      }
    } catch (err) {
      error.value = err.message
    } finally {
      loading.value = false
    }
  }

  const goToPage = (page) => {
    if (page >= 1 && page <= lastPage.value) {
      currentPage.value = page
      fetch()
    }
  }

  const nextPage = () => {
    if (hasNextPage.value) {
      goToPage(currentPage.value + 1)
    }
  }

  const prevPage = () => {
    if (hasPrevPage.value) {
      goToPage(currentPage.value - 1)
    }
  }

  const refresh = () => fetch()

  const reset = () => {
    currentPage.value = 1
    items.value = []
    total.value = 0
  }

  return {
    items,
    loading,
    error,
    currentPage,
    perPage,
    total,
    lastPage,
    hasNextPage,
    hasPrevPage,
    pageInfo,
    fetch,
    goToPage,
    nextPage,
    prevPage,
    refresh,
    reset
  }
}
