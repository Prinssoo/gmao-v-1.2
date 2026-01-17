<script setup>
const props = defineProps({
  type: {
    type: String,
    default: 'button'
  },
  variant: {
    type: String,
    default: 'primary', // primary, secondary, success, danger, warning, info, ghost
    validator: (v) => ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'ghost'].includes(v)
  },
  size: {
    type: String,
    default: 'md', // sm, md, lg
    validator: (v) => ['sm', 'md', 'lg'].includes(v)
  },
  loading: {
    type: Boolean,
    default: false
  },
  disabled: {
    type: Boolean,
    default: false
  },
  icon: {
    type: String,
    default: ''
  },
  block: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['click'])

const handleClick = (e) => {
  if (!props.disabled && !props.loading) {
    emit('click', e)
  }
}
</script>

<template>
  <button
    :type="type"
    :class="[
      'base-btn',
      `btn-${variant}`,
      `btn-${size}`,
      { 'btn-loading': loading, 'btn-block': block }
    ]"
    :disabled="disabled || loading"
    @click="handleClick"
  >
    <span v-if="loading" class="btn-spinner"></span>
    <span v-else-if="icon" class="btn-icon">{{ icon }}</span>
    <span class="btn-content"><slot></slot></span>
  </button>
</template>

<style scoped>
.base-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.base-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Sizes */
.btn-sm {
  padding: 6px 12px;
  font-size: 12px;
}

.btn-md {
  padding: 10px 20px;
  font-size: 14px;
}

.btn-lg {
  padding: 14px 28px;
  font-size: 16px;
}

/* Variants */
.btn-primary {
  background: linear-gradient(135deg, #3b82f6, #2563eb);
  color: white;
}
.btn-primary:hover:not(:disabled) {
  background: linear-gradient(135deg, #2563eb, #1d4ed8);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

.btn-secondary {
  background: #e2e8f0;
  color: #475569;
}
.btn-secondary:hover:not(:disabled) {
  background: #cbd5e1;
}

.btn-success {
  background: linear-gradient(135deg, #10b981, #059669);
  color: white;
}
.btn-success:hover:not(:disabled) {
  background: linear-gradient(135deg, #059669, #047857);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.btn-danger {
  background: linear-gradient(135deg, #ef4444, #dc2626);
  color: white;
}
.btn-danger:hover:not(:disabled) {
  background: linear-gradient(135deg, #dc2626, #b91c1c);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

.btn-warning {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: white;
}
.btn-warning:hover:not(:disabled) {
  background: linear-gradient(135deg, #d97706, #b45309);
}

.btn-info {
  background: linear-gradient(135deg, #06b6d4, #0891b2);
  color: white;
}
.btn-info:hover:not(:disabled) {
  background: linear-gradient(135deg, #0891b2, #0e7490);
}

.btn-ghost {
  background: transparent;
  color: #64748b;
}
.btn-ghost:hover:not(:disabled) {
  background: #f1f5f9;
  color: #334155;
}

/* Block */
.btn-block {
  width: 100%;
}

/* Spinner */
.btn-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid currentColor;
  border-top-color: transparent;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

.btn-lg .btn-spinner {
  width: 20px;
  height: 20px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.btn-icon {
  font-size: 1.1em;
}

.btn-loading .btn-content {
  opacity: 0.7;
}
</style>
