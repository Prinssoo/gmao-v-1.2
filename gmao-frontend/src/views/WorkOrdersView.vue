<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

const workOrders = ref([])
const equipments = ref([])
const trucks = ref([])
const users = ref([])
const loading = ref(false)
const error = ref('')
const successMessage = ref('')
const searchQuery = ref('')
const filterStatus = ref('')
const filterType = ref('')
const filterPriority = ref('')
const filterAssetType = ref('')
const filterEquipmentId = ref(null)
const filterTruckId = ref(null)

// Modals
const showCreateModal = ref(false)
const showDetailModal = ref(false)
const showAddPartModal = ref(false)
const showCloseModal = ref(false)
const showCancelModal = ref(false)
const selectedWorkOrder = ref(null)
const availableParts = ref([])
const saving = ref(false)

const form = ref({
  asset_type: 'equipment',
  equipment_id: '',
  truck_id: '',
  title: '',
  description: '',
  type: 'corrective',
  priority: 'medium',
  assigned_to: '',
})

const partForm = ref({
  part_id: '',
  quantity: 1,
})

const closeForm = ref({
  work_performed: '',
  root_cause: '',
  diagnosis: '',
  technician_notes: '',
  mileage_at_intervention: '',
})

const cancelForm = ref({
  reason: '',
})

const commentForm = ref({
  comment: '',
})

// Labels
const statusLabels = {
  pending: 'En attente',
  approved: 'Approuvé',
  assigned: 'Assigné',
  in_progress: 'En cours',
  on_hold: 'En pause',
  completed: 'Terminé',
  cancelled: 'Annulé',
}

const statusColors = {
  pending: { bg: '#fff3cd', text: '#856404' },
  approved: { bg: '#cce5ff', text: '#004085' },
  assigned: { bg: '#e2d5f1', text: '#563d7c' },
  in_progress: { bg: '#d4edda', text: '#155724' },
  on_hold: { bg: '#e2e3e5', text: '#383d41' },
  completed: { bg: '#d1ecf1', text: '#0c5460' },
  cancelled: { bg: '#f8d7da', text: '#721c24' },
}

const priorityLabels = { low: 'Basse', medium: 'Moyenne', high: 'Haute', urgent: 'Urgente' }
const priorityColors = {
  low: { bg: '#d4edda', text: '#155724' },
  medium: { bg: '#fff3cd', text: '#856404' },
  high: { bg: '#ffe5d0', text: '#c45200' },
  urgent: { bg: '#f8d7da', text: '#721c24' },
}

const typeLabels = { corrective: 'Corrective', preventive: 'Préventive', improvement: 'Amélioration', inspection: 'Inspection' }
const typeIcons = { corrective: '🔧', preventive: '📅', improvement: '⬆️', inspection: '🔍' }

const assetTypeLabels = { equipment: 'Équipement', truck: 'Camion' }
const assetTypeIcons = { equipment: '⚙️', truck: '🚚' }

// Computed
const filteredWorkOrders = computed(() => {
  return workOrders.value.filter(wo => {
    const assetName = wo.asset_type === 'truck'
      ? (wo.truck?.registration_number || wo.truck?.code || '')
      : (wo.equipment?.name || wo.equipment?.code || '')

    const matchSearch = !searchQuery.value ||
      wo.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      wo.code.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      assetName.toLowerCase().includes(searchQuery.value.toLowerCase())

    const matchStatus = !filterStatus.value || wo.status === filterStatus.value
    const matchType = !filterType.value || wo.type === filterType.value
    const matchPriority = !filterPriority.value || wo.priority === filterPriority.value
    const matchAssetType = !filterAssetType.value || wo.asset_type === filterAssetType.value

    return matchSearch && matchStatus && matchType && matchPriority && matchAssetType
  })
})

const stats = computed(() => ({
  pending: workOrders.value.filter(w => w.status === 'pending').length,
  assigned: workOrders.value.filter(w => w.status === 'assigned').length,
  in_progress: workOrders.value.filter(w => w.status === 'in_progress').length,
  completed: workOrders.value.filter(w => w.status === 'completed').length,
  equipment: workOrders.value.filter(w => w.asset_type === 'equipment').length,
  truck: workOrders.value.filter(w => w.asset_type === 'truck').length,
}))

// Helpers
function showSuccess(msg) {
  successMessage.value = msg
  setTimeout(() => { successMessage.value = '' }, 3000)
}

function showError(msg) {
  error.value = msg
  setTimeout(() => { error.value = '' }, 5000)
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
  })
}

function formatDuration(minutes) {
  if (!minutes) return '-'
  const hours = Math.floor(minutes / 60)
  const mins = minutes % 60
  if (hours === 0) return `${mins} min`
  return `${hours}h ${mins}min`
}

function formatMileage(km) {
  if (!km) return '-'
  return km.toLocaleString('fr-FR') + ' km'
}

function getStatusStyle(status) {
  const c = statusColors[status] || statusColors.pending
  return { backgroundColor: c.bg, color: c.text }
}

function getPriorityStyle(priority) {
  const c = priorityColors[priority] || priorityColors.medium
  return { backgroundColor: c.bg, color: c.text }
}

function getAssetName(wo) {
  if (wo.asset_type === 'truck') {
    const truck = wo.truck
    if (!truck) return '-'
    return truck.registration_number || `${truck.brand} ${truck.model}`
  }
  return wo.equipment?.name || '-'
}

function getAssetCode(wo) {
  if (wo.asset_type === 'truck') {
    return wo.truck?.code || ''
  }
  return wo.equipment?.code || ''
}

// Fonction pour effacer le filtre d'asset
function clearAssetFilter() {
  router.push({ path: '/work-orders' })
}

