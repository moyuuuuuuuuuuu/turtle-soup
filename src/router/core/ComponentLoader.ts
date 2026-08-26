import { h } from 'vue'
import type { AppRouteRecord } from '@/types/router'

export class ComponentLoader {
  private modules: Record<string, () => Promise<any>>
  private preloadTasks = new Map<string, Promise<any>>()

  constructor() {
    this.modules = import.meta.glob('../../views/**/*.vue')
  }

  load(componentPath: string): () => Promise<any> {
    if (!componentPath) {
      return this.createEmptyComponent()
    }

    const module = this.resolveModule(componentPath)

    if (!module) {
      console.error(`[ComponentLoader] Missing component: ${componentPath}`)
      return this.createErrorComponent(componentPath)
    }

    return module
  }

  preloadMenuRoutes(menuList: AppRouteRecord[]): void {
    void menuList
    // 默认禁用全量路由预加载，以避免登录后瞬间发起大量 import() 请求导致浏览器假死及 Vite 服务过载。
    // 动态路由会在用户实际导航访问时由 vue-router 自动按需加载，无需提前全量加载。
  }

  loadLayout(): () => Promise<any> {
    return () => import('@/views/index/index.vue')
  }

  loadIframe(): () => Promise<any> {
    return () => import('@/views/outside/Iframe.vue')
  }

  private createEmptyComponent(): () => Promise<any> {
    return () =>
      Promise.resolve({
        render() {
          return h('div', {})
        }
      })
  }

  private createErrorComponent(componentPath: string): () => Promise<any> {
    return () =>
      Promise.resolve({
        render() {
          return h('div', { class: 'route-error' }, `Component not found: ${componentPath}`)
        }
      })
  }

  private preload(componentPath: string): void {
    const module = this.resolveModule(componentPath)
    if (!module || this.preloadTasks.has(componentPath)) {
      return
    }

    const preloadTask = module().catch((error) => {
      console.warn(`[ComponentLoader] Failed to preload component: ${componentPath}`, error)
    })

    this.preloadTasks.set(componentPath, preloadTask)
  }

  private resolveModule(componentPath: string): (() => Promise<any>) | undefined {
    const normalizedPath = componentPath.startsWith('/') ? componentPath : `/${componentPath}`
    const fullPath = `../../views${normalizedPath}.vue`
    const fullPathWithIndex = `../../views${normalizedPath}/index.vue`
    return this.modules[fullPath] || this.modules[fullPathWithIndex]
  }
}
