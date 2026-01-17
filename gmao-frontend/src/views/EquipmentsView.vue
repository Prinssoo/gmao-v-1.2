<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()
const equipments = ref([])
const loading = ref(false)
const error = ref('')
const showModal = ref(false)
const showDetailModal = ref(false)
const editingEquipment = ref(null)
const selectedEquipment = ref(null)
const activeView = ref('table')
const pagination = ref({})

// Filtres
const search = ref('')
const typeFilter = ref('')
const statusFilter = ref('')
const criticalityFilter = ref('')
const locationFilter = ref('')
const types = ref([])
const locationsOptions = ref([]) // Liste des emplacements pour les filtres et formulaire

function formatDateForInput(dateString) {
  if (!dateString) return ''
  return dateString.split('T')[0]
}

const form = ref({
  code: '',
  name: '',
  type: '',
  brand: '',
  model: '',
  serial_number: '',
  location_id: '', // Changé de 'location' à 'location_id'
  department: '',
  criticality: 'medium',
  status: 'operational',
  installation_date: '',
  warranty_expiry_date: '',
  description: '',
  specifications: '',
  notes: '',
})

const saving = ref(false)

const statusLabels = {
  operational: { label: 'Opérationnel', class: 'success', icon: '🟢' },
  degraded: { label: 'Dégradé', class: 'warning', icon: '🟠' },
  stopped: { label: 'Arrêté', class: 'danger', icon: '🔴' },
  maintenance: { label: 'En maintenance', class: 'info', icon: '🔵' },
}

const criticalityLabels = {
  low: { label: 'Basse', class: 'low', icon: '⬇️' },
  medium: { label: 'Moyenne', class: 'medium', icon: '➡️' },
  high: { label: 'Haute', class: 'high', icon: '⬆️' },
  critical: { label: 'Critique', class: 'critical', icon: '⚠️' },
}

const equipmentTypes = [
  'Machine',
  'Moteur',
  'Pompe',
  'Compresseur',
  'Convoyeur',
  'Robot',
  'Automate',
  'Vérin',
  'Ventilateur',
  'Transformateur',
  'Groupe électrogène',
  'Chariot élévateur',
  'Pont roulant',
  'Autre',
]

// Stats calculées
const stats = computed(() => {
  const all = equipments.value
  return {
    total: pagination.value.total || all.length,
    operational: all.filter(e => e.status === 'operational').length,
    degraded: all.filter(e => e.status === 'degraded').length,
    stopped: all.filter(e => e.status === 'stopped').length,
    maintenance: all.filter(e => e.status === 'maintenance').length,
    critical: all.filter(e => e.criticality === 'critical' || e.criticality === 'high').length,
  }
})

async function fetchEquipments(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const params = { page, per_page: 20 }
    if (search.value) params.search = search.value
    if (typeFilter.value) params.type = typeFilter.value
    if (statusFilter.value) params.status = statusFilter.value
    if (criticalityFilter.value) params.criticality = criticalityFilter.value
    if (locationFilter.value) params.location_id = locationFilter.value

    const response = await api.get('/equipments', { params })
    equipments.value = response.data.data
    pagination.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      total: response.data.total,
    }
    extractFilters()
  } catch (err) {
    error.value = 'Erreur lors du chargement des équipements'
    console.error(err)
  } finally {
    loading.value = false
  }
}

async function fetchLocations() {
  try {
    const response = await api.get('/locations-list')
    locationsOptions.value = response.data
  } catch (err) {
    console.error('Erreur chargement emplacements:', err)
  }
}

function extractFilters() {
  types.value = [...new Set(equipments.value.map(e => e.type).filter(Boolean))]
}

function getLocationPath(locationId) {
  const location = locationsOptions.value.find(l => l.id === locationId)
  if (!location) return '-'
  if (location.parent) {
    return `${location.parent.name} > ${location.name}`
  }
  return location.name
}

function getLocationName(equipment) {
  if (equipment.location) {
    return equipment.location.name
  }
  return getLocationPath(equipment.location_id)
}

function openCreateModal() {
  editingEquipment.value = null
  form.value = {
    code: '',
    name: '',
    type: '',
    brand: '',
    model: '',
    serial_number: '',
    location_id: '',
    department: '',
    criticality: 'medium',
    status: 'operational',
    installation_date: '',
    warranty_expiry_date: '',
    description: '',
    specifications: '',
    notes: '',
  }
  showModal.value = true
}

function openEditModal(equipment) {
  editingEquipment.value = equipment
  form.value = {
    code: equipment.code,
    name: equipment.name,
    type: equipment.type || '',
    brand: equipment.brand || '',
    model: equipment.model || '',
    serial_number: equipment.serial_number || '',
    location_id: equipment.location_id || '',
    department: equipment.department || '',
    criticality: equipment.criticality || 'medium',
    status: equipment.status || 'operational',
    installation_date: formatDateForInput(equipment.installation_date) || '',
    warranty_expiry_date: formatDateForInput(equipment.warranty_expiry_date) || '',
    description: equipment.description || '',
    specifications: equipment.specifications || '',
    notes: equipment.notes || '',
  }

  showModal.value = true
}

