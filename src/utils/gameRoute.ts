let returning: Promise<void> | undefined

/** 复用栈内的当前游戏页，避免每次返回房间都创建新的 Socket 订阅页面。 */
export function returnToGame(id: string): Promise<void> {
  if (returning)
    return returning
  const pages = getCurrentPages()
  let target = -1
  for (let index = pages.length - 1; index >= 0; index--) {
    const page = pages[index]
    const options = (page as unknown as { options?: { id?: string } }).options
    if (page.route === 'pages/game/index' && options?.id === id) {
      target = index
      break
    }
  }
  if (target === pages.length - 1 && target >= 0)
    return Promise.resolve()
  returning = new Promise<void>((resolve, reject) => {
    const callbacks = {
      success: () => resolve(),
      fail: () => reject(new Error('返回房间失败，请重试')),
    }
    if (target >= 0)
      uni.navigateBack({ delta: pages.length - 1 - target, ...callbacks })
    else
      uni.redirectTo({ url: `/pages/game/index?id=${encodeURIComponent(id)}`, ...callbacks })
  }).finally(() => { returning = undefined })
  return returning
}
