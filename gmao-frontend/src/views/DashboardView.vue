<script setup>
import { ref, onMounted, onUnmounted, computed, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { Chart, registerables } from 'chart.js'
import ChartDataLabels from 'chartjs-plugin-datalabels'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

Chart.register(...registerables, ChartDataLabels)

const router = useRouter()
const authStore = useAuthStore()

const loading = ref(true)
const chartsReady = ref(false)
const dashboard = ref(null)
const currentTime = ref(new Date())
const refreshInterval = ref(null)
const timeInterval = ref(null)

let trendChart = null
let statusChart = null
let equipmentChart = null

const greeting = computed(() => {
  const hour = currentTime.value.getHours()
  if (hour < 12) return 'Bonjour'
  if (hour < 18) return 'Bon après-midi'
  return 'Bonsoir'
})

const formattedTime = computed(() => {
  return currentTime.value.toLocaleTimeString('fr-FR', {
    hour: '2-digit',
    minute: '2-digit'
  })
})

const formattedDate = computed(() => {
  return currentTime.value.toLocaleDateString('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })
})

async function fetchDashboard() {
  try {
    const response = await api.get('/dashboard')
    dashboard.value = response.data
    loading.value = false

    // Attendre le prochain tick pour que le DOM soit prêt
    await nextTick()

    // Petit délai pour s'assurer que les canvas sont rendus
    requestAnimationFrame(() => {
      renderCharts()
      chartsReady.value = true
    })
  } catch (err) {
    console.error('Erreur dashboard:', err)
    loading.value = false
  }
}

function renderCharts() {
  renderTrendChart()
  renderStatusChart()
  renderEquipmentChart()
}

function renderTrendChart() {
  const ctx = document.getElementById('trendChart')
  if (!ctx || !dashboard.value?.monthly_trend) return

  if (trendChart) trendChart.destroy()

  trendChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: dashboard.value.monthly_trend.map(d => d.month),
      datasets: [
        {
          label: 'Créés',
          data: dashboard.value.monthly_trend.map(d => d.created),
          borderColor: '#3498db',
          backgroundColor: 'rgba(52, 152, 219, 0.1)',
          fill: true,
          tension: 0.4,
          pointBackgroundColor: '#3498db',
          pointRadius: 4,
          pointHoverRadius: 6,
        },
        {
          label: 'Terminés',
          data: dashboard.value.monthly_trend.map(d => d.completed),
          borderColor: '#27ae60',
          backgroundColor: 'rgba(39, 174, 96, 0.1)',
          fill: true,
          tension: 0.4,
          pointBackgroundColor: '#27ae60',
          pointRadius: 4,
          pointHoverRadius: 6,
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 500 },
      plugins: {
        legend: { position: 'bottom' },
        datalabels: { display: false }
      },
      scales: {
        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
        x: { grid: { display: false } }
      },
      interaction: { intersect: false, mode: 'index' }
    }
  })
}

function renderStatusChart() {
  const ctx = document.getElementById('statusChart')
  if (!ctx || !dashboard.value?.work_orders) return

  if (statusChart) statusChart.destroy()

  const data = dashboard.value.work_orders.filter(d => d.count > 0)
  if (data.length === 0) return

  statusChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: data.map(d => d.label),
      datasets: [{
        data: data.map(d => d.count),
        backgroundColor: data.map(d => d.color),
        borderWidth: 0,
        hoverOffset: 10,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 500 },
      cutout: '65%',
      plugins: {
        legend: { display: false },
        datalabels: {
          color: '#fff',
          font: { weight: 'bold', size: 12 },
          formatter: (value) => value > 0 ? value : '',
        }
      }
    }
  })
}

function renderEquipmentChart() {
  const ctx = document.getElementById('equipmentChart')
  if (!ctx || !dashboard.value?.equipment_status) return

  if (equipmentChart) equipmentChart.destroy()

  const data = dashboard.value.equipment_status
  if (data.length === 0) return

  equipmentChart = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: data.map(d => d.label),
      datasets: [{
        data: data.map(d => d.count),
        backgroundColor: data.map(d => d.color),
        borderRadius: 6,
        barThickness: 40,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 500 },
      indexAxis: 'y',
      plugins: {
        legend: { display: false },
        datalabels: {
          anchor: 'end',
          align: 'end',
          color: '#2c3e50',
          font: { weight: 'bold' },
        }
      },
      scales: {
        x: { display: false },
        y: { grid: { display: false } }
      }
    }
  })
}