function openDetailModal(equipment) {
  selectedEquipment.value = equipment
  showDetailModal.value = true
}

async function saveEquipment() {
  saving.value = true
  error.value = ''
  try {
    const data = { ...form.value }
    // Convertir location_id vide en null
    if (!data.location_id) data.location_id = null

    if (editingEquipment.value) {
      await api.put(`/equipments/${editingEquipment.value.id}`, data)
    } else {
      await api.post('/equipments', data)
    }
    showModal.value = false
    fetchEquipments()
  } catch (err) {
    error.value = err.response?.data?.message || 'Erreur lors de la sauvegarde'
  } finally {
    saving.value = false
  }
}

async function deleteEquipment(equipment) {
  if (!confirm(`Supprimer l'équipement "${equipment.name}" ?`)) return

  try {
    await api.delete(`/equipments/${equipment.id}`)
    fetchEquipments()
  } catch (err) {
    error.value = 'Erreur lors de la suppression'
  }
}

async function updateStatus(equipment, newStatus) {
  try {
    await api.put(`/equipments/${equipment.id}`, {
      ...equipment,
      status: newStatus,
    })
    fetchEquipments()
  } catch (err) {
    error.value = 'Erreur lors de la mise à jour du statut'
  }
}

function createWorkOrder(equipment) {
  router.push({
    name: 'work-orders',
    query: { equipment_id: equipment.id, action: 'create' },
  })
}

function viewWorkOrders(equipment) {
  router.push({
    name: 'work-orders',
    query: { equipment_id: equipment.id },
  })
}

function applyFilters() {
  fetchEquipments()
}

function resetFilters() {
  search.value = ''
  typeFilter.value = ''
  statusFilter.value = ''
  criticalityFilter.value = ''
  locationFilter.value = ''
  fetchEquipments()
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('fr-FR')
}

function isWarrantyExpired(date) {
  if (!date) return false
  return new Date(date) < new Date()
}

function isWarrantyExpiringSoon(date) {
  if (!date) return false
  const expiry = new Date(date)
  const today = new Date()
  const diffDays = Math.ceil((expiry - today) / (1000 * 60 * 60 * 24))
  return diffDays > 0 && diffDays <= 30
}

onMounted(() => {
  fetchEquipments()
  fetchLocations()
})
</script>

