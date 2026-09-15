/**
 * 获取当前页面路径
 * @returns 当前页面路径
 */
export function getCurrentPath() {
  const pages = getCurrentPages()
  const currentPage = pages[pages.length - 1]
  return currentPage.route || ''
}

/**
 * 千分位格式化数字。
 * 抖音/头条真机 JS 引擎可能没有 Intl，不能直接依赖 Intl.NumberFormat。
 */
export function formatCount(value: number) {
  const n = Math.trunc(Number(value) || 0)
  const sign = n < 0 ? '-' : ''
  return sign + String(Math.abs(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ',')
}