function getKPIColor(color) {
  const colors = {
    blue: { bg: 'linear-gradient(135deg, #3498db 0%, #2980b9 100%)', text: 'white' },
    green: { bg: 'linear-gradient(135deg, #27ae60 0%, #1e8449 100%)', text: 'white' },
    purple: { bg: 'linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%)', text: 'white' },
    orange: { bg: 'linear-gradient(135deg, #f39c12 0%, #d68910 100%)', text: 'white' },
    red: { bg: 'linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)', text: 'white' },
    teal: { bg: 'linear-gradient(135deg, #1abc9c 0%, #16a085 100%)', text: 'white' },
  }
  return colors[color] || colors.blue
}

function getPriorityClass(priority) {
  return {
    urgent: 'priority-urgent',
    high: 'priority-high',
    medium: 'priority-medium',
    low: 'priority-low',
  }[priority] || ''
}

function navigateTo(link) {
  if (link) router.push(link)
}

onMounted(() => {
  fetchDashboard()

  // Rafraîchir toutes les 2 minutes
  refreshInterval.value = setInterval(fetchDashboard, 120000)

  // Mettre à jour l'heure chaque minute (pas chaque seconde pour réduire les rerenders)
  timeInterval.value = setInterval(() => {
    currentTime.value = new Date()
  }, 60000)
})

onUnmounted(() => {
  if (refreshInterval.value) clearInterval(refreshInterval.value)
  if (timeInterval.value) clearInterval(timeInterval.value)
  if (trendChart) trendChart.destroy()
  if (statusChart) statusChart.destroy()
  if (equipmentChart) equipmentChart.destroy()
})
</script>


