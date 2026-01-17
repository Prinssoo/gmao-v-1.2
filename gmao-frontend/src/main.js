import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import './assets/main.css'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

// Directive globale pour focus auto
app.directive('focus', {
  mounted: (el) => el.focus()
})

// Directive pour click outside
app.directive('click-outside', {
  mounted: (el, binding) => {
    el._clickOutside = (event) => {
      if (!(el === event.target || el.contains(event.target))) {
        binding.value(event)
      }
    }
    document.addEventListener('click', el._clickOutside)
  },
  unmounted: (el) => {
    document.removeEventListener('click', el._clickOutside)
  }
})

// Gestionnaire d'erreur global
app.config.errorHandler = (err, vm, info) => {
  console.error('Vue Error:', err)
  console.error('Component:', vm)
  console.error('Info:', info)
}

app.mount('#app')