<template>
  <div class="equipments-page">
    <header class="page-header">
      <div>
        <h1>⚙️ Équipements</h1>
        <p class="subtitle">Gestion du parc équipements</p>
      </div>
      <button class="btn btn-primary" @click="openCreateModal" v-if="authStore.hasPermission('equipment:create')">
        + Nouvel équipement
      </button>
    </header>

    <!-- Stats Cards -->
    <div class="stats-cards">
      <div class="stat-card" @click="statusFilter = ''; applyFilters()">
        <div class="stat-icon blue">⚙️</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.total }}</div>
          <div class="stat-label">Total</div>
        </div>
      </div>
      <div class="stat-card success" @click="statusFilter = 'operational'; applyFilters()">
        <div class="stat-icon green">🟢</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.operational }}</div>
          <div class="stat-label">Opérationnels</div>
        </div>
      </div>
      <div class="stat-card warning" @click="statusFilter = 'degraded'; applyFilters()">
        <div class="stat-icon orange">🟠</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.degraded }}</div>
          <div class="stat-label">Dégradés</div>
        </div>
      </div>
      <div class="stat-card danger" @click="statusFilter = 'stopped'; applyFilters()">
        <div class="stat-icon red">🔴</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.stopped }}</div>
          <div class="stat-label">Arrêtés</div>
        </div>
      </div>
      <div class="stat-card info" @click="statusFilter = 'maintenance'; applyFilters()">
        <div class="stat-icon purple">🔧</div>
        <div class="stat-content">
          <div class="stat-value">{{ stats.maintenance }}</div>
          <div class="stat-label">Maintenance</div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar">
      <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" v-model="search" placeholder="Rechercher par code, nom, marque..." @input="applyFilters" />
      </div>
      <select v-model="typeFilter" @change="applyFilters">
        <option value="">Tous les types</option>
        <option v-for="type in equipmentTypes" :key="type" :value="type">{{ type }}</option>
      </select>
      <select v-model="statusFilter" @change="applyFilters">
        <option value="">Tous les statuts</option>
        <option value="operational">Opérationnel</option>
        <option value="degraded">Dégradé</option>
        <option value="stopped">Arrêté</option>
        <option value="maintenance">Maintenance</option>
      </select>
      <select v-model="criticalityFilter" @change="applyFilters">
        <option value="">Toutes criticités</option>
        <option value="low">Basse</option>
        <option value="medium">Moyenne</option>
        <option value="high">Haute</option>
        <option value="critical">Critique</option>
      </select>
      <select v-model="locationFilter" @change="applyFilters" v-if="locationsOptions.length">
        <option value="">Tous emplacements</option>
        <option v-for="loc in locationsOptions" :key="loc.id" :value="loc.id">
          {{ loc.parent ? loc.parent.name + ' > ' : '' }}{{ loc.name }}
        </option>
      </select>
      <button class="btn btn-secondary btn-sm" @click="resetFilters"
        v-if="search || typeFilter || statusFilter || criticalityFilter || locationFilter">
        ✕ Reset
      </button>
      <div class="view-toggle">
        <button :class="{ active: activeView === 'grid' }" @click="activeView = 'grid'" title="Vue grille">▦</button>
        <button :class="{ active: activeView === 'table' }" @click="activeView = 'table'" title="Vue tableau">☰</button>
      </div>
    </div>

    <div class="alert alert-error" v-if="error">{{ error }}</div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Chargement...</p>
    </div>

    <!-- Grid View -->
    <div v-else-if="activeView === 'grid'" class="equipments-grid">
      <div v-for="equipment in equipments" :key="equipment.id" class="equipment-card" :class="equipment.status"
        @click="openDetailModal(equipment)">
        <div class="card-header">
          <div class="equipment-icon">⚙️</div>
          <div class="header-badges">
            <span class="criticality-badge" :class="criticalityLabels[equipment.criticality]?.class">
              {{ criticalityLabels[equipment.criticality]?.icon }}
            </span>
            <span class="status-indicator" :class="statusLabels[equipment.status]?.class"></span>
          </div>
        </div>

        <div class="card-body">
          <div class="equipment-code">{{ equipment.code }}</div>
          <h3 class="equipment-name">{{ equipment.name }}</h3>

          <div class="equipment-meta">
            <span v-if="equipment.type" class="meta-item">
              <span class="meta-icon">📂</span> {{ equipment.type }}
            </span>
            <span v-if="equipment.location || equipment.location_id" class="meta-item">
              <span class="meta-icon">📍</span> {{ getLocationName(equipment) }}
            </span>
            <span v-if="equipment.brand" class="meta-item">
              <span class="meta-icon">🏭</span> {{ equipment.brand }}
            </span>
          </div>

          <div class="status-row">
            <span class="status-badge" :class="statusLabels[equipment.status]?.class">
              {{ statusLabels[equipment.status]?.icon }} {{ statusLabels[equipment.status]?.label }}
            </span>
            <span class="criticality-text" :class="criticalityLabels[equipment.criticality]?.class">
              {{ criticalityLabels[equipment.criticality]?.label }}
            </span>
          </div>

          <!-- Warranty Alert -->
          <div class="warranty-alert" v-if="isWarrantyExpired(equipment.warranty_expiry_date)">
            ⚠️ Garantie expirée
          </div>
          <div class="warranty-warning" v-else-if="isWarrantyExpiringSoon(equipment.warranty_expiry_date)">
            ⏰ Garantie expire bientôt
          </div>
        </div>

        <div class="card-actions" @click.stop>
          <button class="btn-action" @click="createWorkOrder(equipment)" title="Créer OT">
            📝
          </button>
          <button class="btn-action" @click="viewWorkOrders(equipment)" title="Voir OTs">
            📋
          </button>
          <button class="btn-action" @click="openEditModal(equipment)" title="Modifier"
            v-if="authStore.hasPermission('equipment:update')">
            ✏️
          </button>
          <div class="status-dropdown">
            <button class="btn-action" title="Changer statut">⚡</button>
            <div class="dropdown-menu">
              <button @click="updateStatus(equipment, 'operational')">🟢 Opérationnel</button>
              <button @click="updateStatus(equipment, 'degraded')">🟠 Dégradé</button>
              <button @click="updateStatus(equipment, 'stopped')">🔴 Arrêté</button>
              <button @click="updateStatus(equipment, 'maintenance')">🔵 Maintenance</button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="equipments.length === 0" class="empty-state full-width">
        <span class="empty-icon">⚙️</span>
        <h3>Aucun équipement trouvé</h3>
        <p>Ajoutez des équipements ou modifiez vos filtres</p>
      </div>
    </div>

    <!-- Table View -->
    <div v-else class="table-container">
      <table class="equipments-table" v-if="equipments.length">
        <thead>
          <tr>
            <th>Code</th>
            <th>Équipement</th>
            <th>Type</th>
            <th>Emplacement</th>
            <th>Criticité</th>
            <th>Statut</th>
            <th>Garantie</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="equipment in equipments" :key="equipment.id" :class="equipment.status">
            <td class="code-cell">
              <strong>{{ equipment.code }}</strong>
            </td>
            <td class="name-cell">
              <div class="equipment-info">
                <div class="eq-name">{{ equipment.name }}</div>
                <div class="eq-brand" v-if="equipment.brand || equipment.model">
                  {{ equipment.brand }} {{ equipment.model }}
                </div>
              </div>
            </td>
            <td>
              <span class="type-badge" v-if="equipment.type">{{ equipment.type }}</span>
              <span v-else class="text-muted">-</span>
            </td>
            <td>
              <span v-if="equipment.location || equipment.location_id">
                📍 {{ getLocationName(equipment) }}
              </span>
              <span v-else class="text-muted">-</span>
            </td>
            <td>
              <span class="criticality-badge full" :class="criticalityLabels[equipment.criticality]?.class">
                {{ criticalityLabels[equipment.criticality]?.icon }}
                {{ criticalityLabels[equipment.criticality]?.label }}
              </span>
            </td>
            <td>
              <span class="status-badge" :class="statusLabels[equipment.status]?.class">
                {{ statusLabels[equipment.status]?.icon }}
                {{ statusLabels[equipment.status]?.label }}
              </span>
            </td>
            <td>
              <span v-if="equipment.warranty_expiry_date" :class="{
                'warranty-expired': isWarrantyExpired(equipment.warranty_expiry_date),
                'warranty-soon': isWarrantyExpiringSoon(equipment.warranty_expiry_date)
              }">
                {{ formatDate(equipment.warranty_expiry_date) }}
              </span>
              <span v-else class="text-muted">-</span>
            </td>
            <td class="actions-cell">
              <div class="action-buttons">
                <button class="btn-icon primary" @click="openDetailModal(equipment)" title="Détails">
                  👁️
                </button>
                <button class="btn-icon success" @click="createWorkOrder(equipment)" title="Créer OT">
                  📝
                </button>
                <button class="btn-icon warning" @click="openEditModal(equipment)" title="Modifier"
                  v-if="authStore.hasPermission('equipment:update')">
                  ✏️
                </button>
                <button class="btn-icon danger" @click="deleteEquipment(equipment)" title="Supprimer"
                  v-if="authStore.hasPermission('equipment:delete')">
                  🗑️
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-else class="empty-state">
        <span class="empty-icon">⚙️</span>
        <h3>Aucun équipement trouvé</h3>
        <p>Ajoutez des équipements ou modifiez vos filtres</p>
      </div>
    </div>

    <!-- Pagination -->
    <div class="pagination" v-if="pagination.last_page > 1">
      <button v-for="page in pagination.last_page" :key="page" :class="{ active: page === pagination.current_page }"
        @click="fetchEquipments(page)">
        {{ page }}
      </button>
    </div>

    <!-- Modal Création/Édition -->
    <div class="modal-overlay" v-if="showModal" @click.self="showModal = false">
      <div class="modal modal-lg">
        <div class="modal-header">
          <h2>{{ editingEquipment ? '✏️ Modifier l\'équipement' : '➕ Nouvel équipement' }}</h2>
          <button class="close-btn" @click="showModal = false">&times;</button>
        </div>
        <form @submit.prevent="saveEquipment" class="modal-body">
          <div class="form-section">
            <h3>Identification</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Code *</label>
                <input type="text" v-model="form.code" required :disabled="!!editingEquipment" placeholder="EQ-001" />
              </div>
              <div class="form-group">
                <label>Nom *</label>
                <input type="text" v-model="form.name" required placeholder="Nom de l'équipement" />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Type</label>
                <select v-model="form.type">
                  <option value="">Sélectionner</option>
                  <option v-for="type in equipmentTypes" :key="type" :value="type">{{ type }}</option>
                </select>
              </div>
              <div class="form-group">
                <label>N° Série</label>
                <input type="text" v-model="form.serial_number" placeholder="Numéro de série" />
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Marque</label>
                <input type="text" v-model="form.brand" placeholder="Ex: Siemens" />
              </div>
              <div class="form-group">
                <label>Modèle</label>
                <input type="text" v-model="form.model" placeholder="Ex: S7-1500" />
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>Localisation</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Emplacement</label>
                <select v-model="form.location_id">
                  <option value="">Sélectionner un emplacement</option>
                  <option v-for="loc in locationsOptions" :key="loc.id" :value="loc.id">
                    {{ loc.parent ? loc.parent.name + ' > ' : '' }}{{ loc.name }}
                  </option>
                </select>
              </div>
              <div class="form-group">
                <label>Département</label>
                <input type="text" v-model="form.department" placeholder="Ex: Production" />
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>Classification</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Criticité</label>
                <div class="radio-group">
                  <label class="radio-item low" :class="{ selected: form.criticality === 'low' }">
                    <input type="radio" v-model="form.criticality" value="low" />
                    <span>⬇️ Basse</span>
                  </label>
                  <label class="radio-item medium" :class="{ selected: form.criticality === 'medium' }">
                    <input type="radio" v-model="form.criticality" value="medium" />
                    <span>➡️ Moyenne</span>
                  </label>
                  <label class="radio-item high" :class="{ selected: form.criticality === 'high' }">
                    <input type="radio" v-model="form.criticality" value="high" />
                    <span>⬆️ Haute</span>
                  </label>
                  <label class="radio-item critical" :class="{ selected: form.criticality === 'critical' }">
                    <input type="radio" v-model="form.criticality" value="critical" />
                    <span>⚠️ Critique</span>
                  </label>
                </div>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Statut</label>
                <div class="radio-group">
                  <label class="radio-item success" :class="{ selected: form.status === 'operational' }">
                    <input type="radio" v-model="form.status" value="operational" />
                    <span>🟢 Opérationnel</span>
                  </label>
                  <label class="radio-item warning" :class="{ selected: form.status === 'degraded' }">
                    <input type="radio" v-model="form.status" value="degraded" />
                    <span>🟠 Dégradé</span>
                  </label>
                  <label class="radio-item danger" :class="{ selected: form.status === 'stopped' }">
                    <input type="radio" v-model="form.status" value="stopped" />
                    <span>🔴 Arrêté</span>
                  </label>
                  <label class="radio-item info" :class="{ selected: form.status === 'maintenance' }">
                    <input type="radio" v-model="form.status" value="maintenance" />
                    <span>🔵 Maintenance</span>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>Dates importantes</h3>
            <div class="form-row">
              <div class="form-group">
                <label>Date d'installation</label>
                <input type="date" v-model="form.installation_date" />
              </div>
              <div class="form-group">
                <label>Fin de garantie</label>
                <input type="date" v-model="form.warranty_expiry_date" />
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3>Informations complémentaires</h3>
            <div class="form-group">
              <label>Description</label>
              <textarea v-model="form.description" rows="2" placeholder="Description de l'équipement..."></textarea>
            </div>
            <div class="form-group">
              <label>Spécifications techniques</label>
              <textarea v-model="form.specifications" rows="2" placeholder="Puissance, dimensions, etc."></textarea>
            </div>
            <div class="form-group">
              <label>Notes</label>
              <textarea v-model="form.notes" rows="2" placeholder="Remarques..."></textarea>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showModal = false">Annuler</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              {{ saving ? 'Enregistrement...' : (editingEquipment ? 'Enregistrer' : 'Créer') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Détails -->
    <div class="modal-overlay" v-if="showDetailModal" @click.self="showDetailModal = false">
      <div class="modal modal-lg">
        <div class="modal-header">
          <h2>📋 Détails de l'équipement</h2>
          <button class="close-btn" @click="showDetailModal = false">&times;</button>
        </div>
        <div class="modal-body" v-if="selectedEquipment">
          <div class="detail-header">
            <div class="detail-icon">⚙️</div>
            <div class="detail-title">
              <div class="detail-code">{{ selectedEquipment.code }}</div>
              <h2>{{ selectedEquipment.name }}</h2>
              <div class="detail-badges">
                <span class="status-badge" :class="statusLabels[selectedEquipment.status]?.class">
                  {{ statusLabels[selectedEquipment.status]?.icon }}
                  {{ statusLabels[selectedEquipment.status]?.label }}
                </span>
                <span class="criticality-badge full" :class="criticalityLabels[selectedEquipment.criticality]?.class">
                  {{ criticalityLabels[selectedEquipment.criticality]?.icon }}
                  {{ criticalityLabels[selectedEquipment.criticality]?.label }}
                </span>
              </div>
            </div>
          </div>

          <div class="detail-grid">
            <div class="detail-section">
              <h4>Identification</h4>
              <div class="detail-row">
                <span class="detail-label">Type</span>
                <span class="detail-value">{{ selectedEquipment.type || '-' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Marque</span>
                <span class="detail-value">{{ selectedEquipment.brand || '-' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Modèle</span>
                <span class="detail-value">{{ selectedEquipment.model || '-' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">N° Série</span>
                <span class="detail-value">{{ selectedEquipment.serial_number || '-' }}</span>
              </div>
            </div>

            <div class="detail-section">
              <h4>Localisation</h4>
              <div class="detail-row">
                <span class="detail-label">Emplacement</span>
                <span class="detail-value">
                  {{ selectedEquipment.location?.name || getLocationPath(selectedEquipment.location_id) }}
                </span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Département</span>
                <span class="detail-value">{{ selectedEquipment.department || '-' }}</span>
              </div>
            </div>

            <div class="detail-section">
              <h4>Dates</h4>
              <div class="detail-row">
                <span class="detail-label">Installation</span>
                <span class="detail-value">{{ formatDate(selectedEquipment.installation_date) }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Fin garantie</span>
                <span class="detail-value" :class="{
                  'warranty-expired': isWarrantyExpired(selectedEquipment.warranty_expiry_date),
                  'warranty-soon': isWarrantyExpiringSoon(selectedEquipment.warranty_expiry_date)
                }">
                  {{ formatDate(selectedEquipment.warranty_expiry_date) }}
                  <span v-if="isWarrantyExpired(selectedEquipment.warranty_expiry_date)"> ⚠️ Expirée</span>
                  <span v-else-if="isWarrantyExpiringSoon(selectedEquipment.warranty_expiry_date)"> ⏰ Bientôt</span>
                </span>
              </div>
            </div>

            <div class="detail-section full-width" v-if="selectedEquipment.description">
              <h4>Description</h4>
              <p class="detail-text">{{ selectedEquipment.description }}</p>
            </div>

            <div class="detail-section full-width" v-if="selectedEquipment.specifications">
              <h4>Spécifications techniques</h4>
              <p class="detail-text">{{ selectedEquipment.specifications }}</p>
            </div>

            <div class="detail-section full-width" v-if="selectedEquipment.notes">
              <h4>Notes</h4>
              <p class="detail-text">{{ selectedEquipment.notes }}</p>
            </div>
          </div>

          <div class="detail-actions">
            <button class="btn btn-primary" @click="createWorkOrder(selectedEquipment)">
              📝 Créer un OT
            </button>
            <button class="btn btn-secondary" @click="viewWorkOrders(selectedEquipment)">
              📋 Voir les OTs
            </button>
            <button class="btn btn-warning" @click="openEditModal(selectedEquipment); showDetailModal = false"
              v-if="authStore.hasPermission('equipment:update')">
              ✏️ Modifier
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ... Garde tous les styles existants du fichier original ... */
.equipments-page {
  padding: 30px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 25px;
}

.page-header h1 {
  font-size: 28px;
  color: #2c3e50;
  margin: 0;
}

.subtitle {
  color: #7f8c8d;
  font-size: 14px;
  margin: 5px 0 0;
}

.btn {
  padding: 10px 20px;
  border: none;
  border-radius: 8px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-primary {
  background: linear-gradient(135deg, #3498db, #2980b9);
  color: white;
}

.btn-success {
  background: linear-gradient(135deg, #27ae60, #1e8449);
  color: white;
}

.btn-warning {
  background: linear-gradient(135deg, #f39c12, #d68910);
  color: white;
}

.btn-secondary {
  background: #ecf0f1;
  color: #2c3e50;
}

.btn-danger {
  background: linear-gradient(135deg, #e74c3c, #c0392b);
  color: white;
}

.btn-sm {
  padding: 6px 12px;
  font-size: 12px;
}

/* Stats Cards */
.stats-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 15px;
  margin-bottom: 25px;
}

.stat-card {
  background: white;
  border-radius: 12px;
  padding: 18px;
  display: flex;
  align-items: center;
  gap: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  cursor: pointer;
  transition: all 0.2s;
  border-left: 4px solid transparent;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.stat-card.success {
  border-left-color: #27ae60;
}

.stat-card.warning {
  border-left-color: #f39c12;
}

.stat-card.danger {
  border-left-color: #e74c3c;
}

.stat-card.info {
  border-left-color: #3498db;
}

.stat-icon {
  width: 45px;
  height: 45px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
}

.stat-icon.blue {
  background: #e8f4fd;
}

.stat-icon.green {
  background: #d4edda;
}

.stat-icon.orange {
  background: #fff3cd;
}

.stat-icon.red {
  background: #f8d7da;
}

.stat-icon.purple {
  background: #e8daef;
}

.stat-value {
  font-size: 26px;
  font-weight: 700;
  color: #2c3e50;
}

.stat-label {
  font-size: 12px;
  color: #7f8c8d;
}

/* Filters */
.filters-bar {
  display: flex;
  gap: 12px;
  margin-bottom: 25px;
  flex-wrap: wrap;
  align-items: center;
}

.search-box {
  flex: 1;
  min-width: 250px;
  max-width: 350px;
  position: relative;
}

.search-box input {
  width: 100%;
  padding: 10px 15px 10px 40px;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 14px;
}

.search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
}

.filters-bar select {
  padding: 10px 15px;
  border: 1px solid #ddd;
  border-radius: 8px;
  font-size: 14px;
  min-width: 140px;
}

.view-toggle {
  display: flex;
  background: white;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #ddd;
  margin-left: auto;
}

.view-toggle button {
  padding: 8px 12px;
  border: none;
  background: transparent;
  cursor: pointer;
  font-size: 16px;
}

.view-toggle button.active {
  background: #3498db;
  color: white;
}

/* Grid View */
.equipments-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 20px;
}

.equipment-card {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  cursor: pointer;
  transition: all 0.2s;
  border-top: 4px solid #27ae60;
}

.equipment-card:hover {
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
  transform: translateY(-3px);
}

.equipment-card.degraded {
  border-top-color: #f39c12;
}

.equipment-card.stopped {
  border-top-color: #e74c3c;
}

.equipment-card.maintenance {
  border-top-color: #3498db;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 20px;
  background: #f8f9fa;
}

.equipment-icon {
  font-size: 32px;
}

.header-badges {
  display: flex;
  align-items: center;
  gap: 8px;
}

.status-indicator {
  width: 14px;
  height: 14px;
  border-radius: 50%;
}

.status-indicator.success {
  background: #27ae60;
}

.status-indicator.warning {
  background: #f39c12;
}

.status-indicator.danger {
  background: #e74c3c;
}

.status-indicator.info {
  background: #3498db;
}

.criticality-badge {
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 12px;
}

.criticality-badge.low {
  background: #d4edda;
}

.criticality-badge.medium {
  background: #fff3cd;
}

.criticality-badge.high {
  background: #f8d7da;
}

.criticality-badge.critical {
  background: #f5b7b1;
}

.criticality-badge.full {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 5px 10px;
}

.criticality-badge.full.low {
  color: #155724;
}

.criticality-badge.full.medium {
  color: #856404;
}

.criticality-badge.full.high {
  color: #721c24;
}

.criticality-badge.full.critical {
  color: #922b21;
}

.card-body {
  padding: 20px;
}

.equipment-code {
  font-family: monospace;
  font-size: 12px;
  color: #7f8c8d;
  background: #f0f0f0;
  padding: 3px 8px;
  border-radius: 4px;
  display: inline-block;
  margin-bottom: 8px;
}

.equipment-name {
  font-size: 18px;
  color: #2c3e50;
  margin: 0 0 12px;
}

.equipment-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 15px;
}

.meta-item {
  font-size: 12px;
  color: #7f8c8d;
  display: flex;
  align-items: center;
  gap: 4px;
}

.status-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
}

.status-badge.success {
  background: #d4edda;
  color: #155724;
}

.status-badge.warning {
  background: #fff3cd;
  color: #856404;
}

.status-badge.danger {
  background: #f8d7da;
  color: #721c24;
}

.status-badge.info {
  background: #d1ecf1;
  color: #0c5460;
}

.criticality-text {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
}

.criticality-text.low {
  color: #27ae60;
}

.criticality-text.medium {
  color: #f39c12;
}

.criticality-text.high {
  color: #e74c3c;
}

.criticality-text.critical {
  color: #c0392b;
}

.warranty-alert {
  margin-top: 12px;
  padding: 8px 12px;
  background: #f8d7da;
  color: #721c24;
  border-radius: 6px;
  font-size: 12px;
}

.warranty-warning {
  margin-top: 12px;
  padding: 8px 12px;
  background: #fff3cd;
  color: #856404;
  border-radius: 6px;
  font-size: 12px;
}

.card-actions {
  display: flex;
  gap: 5px;
  padding: 15px 20px;
  background: #f8f9fa;
  border-top: 1px solid #eee;
}

.btn-action {
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 8px;
  background: white;
  cursor: pointer;
  font-size: 16px;
  transition: all 0.2s;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.btn-action:hover {
  background: #e9ecef;
  transform: scale(1.05);
}

.status-dropdown {
  position: relative;
  margin-left: auto;
}

.status-dropdown .dropdown-menu {
  display: none;
  position: absolute;
  bottom: 100%;
  right: 0;
  background: white;
  border-radius: 8px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
  padding: 5px;
  margin-bottom: 5px;
  z-index: 100;
}

.status-dropdown:hover .dropdown-menu {
  display: block;
}

.dropdown-menu button {
  display: block;
  width: 100%;
  padding: 8px 15px;
  border: none;
  background: none;
  text-align: left;
  cursor: pointer;
  white-space: nowrap;
  border-radius: 4px;
  font-size: 13px;
}

.dropdown-menu button:hover {
  background: #f0f0f0;
}

/* Table View */
.table-container {
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.equipments-table {
  width: 100%;
  border-collapse: collapse;
}

.equipments-table th {
  text-align: left;
  padding: 15px;
  background: #f8f9fa;
  font-size: 12px;
  text-transform: uppercase;
  color: #7f8c8d;
  font-weight: 600;
}

.equipments-table td {
  padding: 15px;
  border-top: 1px solid #eee;
  vertical-align: middle;
}

.equipments-table tr:hover {
  background: #f8f9fa;
}

.equipments-table tr.stopped {
  background: #fff5f5;
}

.equipments-table tr.degraded {
  background: #fffbf0;
}

.code-cell strong {
  font-family: monospace;
  color: #3498db;
}

.equipment-info .eq-name {
  font-weight: 500;
  color: #2c3e50;
}

.equipment-info .eq-brand {
  font-size: 12px;
  color: #7f8c8d;
}

.type-badge {
  padding: 4px 10px;
  background: #e8f4fd;
  color: #3498db;
  border-radius: 15px;
  font-size: 12px;
}

.warranty-expired {
  color: #e74c3c;
  font-weight: 500;
}

.warranty-soon {
  color: #f39c12;
  font-weight: 500;
}

.actions-cell {
  white-space: nowrap;
}

.action-buttons {
  display: flex;
  gap: 5px;
}

.btn-icon {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s;
}

.btn-icon.primary {
  background: #e8f4fd;
}

.btn-icon.primary:hover {
  background: #cce5ff;
}

.btn-icon.success {
  background: #d4edda;
}

.btn-icon.success:hover {
  background: #c3e6cb;
}

.btn-icon.warning {
  background: #fff3cd;
}

.btn-icon.warning:hover {
  background: #ffeeba;
}

.btn-icon.danger {
  background: #f8d7da;
}

.btn-icon.danger:hover {
  background: #f5c6cb;
}

/* Empty & Loading States */
.empty-state {
  text-align: center;
  padding: 60px;
  color: #7f8c8d;
}

.empty-state.full-width {
  grid-column: 1 / -1;
  background: white;
  border-radius: 12px;
}

.empty-icon {
  font-size: 60px;
  opacity: 0.5;
}

.empty-state h3 {
  margin: 15px 0 5px;
  color: #2c3e50;
}

.loading-state {
  text-align: center;
  padding: 60px;
  color: #7f8c8d;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid #eee;
  border-top-color: #3498db;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 15px;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Pagination */
.pagination {
  display: flex;
  justify-content: center;
  gap: 5px;
  margin-top: 25px;
}

.pagination button {
  padding: 8px 14px;
  border: 1px solid #ddd;
  background: white;
  border-radius: 6px;
  cursor: pointer;
}

.pagination button.active {
  background: #3498db;
  color: white;
  border-color: #3498db;
}

/* Alert */
.alert {
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 20px;
}

.alert-error {
  background: #f8d7da;
  color: #721c24;
}

/* Modal */
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
  z-index: 2000;
}

.modal {
  background: white;
  border-radius: 12px;
  width: 100%;
  max-width: 600px;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.modal-lg {
  max-width: 750px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid #eee;
}

.modal-header h2 {
  margin: 0;
  font-size: 18px;
  color: #2c3e50;
}

.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #7f8c8d;
}

.modal-body {
  padding: 20px;
  overflow-y: auto;
}

.form-section {
  margin-bottom: 25px;
}

.form-section h3 {
  font-size: 14px;
  color: #7f8c8d;
  text-transform: uppercase;
  margin: 0 0 15px;
  padding-bottom: 8px;
  border-bottom: 1px solid #eee;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
}

.form-group {
  margin-bottom: 15px;
}

.form-group label {
  display: block;
  margin-bottom: 5px;
  font-size: 13px;
  font-weight: 500;
  color: #555;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #3498db;
}

.form-group input:disabled {
  background: #f8f9fa;
  cursor: not-allowed;
}

.radio-group {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.radio-item {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  border: 2px solid #ddd;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
  font-size: 13px;
}

.radio-item input {
  display: none;
}

.radio-item.selected.low {
  border-color: #27ae60;
  background: #d4edda;
}

.radio-item.selected.medium {
  border-color: #f39c12;
  background: #fff3cd;
}

.radio-item.selected.high {
  border-color: #e74c3c;
  background: #f8d7da;
}

.radio-item.selected.critical {
  border-color: #c0392b;
  background: #f5b7b1;
}

.radio-item.selected.success {
  border-color: #27ae60;
  background: #d4edda;
}

.radio-item.selected.warning {
  border-color: #f39c12;
  background: #fff3cd;
}

.radio-item.selected.danger {
  border-color: #e74c3c;
  background: #f8d7da;
}

.radio-item.selected.info {
  border-color: #3498db;
  background: #d1ecf1;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding-top: 15px;
  border-top: 1px solid #eee;
  margin-top: 10px;
}

/* Detail Modal */
.detail-header {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 20px;
  background: #f8f9fa;
  border-radius: 12px;
  margin-bottom: 25px;
}

.detail-icon {
  font-size: 48px;
}

.detail-code {
  font-family: monospace;
  font-size: 12px;
  color: #7f8c8d;
  background: #e0e0e0;
  padding: 3px 8px;
  border-radius: 4px;
  display: inline-block;
  margin-bottom: 5px;
}

.detail-title h2 {
  margin: 0 0 10px;
  font-size: 22px;
  color: #2c3e50;
}

.detail-badges {
  display: flex;
  gap: 10px;
}

.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}

.detail-section {
  background: #f8f9fa;
  padding: 15px;
  border-radius: 8px;
}

.detail-section.full-width {
  grid-column: 1 / -1;
}

.detail-section h4 {
  margin: 0 0 12px;
  font-size: 12px;
  text-transform: uppercase;
  color: #7f8c8d;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  border-bottom: 1px solid #e9ecef;
  font-size: 13px;
}

.detail-row:last-child {
  border-bottom: none;
}

.detail-label {
  color: #7f8c8d;
}

.detail-value {
  color: #2c3e50;
  font-weight: 500;
}

.detail-text {
  margin: 0;
  font-size: 14px;
  color: #555;
  line-height: 1.5;
}

.detail-actions {
  display: flex;
  gap: 10px;
  margin-top: 25px;
  padding-top: 20px;
  border-top: 1px solid #eee;
}

.text-muted {
  color: #95a5a6;
}

@media (max-width: 768px) {

  .form-row,
  .detail-grid {
    grid-template-columns: 1fr;
  }

  .filters-bar {
    flex-direction: column;
    align-items: stretch;
  }

  .search-box {
    max-width: 100%;
  }

  .view-toggle {
    margin-left: 0;
  }

  .radio-group {
    flex-direction: column;
  }
}
</style>
