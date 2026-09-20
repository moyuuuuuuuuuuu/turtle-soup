import type { HgtConfirmTone, HgtToastTone } from '@/store/feedbackStore'
import { useFeedbackStore } from '@/store/feedbackStore'

export interface HgtToastOptions {
  tone?: HgtToastTone
  duration?: number
}

export interface HgtConfirmOptions {
  title: string
  description?: string
  eyebrow?: string
  confirmText?: string
  cancelText?: string
  tone?: HgtConfirmTone
  showCancel?: boolean
}

/** 保存未打补丁的原生 API，避免 host 未挂载时自定义层递归调用 */
const nativeApi: {
  showToast?: typeof uni.showToast
  hideToast?: typeof uni.hideToast
  showModal?: typeof uni.showModal
  showLoading?: typeof uni.showLoading
  hideLoading?: typeof uni.hideLoading
} = {}

function safeBind(fn: unknown, thisArg: unknown): ((...args: any[]) => any) | undefined {
  return typeof fn === 'function' ? (fn as (...args: any[]) => any).bind(thisArg) : undefined
}

export function rememberNativeFeedbackApi() {
  if (nativeApi.showToast || typeof uni === 'undefined')
    return
  nativeApi.showToast = safeBind(uni.showToast, uni)
  nativeApi.hideToast = safeBind(uni.hideToast, uni)
  nativeApi.showModal = safeBind(uni.showModal, uni)
  nativeApi.showLoading = safeBind(uni.showLoading, uni)
  nativeApi.hideLoading = safeBind(uni.hideLoading, uni)
}

function getStore() {
  try {
    return useFeedbackStore()
  }
  catch {
    return null
  }
}

function hasHost(store: NonNullable<ReturnType<typeof getStore>> | null): boolean {
  return Boolean(store?.hostId)
}

function nativeToast(message: string, icon: 'success' | 'error' | 'loading' | 'none' = 'none', duration = 2200) {
  if (nativeApi.showToast) {
    nativeApi.showToast({ title: message, icon, duration })
    return
  }
  // 未 patch 前的极早期兜底
  try {
    uni.showToast({ title: message, icon, duration })
  }
  catch {}
}

/** 深海风格轻提示。tone: info | success | error | warning | loading */
export function hgtToast(message: string, options: HgtToastOptions | HgtToastTone = {}) {
  const opts = typeof options === 'string' ? { tone: options } : options
  const store = getStore()
  const tone = opts.tone || 'info'
  if (!store || !hasHost(store)) {
    const icon = tone === 'success' ? 'success' : tone === 'error' ? 'error' : tone === 'loading' ? 'loading' : 'none'
    nativeToast(message, icon, opts.duration ?? (tone === 'loading' ? 0 : 2200))
    return
  }
  const duration = opts.duration ?? (tone === 'loading' ? 0 : 2200)
  store.showToast(message, tone, duration)
}

export function hgtToastSuccess(message: string, duration?: number) {
  hgtToast(message, { tone: 'success', duration })
}

export function hgtToastError(message: string, duration?: number) {
  hgtToast(message, { tone: 'error', duration: duration ?? 2600 })
}

export function hgtToastWarning(message: string, duration?: number) {
  hgtToast(message, { tone: 'warning', duration: duration ?? 2600 })
}

export function hgtToastInfo(message: string, duration?: number) {
  hgtToast(message, { tone: 'info', duration })
}

export function hgtToastHide() {
  const store = getStore()
  if (!store || !hasHost(store)) {
    nativeApi.hideToast?.()
    return
  }
  store.hideToast()
}

export function hgtLoading(message = '加载中') {
  const store = getStore()
  if (!store || !hasHost(store)) {
    if (nativeApi.showLoading)
      nativeApi.showLoading({ title: message, mask: true })
    return
  }
  store.showLoading(message)
}

export function hgtLoadingHide() {
  const store = getStore()
  if (!store || !hasHost(store)) {
    nativeApi.hideLoading?.()
    return
  }
  store.hideLoading()
}

/** 深海风格确认框，返回是否确认 */
export function hgtConfirm(options: HgtConfirmOptions): Promise<boolean> {
  const store = getStore()
  if (!store || !hasHost(store)) {
    return new Promise((resolve) => {
      if (!nativeApi.showModal) {
        resolve(false)
        return
      }
      nativeApi.showModal({
        title: options.title,
        content: options.description || '',
        confirmText: options.confirmText || '确认',
        cancelText: options.cancelText || '取消',
        showCancel: options.showCancel !== false,
        success: ({ confirm }) => resolve(Boolean(confirm)),
        fail: () => resolve(false),
      })
    })
  }
  return store.openConfirm(options)
}

export function hgtConfirmYes(options: HgtConfirmOptions): Promise<boolean> {
  return hgtConfirm(options)
}
