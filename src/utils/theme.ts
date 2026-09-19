export type HgtTheme = 'light' | 'dark'

/** 水墨风默认宣纸浅色；深色为用户手动切换的夜墨主题 */
export function timeTheme(): HgtTheme {
  return 'light'
}

export function storedTheme(): HgtTheme {
  if (uni.getStorageSync('hgt_theme_manual') !== true)
    return timeTheme()
  return uni.getStorageSync('hgt_theme') === 'dark' ? 'dark' : 'light'
}

export function applyRootTheme(theme: HgtTheme): void {
  // #ifdef H5
  // 根样式默认宣纸浅色；仅深色时挂夜墨 class
  document.documentElement.classList.toggle('hgt-dark-theme', theme === 'dark')
  document.documentElement.classList.toggle('hgt-light-theme', theme === 'light')
  document.documentElement.style.colorScheme = theme
  const favicon = document.querySelector<HTMLLinkElement>('#app-favicon')
  if (favicon)
    favicon.href = `/static/brand/favicon-${theme}.png`
  // #endif
}

export function applyStoredTheme(): HgtTheme {
  const theme = storedTheme()
  applyRootTheme(theme)
  return theme
}
