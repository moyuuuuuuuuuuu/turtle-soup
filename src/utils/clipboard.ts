/** uni-app 提供 H5 Clipboard API 回退；由调用方在当前弹层中显示结果。 */
export function copyText(text: string): Promise<void> {
  return new Promise((resolve, reject) => {
    uni.setClipboardData({
      data: text,
      showToast: false,
      success: () => resolve(),
      fail: () => reject(new Error('clipboard.failed')),
    })
  })
}
