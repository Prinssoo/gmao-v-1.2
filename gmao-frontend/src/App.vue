<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import NotificationDropdown from '@/components/NotificationDropdown.vue'

const router = useRouter()
const authStore = useAuthStore()

const isAuthenticated = computed(() => authStore.isAuthenticated)

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="app" :class="{ 'has-sidebar': isAuthenticated }">
    <!-- Sidebar (visible seulement si connecté) -->
    <aside class="sidebar" v-if="isAuthenticated">
      <div class="sidebar-header">
        <h1 class="logo">GMAO</h1>
        <span class="version">v1.0</span>
      </div>

      <div class="sidebar-site" v-if="authStore.currentSite">
        <span class="site-label">Site actuel</span>
        <span class="site-name">{{ authStore.currentSite.name }}</span>
      </div>

      <nav class="sidebar-nav">
        <div class="nav-section">
          <span class="nav-section-title">Principal</span>
          <router-link to="/" class="nav-item">
            <span class="nav-icon">📊</span>
            <span>Tableau de bord</span>
          </router-link>
        </div>

        <div class="nav-section">
          <span class="nav-section-title">Gestion technique</span>
          <router-link to="/equipments" class="nav-item" v-if="authStore.hasPermission('equipment:view_any')">
            <span class="nav-icon">⚙️</span>
            <span>Équipements</span>
          </router-link>
          <router-link to="/locations" class="nav-item" v-if="authStore.hasPermission('location:view_any')">
            <span class="nav-icon">📍</span>
            <span>Emplacements</span>
          </router-link>
        </div>

        <div class="nav-section">
          <span class="nav-section-title">Maintenance</span>
          <router-link to="/intervention-requests" class="nav-item"
            v-if="authStore.hasPermission('intervention_request:view_any')">
            <span class="nav-icon">📋</span>
            <span>Demandes (DI)</span>
          </router-link>
          <router-link to="/work-orders" class="nav-item" v-if="authStore.hasPermission('workorder:view_any')">
            <span class="nav-icon">🔧</span>
            <span>Interventions</span>
          </router-link>
          <router-link to="/preventive-maintenance" class="nav-item"
            v-if="authStore.hasPermission('preventive:view_any')">
            <span class="nav-icon">📅</span>
            <span>Préventive</span>
          </router-link>
        </div>

        <div class="nav-section">
          <span class="nav-section-title">Transport</span>
          <router-link to="/drivers" class="nav-item">
            <span class="nav-icon">👷</span>
            <span>Chauffeurs</span>
          </router-link>
          <router-link to="/trucks" class="nav-item">
            <span class="nav-icon">🚛</span>
            <span>Camions</span>
          </router-link>
          <router-link to="/assignments" class="nav-item">
            <span class="nav-icon">🔄</span>
            <span>Attributions</span>
          </router-link>
          <router-link to="/clients" class="nav-item">
            <span class="nav-icon">🏢</span>
            <span>Clients</span>
          </router-link>
          <router-link to="/habilitations" class="nav-item">
            <span class="nav-icon">📜</span>
            <span>Habilitations</span>
          </router-link>
        </div>


        <div class="nav-section">
          <span class="nav-section-title">Stock</span>
          <router-link to="/parts" class="nav-item" v-if="authStore.hasPermission('part:view_any')">
            <span class="nav-icon">🔩</span>
            <span>Pièces détachées</span>
          </router-link>
        </div>

        <div class="nav-section">
          <span class="nav-section-title">Analyses</span>
          <router-link to="/reports" class="nav-item">
            <span class="nav-icon">📊</span>
            <span>Rapports</span>
          </router-link>
        </div>

        <div class="nav-section"
          v-if="authStore.hasPermission('site:view_any') || authStore.hasPermission('user:view_any')">
          <span class="nav-section-title">Administration</span>
          <router-link to="/sites" class="nav-item" v-if="authStore.hasPermission('site:view_any')">
            <span class="nav-icon">🏭</span>
            <span>Sites</span>
          </router-link>
          <router-link to="/users" class="nav-item" v-if="authStore.hasPermission('user:view_any')">
            <span class="nav-icon">👥</span>
            <span>Utilisateurs</span>
          </router-link>
        </div>
      </nav>

      <div class="sidebar-footer">
        <div class="user-card">
          <div class="user-avatar">
            {{ authStore.user?.name?.charAt(0).toUpperCase() }}
          </div>
          <div class="user-details">
            <span class="user-name">{{ authStore.user?.name }}</span>
            <span class="user-role">{{ authStore.roles[0] || 'Utilisateur' }}</span>
          </div>
        </div>
        <button @click="handleLogout" class="logout-btn">
          🚪 Déconnexion
        </button>
      </div>
    </aside>

    <!-- Contenu principal -->
    <main class="main-content">
      <!-- Top Header -->
      <header class="top-header" v-if="isAuthenticated">
        <div class="header-left">
          <span class="current-date">{{ new Date().toLocaleDateString('fr-FR', {
            weekday: 'long', year: 'numeric',
            month: 'long', day: 'numeric'
          }) }}</span>
        </div>
        <div class="header-right">
          <NotificationDropdown />
          <div class="header-user">
            <span>{{ authStore.user?.name }}</span>
          </div>
        </div>
      </header>

      <div class="page-content">
        <router-view />
      </div>
    </main>
  </div>
