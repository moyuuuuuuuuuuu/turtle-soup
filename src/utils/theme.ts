export type HgtTheme = 'light' | 'dark'

export function storedTheme(): HgtTheme {
  return uni.getStorageSync('hgt_theme') === 'light' ? 'light' : 'dark'
}

export function applyRootTheme(theme: HgtTheme): void {
  // #ifdef H5
  document.documentElement.classList.toggle('hgt-light-theme', theme === 'light')
  document.documentElement.style.colorScheme = theme
  // #endif
}

export function applyStoredTheme(): HgtTheme {
  const theme = storedTheme()
  applyRootTheme(theme)
  return theme
}
