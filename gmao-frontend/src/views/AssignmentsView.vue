<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'

const assignments = ref([])
const activeAssignments = ref([])
const stats = ref(null)
const drivers = ref([])
const trucks = ref([])
const loading = ref(true)
const showAssignModal = ref(false)
const showUnassignModal = ref(false)
const showDetailModal = ref(false)
const selectedAssignment = ref(null)
const activeTab = ref('active')
const activeView = ref('grid')
const pagination = ref({})

// Filtres
const filters = ref({
  truck_id: '',
  driver_id: '',
  status: '',
  reason: '',
  from_date: '',
  to_date: '',
})

const assignForm = ref({
  truck_id: '',
  driver_id: '',
  start_mileage: '',
  assignment_reason: 'regular',
  notes: '',
})

const unassignForm = ref({
  end_mileage: '',
  unassignment_reason: 'end_mission',
  notes: '',
})

const saving = ref(false)
const error = ref('')

const assignmentReasons = [
  { value: 'regular', label: 'Attribution régulière', icon: '🔄' },
  { value: 'mission', label: 'Mission spécifique', icon: '🎯' },
  { value: 'replacement', label: 'Remplacement', icon: '🔁' },
  { value: 'training', label: 'Formation', icon: '📚' },
  { value: 'temporary', label: 'Temporaire', icon: '⏱️' },
]

const unassignmentReasons = [
  { value: 'end_mission', label: 'Fin de mission', icon: '✅' },
  { value: 'breakdown', label: 'Panne véhicule', icon: '🔧' },
  { value: 'maintenance', label: 'Maintenance', icon: '🛠️' },
  { value: 'leave', label: 'Congé chauffeur', icon: '🏖️' },
  { value: 'reassignment', label: 'Réaffectation', icon: '🔀' },
  { value: 'termination', label: 'Fin de contrat', icon: '📋' },
]

// Calculs
const computedStats = computed(() => {
  if (!stats.value) return null
  
  const active = activeAssignments.value
  return {
    ...stats.value,
    trucksAssigned: active.length,
    trucksAvailable: trucks.value.filter(t => 
      !active.some(a => a.truck_id === t.id) && t.status !== 'out_of_service'
    ).length,
    driversAssigned: active.length,
    driversAvailable: drivers.value.filter(d => 
      !active.some(a => a.driver_id === d.id)
    ).length,
  }
})

// Grouper par chauffeur
const assignmentsByDriver = computed(() => {
  const grouped = {}
  assignments.value.forEach(a => {
    const driverName = `${a.driver?.first_name} ${a.driver?.last_name}`
    if (!grouped[driverName]) {
      grouped[driverName] = {
        driver: a.driver,
        assignments: [],
        totalDistance: 0,
      }
    }
    grouped[driverName].assignments.push(a)
    grouped[driverName].totalDistance += a.distance || 0
  })
  return grouped
})

// Grouper par camion
const assignmentsByTruck = computed(() => {
  const grouped = {}
  assignments.value.forEach(a => {
    const truckReg = a.truck?.registration_number
    if (!grouped[truckReg]) {
      grouped[truckReg] = {
        truck: a.truck,
        assignments: [],
        totalDistance: 0,
      }
    }
    grouped[truckReg].assignments.push(a)
    grouped[truckReg].totalDistance += a.distance || 0
  })
  return grouped
})

async function fetchAssignments(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: 20 }
    Object.keys(filters.value).forEach(key => {
      if (filters.value[key]) params[key] = filters.value[key]
    })
    const response = await api.get('/assignments', { params })
    assignments.value = response.data.data
    pagination.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      total: response.data.total,
    }
  } catch (err) {
    console.error('Erreur:', err)
  } finally {
    loading.value = false
  }
}

async function fetchActiveAssignments() {
  try {
    const response = await api.get('/assignments/active')
    activeAssignments.value = response.data
  } catch (err) {
    console.error('Erreur:', err)
  }
}

async function fetchStats() {
  try {
    const response = await api.get('/assignments/stats')
    stats.value = response.data
  } catch (err) {
    console.error('Erreur:', err)
  }
}

async function fetchDrivers() {
  try {
    const response = await api.get('/drivers-list')
    drivers.value = response.data
  } catch (err) {
    console.error('Erreur:', err)
  }
}

async function fetchTrucks() {
  try {
    const response = await api.get('/trucks-list')
    trucks.value = response.data
  } catch (err) {
    console.error('Erreur:', err)
  }
}

function openAssignModal() {
  assignForm.value = {
    truck_id: '',
    driver_id: '',
    start_mileage: '',
    assignment_reason: 'regular',
    notes: '',
  }
  error.value = ''
  showAssignModal.value = true
}

function closeAssignModal() {
  showAssignModal.value = false
}

async function submitAssignment() {
  saving.value = true
  error.value = ''
  try {
    await api.post('/assignments/assign', assignForm.value)
    closeAssignModal()
    fetchActiveAssignments()
    fetchAssignments()
    fetchStats()
  } catch (err) {
    error.value = err.response?.data?.message || 'Erreur lors de l\'attribution'
  } finally {
    saving.value = false
  }
}

function openUnassignModal(assignment) {
  selectedAssignment.value = assignment
  unassignForm.value = {
    end_mileage: assignment.truck?.mileage || assignment.start_mileage || '',
    unassignment_reason: 'end_mission',
    notes: '',
  }
  error.value = ''
  showUnassignModal.value = true
}

function closeUnassignModal() {
  showUnassignModal.value = false
  selectedAssignment.value = null
}

async function submitUnassignment() {
  saving.value = true
  error.value = ''
  try {
    await api.post(`/assignments/${selectedAssignment.value.id}/unassign`, unassignForm.value)
    closeUnassignModal()
    fetchActiveAssignments()
    fetchAssignments()
    fetchStats()
  } catch (err) {
    error.value = err.response?.data?.message || 'Erreur lors de la fin d\'attribution'
  } finally {
    saving.value = false
  }
}

function openDetailModal(assignment) {
  selectedAssignment.value = assignment
  showDetailModal.value = true
}

function closeDetailModal() {
  showDetailModal.value = false
  selectedAssignment.value = null
}

function applyFilters() {
  fetchAssignments()
}