</template>

<style scoped>
.app {
  min-height: 100vh;
  display: flex;
}

/* Sidebar */
.sidebar {
  width: 260px;
  background: linear-gradient(180deg, #1a252f 0%, #2c3e50 100%);
  color: white;
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0;
  left: 0;
  height: 100vh;
  z-index: 1000;
  overflow-y: auto;
}

.sidebar-header {
  padding: 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.logo {
  font-size: 24px;
  font-weight: bold;
  color: #3498db;
  margin: 0;
}

.version {
  font-size: 11px;
  background: rgba(255, 255, 255, 0.1);
  padding: 2px 8px;
  border-radius: 10px;
  color: rgba(255, 255, 255, 0.6);
}

.sidebar-site {
  padding: 15px 20px;
  background: rgba(52, 152, 219, 0.2);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.site-label {
  display: block;
  font-size: 10px;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.5);
  margin-bottom: 4px;
}

.site-name {
  font-size: 14px;
  font-weight: 600;
  color: #3498db;
}

/* Navigation */
.sidebar-nav {
  flex: 1;
  padding: 15px 0;
  overflow-y: auto;
}

.nav-section {
  margin-bottom: 20px;
}

.nav-section-title {
  display: block;
  padding: 0 20px;
  font-size: 11px;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.4);
  margin-bottom: 8px;
  letter-spacing: 0.5px;
}

.nav-item {
  display: flex;
  align-items: center;
  padding: 12px 20px;
  color: rgba(255, 255, 255, 0.7);
  text-decoration: none;
  transition: all 0.2s;
  border-left: 3px solid transparent;
}

.nav-item:hover {
  background: rgba(255, 255, 255, 0.05);
  color: white;
  text-decoration: none;
}

.nav-item.router-link-active {
  background: rgba(52, 152, 219, 0.2);
  color: #3498db;
  border-left-color: #3498db;
}

.nav-item.disabled {
  opacity: 0.5;
  pointer-events: none;
}

.nav-icon {
  margin-right: 12px;
  font-size: 18px;
}

.badge-soon {
  margin-left: auto;
  font-size: 9px;
  background: rgba(255, 255, 255, 0.1);
  padding: 2px 6px;
  border-radius: 8px;
  color: rgba(255, 255, 255, 0.5);
}

/* Footer */
.sidebar-footer {
  padding: 15px;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.user-card {
  display: flex;
  align-items: center;
  padding: 10px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 8px;
  margin-bottom: 10px;
}

.user-avatar {
  width: 40px;
  height: 40px;
  background: #3498db;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  font-size: 18px;
  margin-right: 12px;
}

.user-details {
  display: flex;
  flex-direction: column;
}

.user-name {
  font-size: 14px;
  font-weight: 600;
}

.user-role {
  font-size: 11px;
  color: rgba(255, 255, 255, 0.5);
}

.logout-btn {
  width: 100%;
  padding: 10px;
  background: rgba(231, 76, 60, 0.2);
  color: #e74c3c;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
  transition: background 0.2s;
}

.logout-btn:hover {
  background: rgba(231, 76, 60, 0.3);
}

/* Main content */
.main-content {
  flex: 1;
  min-height: 100vh;
  background: #f5f7fa;
  display: flex;
  flex-direction: column;
}

.app.has-sidebar .main-content {
  margin-left: 260px;
}

/* Top Header */
.top-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px 30px;
  background: white;
  border-bottom: 1px solid #eee;
  position: sticky;
  top: 0;
  z-index: 100;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 20px;
}

.current-date {
  color: #7f8c8d;
  font-size: 14px;
  text-transform: capitalize;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 20px;
}

.header-user {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 15px;
  background: #f8f9fa;
  border-radius: 8px;
  font-size: 14px;
  color: #2c3e50;
}

.page-content {
  flex: 1;
}
</style>