// API calls
async function fetchWorkOrders() {
  loading.value = true
  try {
    const params = new URLSearchParams({ per_page: '100' })
    
    // Filtre par type d'asset
    if (filterAssetType.value) {
      params.append('asset_type', filterAssetType.value)
    }
    
    // Récupérer equipment_id depuis l'URL
    if (route.query.equipment_id) {
      params.append('equipment_id', route.query.equipment_id)
      filterEquipmentId.value = route.query.equipment_id
    } else {
      filterEquipmentId.value = null
    }
    
    // Récupérer truck_id depuis l'URL
    if (route.query.truck_id) {
      params.append('truck_id', route.query.truck_id)
      filterTruckId.value = route.query.truck_id
    } else {
      filterTruckId.value = null
    }

    const response = await api.get(`/work-orders?${params}`)
    workOrders.value = response.data.data
  } catch (err) {
    showError('Erreur lors du chargement')
  } finally {
    loading.value = false
  }
}

async function fetchEquipments() {
  try {
    const response = await api.get('/equipments?per_page=100&is_active=true')
    equipments.value = response.data.data
  } catch (err) { console.error(err) }
}

async function fetchTrucks() {
  try {
    const response = await api.get('/trucks?per_page=100&status=active')
    trucks.value = response.data.data
  } catch (err) { console.error(err) }
}

async function fetchUsers() {
  try {
    const response = await api.get('/users?per_page=100')
    users.value = response.data.data.filter(u =>
      u.roles?.some(r => ['SuperAdmin', 'AdminSite', 'Planificateur', 'Technicien'].includes(r.name))
    )
  } catch (err) { console.error(err) }
}

// Create
function openCreateModal() {
  form.value = {
    asset_type: 'equipment',
    equipment_id: '',
    truck_id: '',
    title: '',
    description: '',
    type: 'corrective',
    priority: 'medium',
    assigned_to: ''
  }
  showCreateModal.value = true
}

function onAssetTypeChange() {
  form.value.equipment_id = ''
  form.value.truck_id = ''
}

async function createWorkOrder() {
  saving.value = true
  try {
    const payload = {
      asset_type: form.value.asset_type,
      title: form.value.title,
      description: form.value.description,
      type: form.value.type,
      priority: form.value.priority,
      assigned_to: form.value.assigned_to || null,
    }

    if (form.value.asset_type === 'equipment') {
      payload.equipment_id = form.value.equipment_id
    } else {
      payload.truck_id = form.value.truck_id
    }

    await api.post('/work-orders', payload)
    showCreateModal.value = false
    showSuccess('Intervention créée avec succès')
    fetchWorkOrders()
  } catch (err) {
    showError(err.response?.data?.message || 'Erreur')
  } finally {
    saving.value = false
  }
}

// Detail
async function openDetailModal(wo) {
  try {
    const response = await api.get(`/work-orders/${wo.id}`)
    selectedWorkOrder.value = response.data
    showDetailModal.value = true
  } catch (err) {
    showError('Erreur lors du chargement des détails')
  }
}

async function refreshDetail() {
  if (!selectedWorkOrder.value) return
  const response = await api.get(`/work-orders/${selectedWorkOrder.value.id}`)
  selectedWorkOrder.value = response.data
}

// Status
async function updateStatus(newStatus) {
  if (newStatus === 'completed') {
    closeForm.value = {
      work_performed: '',
      root_cause: '',
      diagnosis: '',
      technician_notes: '',
      mileage_at_intervention: selectedWorkOrder.value?.truck?.mileage || '',
    }
    showCloseModal.value = true
    return
  }

  if (newStatus === 'cancelled') {
    cancelForm.value = { reason: '' }
    showCancelModal.value = true
    return
  }

  saving.value = true
  try {
    await api.post(`/work-orders/${selectedWorkOrder.value.id}/status`, { status: newStatus })
    showSuccess('Statut mis à jour')
    await refreshDetail()
    fetchWorkOrders()
  } catch (err) {
    showError(err.response?.data?.message || 'Erreur')
  } finally {
    saving.value = false
  }
}

async function completeWorkOrder() {
  saving.value = true
  try {
    const payload = {
      status: 'completed',
      work_performed: closeForm.value.work_performed,
      root_cause: closeForm.value.root_cause,
      diagnosis: closeForm.value.diagnosis,
      technician_notes: closeForm.value.technician_notes,
    }

    if (selectedWorkOrder.value.asset_type === 'truck' && closeForm.value.mileage_at_intervention) {
      payload.mileage_at_intervention = parseInt(closeForm.value.mileage_at_intervention)
    }

    await api.post(`/work-orders/${selectedWorkOrder.value.id}/status`, payload)
    showCloseModal.value = false
    showSuccess('Intervention clôturée avec succès')
    await refreshDetail()
    fetchWorkOrders()
  } catch (err) {
    showError(err.response?.data?.message || 'Erreur')
  } finally {
    saving.value = false
  }
}

async function cancelWorkOrder() {
  if (!cancelForm.value.reason.trim()) {
    showError('Veuillez indiquer la raison de l\'annulation')
    return
  }

  saving.value = true
  try {
    await api.post(`/work-orders/${selectedWorkOrder.value.id}/cancel`, {
      reason: cancelForm.value.reason
    })
    showCancelModal.value = false
    showSuccess('Intervention annulée')
    await refreshDetail()
    fetchWorkOrders()
  } catch (err) {
    showError(err.response?.data?.message || 'Erreur')
  } finally {
    saving.value = false
  }
}

// Parts
async function openAddPartModal() {
  try {
    const response = await api.get(`/work-orders/${selectedWorkOrder.value.id}/available-parts`)
    availableParts.value = response.data
    partForm.value = { part_id: '', quantity: 1 }
    showAddPartModal.value = true
  } catch (err) {
    showError('Erreur lors du chargement des pièces')
  }
}

