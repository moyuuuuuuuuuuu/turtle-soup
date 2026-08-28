import type { HgtTheme } from '@/utils/theme'
import { applyRootTheme, storedTheme } from '@/utils/theme'

interface ThemeTransitionOverlay {
  active: boolean
  fading: boolean
  expanding: boolean
  style: Record<string, string>
}

interface ViewTransitionDocument extends Document {
  startViewTransition?: (update: () => void) => { finished: Promise<void> }
}

function tapPoint(event: unknown): { x: number, y: number } {
  const value = event as {
    clientX?: number
    clientY?: number
    detail?: { x?: number, y?: number }
    touches?: Array<{ clientX?: number, clientY?: number }>
    changedTouches?: Array<{ clientX?: number, clientY?: number }>
  }
  const touch = value.touches?.[0] || value.changedTouches?.[0]
  return {
    x: value.clientX ?? value.detail?.x ?? touch?.clientX ?? 0,
    y: value.clientY ?? value.detail?.y ?? touch?.clientY ?? 0,
  }
}

function saveTheme(theme: HgtTheme): void {
  uni.setStorageSync('hgt_theme', theme)
  uni.setStorageSync('hgt_theme_manual', true)
  applyRootTheme(theme)
}

export function useAnimatedTheme() {
  const light = ref(storedTheme() === 'light')
  const transitioning = ref(false)
  const overlay = reactive<ThemeTransitionOverlay>({ active: false, fading: false, expanding: false, style: {} })

  function commit(theme: HgtTheme) {
    light.value = theme === 'light'
    saveTheme(theme)
  }

  async function toggleTheme(event: unknown) {
    if (transitioning.value)
      return

    const target: HgtTheme = light.value ? 'dark' : 'light'
    const point = tapPoint(event)

    // #ifdef H5
    const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true
    const transitionDocument = document as ViewTransitionDocument
    if (!reduceMotion && transitionDocument.startViewTransition) {
      transitioning.value = true
      const x = point.x || window.innerWidth / 2
      const y = point.y || window.innerHeight / 2
      const radius = Math.hypot(Math.max(x, window.innerWidth - x), Math.max(y, window.innerHeight - y))
      document.documentElement.style.setProperty('--hgt-theme-x', `${x}px`)
      document.documentElement.style.setProperty('--hgt-theme-y', `${y}px`)
      document.documentElement.style.setProperty('--hgt-theme-radius', `${radius}px`)
      const transition = transitionDocument.startViewTransition(() => commit(target))
      await transition.finished.catch(() => undefined)
      transitioning.value = false
      return
    }
    // #endif

    // #ifdef MP-WEIXIN || MP-TOUTIAO
    commit(target)
    return undefined
    // #endif

    commit(target)
  }

  return { light, overlay, toggleTheme }
}
