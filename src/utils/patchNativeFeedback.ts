import { hgtConfirm, hgtLoading, hgtLoadingHide, hgtToast, hgtToastHide, rememberNativeFeedbackApi } from './feedback'

let patched = false

function toastToneFromIcon(icon?: string) {
  if (icon === 'success')
    return 'success' as const
  if (icon === 'error')
    return 'error' as const
  if (icon === 'loading')
    return 'loading' as const
  return 'info' as const
}

function confirmToneFromTitle(title?: string) {
  const text = title || ''
  if (text.includes('风险') || text.includes('警告') || text.includes('危险'))
    return 'warning' as const
  if (text.includes('删除') || text.includes('退出') || text.includes('放弃'))
    return 'danger' as const
  return 'default' as const
}

function safeCallback(fn?: (res: any) => void, res?: any) {
  try {
    fn?.(res)
  }
  catch {}
}

/**
 * 将原生 uni.showToast / showModal / showLoading 接管为深海自定义弹层。
 * 业务代码可继续写 uni.showToast，视觉统一走 HgtFeedbackHost。
 */
export function patchNativeFeedback() {
  if (patched)
    return
  patched = true
  rememberNativeFeedbackApi()

  uni.showToast = ((options: UniApp.ShowToastOptions = {}) => {
    const title = options.title || ''
    hgtToast(title, {
      tone: toastToneFromIcon(options.icon),
      duration: typeof options.duration === 'number' ? options.duration : 2200,
    })
    safeCallback(options.success, { errMsg: 'showToast:ok' })
    safeCallback(options.complete, { errMsg: 'showToast:ok' })
  }) as typeof uni.showToast

  uni.hideToast = ((options?: { success?: (res: any) => void, complete?: (res: any) => void }) => {
    hgtToastHide()
    safeCallback(options?.success, { errMsg: 'hideToast:ok' })
    safeCallback(options?.complete, { errMsg: 'hideToast:ok' })
  }) as typeof uni.hideToast

  uni.showLoading = ((options: UniApp.ShowLoadingOptions = {}) => {
    hgtLoading(options.title || '加载中')
    safeCallback(options.success, { errMsg: 'showLoading:ok' })
    safeCallback(options.complete, { errMsg: 'showLoading:ok' })
  }) as typeof uni.showLoading

  uni.hideLoading = ((options?: { success?: (res: any) => void, complete?: (res: any) => void }) => {
    hgtLoadingHide()
    safeCallback(options?.success, { errMsg: 'hideLoading:ok' })
    safeCallback(options?.complete, { errMsg: 'hideLoading:ok' })
  }) as typeof uni.hideLoading

  uni.showModal = ((options: UniApp.ShowModalOptions) => {
    const showCancel = options.showCancel !== false
    hgtConfirm({
      title: options.title || '提示',
      description: options.content || '',
      confirmText: options.confirmText || '确认',
      cancelText: options.cancelText || '取消',
      showCancel,
      tone: confirmToneFromTitle(options.title),
    }).then((confirm) => {
      const res = {
        confirm,
        cancel: !confirm,
        content: '',
        errMsg: 'showModal:ok',
      }
      safeCallback(options.success, res)
      safeCallback(options.complete, res)
    }).catch(() => {
      const res = { errMsg: 'showModal:fail' }
      safeCallback(options.fail, res)
      safeCallback(options.complete, res)
    })
  }) as typeof uni.showModal
}
