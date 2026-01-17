import { ref } from 'vue'

const isOpen = ref(false)
const confirmConfig = ref({
  title: 'Confirmation',
  message: 'Êtes-vous sûr ?',
  confirmText: 'Confirmer',
  cancelText: 'Annuler',
  type: 'warning', // 'warning', 'danger', 'info'
  onConfirm: null,
  onCancel: null
})

export function useConfirm() {
  const confirm = (options) => {
    return new Promise((resolve) => {
      confirmConfig.value = {
        title: options.title || 'Confirmation',
        message: options.message || 'Êtes-vous sûr ?',
        confirmText: options.confirmText || 'Confirmer',
        cancelText: options.cancelText || 'Annuler',
        type: options.type || 'warning',
        onConfirm: () => {
          isOpen.value = false
          resolve(true)
        },
        onCancel: () => {
          isOpen.value = false
          resolve(false)
        }
      }
      isOpen.value = true
    })
  }

  const confirmDelete = (itemName = 'cet élément') => {
    return confirm({
      title: 'Confirmer la suppression',
      message: `Êtes-vous sûr de vouloir supprimer ${itemName} ? Cette action est irréversible.`,
      confirmText: 'Supprimer',
      cancelText: 'Annuler',
      type: 'danger'
    })
  }

  const close = () => {
    isOpen.value = false
  }

  return {
    isOpen,
    confirmConfig,
    confirm,
    confirmDelete,
    close
  }
}