async function addPart() {
  saving.value = true
  try {
    await api.post(`/work-orders/${selectedWorkOrder.value.id}/parts`, partForm.value)
    showAddPartModal.value = false
    showSuccess('Pièce ajoutée')
    await refreshDetail()
  } catch (err) {
    showError(err.response?.data?.message || 'Erreur')
  } finally {
    saving.value = false
  }
}

async function removePart(partId) {
  if (!confirm('Retirer cette pièce ?')) return
  try {
    await api.delete(`/work-orders/${selectedWorkOrder.value.id}/parts/${partId}`)
    showSuccess('Pièce retirée')
    await refreshDetail()
  } catch (err) {
    showError(err.response?.data?.message || 'Erreur')
  }
}

// Comments
async function addComment() {
  if (!commentForm.value.comment.trim()) return
  saving.value = true
  try {
    await api.post(`/work-orders/${selectedWorkOrder.value.id}/comments`, commentForm.value)
    commentForm.value.comment = ''
    showSuccess('Commentaire ajouté')
    await refreshDetail()
  } catch (err) {
    showError(err.response?.data?.message || 'Erreur')
  } finally {
    saving.value = false
  }
}

function getHistoryIcon(action) {
  const icons = {
    created: '🆕',
    status_changed: '🔄',
    assigned: '👤',
    part_added: '➕',
    part_removed: '➖',
    comment_added: '💬',
    priority_changed: '⚡',
    started: '▶️',
    paused: '⏸️',
    resumed: '▶️',
    completed: '✅',
    cancelled: '❌',
  }
  return icons[action] || '📝'
}

// Surveiller les changements d'URL
watch(() => route.query, () => {
  fetchWorkOrders()
}, { deep: true })

onMounted(() => {
  fetchWorkOrders()
  fetchEquipments()
  fetchTrucks()
  fetchUsers()
})
</script>

