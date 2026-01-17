<script setup>
import { onMounted, onUnmounted } from 'vue'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: ''
  },
  size: {
    type: String,
    default: 'md', // sm, md, lg, xl, full
    validator: (value) => ['sm', 'md', 'lg', 'xl', 'full'].includes(value)
  },
  closable: {
    type: Boolean,
    default: true
  },
  headerClass: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['close', 'confirm'])

const close = () => {
  if (props.closable) {
    emit('close')
  }
}

const handleKeydown = (e) => {
  if (e.key === 'Escape' && props.closable) {
    close()
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
  document.body.style.overflow = 'hidden'
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
  document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="show" class="modal-overlay" @click.self="close">
        <div :class="['modal', `modal-${size}`]" role="dialog" aria-modal="true">
          <div :class="['modal-header', headerClass]">
            <h2 class="modal-title">
              <slot name="title">{{ title }}</slot>
            </h2>
            <button v-if="closable" type="button" class="close-btn" @click="close" aria-label="Fermer">
              &times;
            </button>
          </div>
          
          <div class="modal-body">
            <slot></slot>
          </div>
          
          <div v-if="$slots.footer" class="modal-footer">
            <slot name="footer"></slot>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
  backdrop-filter: blur(2px);
}

.modal {
  background: white;
  border-radius: 16px;
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.modal-sm { max-width: 400px; }
.modal-md { max-width: 500px; }
.modal-lg { max-width: 700px; }
.modal-xl { max-width: 900px; }
.modal-full { max-width: 95vw; max-height: 95vh; }

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #e2e8f0;
  flex-shrink: 0;
}

.modal-header.success { background: linear-gradient(135deg, #d4edda, #c3e6cb); }
.modal-header.danger { background: linear-gradient(135deg, #f8d7da, #f5c6cb); }
.modal-header.warning { background: linear-gradient(135deg, #fff3cd, #ffeeba); }
.modal-header.info { background: linear-gradient(135deg, #d1ecf1, #bee5eb); }

.modal-title {
  font-size: 18px;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.close-btn {
  background: none;
  border: none;
  font-size: 28px;
  cursor: pointer;
  color: #64748b;
  padding: 0;
  line-height: 1;
  transition: color 0.2s;
}

.close-btn:hover {
  color: #1e293b;
}

.modal-body {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid #e2e8f0;
  flex-shrink: 0;
}

/* Animations */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-active .modal,
.modal-leave-active .modal {
  transition: transform 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from .modal,
.modal-leave-to .modal {
  transform: scale(0.95) translateY(-20px);
}

@media (max-width: 768px) {
  .modal-overlay {
    padding: 10px;
    align-items: flex-end;
  }
  
  .modal {
    max-width: 100%;
    max-height: 85vh;
    border-radius: 16px 16px 0 0;
  }
  
  .modal-header {
    padding: 16px 20px;
  }
  
  .modal-body {
    padding: 20px;
  }
}
</style>
