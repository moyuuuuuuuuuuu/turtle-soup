import { defineStore } from 'pinia'

export type HgtToastTone = 'info' | 'success' | 'error' | 'warning' | 'loading'
export type HgtConfirmTone = 'default' | 'warning' | 'danger'

export interface HgtToastState {
  show: boolean
  message: string
  tone: HgtToastTone
  duration: number
  seq: number
}

export interface HgtConfirmState {
  show: boolean
  eyebrow: string
  title: string
  description: string
  confirmText: string
  cancelText: string
  tone: HgtConfirmTone
  showCancel: boolean
  seq: number
}

export interface HgtLoadingState {
  show: boolean
  message: string
  seq: number
}

interface FeedbackState {
  toast: HgtToastState
  confirm: HgtConfirmState
  loading: HgtLoadingState
  hostId: string | null
  confirmResolver: ((confirm: boolean) => void) | null
}

const emptyToast: HgtToastState = {
  show: false,
  message: '',
  tone: 'info',
  duration: 2000,
  seq: 0,
}

const emptyConfirm: HgtConfirmState = {
  show: false,
  eyebrow: '请确认',
  title: '',
  description: '',
  confirmText: '确认',
  cancelText: '取消',
  tone: 'default',
  showCancel: true,
  seq: 0,
}

const emptyLoading: HgtLoadingState = {
  show: false,
  message: '加载中',
  seq: 0,
}

let toastTimer: ReturnType<typeof setTimeout> | null = null

export const useFeedbackStore = defineStore('hgt-feedback', {
  state: (): FeedbackState => ({
    toast: { ...emptyToast },
    confirm: { ...emptyConfirm },
    loading: { ...emptyLoading },
    hostId: null,
    confirmResolver: null,
  }),
  actions: {
    claimHost(id: string) {
      if (!this.hostId)
        this.hostId = id
      return this.hostId === id
    },
    releaseHost(id: string) {
      if (this.hostId === id)
        this.hostId = null
    },
    showToast(message: string, tone: HgtToastTone = 'info', duration = 2000) {
      if (toastTimer) {
        clearTimeout(toastTimer)
        toastTimer = null
      }
      this.toast = {
        show: true,
        message,
        tone,
        duration,
        seq: this.toast.seq + 1,
      }
      if (tone !== 'loading' && duration > 0) {
        toastTimer = setTimeout(() => {
          this.hideToast()
          toastTimer = null
        }, duration)
      }
    },
    hideToast() {
      if (toastTimer) {
        clearTimeout(toastTimer)
        toastTimer = null
      }
      this.toast = { ...emptyToast, seq: this.toast.seq }
    },
    showLoading(message = '加载中') {
      this.loading = {
        show: true,
        message,
        seq: this.loading.seq + 1,
      }
    },
    hideLoading() {
      this.loading = { ...emptyLoading, seq: this.loading.seq }
    },
    openConfirm(options: {
      title: string
      description?: string
      eyebrow?: string
      confirmText?: string
      cancelText?: string
      tone?: HgtConfirmTone
      showCancel?: boolean
    }) {
      return new Promise<boolean>((resolve) => {
        // 确认框优先，避免 toast 叠在弹层上
        this.hideToast()
        this.hideLoading()
        if (this.confirmResolver) {
          this.confirmResolver(false)
          this.confirmResolver = null
        }
        const tone = options.tone || 'default'
        const defaultEyebrow = tone === 'danger' ? 'DANGER' : tone === 'warning' ? 'RISK' : 'CONFIRM'
        this.confirmResolver = resolve
        this.confirm = {
          show: true,
          eyebrow: options.eyebrow || defaultEyebrow,
          title: options.title,
          description: options.description || '',
          confirmText: options.confirmText || '确认',
          cancelText: options.cancelText || '取消',
          tone,
          showCancel: options.showCancel !== false,
          seq: this.confirm.seq + 1,
        }
      })
    },
    settleConfirm(confirm: boolean) {
      if (!this.confirmResolver && !this.confirm.show)
        return
      const resolver = this.confirmResolver
      this.confirmResolver = null
      this.confirm = { ...emptyConfirm, seq: this.confirm.seq }
      resolver?.(confirm)
    },
  },
})