function resetFilters() {
  filters.value = {
    truck_id: '',
    driver_id: '',
    status: '',
    reason: '',
    from_date: '',
    to_date: '',
  }
  fetchAssignments()
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function formatDateShort(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}

function formatNumber(num) {
  if (!num && num !== 0) return '0'
  return num.toLocaleString('fr-FR')
}

function getReasonLabel(reason, type = 'assignment') {
  const reasons = type === 'assignment' ? assignmentReasons : unassignmentReasons
  return reasons.find(r => r.value === reason)?.label || reason
}

function getReasonIcon(reason, type = 'assignment') {
  const reasons = type === 'assignment' ? assignmentReasons : unassignmentReasons
  return reasons.find(r => r.value === reason)?.icon || '📋'
}

function getDurationClass(duration) {
  if (!duration) return ''
  const days = parseInt(duration)
  if (days > 30) return 'long'
  if (days > 7) return 'medium'
  return 'short'
}

// Fonction pour afficher le camion avec son numéro
function getTruckDisplay(truck) {
  if (!truck) return '-'
  const parts = []
  if (truck.numero) parts.push(truck.numero)
  if (truck.registration_number) parts.push(truck.registration_number)
  return parts.join(' - ') || '-'
}

function getTruckFullDisplay(truck) {
  if (!truck) return '-'
  const parts = []
  if (truck.numero) parts.push(truck.numero)
  if (truck.brand) parts.push(truck.brand)
  if (truck.model) parts.push(truck.model)
  return parts.join(' - ') || '-'
}

// Camions et chauffeurs disponibles pour l'attribution
const availableTrucks = computed(() => {
  const assignedTruckIds = activeAssignments.value.map(a => a.truck_id)
  return trucks.value.filter(t => !assignedTruckIds.includes(t.id) && t.status !== 'out_of_service')
})

const availableDrivers = computed(() => {
  const assignedDriverIds = activeAssignments.value.map(a => a.driver_id)
  return drivers.value.filter(d => !assignedDriverIds.includes(d.id))
})

// Sélection camion pour auto-remplir kilométrage
function onTruckSelect() {
  const truck = trucks.value.find(t => t.id === assignForm.value.truck_id)
  if (truck) {
    assignForm.value.start_mileage = truck.mileage || ''
  }
}

onMounted(() => {
  fetchAssignments()
  fetchActiveAssignments()
  fetchStats()
  fetchDrivers()
  fetchTrucks()
})
</script>

<template>
  <div class="assignments-page">
    <header class="page-header">
      <div>
        <h1>🔄 Attributions</h1>
        <p class="subtitle">Gestion des affectations camions / chauffeurs</p>
      </div>
      <button class="btn btn-primary" @click="openAssignModal">
        + Nouvelle attribution
      </button>
    </header>

    <!-- Stats Cards -->
    <div class="stats-cards" v-if="computedStats">
      <div class="stat-card active" @click="activeTab = 'active'">
        <div class="stat-icon green">🔄</div>
        <div class="stat-content">
          <div class="stat-value">{{ computedStats.active_assignments || activeAssignments.length }}</div>
          <div class="stat-label">Actives</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon blue">🚛</div>
        <div class="stat-content">
          <div class="stat-value">{{ computedStats.trucksAvailable }}</div>
          <div class="stat-label">Camions dispos</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon purple">👷</div>
        <div class="stat-content">
          <div class="stat-value">{{ computedStats.driversAvailable }}</div>
          <div class="stat-label">Chauffeurs dispos</div>
        </div>
      </div>
      <div class="stat-card" @click="activeTab = 'history'">
        <div class="stat-icon gray">📊</div>
        <div class="stat-content">
          <div class="stat-value">{{ computedStats.total_assignments || 0 }}</div>
          <div class="stat-label">Total historique</div>
        </div>
      </div>
      <div class="stat-card info">
        <div class="stat-icon cyan">🛣️</div>
        <div class="stat-content">
          <div class="stat-value">{{ formatNumber(computedStats.total_distance) }}</div>
          <div class="stat-label">Km parcourus</div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-container">
      <div class="tabs">
        <button :class="{ active: activeTab === 'active' }" @click="activeTab = 'active'">
          🟢 Actives <span class="tab-count">{{ activeAssignments.length }}</span>
        </button>
        <button :class="{ active: activeTab === 'history' }" @click="activeTab = 'history'">
          📜 Historique
        </button>
        <button :class="{ active: activeTab === 'stats' }" @click="activeTab = 'stats'">
          📊 Statistiques
        </button>
      </div>
      
      <div class="view-toggle" v-if="activeTab === 'active'">
        <button :class="{ active: activeView === 'grid' }" @click="activeView = 'grid'" title="Vue grille">▦</button>
        <button :class="{ active: activeView === 'list' }" @click="activeView = 'list'" title="Vue liste">☰</button>
      </div>
    </div>

    <!-- Attributions Actives -->
    <div v-if="activeTab === 'active'" class="active-section">
      <div v-if="activeAssignments.length === 0" class="empty-state">
        <span class="empty-icon">🚛</span>
        <h3>Aucune attribution active</h3>
        <p>Créez une nouvelle attribution pour commencer</p>
        <button class="btn btn-primary" @click="openAssignModal">
          + Nouvelle attribution
        </button>
      </div>

      <!-- Grid View -->
      <div v-else-if="activeView === 'grid'" class="assignments-grid">
        <div 
          v-for="assignment in activeAssignments" 
          :key="assignment.id" 
          class="assignment-card"
          @click="openDetailModal(assignment)"
        >
          <div class="card-status">
            <span class="status-indicator active"></span>
            <span class="status-text">En cours</span>
            <span class="assignment-duration" :class="getDurationClass(assignment.duration)">
              {{ assignment.duration || 'Aujourd\'hui' }}
            </span>
          </div>

          <div class="assignment-pair">
            <div class="entity truck">
              <div class="entity-avatar truck">🚛</div>
              <div class="entity-info">
                <div class="entity-name">{{ assignment.truck?.numero || '' }} {{ assignment.truck?.numero ? '-' : '' }} {{ assignment.truck?.registration_number }}</div>
                <div class="entity-detail">{{ assignment.truck?.brand }} {{ assignment.truck?.model }}</div>
              </div>
            </div>
            
            <div class="pair-connector">
              <div class="connector-line"></div>
              <div class="connector-icon">🔗</div>
              <div class="connector-line"></div>
            </div>
            
            <div class="entity driver">
              <div class="entity-avatar driver">👷</div>
              <div class="entity-info">
                <div class="entity-name">{{ assignment.driver?.first_name }} {{ assignment.driver?.last_name }}</div>
                <div class="entity-detail">{{ assignment.driver?.code }}</div>
              </div>
            </div>
          </div>

          <div class="assignment-meta">
            <div class="meta-item">
              <span class="meta-icon">📅</span>
              <span class="meta-label">Début</span>
              <span class="meta-value">{{ formatDateShort(assignment.assigned_at) }}</span>
            </div>
            <div class="meta-item">
              <span class="meta-icon">🛣️</span>
              <span class="meta-label">Km départ</span>
              <span class="meta-value">{{ formatNumber(assignment.start_mileage) }}</span>
            </div>
            <div class="meta-item" v-if="assignment.assignment_reason">
              <span class="meta-icon">{{ getReasonIcon(assignment.assignment_reason) }}</span>
              <span class="meta-label">Raison</span>
              <span class="meta-value">{{ getReasonLabel(assignment.assignment_reason) }}</span>
            </div>
          </div>

          <div class="card-actions" @click.stop>
            <button class="btn-action info" @click="openDetailModal(assignment)" title="Détails">
              👁️
            </button>
            <button class="btn-action danger" @click="openUnassignModal(assignment)" title="Terminer">
              ⏹️
            </button>
          </div>
        </div>
      </div>

      <!-- List View -->
      <div v-else class="assignments-list">
        <div 
          v-for="assignment in activeAssignments" 
          :key="assignment.id" 
          class="assignment-row"
          @click="openDetailModal(assignment)"
        >
          <div class="row-status">
            <span class="status-dot active"></span>
          </div>
          
          <div class="row-truck">
            <span class="row-icon">🚛</span>
            <div class="row-info">
              <div class="row-primary">{{ assignment.truck?.numero || '' }} {{ assignment.truck?.numero ? '-' : '' }} {{ assignment.truck?.registration_number }}</div>
              <div class="row-secondary">{{ assignment.truck?.brand }} {{ assignment.truck?.model }}</div>
            </div>
          </div>
          
          <div class="row-connector">↔️</div>
          
          <div class="row-driver">
            <span class="row-icon">👷</span>
            <div class="row-info">
              <div class="row-primary">{{ assignment.driver?.first_name }} {{ assignment.driver?.last_name }}</div>
              <div class="row-secondary">{{ assignment.driver?.code }}</div>
            </div>
          </div>
          
          <div class="row-date">
            <div class="row-primary">{{ formatDateShort(assignment.assigned_at) }}</div>
            <div class="row-secondary">{{ assignment.duration || 'Aujourd\'hui' }}</div>
          </div>
          
          <div class="row-mileage">
            <span class="mileage-badge">{{ formatNumber(assignment.start_mileage) }} km</span>
          </div>
          
          <div class="row-reason">
            <span class="reason-badge">
              {{ getReasonIcon(assignment.assignment_reason) }} {{ getReasonLabel(assignment.assignment_reason) }}
            </span>
          </div>
          
          <div class="row-actions" @click.stop>
            <button class="btn-icon danger" @click="openUnassignModal(assignment)" title="Terminer">
              ⏹️
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Historique -->
    <div v-if="activeTab === 'history'" class="history-section">
      <!-- Filtres -->
      <div class="filters-bar">
        <div class="search-group">
          <select v-model="filters.truck_id" @change="applyFilters">
            <option value="">🚛 Tous les camions</option>
            <option v-for="truck in trucks" :key="truck.id" :value="truck.id">
              {{ truck.numero }} {{ truck.numero ? '-' : '' }} {{ truck.registration_number }}
            </option>
          </select>
          <select v-model="filters.driver_id" @change="applyFilters">
            <option value="">👷 Tous les chauffeurs</option>
            <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
              {{ driver.first_name }} {{ driver.last_name }}
            </option>
          </select>
          <select v-model="filters.status" @change="applyFilters">
            <option value="">📊 Tous statuts</option>
            <option value="active">🟢 En cours</option>
            <option value="completed">✅ Terminées</option>
          </select>
          <select v-model="filters.reason" @change="applyFilters">
            <option value="">📋 Toutes raisons</option>
            <option v-for="reason in assignmentReasons" :key="reason.value" :value="reason.value">
              {{ reason.icon }} {{ reason.label }}
            </option>
          </select>
        </div>
        <div class="date-group">
          <div class="date-input">
            <label>Du</label>
            <input type="date" v-model="filters.from_date" @change="applyFilters" />
          </div>
          <div class="date-input">
            <label>Au</label>
            <input type="date" v-model="filters.to_date" @change="applyFilters" />
          </div>
        </div>
        <button class="btn btn-secondary btn-sm" @click="resetFilters" v-if="Object.values(filters).some(v => v)">
          ✕ Reset
        </button>
      </div>

      <!-- Table -->
      <div class="table-container" v-if="!loading">
        <table class="history-table" v-if="assignments.length">
          <thead>
            <tr>
              <th>Statut</th>
              <th>Camion</th>
              <th>Chauffeur</th>
              <th>Début</th>
              <th>Fin</th>
              <th>Durée</th>
              <th>Distance</th>
              <th>Raison</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr 
              v-for="item in assignments" 
              :key="item.id"
              :class="{ active: !item.unassigned_at }"
              @click="openDetailModal(item)"
            >
              <td>
                <span class="status-badge" :class="item.unassigned_at ? 'completed' : 'active'">
                  {{ item.unassigned_at ? '✅ Terminée' : '🟢 En cours' }}
                </span>
              </td>
              <td>
                <div class="cell-entity">
                  <span class="cell-icon">🚛</span>
                  <div>
                    <div class="cell-primary">{{ item.truck?.numero || '' }} {{ item.truck?.numero ? '-' : '' }} {{ item.truck?.registration_number }}</div>
                    <div class="cell-secondary">{{ item.truck?.brand }} {{ item.truck?.model }}</div>
                  </div>
                </div>
              </td>
              <td>
                <div class="cell-entity">
                  <span class="cell-icon">👷</span>
                  <div>
                    <div class="cell-primary">{{ item.driver?.first_name }} {{ item.driver?.last_name }}</div>
                    <div class="cell-secondary">{{ item.driver?.code }}</div>
                  </div>
                </div>
              </td>
              <td>
                <div class="cell-date">
                  <div class="cell-primary">{{ formatDateShort(item.assigned_at) }}</div>
                  <div class="cell-secondary">{{ formatNumber(item.start_mileage) }} km</div>
                </div>
              </td>
              <td>
                <div class="cell-date" v-if="item.unassigned_at">
                  <div class="cell-primary">{{ formatDateShort(item.unassigned_at) }}</div>
                  <div class="cell-secondary">{{ formatNumber(item.end_mileage) }} km</div>
                </div>
                <span v-else class="text-muted">-</span>
              </td>
              <td>
                <span class="duration-badge" :class="getDurationClass(item.duration)">
                  {{ item.duration || '-' }}
                </span>
              </td>
              <td>
                <span v-if="item.distance !== null" class="distance-badge">
                  {{ formatNumber(item.distance) }} km
                </span>
                <span v-else class="text-muted">-</span>
              </td>
              <td>
                <span class="reason-tag">
                  {{ getReasonIcon(item.assignment_reason) }}
                  {{ getReasonLabel(item.assignment_reason) }}
                </span>
              </td>
              <td class="actions-cell" @click.stop>
                <div class="action-buttons">
                  <button class="btn-icon primary" @click="openDetailModal(item)" title="Détails">
                    👁️
                  </button>
                  <button 
                    v-if="!item.unassigned_at" 
                    class="btn-icon danger" 
                    @click="openUnassignModal(item)" 
                    title="Terminer"
                  >
                    ⏹️
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-else class="empty-state">
          <span class="empty-icon">📜</span>
          <h3>Aucun historique trouvé</h3>
          <p>Modifiez vos filtres ou créez une attribution</p>
        </div>
      </div>

      <div v-else class="loading-state">
        <div class="spinner"></div>
        <p>Chargement...</p>
      </div>

      <!-- Pagination -->
      <div class="pagination" v-if="pagination.last_page > 1">
        <button
          v-for="page in pagination.last_page"
          :key="page"
          :class="{ active: page === pagination.current_page }"
          @click="fetchAssignments(page)"
        >
          {{ page }}
        </button>
      </div>
    </div>

    <!-- Statistiques -->
    <div v-if="activeTab === 'stats'" class="stats-section">
      <!-- Vue d'ensemble -->
      <div class="overview-cards">
        <div class="overview-card">
          <div class="overview-icon">📈</div>
          <div class="overview-content">
            <div class="overview-value">{{ formatNumber(stats?.avg_distance_per_assignment) }}</div>
            <div class="overview-label">Km / Attribution (moy)</div>
          </div>
        </div>
        <div class="overview-card">
          <div class="overview-icon">⏱️</div>
          <div class="overview-content">
            <div class="overview-value">{{ stats?.avg_duration || '-' }}</div>
            <div class="overview-label">Durée moyenne</div>
          </div>
        </div>
        <div class="overview-card">
          <div class="overview-icon">📅</div>
          <div class="overview-content">
            <div class="overview-value">{{ stats?.assignments_this_month || 0 }}</div>
            <div class="overview-label">Ce mois</div>
          </div>
        </div>
      </div>

      <div class="stats-grid">
        <!-- Top Chauffeurs -->
        <div class="stats-widget">
          <div class="widget-header">
            <h3>🏆 Top Chauffeurs</h3>
            <span class="widget-subtitle">Par distance parcourue</span>
          </div>
          <div class="ranking-list">
            <div v-for="(driver, index) in stats?.top_drivers" :key="driver.id" class="ranking-item">
              <span class="rank" :class="'rank-' + (index + 1)">{{ index + 1 }}</span>
              <div class="rank-avatar">👷</div>
              <div class="rank-info">
                <div class="rank-name">{{ driver.first_name }} {{ driver.last_name }}</div>
                <div class="rank-detail">{{ driver.assignments_count || 0 }} attributions</div>
              </div>
              <div class="rank-stats">
                <div class="rank-value">{{ formatNumber(driver.total_distance) }}</div>
                <div class="rank-unit">km</div>
              </div>
            </div>
            <div v-if="!stats?.top_drivers?.length" class="empty-ranking">
              <span>📊</span>
              <p>Pas encore de données</p>
            </div>
          </div>
        </div>

        <!-- Top Camions -->
        <div class="stats-widget">
          <div class="widget-header">
            <h3>🚛 Camions les plus utilisés</h3>
            <span class="widget-subtitle">Par nombre d'attributions</span>
          </div>
          <div class="ranking-list">
            <div v-for="(truck, index) in stats?.top_trucks" :key="truck.id" class="ranking-item">
              <span class="rank" :class="'rank-' + (index + 1)">{{ index + 1 }}</span>
              <div class="rank-avatar truck">🚛</div>
              <div class="rank-info">
                <div class="rank-name">{{ truck.numero || '' }} {{ truck.numero ? '-' : '' }} {{ truck.registration_number }}</div>
                <div class="rank-detail">{{ truck.brand }} {{ truck.model }}</div>
              </div>
              <div class="rank-stats">
                <div class="rank-value">{{ truck.assignments_count }}</div>
                <div class="rank-unit">attrib.</div>
              </div>
            </div>
            <div v-if="!stats?.top_trucks?.length" class="empty-ranking">
              <span>📊</span>
              <p>Pas encore de données</p>
            </div>
          </div>
        </div>

        <!-- Raisons d'attribution -->
        <div class="stats-widget">
          <div class="widget-header">
            <h3>📋 Raisons d'attribution</h3>
            <span class="widget-subtitle">Répartition</span>
          </div>
          <div class="reasons-list">
            <div v-for="reason in stats?.reasons_breakdown" :key="reason.reason" class="reason-item">
              <div class="reason-info">
                <span class="reason-icon">{{ getReasonIcon(reason.reason) }}</span>
                <span class="reason-name">{{ getReasonLabel(reason.reason) }}</span>
              </div>
              <div class="reason-bar-container">
                <div 
                  class="reason-bar" 
                  :style="{ width: (reason.count / stats.total_assignments * 100) + '%' }"
                ></div>
              </div>
              <span class="reason-count">{{ reason.count }}</span>
            </div>
            <div v-if="!stats?.reasons_breakdown?.length" class="empty-ranking">
              <span>📊</span>
              <p>Pas encore de données</p>
            </div>
          </div>
        </div>

        <!-- Activité récente -->
        <div class="stats-widget">
          <div class="widget-header">
            <h3>📅 Activité récente</h3>
            <span class="widget-subtitle">7 derniers jours</span>
          </div>
          <div class="activity-list">
            <div v-for="day in stats?.daily_activity" :key="day.date" class="activity-item">
              <span class="activity-date">{{ formatDateShort(day.date) }}</span>
              <div class="activity-bar-container">
                <div 
                  class="activity-bar" 
                  :style="{ width: (day.count / Math.max(...stats.daily_activity.map(d => d.count)) * 100) + '%' }"
                ></div>
              </div>
              <span class="activity-count">{{ day.count }}</span>
            </div>
            <div v-if="!stats?.daily_activity?.length" class="empty-ranking">
              <span>📊</span>
              <p>Pas encore de données</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Attribution -->
    <div class="modal-overlay" v-if="showAssignModal" @click.self="closeAssignModal">
      <div class="modal">
        <div class="modal-header">
          <h2>➕ Nouvelle attribution</h2>
          <button class="close-btn" @click="closeAssignModal">×</button>
        </div>

        <form @submit.prevent="submitAssignment" class="modal-body">
          <div class="availability-summary">
            <div class="avail-item">
              <span class="avail-icon">🚛</span>
              <span class="avail-count">{{ availableTrucks.length }}</span>
              <span class="avail-label">camion(s) disponible(s)</span>
            </div>
            <div class="avail-item">
              <span class="avail-icon">👷</span>
              <span class="avail-count">{{ availableDrivers.length }}</span>
              <span class="avail-label">chauffeur(s) disponible(s)</span>
            </div>
          </div>

          <div class="form-section">
            <h3>Affectation</h3>
            <div class="form-group">
              <label>🚛 Camion *</label>
              <select v-model="assignForm.truck_id" required @change="onTruckSelect">
                <option value="">Sélectionner un camion</option>
                <option v-for="truck in availableTrucks" :key="truck.id" :value="truck.id">
                  {{ truck.numero || '' }} {{ truck.numero ? '-' : '' }} {{ truck.registration_number }} - {{ truck.brand }} {{ truck.model }}
                  <span v-if="truck.mileage">({{ formatNumber(truck.mileage) }} km)</span>
                </option>
              </select>
            </div>

            <div class="form-group">
              <label>👷 Chauffeur *</label>
              <select v-model="assignForm.driver_id" required>
                <option value="">Sélectionner un chauffeur</option>
                <option v-for="driver in availableDrivers" :key="driver.id" :value="driver.id">
                  {{ driver.first_name }} {{ driver.last_name }} ({{ driver.code }})
                </option>
              </select>
            </div>

            <div class="form-group">
              <label>🛣️ Kilométrage de départ</label>
              <input type="number" v-model="assignForm.start_mileage" min="0" placeholder="Km actuels du camion" />
              <small class="form-hint">Sera automatiquement rempli si le camion a un kilométrage enregistré</small>
            </div>
          </div>

          <div class="form-section">
            <h3>Détails</h3>
            <div class="form-group">
              <label>📋 Raison de l'attribution</label>
              <div class="reason-selector">
                <label 
                  v-for="reason in assignmentReasons" 
                  :key="reason.value" 
                  class="reason-option"
                  :class="{ selected: assignForm.assignment_reason === reason.value }"
                >
                  <input 
                    type="radio" 
                    v-model="assignForm.assignment_reason" 
                    :value="reason.value"
                  />
                  <span class="reason-icon">{{ reason.icon }}</span>
                  <span class="reason-label">{{ reason.label }}</span>
                </label>
              </div>
            </div>

            <div class="form-group">
              <label>📝 Notes</label>
              <textarea v-model="assignForm.notes" rows="2" placeholder="Informations complémentaires..."></textarea>
            </div>
          </div>

          <div class="form-error" v-if="error">{{ error }}</div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="closeAssignModal">Annuler</button>
            <button type="submit" class="btn btn-primary" :disabled="saving || !assignForm.truck_id || !assignForm.driver_id">
              {{ saving ? 'Attribution...' : '🔗 Attribuer' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Fin Attribution -->
    <div class="modal-overlay" v-if="showUnassignModal" @click.self="closeUnassignModal">
      <div class="modal">
        <div class="modal-header">
          <h2>⏹️ Terminer l'attribution</h2>
          <button class="close-btn" @click="closeUnassignModal">×</button>
        </div>

        <form @submit.prevent="submitUnassignment" class="modal-body">
          <div class="assignment-summary" v-if="selectedAssignment">
            <div class="summary-header">Attribution en cours</div>
            <div class="summary-pair">
              <div class="summary-entity">
                <span class="entity-icon">🚛</span>
                <span>{{ selectedAssignment.truck?.numero || '' }} {{ selectedAssignment.truck?.numero ? '-' : '' }} {{ selectedAssignment.truck?.registration_number }}</span>
              </div>
              <span class="summary-connector">↔️</span>
              <div class="summary-entity">
                <span class="entity-icon">👷</span>
                <span>{{ selectedAssignment.driver?.first_name }} {{ selectedAssignment.driver?.last_name }}</span>
              </div>
            </div>
            <div class="summary-details">
              <div class="summary-item">
                <span class="summary-label">Début</span>
                <span class="summary-value">{{ formatDate(selectedAssignment.assigned_at) }}</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Km départ</span>
                <span class="summary-value">{{ formatNumber(selectedAssignment.start_mileage) }} km</span>
              </div>
              <div class="summary-item">
                <span class="summary-label">Durée</span>
                <span class="summary-value">{{ selectedAssignment.duration || 'Aujourd\'hui' }}</span>
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>Fin de l'attribution</h3>
            <div class="form-group">
              <label>🛣️ Kilométrage de fin</label>
              <input 
                type="number" 
                v-model="unassignForm.end_mileage" 
                :min="selectedAssignment?.start_mileage"
                placeholder="Km actuels"
              />
              <div class="mileage-calc" v-if="unassignForm.end_mileage && selectedAssignment?.start_mileage">
                <span class="calc-label">Distance parcourue:</span>
                <span class="calc-value">{{ formatNumber(unassignForm.end_mileage - selectedAssignment.start_mileage) }} km</span>
              </div>
            </div>

            <div class="form-group">
              <label>📋 Raison de fin</label>
              <div class="reason-selector">
                <label 
                  v-for="reason in unassignmentReasons" 
                  :key="reason.value" 
                  class="reason-option"
                  :class="{ selected: unassignForm.unassignment_reason === reason.value }"
                >
                  <input 
                    type="radio" 
                    v-model="unassignForm.unassignment_reason" 
                    :value="reason.value"
                  />
                  <span class="reason-icon">{{ reason.icon }}</span>
                  <span class="reason-label">{{ reason.label }}</span>
                </label>
              </div>
            </div>

            <div class="form-group">
              <label>📝 Notes</label>
              <textarea v-model="unassignForm.notes" rows="2" placeholder="Commentaires..."></textarea>
            </div>
          </div>

          <div class="form-error" v-if="error">{{ error }}</div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="closeUnassignModal">Annuler</button>
            <button type="submit" class="btn btn-danger" :disabled="saving">
              {{ saving ? 'Traitement...' : '⏹️ Terminer' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Détails -->
    <div class="modal-overlay" v-if="showDetailModal" @click.self="closeDetailModal">
      <div class="modal modal-lg">
        <div class="modal-header">
          <h2>📋 Détails de l'attribution</h2>
          <button class="close-btn" @click="closeDetailModal">×</button>
        </div>

        <div class="modal-body" v-if="selectedAssignment">
          <div class="detail-status-banner" :class="selectedAssignment.unassigned_at ? 'completed' : 'active'">
            <span class="status-icon">{{ selectedAssignment.unassigned_at ? '✅' : '🟢' }}</span>
            <span class="status-text">{{ selectedAssignment.unassigned_at ? 'Attribution terminée' : 'Attribution en cours' }}</span>
            <span class="status-duration">{{ selectedAssignment.duration || 'Aujourd\'hui' }}</span>
          </div>

          <div class="detail-pair-section">
            <div class="detail-entity truck">
              <div class="detail-entity-header">
                <span class="detail-entity-icon">🚛</span>
                <span class="detail-entity-label">Camion</span>
              </div>
              <div class="detail-entity-name">{{ selectedAssignment.truck?.numero || 'N/A' }}</div>
              <div class="detail-entity-info">{{ selectedAssignment.truck?.registration_number }}</div>
              <div class="detail-entity-info">{{ selectedAssignment.truck?.brand }} {{ selectedAssignment.truck?.model }}</div>
              <div class="detail-entity-code">{{ selectedAssignment.truck?.code }}</div>
            </div>

            <div class="detail-connector">
              <div class="connector-vertical"></div>
              <span class="connector-symbol">🔗</span>
              <div class="connector-vertical"></div>
            </div>

            <div class="detail-entity driver">
              <div class="detail-entity-header">
                <span class="detail-entity-icon">👷</span>
                <span class="detail-entity-label">Chauffeur</span>
              </div>
              <div class="detail-entity-name">{{ selectedAssignment.driver?.first_name }} {{ selectedAssignment.driver?.last_name }}</div>
              <div class="detail-entity-info">{{ selectedAssignment.driver?.phone }}</div>
              <div class="detail-entity-code">{{ selectedAssignment.driver?.code }}</div>
            </div>
          </div>

          <div class="detail-stats-row">
            <div class="detail-stat-item">
              <div class="detail-stat-icon">📅</div>
              <div class="detail-stat-content">
                <div class="detail-stat-label">Début</div>
                <div class="detail-stat-value">{{ formatDate(selectedAssignment.assigned_at) }}</div>
              </div>
            </div>
            <div class="detail-stat-item" v-if="selectedAssignment.unassigned_at">
              <div class="detail-stat-icon">🏁</div>
              <div class="detail-stat-content">
                <div class="detail-stat-label">Fin</div>
                <div class="detail-stat-value">{{ formatDate(selectedAssignment.unassigned_at) }}</div>
              </div>
            </div>
            <div class="detail-stat-item">
              <div class="detail-stat-icon">🛣️</div>
              <div class="detail-stat-content">
                <div class="detail-stat-label">Km départ</div>
                <div class="detail-stat-value">{{ formatNumber(selectedAssignment.start_mileage) }} km</div>
              </div>
            </div>
            <div class="detail-stat-item" v-if="selectedAssignment.end_mileage">
              <div class="detail-stat-icon">🛣️</div>
              <div class="detail-stat-content">
                <div class="detail-stat-label">Km fin</div>
                <div class="detail-stat-value">{{ formatNumber(selectedAssignment.end_mileage) }} km</div>
              </div>
            </div>
            <div class="detail-stat-item highlight" v-if="selectedAssignment.distance">
              <div class="detail-stat-icon">📏</div>
              <div class="detail-stat-content">
                <div class="detail-stat-label">Distance</div>
                <div class="detail-stat-value">{{ formatNumber(selectedAssignment.distance) }} km</div>
              </div>
            </div>
          </div>

          <div class="detail-info-section">
            <div class="detail-info-item">
              <span class="detail-info-label">Raison d'attribution</span>
              <span class="detail-info-value">
                {{ getReasonIcon(selectedAssignment.assignment_reason) }}
                {{ getReasonLabel(selectedAssignment.assignment_reason) }}
              </span>
            </div>
            <div class="detail-info-item" v-if="selectedAssignment.unassignment_reason">
              <span class="detail-info-label">Raison de fin</span>
              <span class="detail-info-value">
                {{ getReasonIcon(selectedAssignment.unassignment_reason, 'unassignment') }}
                {{ getReasonLabel(selectedAssignment.unassignment_reason, 'unassignment') }}
              </span>
            </div>
            <div class="detail-info-item" v-if="selectedAssignment.notes">
              <span class="detail-info-label">Notes</span>
              <span class="detail-info-value">{{ selectedAssignment.notes }}</span>
            </div>
          </div>

          <div class="detail-actions" v-if="!selectedAssignment.unassigned_at">
            <button class="btn btn-danger" @click="closeDetailModal(); openUnassignModal(selectedAssignment)">
              ⏹️ Terminer cette attribution
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.assignments-page {
  padding: 30px;
  max-width: 1600px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 25px;
}

.page-header h1 {
  font-size: 28px;
  color: #2c3e50;
  margin-bottom: 5px;
}

.subtitle {
  color: #7f8c8d;
  font-size: 14px;
}

/* Stats Cards */
.stats-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 15px;
  margin-bottom: 25px;
}

.stat-card {
  background: white;
  border-radius: 16px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 15px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  cursor: pointer;
  transition: all 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
}

.stat-icon {
  width: 50px;
  height: 50px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
}

.stat-icon.green { background: #d4edda; }
.stat-icon.blue { background: #cce5ff; }
.stat-icon.purple { background: #e2d5f1; }
.stat-icon.gray { background: #e2e3e5; }
.stat-icon.cyan { background: #d1ecf1; }

.stat-value {
  font-size: 28px;
  font-weight: bold;
  color: #2c3e50;
}

.stat-label {
  font-size: 13px;
  color: #7f8c8d;
}

/* Tabs */
.tabs-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.tabs {
  display: flex;
  gap: 10px;
  background: white;
  padding: 5px;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.tabs button {
  padding: 12px 20px;
  border: none;
  background: transparent;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #7f8c8d;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  gap: 8px;
}

.tabs button:hover {
  background: #f8f9fa;
  color: #2c3e50;
}

.tabs button.active {
  background: #3498db;
  color: white;
}

.tab-count {
  background: rgba(255, 255, 255, 0.3);
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 12px;
}

.view-toggle {
  display: flex;
  gap: 5px;
  background: white;
  padding: 5px;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.view-toggle button {
  width: 36px;
  height: 36px;
  border: none;
  background: transparent;
  border-radius: 6px;
  cursor: pointer;
  font-size: 16px;
  transition: all 0.2s ease;
}

.view-toggle button:hover {
  background: #f8f9fa;
}

.view-toggle button.active {
  background: #3498db;
  color: white;
}

/* Grid View */
.assignments-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 20px;
}

.assignment-card {
  background: white;
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
  cursor: pointer;
  transition: all 0.3s ease;
  border: 2px solid transparent;
}

.assignment-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
  border-color: #3498db;
}

.card-status {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 15px;
}

.status-indicator {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  animation: pulse 2s infinite;
}

.status-indicator.active {
  background: #27ae60;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.status-text {
  font-size: 12px;
  font-weight: 600;
  color: #27ae60;
  text-transform: uppercase;
}

.assignment-duration {
  margin-left: auto;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
}

.assignment-duration.short { background: #d4edda; color: #155724; }
.assignment-duration.medium { background: #fff3cd; color: #856404; }
.assignment-duration.long { background: #f8d7da; color: #721c24; }

.assignment-pair {
  display: flex;
  align-items: center;
  gap: 15px;
  margin-bottom: 20px;
}

.entity {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 12px;
}

.entity-avatar {
  width: 45px;
  height: 45px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
}

.entity-avatar.truck { background: #e3f2fd; }
.entity-avatar.driver { background: #f3e5f5; }

.entity-name {
  font-weight: 600;
  color: #2c3e50;
  font-size: 14px;
}

.entity-detail {
  font-size: 12px;
  color: #7f8c8d;
}

.pair-connector {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 5px;
}

.connector-line {
  width: 2px;
  height: 15px;
  background: linear-gradient(to bottom, #e0e0e0, #3498db);
}

.connector-icon {
  font-size: 16px;
}

.assignment-meta {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 10px;
  margin-bottom: 15px;
}

.meta-item {
  text-align: center;
}

.meta-icon {
  font-size: 16px;
  display: block;
  margin-bottom: 5px;
}

.meta-label {
  font-size: 10px;
  color: #7f8c8d;
  text-transform: uppercase;
  display: block;
}

.meta-value {
  font-size: 12px;
  font-weight: 600;
  color: #2c3e50;
}

.card-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
}

.btn-action {
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 16px;
  transition: all 0.2s ease;
}

.btn-action.info {
  background: #e3f2fd;
}

.btn-action.info:hover {
  background: #bbdefb;
}

.btn-action.danger {
  background: #ffebee;
}

.btn-action.danger:hover {
  background: #ffcdd2;
}

/* List View */
.assignments-list {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.assignment-row {
  display: flex;
  align-items: center;
  padding: 15px 20px;
  border-bottom: 1px solid #f1f1f1;
  cursor: pointer;
  transition: background 0.2s ease;
}

.assignment-row:hover {
  background: #f8f9fa;
}

.assignment-row:last-child {
  border-bottom: none;
}

.row-status {
  margin-right: 15px;
}

.status-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.status-dot.active {
  background: #27ae60;
  animation: pulse 2s infinite;
}

.row-truck,
.row-driver {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
}

.row-icon {
  font-size: 20px;
}

.row-primary {
  font-weight: 600;
  color: #2c3e50;
  font-size: 14px;
}

.row-secondary {
  font-size: 12px;
  color: #7f8c8d;
}

.row-connector {
  margin: 0 15px;
  font-size: 16px;
}

.row-date,
.row-mileage,
.row-reason {
  margin-left: 15px;
}

.mileage-badge {
  background: #e3f2fd;
  color: #1565c0;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
}

.reason-badge {
  background: #f3e5f5;
  color: #7b1fa2;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12px;
}

.row-actions {
  margin-left: 15px;
}

.btn-icon {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s ease;
}

.btn-icon.primary { background: #e3f2fd; }
.btn-icon.danger { background: #ffebee; }
.btn-icon:hover { transform: scale(1.1); }

/* History Section */
.history-section {
  background: white;
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.filters-bar {
  display: flex;
  gap: 15px;
  margin-bottom: 20px;
  flex-wrap: wrap;
  align-items: flex-end;
}

.search-group {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.search-group select {
  padding: 10px 15px;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 14px;
  min-width: 180px;
}

.date-group {
  display: flex;
  gap: 10px;
}

.date-input {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.date-input label {
  font-size: 12px;
  color: #7f8c8d;
}

.date-input input {
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 8px;
}

.table-container {
  overflow-x: auto;
}

.history-table {
  width: 100%;
  border-collapse: collapse;
}

.history-table th {
  text-align: left;
  padding: 12px 15px;
  background: #f8f9fa;
  font-size: 12px;
  font-weight: 600;
  color: #7f8c8d;
  text-transform: uppercase;
}

.history-table td {
  padding: 15px;
  border-bottom: 1px solid #f1f1f1;
}

.history-table tr {
  cursor: pointer;
  transition: background 0.2s ease;
}

.history-table tr:hover {
  background: #f8f9fa;
}

.history-table tr.active {
  background: #f0fff4;
}

.cell-entity {
  display: flex;
  align-items: center;
  gap: 10px;
}

.cell-icon {
  font-size: 18px;
}

.cell-primary {
  font-weight: 600;
  color: #2c3e50;
  font-size: 13px;
}

.cell-secondary {
  font-size: 11px;
  color: #7f8c8d;
}

.status-badge {
  padding: 5px 12px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
}

.status-badge.active {
  background: #d4edda;
  color: #155724;
}

.status-badge.completed {
  background: #cce5ff;
  color: #004085;
}

.duration-badge {
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 500;
  background: #f8f9fa;
  color: #495057;
}

.duration-badge.short { background: #d4edda; color: #155724; }
.duration-badge.medium { background: #fff3cd; color: #856404; }
.duration-badge.long { background: #f8d7da; color: #721c24; }

.distance-badge {
  background: #e3f2fd;
  color: #1565c0;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 500;
}

.reason-tag {
  font-size: 12px;
  color: #6c757d;
}

.action-buttons {
  display: flex;
  gap: 8px;
}

.text-muted {
  color: #adb5bd;
}

/* Stats Section */
.stats-section {
  padding: 0;
}

.overview-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  margin-bottom: 25px;
}

.overview-card {
  background: white;
  border-radius: 16px;
  padding: 25px;
  display: flex;
  align-items: center;
  gap: 20px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.overview-icon {
  width: 60px;
  height: 60px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 28px;
}

.overview-value {
  font-size: 32px;
  font-weight: bold;
  color: #2c3e50;
}

.overview-label {
  font-size: 13px;
  color: #7f8c8d;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 20px;
}

.stats-widget {
  background: white;
  border-radius: 16px;
  padding: 25px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.widget-header {
  margin-bottom: 20px;
}

.widget-header h3 {
  font-size: 16px;
  color: #2c3e50;
  margin-bottom: 5px;
}

.widget-subtitle {
  font-size: 12px;
  color: #7f8c8d;
}

.ranking-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.ranking-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background: #f8f9fa;
  border-radius: 10px;
}

.rank {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: bold;
  background: #e2e3e5;
  color: #495057;
}

.rank.rank-1 { background: #ffd700; color: #856404; }
.rank.rank-2 { background: #c0c0c0; color: #495057; }
.rank.rank-3 { background: #cd7f32; color: white; }

.rank-avatar {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  background: #f3e5f5;
}

.rank-avatar.truck {
  background: #e3f2fd;
}

.rank-info {
  flex: 1;
}

.rank-name {
  font-weight: 600;
  color: #2c3e50;
  font-size: 14px;
}

.rank-detail {
  font-size: 12px;
  color: #7f8c8d;
}

.rank-stats {
  text-align: right;
}

.rank-value {
  font-size: 18px;
  font-weight: bold;
  color: #3498db;
}

.rank-unit {
  font-size: 11px;
  color: #7f8c8d;
}

.empty-ranking {
  text-align: center;
  padding: 30px;
  color: #7f8c8d;
}

.empty-ranking span {
  font-size: 40px;
  display: block;
  margin-bottom: 10px;
}

.reasons-list,
.activity-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.reason-item,
.activity-item {
  display: flex;
  align-items: center;
  gap: 12px;
}

.reason-info {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 150px;
}

.reason-icon {
  font-size: 16px;
}

.reason-name {
  font-size: 13px;
  color: #2c3e50;
}

.reason-bar-container,
.activity-bar-container {
  flex: 1;
  height: 8px;
  background: #e9ecef;
  border-radius: 4px;
  overflow: hidden;
}

.reason-bar {
  height: 100%;
  background: linear-gradient(90deg, #667eea, #764ba2);
  border-radius: 4px;
  transition: width 0.5s ease;
}

.activity-bar {
  height: 100%;
  background: linear-gradient(90deg, #3498db, #2ecc71);
  border-radius: 4px;
  transition: width 0.5s ease;
}

.reason-count,
.activity-count {
  min-width: 30px;
  text-align: right;
  font-weight: 600;
  color: #2c3e50;
  font-size: 13px;
}

.activity-date {
  min-width: 80px;
  font-size: 12px;
  color: #7f8c8d;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: #7f8c8d;
}

.empty-icon {
  font-size: 60px;
  display: block;
  margin-bottom: 15px;
}

.empty-state h3 {
  font-size: 18px;
  color: #2c3e50;
  margin-bottom: 10px;
}

.empty-state p {
  margin-bottom: 20px;
}

/* Loading State */
.loading-state {
  text-align: center;
  padding: 60px;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #f3f3f3;
  border-top: 3px solid #3498db;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 15px;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Pagination */
.pagination {
  display: flex;
  justify-content: center;
  gap: 5px;
  margin-top: 20px;
}

.pagination button {
  width: 36px;
  height: 36px;
  border: 1px solid #ddd;
  background: white;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s ease;
}

.pagination button:hover {
  background: #f8f9fa;
}

.pagination button.active {
  background: #3498db;
  color: white;
  border-color: #3498db;
}

/* Modals */
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
}

.modal {
  background: white;
  border-radius: 20px;
  width: 100%;
  max-width: 550px;
  max-height: 90vh;
  overflow-y: auto;
  animation: modalSlide 0.3s ease;
}

.modal.modal-lg {
  max-width: 700px;
}

@keyframes modalSlide {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 25px;
  border-bottom: 1px solid #eee;
}

.modal-header h2 {
  font-size: 18px;
  color: #2c3e50;
}

.close-btn {
  width: 36px;
  height: 36px;
  border: none;
  background: #f8f9fa;
  border-radius: 50%;
  font-size: 24px;
  cursor: pointer;
  color: #7f8c8d;
  transition: all 0.2s ease;
}

.close-btn:hover {
  background: #e9ecef;
  color: #2c3e50;
}

.modal-body {
  padding: 25px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 20px 25px;
  border-top: 1px solid #eee;
}

/* Form Styles */
.availability-summary {
  display: flex;
  gap: 20px;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 12px;
  margin-bottom: 25px;
}

.avail-item {
  display: flex;
  align-items: center;
  gap: 10px;
}

.avail-icon {
  font-size: 24px;
}

.avail-count {
  font-size: 24px;
  font-weight: bold;
  color: #27ae60;
}

.avail-label {
  font-size: 13px;
  color: #7f8c8d;
}

.form-section {
  margin-bottom: 25px;
}

.form-section h3 {
  font-size: 14px;
  color: #7f8c8d;
  text-transform: uppercase;
  margin-bottom: 15px;
  padding-bottom: 10px;
  border-bottom: 1px solid #eee;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #2c3e50;
  margin-bottom: 8px;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 12px 15px;
  border: 1px solid #ddd;
  border-radius: 10px;
  font-size: 14px;
  transition: border-color 0.2s ease;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #3498db;
}

.form-hint {
  display: block;
  font-size: 12px;
  color: #7f8c8d;
  margin-top: 5px;
}

.reason-selector {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 10px;
}

.reason-option {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 15px 10px;
  border: 2px solid #e9ecef;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
}

.reason-option:hover {
  border-color: #3498db;
  background: #f8f9fa;
}

.reason-option.selected {
  border-color: #3498db;
  background: #e3f2fd;
}

.reason-option input {
  display: none;
}

.reason-option .reason-icon {
  font-size: 24px;
  margin-bottom: 8px;
}

.reason-option .reason-label {
  font-size: 12px;
  text-align: center;
  color: #2c3e50;
}

.mileage-calc {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 10px;
  padding: 10px 15px;
  background: #d4edda;
  border-radius: 8px;
}

.calc-label {
  font-size: 13px;
  color: #155724;
}

.calc-value {
  font-weight: bold;
  color: #155724;
}

.form-error {
  background: #f8d7da;
  color: #721c24;
  padding: 12px 15px;
  border-radius: 8px;
  margin-bottom: 15px;
  font-size: 14px;
}

/* Assignment Summary */
.assignment-summary {
  background: #f8f9fa;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 25px;
}

.summary-header {
  font-size: 12px;
  color: #7f8c8d;
  text-transform: uppercase;
  margin-bottom: 15px;
}

.summary-pair {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 20px;
  margin-bottom: 20px;
}

.summary-entity {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 600;
  color: #2c3e50;
}

.entity-icon {
  font-size: 20px;
}

.summary-connector {
  font-size: 20px;
}

.summary-details {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 15px;
}

.summary-item {
  text-align: center;
}

.summary-label {
  display: block;
  font-size: 11px;
  color: #7f8c8d;
  text-transform: uppercase;
  margin-bottom: 5px;
}

.summary-value {
  font-size: 14px;
  font-weight: 600;
  color: #2c3e50;
}

/* Detail Modal */
.detail-status-banner {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 15px 20px;
  border-radius: 12px;
  margin-bottom: 25px;
}

.detail-status-banner.active {
  background: linear-gradient(135deg, #d4edda, #c3e6cb);
}

.detail-status-banner.completed {
  background: linear-gradient(135deg, #cce5ff, #b8daff);
}

.status-icon {
  font-size: 24px;
}

.detail-status-banner .status-text {
  font-weight: 600;
  color: #2c3e50;
}

.status-duration {
  margin-left: auto;
  padding: 5px 15px;
  background: rgba(255, 255, 255, 0.5);
  border-radius: 20px;
  font-size: 13px;
  font-weight: 500;
}

.detail-pair-section {
  display: flex;
  align-items: stretch;
  gap: 30px;
  margin-bottom: 25px;
}

.detail-entity {
  flex: 1;
  padding: 20px;
  border-radius: 12px;
  text-align: center;
}

.detail-entity.truck {
  background: #e3f2fd;
}

.detail-entity.driver {
  background: #f3e5f5;
}

.detail-entity-header {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-bottom: 15px;
}

.detail-entity-icon {
  font-size: 24px;
}

.detail-entity-label {
  font-size: 12px;
  color: #7f8c8d;
  text-transform: uppercase;
}

.detail-entity-name {
  font-size: 20px;
  font-weight: bold;
  color: #2c3e50;
  margin-bottom: 5px;
}

.detail-entity-info {
  font-size: 14px;
  color: #495057;
  margin-bottom: 3px;
}

.detail-entity-code {
  font-size: 12px;
  color: #7f8c8d;
}

.detail-connector {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.connector-vertical {
  width: 2px;
  height: 30px;
  background: linear-gradient(to bottom, #e0e0e0, #3498db);
}

.connector-symbol {
  font-size: 24px;
  margin: 10px 0;
}

.detail-stats-row {
  display: flex;
  flex-wrap: wrap;
  gap: 15px;
  margin-bottom: 25px;
}

.detail-stat-item {
  flex: 1;
  min-width: 120px;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 15px;
  background: #f8f9fa;
  border-radius: 10px;
}

.detail-stat-item.highlight {
  background: #d4edda;
}

.detail-stat-icon {
  font-size: 24px;
}

.detail-stat-label {
  font-size: 11px;
  color: #7f8c8d;
  text-transform: uppercase;
}

.detail-stat-value {
  font-size: 14px;
  font-weight: 600;
  color: #2c3e50;
}

.detail-info-section {
  background: #f8f9fa;
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 20px;
}

.detail-info-item {
  display: flex;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid #e9ecef;
}

.detail-info-item:last-child {
  border-bottom: none;
}

.detail-info-label {
  font-size: 13px;
  color: #7f8c8d;
}

.detail-info-value {
  font-size: 13px;
  font-weight: 500;
  color: #2c3e50;
}

.detail-actions {
  display: flex;
  justify-content: center;
}

/* Buttons */
.btn {
  padding: 12px 24px;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.btn-primary {
  background: linear-gradient(135deg, #3498db, #2980b9);
  color: white;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
}

.btn-secondary {
  background: #e9ecef;
  color: #495057;
}

.btn-secondary:hover {
  background: #dee2e6;
}

.btn-danger {
  background: linear-gradient(135deg, #e74c3c, #c0392b);
  color: white;
}

.btn-danger:hover {
  transform: translateY(-2px);
  box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
}

.btn-sm {
  padding: 8px 16px;
  font-size: 13px;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none !important;
}

/* Responsive */
@media (max-width: 768px) {
  .assignments-page {
    padding: 15px;
  }

  .page-header {
    flex-direction: column;
    gap: 15px;
  }

  .page-header h1 {
    font-size: 22px;
  }

  .stats-cards {
    grid-template-columns: repeat(2, 1fr);
  }

  .tabs-container {
    flex-direction: column;
    gap: 15px;
  }

  .tabs {
    width: 100%;
    overflow-x: auto;
  }

  .assignments-grid {
    grid-template-columns: 1fr;
  }

  .assignment-pair {
    flex-direction: column;
  }

  .pair-connector {
    flex-direction: row;
    transform: rotate(90deg);
  }

  .assignment-meta {
    grid-template-columns: 1fr;
  }

  .assignment-row {
    flex-wrap: wrap;
    gap: 10px;
  }

  .row-truck,
  .row-driver {
    flex: none;
    width: 45%;
  }

  .row-date,
  .row-mileage,
  .row-reason {
    margin-left: 0;
  }

  .filters-bar {
    flex-direction: column;
  }

  .search-group {
    width: 100%;
  }

  .search-group select {
    width: 100%;
  }

  .date-group {
    width: 100%;
  }

  .overview-cards {
    grid-template-columns: 1fr;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .detail-pair-section {
    flex-direction: column;
  }

  .detail-connector {
    flex-direction: row;
    padding: 10px 0;
  }

  .connector-vertical {
    width: 30px;
    height: 2px;
  }

  .reason-selector {
    grid-template-columns: repeat(2, 1fr);
  }

  .summary-details {
    grid-template-columns: 1fr;
  }

  .modal {
    margin: 10px;
    max-height: calc(100vh - 20px);
  }
}
</style>