<template>
  <div class="wo-page">
    <header class="page-header">
      <div>
        <h1>🔧 Ordres de travail</h1>
        <p class="subtitle">Gestion des interventions de maintenance</p>
      </div>
      <button class="btn btn-success" @click="openCreateModal" v-if="authStore.hasPermission('workorder:create')">
        + Nouvelle intervention
      </button>
    </header>

    <!-- Messages -->
    <div class="alert alert-success" v-if="successMessage">✅ {{ successMessage }}</div>
    <div class="alert alert-error" v-if="error">❌ {{ error }}</div>

    <!-- Filtre actif (equipment ou truck) -->
    <div v-if="filterEquipmentId || filterTruckId" class="active-filter-banner">
      <div class="filter-info">
        <span class="filter-icon">🔍</span>
        <span v-if="filterEquipmentId">Filtré par équipement #{{ filterEquipmentId }}</span>
        <span v-if="filterTruckId">Filtré par camion #{{ filterTruckId }}</span>
      </div>
      <button class="btn btn-sm" @click="clearAssetFilter">
        ✕ Voir tous les OTs
      </button>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card pending">
        <div class="stat-value">{{ stats.pending }}</div>
        <div class="stat-label">En attente</div>
      </div>
      <div class="stat-card assigned">
        <div class="stat-value">{{ stats.assigned }}</div>
        <div class="stat-label">Assignées</div>
      </div>
      <div class="stat-card progress">
        <div class="stat-value">{{ stats.in_progress }}</div>
        <div class="stat-label">En cours</div>
      </div>
      <div class="stat-card completed">
        <div class="stat-value">{{ stats.completed }}</div>
        <div class="stat-label">Terminées</div>
      </div>
      <div class="stat-card equipment">
        <div class="stat-value">{{ stats.equipment }}</div>
        <div class="stat-label">⚙️ Équipements</div>
      </div>
      <div class="stat-card truck">
        <div class="stat-value">{{ stats.truck }}</div>
        <div class="stat-label">🚚 Camions</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
      <div class="filters-row">
        <div class="filter-group search-group">
          <span class="filter-icon">🔍</span>
          <input type="text" v-model="searchQuery" placeholder="Rechercher..." class="search-input" />
        </div>
        <select v-model="filterAssetType" @change="fetchWorkOrders">
          <option value="">Tous les types</option>
          <option value="equipment">⚙️ Équipements</option>
          <option value="truck">🚚 Camions</option>
        </select>
        <select v-model="filterStatus">
          <option value="">Tous statuts</option>
          <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
        </select>
        <select v-model="filterType">
          <option value="">Tous types</option>
          <option v-for="(label, key) in typeLabels" :key="key" :value="key">{{ label }}</option>
        </select>
        <select v-model="filterPriority">
          <option value="">Toutes priorités</option>
          <option v-for="(label, key) in priorityLabels" :key="key" :value="key">{{ label }}</option>
        </select>
      </div>
    </div>

    <!-- Liste -->
    <div class="wo-list" v-if="!loading && filteredWorkOrders.length">
      <div class="wo-card" v-for="wo in filteredWorkOrders" :key="wo.id" @click="openDetailModal(wo)">
        <div class="wo-left">
          <div class="wo-type-icon">{{ typeIcons[wo.type] }}</div>
          <div class="wo-asset-icon">{{ assetTypeIcons[wo.asset_type] }}</div>
        </div>
        <div class="wo-content">
          <div class="wo-header">
            <span class="wo-code">{{ wo.code }}</span>
            <div class="wo-badges">
              <span class="badge asset-badge" :class="wo.asset_type">{{ assetTypeLabels[wo.asset_type] }}</span>
              <span class="badge" :style="getPriorityStyle(wo.priority)">{{ priorityLabels[wo.priority] }}</span>
              <span class="badge" :style="getStatusStyle(wo.status)">{{ statusLabels[wo.status] }}</span>
            </div>
          </div>
          <h3 class="wo-title">{{ wo.title }}</h3>
          <div class="wo-meta">
            <span>
              {{ assetTypeIcons[wo.asset_type] }}
              <strong>{{ getAssetName(wo) }}</strong>
              <small v-if="getAssetCode(wo)">({{ getAssetCode(wo) }})</small>
            </span>
            <span>👤 {{ wo.assigned_to?.name || 'Non assigné' }}</span>
            <span>📅 {{ formatDate(wo.created_at) }}</span>
          </div>
        </div>
        <div class="wo-arrow">→</div>
      </div>
    </div>

    <div class="loading-state" v-if="loading"><div class="spinner"></div></div>
    <div class="empty-state" v-if="!loading && !filteredWorkOrders.length">
      <div class="empty-icon">🔧</div>
      <h3>Aucune intervention trouvée</h3>
      <p v-if="filterEquipmentId || filterTruckId">
        <button class="btn btn-primary" @click="clearAssetFilter">Voir tous les OTs</button>
      </p>
    </div>

    <!-- Modal Création -->
    <div class="modal-overlay" v-if="showCreateModal" @click.self="showCreateModal = false">
      <div class="modal">
        <div class="modal-header">
          <h2>➕ Nouvelle intervention</h2>
          <button class="close-btn" @click="showCreateModal = false">&times;</button>
        </div>
        <form @submit.prevent="createWorkOrder" class="modal-body">
          <!-- Choix du type d'asset -->
          <div class="form-group">
            <label>Type d'actif *</label>
            <div class="asset-type-selector">
              <label class="asset-type-option" :class="{ active: form.asset_type === 'equipment' }">
                <input type="radio" v-model="form.asset_type" value="equipment" @change="onAssetTypeChange" />
                <span class="option-content">
                  <span class="option-icon">⚙️</span>
                  <span class="option-label">Équipement</span>
                </span>
              </label>
              <label class="asset-type-option" :class="{ active: form.asset_type === 'truck' }">
                <input type="radio" v-model="form.asset_type" value="truck" @change="onAssetTypeChange" />
                <span class="option-content">
                  <span class="option-icon">🚚</span>
                  <span class="option-label">Camion</span>
                </span>
              </label>
            </div>
          </div>

          <!-- Sélection équipement -->
          <div class="form-group" v-if="form.asset_type === 'equipment'">
            <label>Équipement *</label>
            <select v-model="form.equipment_id" required>
              <option value="">-- Sélectionner un équipement --</option>
              <option v-for="eq in equipments" :key="eq.id" :value="eq.id">{{ eq.code }} - {{ eq.name }}</option>
            </select>
          </div>

          <!-- Sélection camion -->
          <div class="form-group" v-if="form.asset_type === 'truck'">
            <label>Camion *</label>
            <select v-model="form.truck_id" required>
              <option value="">-- Sélectionner un camion --</option>
              <option v-for="truck in trucks" :key="truck.id" :value="truck.id">
                {{ truck.code }} - {{ truck.registration_number }} ({{ truck.brand }} {{ truck.model }})
              </option>
            </select>
          </div>

          <div class="form-group">
            <label>Titre *</label>
            <input type="text" v-model="form.title" required placeholder="Description courte du problème" />
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Type *</label>
              <select v-model="form.type" required>
                <option v-for="(label, key) in typeLabels" :key="key" :value="key">{{ typeIcons[key] }} {{ label }}</option>
              </select>
            </div>
            <div class="form-group">
              <label>Priorité *</label>
              <select v-model="form.priority" required>
                <option v-for="(label, key) in priorityLabels" :key="key" :value="key">{{ label }}</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label>Assigner à</label>
            <select v-model="form.assigned_to">
              <option value="">-- Non assigné --</option>
              <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
            </select>
          </div>

          <div class="form-group">
            <label>Description</label>
            <textarea v-model="form.description" rows="3" placeholder="Détails supplémentaires..."></textarea>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showCreateModal = false">Annuler</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Création...' : 'Créer' }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Détail -->
    <div class="modal-overlay" v-if="showDetailModal" @click.self="showDetailModal = false">
      <div class="modal modal-xl">
        <div class="modal-header">
          <h2>
            <span class="asset-type-icon">{{ assetTypeIcons[selectedWorkOrder?.asset_type] }}</span>
            {{ selectedWorkOrder?.code }}
          </h2>
          <button class="close-btn" @click="showDetailModal = false">&times;</button>
        </div>
        <div class="modal-body" v-if="selectedWorkOrder">
          <!-- Header -->
          <div class="detail-top">
            <div class="detail-badges">
              <span class="badge asset-badge" :class="selectedWorkOrder.asset_type">
                {{ assetTypeIcons[selectedWorkOrder.asset_type] }} {{ assetTypeLabels[selectedWorkOrder.asset_type] }}
              </span>
              <span class="badge" :style="getPriorityStyle(selectedWorkOrder.priority)">{{ priorityLabels[selectedWorkOrder.priority] }}</span>
              <span class="badge" :style="getStatusStyle(selectedWorkOrder.status)">{{ statusLabels[selectedWorkOrder.status] }}</span>
              <span class="badge type-badge">{{ typeIcons[selectedWorkOrder.type] }} {{ typeLabels[selectedWorkOrder.type] }}</span>
            </div>
            <h2 class="detail-title">{{ selectedWorkOrder.title }}</h2>
          </div>

          <!-- Tabs content -->
          <div class="detail-grid">
            <!-- Info Asset -->
            <div class="detail-section asset-section">
              <h4>{{ assetTypeIcons[selectedWorkOrder.asset_type] }} {{ assetTypeLabels[selectedWorkOrder.asset_type] }}</h4>
              <div class="info-list" v-if="selectedWorkOrder.asset_type === 'equipment' && selectedWorkOrder.equipment">
                <div class="info-item"><span class="info-label">Nom</span><span>{{ selectedWorkOrder.equipment.name }}</span></div>
                <div class="info-item"><span class="info-label">Code</span><span>{{ selectedWorkOrder.equipment.code }}</span></div>
                <div class="info-item"><span class="info-label">Emplacement</span><span>{{ selectedWorkOrder.equipment.location?.name || '-' }}</span></div>
              </div>
              <div class="info-list" v-if="selectedWorkOrder.asset_type === 'truck' && selectedWorkOrder.truck">
                <div class="info-item"><span class="info-label">Immatriculation</span><span>{{ selectedWorkOrder.truck.registration_number }}</span></div>
                <div class="info-item"><span class="info-label">Code</span><span>{{ selectedWorkOrder.truck.code }}</span></div>
                <div class="info-item"><span class="info-label">Modèle</span><span>{{ selectedWorkOrder.truck.brand }} {{ selectedWorkOrder.truck.model }}</span></div>
                <div class="info-item"><span class="info-label">Kilométrage</span><span>{{ formatMileage(selectedWorkOrder.truck.mileage) }}</span></div>
                <div class="info-item" v-if="selectedWorkOrder.truck.current_driver">
                  <span class="info-label">Chauffeur</span><span>{{ selectedWorkOrder.truck.current_driver.name }}</span>
                </div>
                <div class="info-item highlight" v-if="selectedWorkOrder.mileage_at_intervention">
                  <span class="info-label">Km intervention</span>
                  <span class="mileage-value">{{ formatMileage(selectedWorkOrder.mileage_at_intervention) }}</span>
                </div>
              </div>
            </div>

            <!-- Info générale -->
            <div class="detail-section">
              <h4>📋 Informations</h4>
              <div class="info-list">
                <div class="info-item"><span class="info-label">Demandé par</span><span>{{ selectedWorkOrder.requested_by?.name }}</span></div>
                <div class="info-item"><span class="info-label">Assigné à</span><span>{{ selectedWorkOrder.assigned_to?.name || 'Non assigné' }}</span></div>
                <div class="info-item"><span class="info-label">Créé le</span><span>{{ formatDate(selectedWorkOrder.created_at) }}</span></div>
                <div class="info-item" v-if="selectedWorkOrder.intervention_request">
                  <span class="info-label">DI source</span>
                  <span class="di-link">{{ selectedWorkOrder.intervention_request.code }}</span>
                </div>
              </div>
            </div>

            <!-- Temps -->
            <div class="detail-section">
              <h4>⏱️ Temps</h4>
              <div class="info-list">
                <div class="info-item"><span class="info-label">Début réel</span><span>{{ formatDate(selectedWorkOrder.actual_start) }}</span></div>
                <div class="info-item"><span class="info-label">Fin réelle</span><span>{{ formatDate(selectedWorkOrder.actual_end) }}</span></div>
                <div class="info-item highlight"><span class="info-label">Durée totale</span><span class="duration">{{ formatDuration(selectedWorkOrder.actual_duration) }}</span></div>
              </div>
            </div>

            <!-- Coûts -->
            <div class="detail-section">
              <h4>💰 Coûts</h4>
              <div class="info-list">
                <div class="info-item"><span class="info-label">Main d'œuvre</span><span>{{ selectedWorkOrder.labor_cost || 0 }} DA</span></div>
                <div class="info-item"><span class="info-label">Pièces</span><span>{{ selectedWorkOrder.parts_cost || 0 }} DA</span></div>
                <div class="info-item highlight"><span class="info-label">Total</span><span class="cost-total">{{ selectedWorkOrder.total_cost || 0 }} DA</span></div>
              </div>
            </div>
          </div>

          <!-- Description -->
          <div class="detail-section" v-if="selectedWorkOrder.description">
            <h4>📝 Description</h4>
            <p class="description-text">{{ selectedWorkOrder.description }}</p>
          </div>

          <!-- Travaux effectués -->
          <div class="detail-section success-section" v-if="selectedWorkOrder.work_performed">
            <h4>✅ Travaux effectués</h4>
            <p class="description-text">{{ selectedWorkOrder.work_performed }}</p>
          </div>

          <!-- Diagnostic -->
          <div class="detail-section" v-if="selectedWorkOrder.diagnosis">
            <h4>🔎 Diagnostic</h4>
            <p class="description-text">{{ selectedWorkOrder.diagnosis }}</p>
          </div>

          <!-- Cause racine -->
          <div class="detail-section" v-if="selectedWorkOrder.root_cause">
            <h4>🎯 Cause racine</h4>
            <p class="description-text">{{ selectedWorkOrder.root_cause }}</p>
          </div>

          <!-- Annulation -->
          <div class="detail-section danger-section" v-if="selectedWorkOrder.cancellation_reason">
            <h4>❌ Raison d'annulation</h4>
            <p class="description-text">{{ selectedWorkOrder.cancellation_reason }}</p>
            <div class="info-item">
              <span class="info-label">Annulé par</span>
              <span>{{ selectedWorkOrder.cancelled_by?.name }} le {{ formatDate(selectedWorkOrder.cancelled_at) }}</span>
            </div>
          </div>

          <!-- Pièces utilisées -->
          <div class="detail-section">
            <div class="section-header">
              <h4>🔩 Pièces utilisées</h4>
              <button
                class="btn btn-sm btn-primary"
                @click="openAddPartModal"
                v-if="!['completed', 'cancelled'].includes(selectedWorkOrder.status) && authStore.hasPermission('workorder:use_parts')"
              >
                + Ajouter
              </button>
            </div>
            <table class="parts-table" v-if="selectedWorkOrder.parts?.length">
              <thead>
                <tr><th>Pièce</th><th>Qté</th><th>Prix unit.</th><th>Total</th><th></th></tr>
              </thead>
              <tbody>
                <tr v-for="p in selectedWorkOrder.parts" :key="p.id">
                  <td>{{ p.part?.name }}</td>
                  <td>{{ p.quantity_used }}</td>
                  <td>{{ p.unit_price }} DA</td>
                  <td><strong>{{ p.total_price }} DA</strong></td>
                  <td>
                    <button
                      class="btn-icon-sm danger"
                      @click.stop="removePart(p.id)"
                      v-if="!['completed', 'cancelled'].includes(selectedWorkOrder.status)"
                    >✕</button>
                  </td>
                </tr>
              </tbody>
            </table>
            <p class="empty-text" v-else>Aucune pièce utilisée</p>
          </div>

          <!-- Commentaires -->
          <div class="detail-section">
            <h4>💬 Commentaires</h4>
            <div class="comments-list" v-if="selectedWorkOrder.comments?.length">
              <div class="comment-item" v-for="c in selectedWorkOrder.comments" :key="c.id">
                <div class="comment-header">
                  <strong>{{ c.user?.name }}</strong>
                  <span class="comment-date">{{ formatDate(c.created_at) }}</span>
                </div>
                <p>{{ c.comment }}</p>
              </div>
            </div>
            <div class="comment-form" v-if="authStore.hasPermission('workorder:comment')">
              <textarea v-model="commentForm.comment" placeholder="Ajouter un commentaire..." rows="2"></textarea>
              <button class="btn btn-sm btn-primary" @click="addComment" :disabled="!commentForm.comment.trim() || saving">Envoyer</button>
            </div>
          </div>

          <!-- Historique -->
          <div class="detail-section">
            <h4>📜 Historique</h4>
            <div class="history-list" v-if="selectedWorkOrder.histories?.length">
              <div class="history-item" v-for="h in selectedWorkOrder.histories" :key="h.id">
                <span class="history-icon">{{ getHistoryIcon(h.action) }}</span>
                <div class="history-content">
                  <span class="history-desc">{{ h.description }}</span>
                  <span class="history-meta">{{ h.user?.name }} · {{ formatDate(h.created_at) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="detail-actions" v-if="!['completed', 'cancelled'].includes(selectedWorkOrder.status)">
            <button class="btn btn-info" @click="updateStatus('approved')" v-if="selectedWorkOrder.status === 'pending' && authStore.hasPermission('workorder:approve_close')">✓ Approuver</button>
            <button class="btn btn-primary" @click="updateStatus('in_progress')" v-if="['approved', 'assigned'].includes(selectedWorkOrder.status) && authStore.hasPermission('workorder:start')">▶ Démarrer</button>
            <button class="btn btn-warning" @click="updateStatus('on_hold')" v-if="selectedWorkOrder.status === 'in_progress'">⏸ Pause</button>
            <button class="btn btn-primary" @click="updateStatus('in_progress')" v-if="selectedWorkOrder.status === 'on_hold'">▶ Reprendre</button>
            <button class="btn btn-success" @click="updateStatus('completed')" v-if="['in_progress', 'on_hold'].includes(selectedWorkOrder.status) && authStore.hasPermission('workorder:close')">✓ Terminer</button>
            <button class="btn btn-danger" @click="updateStatus('cancelled')" v-if="authStore.hasPermission('workorder:update')">✕ Annuler</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Ajout pièce -->
    <div class="modal-overlay" v-if="showAddPartModal" @click.self="showAddPartModal = false">
      <div class="modal modal-sm">
        <div class="modal-header">
          <h2>🔩 Ajouter une pièce</h2>
          <button class="close-btn" @click="showAddPartModal = false">&times;</button>
        </div>
        <form @submit.prevent="addPart" class="modal-body">
          <div class="form-group">
            <label>Pièce *</label>
            <select v-model="partForm.part_id" required>
              <option value="">-- Sélectionner --</option>
              <option v-for="p in availableParts" :key="p.id" :value="p.id">
                {{ p.code }} - {{ p.name }} ({{ p.quantity_in_stock }} {{ p.unit }} dispo.) - {{ p.unit_price }} DA
              </option>
            </select>
          </div>
          <div class="form-group">
            <label>Quantité *</label>
            <input type="number" v-model="partForm.quantity" min="1" required />
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showAddPartModal = false">Annuler</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Ajout...' : 'Ajouter' }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Clôture -->
    <div class="modal-overlay" v-if="showCloseModal" @click.self="showCloseModal = false">
      <div class="modal">
        <div class="modal-header success">
          <h2>✅ Clôturer l'intervention</h2>
          <button class="close-btn" @click="showCloseModal = false">&times;</button>
        </div>
        <form @submit.prevent="completeWorkOrder" class="modal-body">
          <div class="info-box">
            Le temps sera calculé automatiquement depuis le début de l'intervention.
          </div>

          <!-- Kilométrage pour les camions -->
          <div class="form-group" v-if="selectedWorkOrder?.asset_type === 'truck'">
            <label>Kilométrage actuel du camion *</label>
            <div class="input-with-unit">
              <input
                type="number"
                v-model="closeForm.mileage_at_intervention"
                required
                min="0"
                :placeholder="'Km actuel (dernier: ' + (selectedWorkOrder?.truck?.mileage || 0) + ')'"
              />
              <span class="input-unit">km</span>
            </div>
            <small class="form-help">Le kilométrage du camion sera mis à jour automatiquement</small>
          </div>

          <div class="form-group">
            <label>Travaux effectués *</label>
            <textarea v-model="closeForm.work_performed" rows="4" required placeholder="Décrivez les travaux réalisés..."></textarea>
          </div>

          <div class="form-group">
            <label>Diagnostic</label>
            <textarea v-model="closeForm.diagnosis" rows="2" placeholder="Diagnostic effectué..."></textarea>
          </div>

          <div class="form-group">
            <label>Cause racine</label>
            <textarea v-model="closeForm.root_cause" rows="2" placeholder="Origine du problème si identifiée..."></textarea>
          </div>

          <div class="form-group">
            <label>Notes techniques</label>
            <textarea v-model="closeForm.technician_notes" rows="2" placeholder="Notes complémentaires..."></textarea>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showCloseModal = false">Annuler</button>
            <button type="submit" class="btn btn-success" :disabled="saving">{{ saving ? 'Clôture...' : 'Clôturer' }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Annulation -->
    <div class="modal-overlay" v-if="showCancelModal" @click.self="showCancelModal = false">
      <div class="modal modal-sm">
        <div class="modal-header danger">
          <h2>❌ Annuler l'intervention</h2>
          <button class="close-btn" @click="showCancelModal = false">&times;</button>
        </div>
        <form @submit.prevent="cancelWorkOrder" class="modal-body">
          <div class="warning-box">
            ⚠️ Cette action est irréversible. L'intervention sera marquée comme annulée.
          </div>

          <div class="form-group">
            <label>Raison de l'annulation *</label>
            <textarea v-model="cancelForm.reason" rows="3" required placeholder="Expliquez la raison de l'annulation..."></textarea>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showCancelModal = false">Retour</button>
            <button type="submit" class="btn btn-danger" :disabled="saving || !cancelForm.reason.trim()">
              {{ saving ? 'Annulation...' : 'Confirmer l\'annulation' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wo-page { padding: 30px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.page-header h1 { font-size: 28px; color: #2c3e50; margin-bottom: 5px; }
.subtitle { color: #7f8c8d; font-size: 14px; }

/* Active Filter Banner */
.active-filter-banner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: linear-gradient(135deg, #e3f2fd, #f0f7ff);
  border: 1px solid #90caf9;
  border-radius: 12px;
  padding: 14px 20px;
  margin-bottom: 20px;
}

.filter-info {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #1565c0;
  font-weight: 600;
  font-size: 15px;
}

.filter-icon {
  font-size: 20px;
}

.active-filter-banner .btn {
  background: white;
  color: #64748b;
  border: 1px solid #e2e8f0;
  padding: 8px 16px;
  border-radius: 8px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
}

.active-filter-banner .btn:hover {
  background: #fee2e2;
  color: #dc2626;
  border-color: #fecaca;
}

/* Stats */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 15px; margin-bottom: 20px; }
.stat-card { background: white; border-radius: 12px; padding: 20px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.stat-card.pending { border-left: 4px solid #ffc107; }
.stat-card.assigned { border-left: 4px solid #6f42c1; }
.stat-card.progress { border-left: 4px solid #28a745; }
.stat-card.completed { border-left: 4px solid #17a2b8; }
.stat-card.equipment { border-left: 4px solid #6c757d; }
.stat-card.truck { border-left: 4px solid #007bff; }
.stat-value { font-size: 28px; font-weight: bold; color: #2c3e50; }
.stat-label { font-size: 12px; color: #7f8c8d; }

/* Filters */
.filters-card { background: white; border-radius: 12px; padding: 15px 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.filters-row { display: flex; gap: 15px; flex-wrap: wrap; }
.filter-group { position: relative; }
.search-group { flex: 1; min-width: 200px; }
.filter-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); }
.search-input { width: 100%; padding: 10px 10px 10px 40px; border: 1px solid #ddd; border-radius: 8px; }
.filters-row select { padding: 10px 15px; border: 1px solid #ddd; border-radius: 8px; }

/* Work Order List */
.wo-list { display: flex; flex-direction: column; gap: 12px; }
.wo-card { display: flex; align-items: center; background: white; border-radius: 12px; padding: 15px 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); cursor: pointer; transition: all 0.2s; }
.wo-card:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
.wo-left { display: flex; flex-direction: column; align-items: center; gap: 5px; margin-right: 15px; }
.wo-type-icon { font-size: 24px; }
.wo-asset-icon { font-size: 16px; opacity: 0.7; }
.wo-content { flex: 1; }
.wo-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
.wo-code { font-weight: 600; color: #3498db; font-size: 14px; }
.wo-badges { display: flex; gap: 8px; flex-wrap: wrap; }
.wo-title { font-size: 16px; color: #2c3e50; margin-bottom: 8px; }
.wo-meta { display: flex; gap: 20px; font-size: 13px; color: #7f8c8d; flex-wrap: wrap; }
.wo-arrow { font-size: 20px; color: #bdc3c7; }

/* Badges */
.badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.badge.asset-badge.equipment { background: #e2e8f0; color: #475569; }
.badge.asset-badge.truck { background: #dbeafe; color: #1d4ed8; }
.badge.type-badge { background: #f3e8ff; color: #7c3aed; }

/* Loading & Empty */
.loading-state { display: flex; justify-content: center; padding: 50px; }
.spinner { width: 40px; height: 40px; border: 3px solid #f3f3f3; border-top: 3px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
.empty-state { text-align: center; padding: 50px; color: #7f8c8d; }
.empty-icon { font-size: 60px; margin-bottom: 15px; }

/* Modal */
.modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
.modal { background: white; border-radius: 16px; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
.modal.modal-xl { max-width: 900px; }
.modal.modal-sm { max-width: 400px; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #eee; }
.modal-header.success { background: #d4edda; }
.modal-header.danger { background: #f8d7da; }
.modal-header h2 { font-size: 18px; color: #2c3e50; }
.close-btn { background: none; border: none; font-size: 28px; cursor: pointer; color: #7f8c8d; }
.modal-body { padding: 20px; }
.modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 20px; border-top: 1px solid #eee; }

/* Forms */
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; font-size: 14px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #3498db; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
.form-help { color: #7f8c8d; font-size: 12px; margin-top: 5px; }

/* Asset type selector */
.asset-type-selector { display: flex; gap: 15px; }
.asset-type-option { flex: 1; cursor: pointer; }
.asset-type-option input { display: none; }
.option-content { display: flex; flex-direction: column; align-items: center; padding: 20px; border: 2px solid #e2e8f0; border-radius: 12px; transition: all 0.2s; }
.asset-type-option.active .option-content { border-color: #3498db; background: #f0f7ff; }
.option-icon { font-size: 32px; margin-bottom: 8px; }
.option-label { font-weight: 600; color: #2c3e50; }

/* Detail sections */
.detail-top { margin-bottom: 20px; }
.detail-badges { display: flex; gap: 10px; margin-bottom: 10px; flex-wrap: wrap; }
.detail-title { font-size: 22px; color: #2c3e50; }
.detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
.detail-section { background: #f8f9fa; border-radius: 12px; padding: 15px; }
.detail-section h4 { font-size: 14px; color: #2c3e50; margin-bottom: 12px; }
.detail-section.success-section { background: #d4edda; }
.detail-section.danger-section { background: #f8d7da; }
.detail-section.asset-section { background: #e8f4fd; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.section-header h4 { margin-bottom: 0; }

.info-list { display: flex; flex-direction: column; gap: 8px; }
.info-item { display: flex; justify-content: space-between; font-size: 13px; }
.info-item.highlight { background: white; padding: 8px; border-radius: 6px; font-weight: 600; }
.info-label { color: #7f8c8d; }
.duration { color: #3498db; }
.cost-total { color: #27ae60; }
.mileage-value { color: #e67e22; }

.description-text { font-size: 14px; line-height: 1.6; color: #2c3e50; }

/* Parts table */
.parts-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.parts-table th, .parts-table td { padding: 10px; text-align: left; border-bottom: 1px solid #eee; }
.parts-table th { color: #7f8c8d; font-weight: 600; }
.empty-text { color: #7f8c8d; font-size: 13px; text-align: center; padding: 15px; }

/* Comments */
.comments-list { max-height: 200px; overflow-y: auto; margin-bottom: 15px; }
.comment-item { background: white; padding: 12px; border-radius: 8px; margin-bottom: 10px; }
.comment-header { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 12px; }
.comment-date { color: #7f8c8d; }
.comment-item p { font-size: 13px; color: #2c3e50; margin: 0; }
.comment-form { display: flex; gap: 10px; }
.comment-form textarea { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 8px; resize: none; }

/* History */
.history-list { max-height: 250px; overflow-y: auto; }
.history-item { display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid #eee; }
.history-icon { font-size: 18px; }
.history-content { flex: 1; }
.history-desc { display: block; font-size: 13px; color: #2c3e50; }
.history-meta { font-size: 11px; color: #7f8c8d; }

/* Actions */
.detail-actions { display: flex; gap: 10px; flex-wrap: wrap; padding-top: 20px; border-top: 1px solid #eee; margin-top: 20px; }

/* Info boxes */
.info-box { background: #e3f2fd; border: 1px solid #90caf9; border-radius: 8px; padding: 12px; margin-bottom: 20px; font-size: 13px; color: #1565c0; }
.warning-box { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 12px; margin-bottom: 20px; font-size: 13px; color: #856404; }

/* Input with unit */
.input-with-unit { display: flex; align-items: center; }
.input-with-unit input { flex: 1; border-radius: 8px 0 0 8px; }
.input-unit { padding: 12px 15px; background: #f1f5f9; border: 1px solid #ddd; border-left: none; border-radius: 0 8px 8px 0; color: #64748b; font-weight: 500; }

/* Buttons */
.btn { padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 14px; }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.btn-primary { background: #3498db; color: white; }
.btn-primary:hover { background: #2980b9; }
.btn-secondary { background: #95a5a6; color: white; }
.btn-secondary:hover { background: #7f8c8d; }
.btn-success { background: #27ae60; color: white; }
.btn-success:hover { background: #219a52; }
.btn-danger { background: #e74c3c; color: white; }
.btn-danger:hover { background: #c0392b; }
.btn-warning { background: #f39c12; color: white; }
.btn-warning:hover { background: #d68910; }
.btn-info { background: #17a2b8; color: white; }
.btn-info:hover { background: #138496; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }

.btn-icon-sm { width: 28px; height: 28px; border-radius: 6px; border: none; cursor: pointer; font-size: 14px; background: #f1f5f9; }
.btn-icon-sm.danger:hover { background: #fee2e2; color: #dc2626; }

/* Alerts */
.alert { padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
.alert-success { background: #d4edda; color: #155724; }
.alert-error { background: #f8d7da; color: #721c24; }

/* Responsive */
@media (max-width: 768px) {
  .wo-page { padding: 15px; }
  .page-header { flex-direction: column; gap: 15px; }
  .page-header h1 { font-size: 22px; }
  .filters-row { flex-direction: column; }
  .search-group { min-width: 100%; }
  .filters-row select { width: 100%; }
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
  .wo-card { flex-direction: column; text-align: center; }
  .wo-left { flex-direction: row; margin-right: 0; margin-bottom: 10px; }
  .wo-header { flex-direction: column; gap: 10px; }
  .wo-meta { justify-content: center; }
  .wo-arrow { display: none; }
  .detail-grid { grid-template-columns: 1fr; }
  .form-row { grid-template-columns: 1fr; }
  .asset-type-selector { flex-direction: column; }
  .detail-actions { justify-content: center; }
  .active-filter-banner { flex-direction: column; gap: 12px; text-align: center; }
}
</style>
