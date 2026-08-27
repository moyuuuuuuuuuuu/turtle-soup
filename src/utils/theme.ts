export type HgtTheme = 'light' | 'dark'

export function storedTheme(): HgtTheme {
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
