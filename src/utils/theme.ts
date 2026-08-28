export type HgtTheme = 'light' | 'dark'

export function timeTheme(date = new Date()): HgtTheme {
  const hour = date.getHours()
  return hour >= 6 && hour < 18 ? 'light' : 'dark'
}

export function storedTheme(): HgtTheme {
  if (uni.getStorageSync('hgt_theme_manual') !== true)
    return timeTheme()
  return uni.getStorageSync('hgt_theme') === 'light' ? 'light' : 'dark'
}

export function applyRootTheme(theme: HgtTheme): void {
  // #ifdef H5
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
