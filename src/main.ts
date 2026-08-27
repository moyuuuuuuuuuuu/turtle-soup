import { createSSRApp } from 'vue'
import App from './App.vue'
import router from './router'
import { applyStoredTheme } from './utils/theme'
import 'uno.css'

applyStoredTheme()
const pinia = createPinia()
pinia.use(persistPlugin)
export function createApp() {
  const app = createSSRApp(App)
  app.use(router)
  app.use(pinia)
  return {
    app,
  }
}
