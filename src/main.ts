import { createSSRApp } from 'vue'
import App from './App.vue'
import router from './router'
import { bootstrapPrettyQuestionRoute } from './utils/questionRoute'
import { applyStoredTheme } from './utils/theme'
import 'uno.css'

// 必须在 uni-app 路由匹配前把 /pages/question-detail/{id} 转成注册页 query
bootstrapPrettyQuestionRoute()
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