<template>
  <div class="dashboard">
    <!-- Header -->
    <header class="dashboard-header">
      <div class="header-greeting">
        <h1>{{ greeting }}, {{ authStore.user?.name?.split(' ')[0] }} 👋</h1>
        <p class="header-date">{{ formattedDate }}</p>
      </div>
      <div class="header-clock">
        <span class="clock-time">{{ formattedTime }}</span>
        <span class="clock-label">Heure locale</span>
      </div>
    </header>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner-large"></div>
      <p>Chargement du tableau de bord...</p>
    </div>

    <template v-else-if="dashboard">
      <!-- KPIs -->
      <section class="kpi-section">
        <div v-for="kpi in dashboard.kpis" :key="kpi.key" class="kpi-card"
          :style="{ background: getKPIColor(kpi.color).bg }">
          <div class="kpi-icon">{{ kpi.icon }}</div>
          <div class="kpi-content">
            <div class="kpi-value">
              {{ kpi.value }}<span v-if="kpi.suffix" class="kpi-suffix">{{ kpi.suffix }}</span>
            </div>
            <div class="kpi-label">{{ kpi.label }}</div>
          </div>
          <div v-if="kpi.trend !== null" class="kpi-trend"
            :class="{ positive: kpi.trend >= 0, negative: kpi.trend < 0 }">
            <span v-if="kpi.trend >= 0">↑</span>
            <span v-else>↓</span>
            {{ Math.abs(kpi.trend) }}%
          </div>
        </div>
      </section>

      <!-- Main Grid -->
      <div class="dashboard-grid">
        <!-- Colonne gauche -->
        <div class="grid-left">
          <!-- Tendance -->
          <div class="widget widget-trend">
            <div class="widget-header">
              <h3>📈 Tendance des interventions</h3>
              <span class="widget-badge">6 derniers mois</span>
            </div>
            <div class="chart-container">
              <div v-if="!chartsReady" class="chart-loading">
                <div class="spinner-small"></div>
              </div>
              <canvas id="trendChart"></canvas>
            </div>
          </div>


          <!-- Urgent Items -->
          <div class="widget widget-urgent" v-if="dashboard.urgent_items.length > 0">
            <div class="widget-header">
              <h3>🚨 Éléments urgents</h3>
              <span class="widget-count danger">{{ dashboard.urgent_items.length }}</span>
            </div>
            <div class="urgent-list">
              <div v-for="(item, index) in dashboard.urgent_items" :key="index" class="urgent-item"
                :class="item.urgency" @click="navigateTo(item.link)">
                <span class="urgent-icon">{{ item.icon }}</span>
                <div class="urgent-content">
                  <div class="urgent-title">{{ item.title }}</div>
                  <div class="urgent-subtitle">{{ item.subtitle }}</div>
                  <div class="urgent-desc">{{ item.description }}</div>
                </div>
                <span v-if="item.priority" class="priority-badge" :class="getPriorityClass(item.priority)">
                  {{ item.priority }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Colonne droite -->
        <div class="grid-right">
          <!-- Statut OT -->
          <div class="widget widget-status">
            <div class="widget-header">
              <h3>📊 Ordres de travail</h3>
            </div>
            <div class="status-grid">
              <div class="chart-container-small">
                <canvas id="statusChart"></canvas>
              </div>
              <div class="status-legend">
                <div v-for="item in dashboard.work_orders.filter(w => w.count > 0)" :key="item.status"
                  class="legend-item">
                  <span class="legend-color" :style="{ background: item.color }"></span>
                  <span class="legend-label">{{ item.label }}</span>
                  <span class="legend-value">{{ item.count }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Équipements -->
          <div class="widget widget-equipment">
            <div class="widget-header">
              <h3>⚙️ État des équipements</h3>
            </div>
            <div class="chart-container-small">
              <canvas id="equipmentChart"></canvas>
            </div>
          </div>

          <!-- Performance équipe -->
          <div class="widget widget-team" v-if="dashboard.team_performance.length > 0">
            <div class="widget-header">
              <h3>👥 Top techniciens du mois</h3>
            </div>
            <div class="team-list">
              <div v-for="(tech, index) in dashboard.team_performance" :key="tech.id" class="team-item">
                <div class="team-rank">{{ index + 1 }}</div>
                <div class="team-avatar">{{ tech.initials }}</div>
                <div class="team-info">
                  <div class="team-name">{{ tech.name }}</div>
                  <div class="team-stats">{{ tech.completed }} OT terminés</div>
                </div>
                <div class="team-medal" v-if="index < 3">
                  {{ ['🥇', '🥈', '🥉'][index] }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Colonne activités -->
        <div class="grid-activities">
          <!-- Maintenances à venir -->
          <div class="widget widget-upcoming">
            <div class="widget-header">
              <h3>📅 Maintenances à venir</h3>
              <span class="widget-badge">7 prochains jours</span>
            </div>
            <div class="upcoming-list" v-if="dashboard.upcoming_maintenance.length > 0">
              <div v-for="item in dashboard.upcoming_maintenance" :key="`${item.type}-${item.id}`" class="upcoming-item"
                @click="navigateTo(item.type === 'work_order' ? `/work-orders/${item.id}` : `/preventive-maintenance/${item.id}`)">
                <div class="upcoming-date">
                  <span class="date-day">{{ item.due_date_formatted.split('/')[0] }}</span>
                  <span class="date-month">{{
                    ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov',
                      'Déc'][parseInt(item.due_date_formatted.split('/')[1])
                    - 1] }}</span>
                </div>
                <div class="upcoming-content">
                  <div class="upcoming-title">{{ item.title || item.code }}</div>
                  <div class="upcoming-equipment">{{ item.equipment }}</div>
                </div>
                <div class="upcoming-type" :class="item.type">
                  {{ item.type === 'preventive' ? 'Préventif' : 'OT' }}
                </div>
              </div>
            </div>
            <div v-else class="empty-widget">
              <span class="empty-icon">✅</span>
              <p>Aucune maintenance prévue</p>
            </div>
          </div>

          <!-- Activités récentes -->
          <div class="widget widget-activities">
            <div class="widget-header">
              <h3>🕐 Activités récentes</h3>
            </div>
            <div class="activities-list">
              <div v-for="(activity, index) in dashboard.recent_activities" :key="index" class="activity-item"
                @click="navigateTo(activity.link)">
                <span class="activity-icon">{{ activity.icon }}</span>
                <div class="activity-content">
                  <div class="activity-title">{{ activity.title }}</div>
                  <div class="activity-desc">{{ activity.description }}</div>
                </div>
                <span class="activity-time">{{ activity.time }}</span>
              </div>
            </div>
          </div>

          <!-- Notifications -->
          <div class="widget widget-notifications" v-if="dashboard.notifications.length > 0">
            <div class="widget-header">
              <h3>🔔 Dernières alertes</h3>
              <router-link to="/notifications" class="widget-link">Voir tout</router-link>
            </div>
            <div class="notifications-list">
              <div v-for="notif in dashboard.notifications" :key="notif.id" class="notif-item"
                :class="{ unread: !notif.is_read }" @click="navigateTo(notif.link)">
                <span class="notif-icon">{{ notif.icon }}</span>
                <div class="notif-content">
                  <div class="notif-title">{{ notif.title }}</div>
                  <div class="notif-message">{{ notif.message }}</div>
                </div>
                <span class="notif-time">{{ notif.time }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.dashboard {
  padding: 30px;
  background: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
  min-height: 100vh;
}

/* Header */
.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
}

.header-greeting h1 {
  font-size: 28px;
  color: #2c3e50;
  margin: 0 0 5px;
  font-weight: 700;
}

.header-date {
  color: #7f8c8d;
  font-size: 14px;
  margin: 0;
  text-transform: capitalize;
}

.header-clock {
  text-align: right;
  background: white;
  padding: 15px 25px;
  border-radius: 12px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
}

.clock-time {
  display: block;
  font-size: 32px;
  font-weight: 700;
  color: #2c3e50;
  font-variant-numeric: tabular-nums;
}

.clock-label {
  font-size: 11px;
  color: #95a5a6;
  text-transform: uppercase;
}

/* KPIs */
.kpi-section {
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 20px;
  margin-bottom: 30px;
}

.kpi-card {
  padding: 20px;
  border-radius: 16px;
  color: white;
  display: flex;
  align-items: center;
  gap: 15px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
  transition: transform 0.3s, box-shadow 0.3s;
  position: relative;
  overflow: hidden;
}

.kpi-card::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -50%;
  width: 100%;
  height: 100%;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.2) 0%, transparent 60%);
  pointer-events: none;
}

