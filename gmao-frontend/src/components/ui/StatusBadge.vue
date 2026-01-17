<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: {
    type: String,
    required: true
  },
  type: {
    type: String,
    default: 'default', // default, workorder, priority, asset
  },
  size: {
    type: String,
    default: 'md', // sm, md, lg
  },
  showIcon: {
    type: Boolean,
    default: false
  }
})

// Configuration des statuts
const statusConfig = {
  // Work Order Status
  pending: { label: 'En attente', bg: '#fff3cd', color: '#856404', icon: '⏳' },
  approved: { label: 'Approuvé', bg: '#cce5ff', color: '#004085', icon: '✔️' },
  assigned: { label: 'Assigné', bg: '#e2d5f1', color: '#563d7c', icon: '👤' },
  in_progress: { label: 'En cours', bg: '#d4edda', color: '#155724', icon: '▶️' },
  on_hold: { label: 'En pause', bg: '#e2e3e5', color: '#383d41', icon: '⏸️' },
  completed: { label: 'Terminé', bg: '#d1ecf1', color: '#0c5460', icon: '✅' },
  cancelled: { label: 'Annulé', bg: '#f8d7da', color: '#721c24', icon: '❌' },
  
  // Priority
  low: { label: 'Basse', bg: '#d4edda', color: '#155724', icon: '⬇️' },
  medium: { label: 'Moyenne', bg: '#fff3cd', color: '#856404', icon: '➡️' },
  high: { label: 'Haute', bg: '#ffe5d0', color: '#c45200', icon: '⬆️' },
  urgent: { label: 'Urgente', bg: '#f8d7da', color: '#721c24', icon: '🚨' },
  
  // Asset Status
  active: { label: 'Actif', bg: '#d4edda', color: '#155724', icon: '🟢' },
  inactive: { label: 'Inactif', bg: '#e2e3e5', color: '#383d41', icon: '⚪' },
  maintenance: { label: 'En maintenance', bg: '#fff3cd', color: '#856404', icon: '🛠️' },
  broken: { label: 'En panne', bg: '#f8d7da', color: '#721c24', icon: '🔴' },
  
  // Generic
  default: { label: '', bg: '#e2e8f0', color: '#475569', icon: '📌' }
}

const config = computed(() => {
  return statusConfig[props.status] || { 
    ...statusConfig.default, 
    label: props.status 
  }
})

const style = computed(() => ({
  backgroundColor: config.value.bg,
  color: config.value.color
}))
</script>

<template>
  <span :class="['status-badge', `badge-${size}`]" :style="style">
    <span v-if="showIcon" class="badge-icon">{{ config.icon }}</span>
    <span class="badge-label">{{ config.label || status }}</span>
  </span>
</template>

<style scoped>
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border-radius: 20px;
  font-weight: 600;
  white-space: nowrap;
}

.badge-sm {
  padding: 2px 8px;
  font-size: 10px;
}

.badge-md {
  padding: 4px 12px;
  font-size: 12px;
}

.badge-lg {
  padding: 6px 16px;
  font-size: 14px;
}

.badge-icon {
  font-size: 0.9em;
}

.badge-label {
  text-transform: capitalize;
}
</style>
