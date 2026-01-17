import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/LoginView.vue'),
    meta: { guest: true, title: 'Connexion' }
  },
  {
    path: '/',
    name: 'dashboard',
    component: () => import('@/views/DashboardView.vue'),
    meta: { requiresAuth: true, title: 'Tableau de bord' }
  },
  {
    path: '/sites',
    name: 'sites',
    component: () => import('@/views/SitesView.vue'),
    meta: { requiresAuth: true, permission: 'site:view_any', title: 'Sites' }
  },
  {
    path: '/equipments',
    name: 'equipments',
    component: () => import('@/views/EquipmentsView.vue'),
    meta: { requiresAuth: true, permission: 'equipment:view_any', title: 'Équipements' }
  },
  {
    path: '/locations',
    name: 'locations',
    component: () => import('@/views/LocationsView.vue'),
    meta: { requiresAuth: true, permission: 'location:view_any', title: 'Emplacements' }
  },
  {
    path: '/parts',
    name: 'parts',
    component: () => import('@/views/PartsView.vue'),
    meta: { requiresAuth: true, permission: 'part:view_any', title: 'Pièces de rechange' }
  },
  {
    path: '/work-orders',
    name: 'work-orders',
    component: () => import('@/views/WorkOrdersView.vue'),
    meta: { requiresAuth: true, permission: 'workorder:view_any', title: 'Ordres de travail' }
  },
  {
    path: '/users',
    name: 'users',
    component: () => import('@/views/UsersView.vue'),
    meta: { requiresAuth: true, permission: 'user:view_any', title: 'Utilisateurs' }
  },
  {
    path: '/intervention-requests',
    name: 'intervention-requests',
    component: () => import('@/views/InterventionRequestsView.vue'),
    meta: { requiresAuth: true, permission: 'intervention_request:view_any', title: 'Demandes d\'intervention' }
  },
  {
    path: '/preventive-maintenance',
    name: 'preventive-maintenance',
    component: () => import('@/views/PreventiveMaintenanceView.vue'),
    meta: { requiresAuth: true, permission: 'preventive:view_any', title: 'Maintenance préventive' }
  },
  {
    path: '/reports',
    name: 'reports',
    component: () => import('@/views/ReportsView.vue'),
    meta: { requiresAuth: true, title: 'Rapports' }
  },
  {
    path: '/notifications',
    name: 'notifications',
    component: () => import('@/views/NotificationsView.vue'),
    meta: { requiresAuth: true, title: 'Notifications' }
  },
  {
    path: '/drivers',
    name: 'drivers',
    component: () => import('@/views/DriversView.vue'),
    meta: { requiresAuth: true, title: 'Chauffeurs' }
  },
  {
    path: '/trucks',
    name: 'trucks',
    component: () => import('@/views/TrucksView.vue'),
    meta: { requiresAuth: true, title: 'Camions' }
  },
  {
    path: '/clients',
    name: 'clients',
    component: () => import('@/views/ClientsView.vue'),
    meta: { requiresAuth: true, title: 'Clients' }
  },
  {
    path: '/habilitations',
    name: 'habilitations',
    component: () => import('@/views/HabilitationsView.vue'),
    meta: { requiresAuth: true, title: 'Habilitations' }
  },
  {
    path: '/assignments',
    name: 'assignments',
    component: () => import('@/views/AssignmentsView.vue'),
    meta: { requiresAuth: true, title: 'Affectations' }
  },
  // 404 Page
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/NotFoundView.vue'),
    meta: { title: 'Page non trouvée' }
  }
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    }
    return { top: 0 }
  }
})

// Variable pour éviter les appels multiples
let isCheckingAuth = false

// Navigation guard
router.beforeEach(async (to, from, next) => {
  // Mettre à jour le titre de la page
  document.title = to.meta.title 
    ? `${to.meta.title} | GMAO` 
    : 'GMAO - Gestion de Maintenance'

  const authStore = useAuthStore()

  // Page invité (login)
  if (to.meta.guest) {
    if (authStore.isAuthenticated) {
      return next({ name: 'dashboard' })
    }
    return next()
  }

  // Pages qui nécessitent l'authentification
  if (to.meta.requiresAuth) {
    // Vérifier l'authentification si pas encore fait
    if (!authStore.isInitialized && !isCheckingAuth) {
      isCheckingAuth = true
      await authStore.fetchUser()
      isCheckingAuth = false
    }

    // Non authentifié -> rediriger vers login
    if (!authStore.isAuthenticated) {
      return next({
        name: 'login',
        query: { redirect: to.fullPath }
      })
    }

    // Vérifier les permissions
    if (to.meta.permission && !authStore.hasPermission(to.meta.permission)) {
      // Pas la permission -> rediriger vers dashboard avec message
      console.warn(`Accès refusé: permission ${to.meta.permission} requise`)
      return next({ name: 'dashboard' })
    }
  }

  next()
})

// Après chaque navigation
router.afterEach((to, from) => {
  // Analytics ou autres actions post-navigation
})

export default router
