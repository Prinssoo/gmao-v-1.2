import { ref, computed } from 'vue'

export function useForm(initialData = {}) {
  const form = ref({ ...initialData })
  const errors = ref({})
  const isDirty = ref(false)
  const isSubmitting = ref(false)

  const reset = () => {
    form.value = { ...initialData }
    errors.value = {}
    isDirty.value = false
  }

  const setData = (data) => {
    form.value = { ...data }
    isDirty.value = false
  }

  const setError = (field, message) => {
    errors.value[field] = message
  }

  const clearError = (field) => {
    if (field) {
      delete errors.value[field]
    } else {
      errors.value = {}
    }
  }

  const setErrors = (serverErrors) => {
    // Gère le format Laravel { field: ['message1', 'message2'] }
    if (serverErrors && typeof serverErrors === 'object') {
      Object.keys(serverErrors).forEach(key => {
        const messages = serverErrors[key]
        errors.value[key] = Array.isArray(messages) ? messages[0] : messages
      })
    }
  }

  const hasError = computed(() => Object.keys(errors.value).length > 0)

  const getError = (field) => errors.value[field] || null

  const validate = (rules) => {
    clearError()
    let isValid = true

    Object.keys(rules).forEach(field => {
      const fieldRules = rules[field]
      const value = form.value[field]

      fieldRules.forEach(rule => {
        if (rule === 'required' && (!value || value === '')) {
          setError(field, 'Ce champ est obligatoire')
          isValid = false
        } else if (rule === 'email' && value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
          setError(field, 'Email invalide')
          isValid = false
        } else if (rule.startsWith('min:')) {
          const min = parseInt(rule.split(':')[1])
          if (value && value.length < min) {
            setError(field, `Minimum ${min} caractères`)
            isValid = false
          }
        } else if (rule.startsWith('max:')) {
          const max = parseInt(rule.split(':')[1])
          if (value && value.length > max) {
            setError(field, `Maximum ${max} caractères`)
            isValid = false
          }
        }
      })
    })

    return isValid
  }

  // Surveiller les changements
  const watchChanges = () => {
    isDirty.value = true
  }

  return {
    form,
    errors,
    isDirty,
    isSubmitting,
    reset,
    setData,
    setError,
    clearError,
    setErrors,
    hasError,
    getError,
    validate,
    watchChanges
  }
}