.kpi-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
}

.kpi-icon {
  font-size: 36px;
  opacity: 0.9;
}

.kpi-content {
  flex: 1;
}

.kpi-value {
  font-size: 32px;
  font-weight: 800;
  line-height: 1;
}

.kpi-suffix {
  font-size: 16px;
  font-weight: 400;
  opacity: 0.8;
}

.kpi-label {
  font-size: 12px;
  opacity: 0.9;
  margin-top: 5px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.kpi-trend {
  font-size: 12px;
  font-weight: 600;
  padding: 4px 8px;
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.2);
}

.kpi-trend.positive {
  color: #d4edda;
}

.kpi-trend.negative {
  color: #f8d7da;
}

/* Dashboard Grid */
.dashboard-grid {
  display: grid;
  grid-template-columns: 1.5fr 1fr 1fr;
  gap: 20px;
}

.grid-left,
.grid-right,
.grid-activities {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

/* Chart Loading */
.chart-loading {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.9);
  z-index: 10;
}

.spinner-small {
  width: 30px;
  height: 30px;
  border: 3px solid #ecf0f1;
  border-top-color: #3498db;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}


/* Widget */
.widget {
  background: white;
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
}

.widget-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.widget-header h3 {
  font-size: 15px;
  color: #2c3e50;
  margin: 0;
  font-weight: 600;
}

.widget-badge {
  font-size: 11px;
  padding: 4px 10px;
  background: #ecf0f1;
  border-radius: 10px;
  color: #7f8c8d;
}

.widget-count {
  font-size: 13px;
  font-weight: 600;
  padding: 4px 12px;
  border-radius: 10px;
}

.widget-count.danger {
  background: #fee;
  color: #e74c3c;
}

.widget-link {
  font-size: 12px;
  color: #3498db;
  text-decoration: none;
}

.widget-link:hover {
  text-decoration: underline;
}

/* Charts */
.chart-container {
  height: 250px;
  position: relative;
}

.chart-container-small {
  height: 180px;
  position: relative;
}

/* Status Grid */
.status-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
  align-items: center;
}

.status-legend {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
}

.legend-color {
  width: 12px;
  height: 12px;
  border-radius: 3px;
}

.legend-label {
  flex: 1;
  color: #7f8c8d;
}

.legend-value {
  font-weight: 600;
  color: #2c3e50;
}

/* Urgent List */
.urgent-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.urgent-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s;
}

.urgent-item.high {
  background: #fee;
  border-left: 3px solid #e74c3c;
}

.urgent-item.medium {
  background: #fff8e6;
  border-left: 3px solid #f39c12;
}

.urgent-item:hover {
  transform: translateX(5px);
}

.urgent-icon {
  font-size: 24px;
}

.urgent-content {
  flex: 1;
  min-width: 0;
}

.urgent-title {
  font-weight: 600;
  color: #2c3e50;
  font-size: 13px;
}

.urgent-subtitle {
  font-size: 12px;
  color: #7f8c8d;
}

.urgent-desc {
  font-size: 11px;
  color: #95a5a6;
}

.priority-badge {
  font-size: 10px;
  padding: 3px 8px;
  border-radius: 10px;
  text-transform: uppercase;
  font-weight: 600;
}

.priority-urgent {
  background: #e74c3c;
  color: white;
}

