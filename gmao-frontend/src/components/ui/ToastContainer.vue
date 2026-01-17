<script setup>
import { useToast } from '@/composables/useToast'

const { toasts, removeToast } = useToast()

const getIcon = (type) => {
  const icons = {
    success: '✅',
    error: '❌',
    warning: '⚠️',
    info: 'ℹ️'
  }
  return icons[type] || icons.info
}
</script>

<template>
  <Teleport to="body">
    <div class="toast-container">
      <TransitionGroup name="toast">
        <div
          v-for="toast in toasts"
          :key="toast.id"
          :class="['toast', `toast-${toast.type}`]"
          @click="removeToast(toast.id)"
        >
          <span class="toast-icon">{{ getIcon(toast.type) }}</span>
          <span class="toast-message">{{ toast.message }}</span>
          <button class="toast-close" @click.stop="removeToast(toast.id)">&times;</button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
.toast-container {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 2000;
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-width: 400px;
}

.toast {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 18px;
  border-radius: 12px;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
  cursor: pointer;
  transition: transform 0.2s, opacity 0.2s;
}

.toast:hover {
  transform: translateX(-5px);
}

.toast-success {
  background: linear-gradient(135deg, #10b981, #059669);
  color: white;
}

.toast-error {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: white;
}

.toast-warning {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: white;
}

.toast-info {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: white;
}

.toast-icon {
  font-size: 18px;
}

.toast-message {
  flex: 1;
  font-size: 14px;
  font-weight: 500;
}

.toast-close {
  background: none;
  border: none;
  color: inherit;
  font-size: 20px;
  cursor: pointer;
  opacity: 0.7;
  padding: 0;
  line-height: 1;
}

.toast-close:hover {
  opacity: 1;
}

/* Animations */
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100%);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(100%) scale(0.9);
}

.toast-move {
  transition: transform 0.3s ease;
}

@media (max-width: 768px) {
  .toast-container {
    top: auto;
    bottom: 20px;
    left: 20px;
    right: 20px;
    max-width: none;
  }
}
</style>
