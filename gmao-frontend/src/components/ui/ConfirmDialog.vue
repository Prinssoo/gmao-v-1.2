<script setup>
import { useConfirm } from '@/composables/useConfirm'
import BaseModal from './BaseModal.vue'

const { isOpen, confirmConfig, close } = useConfirm()

const getHeaderClass = () => {
  const types = {
    danger: 'danger',
    warning: 'warning',
    info: 'info'
  }
  return types[confirmConfig.value.type] || ''
}
</script>

<template>
  <BaseModal
    :show="isOpen"
    :title="confirmConfig.title"
    :header-class="getHeaderClass()"
    size="sm"
    @close="confirmConfig.onCancel"
  >
    <p class="confirm-message">{{ confirmConfig.message }}</p>
    
    <template #footer>
      <button type="button" class="btn btn-secondary" @click="confirmConfig.onCancel">
        {{ confirmConfig.cancelText }}
      </button>
      <button
        type="button"
        :class="['btn', confirmConfig.type === 'danger' ? 'btn-danger' : 'btn-primary']"
        @click="confirmConfig.onConfirm"
      >
        {{ confirmConfig.confirmText }}
      </button>
    </template>
  </BaseModal>
</template>

<style scoped>
.confirm-message {
  font-size: 15px;
  color: #334155;
  line-height: 1.6;
  margin: 0;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 14px;
}

.btn-primary {
  background: #3b82f6;
  color: white;
}

.btn-primary:hover {
  background: #2563eb;
}

.btn-secondary {
  background: #e2e8f0;
  color: #475569;
}

.btn-secondary:hover {
  background: #cbd5e1;
}

.btn-danger {
  background: #ef4444;
  color: white;
}

.btn-danger:hover {
  background: #dc2626;
}
</style>