.priority-high {
  background: #f39c12;
  color: white;
}

.priority-medium {
  background: #3498db;
  color: white;
}

.priority-low {
  background: #95a5a6;
  color: white;
}

/* Team List */
.team-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.team-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px;
  background: #f8f9fa;
  border-radius: 10px;
}

.team-rank {
  width: 24px;
  height: 24px;
  background: #ecf0f1;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
  color: #7f8c8d;
}

.team-avatar {
  width: 36px;
  height: 36px;
  background: linear-gradient(135deg, #3498db, #2980b9);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 600;
  color: white;
}

.team-info {
  flex: 1;
}

.team-name {
  font-weight: 600;
  font-size: 13px;
  color: #2c3e50;
}

.team-stats {
  font-size: 11px;
  color: #7f8c8d;
}

.team-medal {
  font-size: 20px;
}

/* Upcoming List */
.upcoming-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.upcoming-item {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 12px;
  background: #f8f9fa;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s;
}

.upcoming-item:hover {
  background: #ecf0f1;
  transform: translateX(5px);
}

.upcoming-date {
  width: 50px;
  text-align: center;
  background: white;
  padding: 8px;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.date-day {
  display: block;
  font-size: 20px;
  font-weight: 700;
  color: #2c3e50;
  line-height: 1;
}

.date-month {
  display: block;
  font-size: 10px;
  color: #7f8c8d;
  text-transform: uppercase;
}

.upcoming-content {
  flex: 1;
  min-width: 0;
}

.upcoming-title {
  font-weight: 600;
  font-size: 13px;
  color: #2c3e50;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.upcoming-equipment {
  font-size: 12px;
  color: #7f8c8d;
}

.upcoming-type {
  font-size: 10px;
  padding: 4px 8px;
  border-radius: 10px;
  font-weight: 600;
}

.upcoming-type.preventive {
  background: #d4edda;
  color: #155724;
}

.upcoming-type.work_order {
  background: #cce5ff;
  color: #004085;
}

/* Activities List */
.activities-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.activity-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.2s;
}

.activity-item:hover {
  background: #f8f9fa;
}

.activity-icon {
  font-size: 20px;
}

.activity-content {
  flex: 1;
  min-width: 0;
}

.activity-title {
  font-weight: 600;
  font-size: 13px;
  color: #2c3e50;
}

.activity-desc {
  font-size: 12px;
  color: #7f8c8d;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.activity-time {
  font-size: 11px;
  color: #95a5a6;
  white-space: nowrap;
}

/* Notifications List */
.notifications-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.notif-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px;
  border-radius: 8px;
  cursor: pointer;
  transition: background 0.2s;
}

.notif-item:hover {
  background: #f8f9fa;
}

.notif-item.unread {
  background: #f0f7ff;
}

.notif-icon {
  font-size: 18px;
}

.notif-content {
  flex: 1;
  min-width: 0;
}

.notif-title {
  font-weight: 600;
  font-size: 12px;
  color: #2c3e50;
}

.notif-message {
  font-size: 11px;
  color: #7f8c8d;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.notif-time {
  font-size: 10px;
  color: #95a5a6;
  white-space: nowrap;
}

/* Empty State */
.empty-widget {
  text-align: center;
  padding: 30px;
  color: #7f8c8d;
}

.empty-icon {
  font-size: 40px;
  opacity: 0.5;
}

.empty-widget p {
  margin: 10px 0 0;
  font-size: 13px;
}

/* Loading */
.loading-state {
  text-align: center;
  padding: 100px;
}

.spinner-large {
  width: 60px;
  height: 60px;
  border: 4px solid #ecf0f1;
  border-top-color: #3498db;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 20px;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Responsive */
@media (max-width: 1400px) {
  .kpi-section {
    grid-template-columns: repeat(3, 1fr);
  }

  .dashboard-grid {
    grid-template-columns: 1fr 1fr;
  }

  .grid-activities {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
  }
}

@media (max-width: 1000px) {
  .kpi-section {
    grid-template-columns: repeat(2, 1fr);
  }

  .dashboard-grid {
    grid-template-columns: 1fr;
  }

  .grid-activities {
    grid-template-columns: 1fr;
  }

  .status-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 600px) {
  .dashboard {
    padding: 15px;
  }

  .dashboard-header {
    flex-direction: column;
    gap: 15px;
    text-align: center;
  }

  .header-clock {
    width: 100%;
  }

  .kpi-section {
    grid-template-columns: 1fr;
  }
}
</style>
