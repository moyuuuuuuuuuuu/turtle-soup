<script setup lang="ts">
import { applyStoredTheme } from '@/utils/theme'

onLaunch(() => applyStoredTheme())
</script>

<style lang="scss">
@use '@wot-ui/ui/styles/theme/index.scss' as *;

/* ========== Design Tokens · 暗色默认 ========== */
:root,
page {
  /* brand */
  --hgt-brand: #5bc8bd;
  --hgt-brand-deep: #3a9a92;
  --hgt-brand-soft: rgba(91, 200, 189, 0.14);
  --hgt-on-brand: #062a28;

  /* surfaces */
  --hgt-bg: #0c2027;
  --hgt-bg-deep: #071418;
  --hgt-card: #0f2a2d;
  --hgt-card-2: #16383c;
  --hgt-paper: #d0dcb6;
  --hgt-paper-ink: #2a2a24;

  /* text */
  --hgt-text: #e5e8e3;
  --hgt-text-2: #999d9b;
  --hgt-text-3: #6b7574;

  /* lines */
  --hgt-border: #1e3a3a;
  --hgt-border-soft: #2e5155;

  /* status */
  --hgt-success: #5e8787;
  --hgt-success-text: #7dcccc;
  --hgt-warning: #c49a55;
  --hgt-danger: #c94a55;
  --hgt-info: #3882f6;
  --hgt-overlay: rgba(4, 12, 14, 0.72);

  /* radius */
  --hgt-radius-xs: 4px;
  --hgt-radius-sm: 8px;
  --hgt-radius-md: 12px;
  --hgt-radius-lg: 16px;
  --hgt-radius-full: 999px;

  /* shadow */
  --hgt-shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
  --hgt-shadow-md: 0 4px 16px rgba(0, 0, 0, 0.12);
  --hgt-shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.18);
  --hgt-shadow-float: 0 16px 48px rgba(0, 0, 0, 0.28);

  /* motion */
  --hgt-ease-out: ease-out;
  --hgt-ease-in-out: ease-in-out;
  --hgt-ease-emphasized: cubic-bezier(0.22, 1, 0.36, 1);
  --hgt-dur-fast: 150ms;
  --hgt-dur-base: 250ms;
  --hgt-dur-slow: 350ms;
  --hgt-dur-reveal: 500ms;

  /* type */
  --hgt-font-display: "Source Han Serif SC", "Noto Serif SC", "Songti SC", "SimSun", Georgia, serif;
  --hgt-font-body: "Source Han Sans SC", "Noto Sans SC", "PingFang SC", "Microsoft YaHei", sans-serif;
  --hgt-font-en: "Inter", "Roboto", system-ui, sans-serif;
  --hgt-font-mono: "JetBrains Mono", "SF Mono", Consolas, monospace;

  /* shell */
  --hgt-header-h: 64px;
  --hgt-tabbar-h: 64px;
  --hgt-content-max: 1280px;

  /* legacy aliases used by existing pages */
  --app-background: var(--hgt-bg);
  --app-foreground: var(--hgt-text);
  --app-card: var(--hgt-card);
  --background: var(--hgt-bg);
  --foreground: var(--hgt-text);
  --card: var(--hgt-card);
  --secondary: var(--hgt-card-2);
  --muted-foreground: var(--hgt-text-2);
  --accent: var(--hgt-brand);
  --border: var(--hgt-border);
}

/* ========== 浅色主题 ========== */
:root.hgt-light-theme {
  --hgt-brand: #2e9a90;
  --hgt-brand-deep: #1f7a72;
  --hgt-brand-soft: rgba(46, 154, 144, 0.12);
  --hgt-on-brand: #ffffff;

  --hgt-bg: #f4f6f3;
  --hgt-bg-deep: #ffffff;
  --hgt-card: #ffffff;
  --hgt-card-2: #eef2ef;
  --hgt-paper: #e8e4d4;
  --hgt-paper-ink: #2a2a24;

  --hgt-text: #1a2b2c;
  --hgt-text-2: #5c6b6a;
  --hgt-text-3: #8a9897;

  --hgt-border: #d5dedc;
  --hgt-border-soft: #e5e7eb;

  --hgt-success: #3d7a6a;
  --hgt-success-text: #2f6b5c;
  --hgt-warning: #a67c3d;
  --hgt-danger: #b33d48;
  --hgt-info: #2f6fd0;
  --hgt-overlay: rgba(20, 32, 34, 0.45);

  --hgt-shadow-sm: 0 2px 8px rgba(12, 32, 39, 0.06);
  --hgt-shadow-md: 0 4px 16px rgba(12, 32, 39, 0.08);
  --hgt-shadow-lg: 0 8px 32px rgba(12, 32, 39, 0.1);
  --hgt-shadow-float: 0 16px 48px rgba(12, 32, 39, 0.14);

  --app-background: var(--hgt-bg);
  --app-foreground: var(--hgt-text);
  --app-card: var(--hgt-card);
  --background: var(--hgt-bg);
  --foreground: var(--hgt-text);
  --card: var(--hgt-card);
  --secondary: var(--hgt-card-2);
  --muted-foreground: var(--hgt-text-2);
  --accent: var(--hgt-brand);
  --border: var(--hgt-border);
}

html,
body,
#app,
page {
  min-height: 100%;
  background: var(--hgt-bg);
  color: var(--hgt-text);
  font-family: var(--hgt-font-body);
}

::view-transition-old(root),
::view-transition-new(root) {
  animation: none;
  mix-blend-mode: normal;
}
::view-transition-old(root) {
  z-index: 1;
}
::view-transition-new(root) {
  z-index: 9999;
  animation: hgt-theme-reveal var(--hgt-dur-reveal) var(--hgt-ease-emphasized) both;
}
@keyframes hgt-theme-reveal {
  from {
    clip-path: circle(0 at var(--hgt-theme-x) var(--hgt-theme-y));
  }
  to {
    clip-path: circle(var(--hgt-theme-radius) at var(--hgt-theme-x) var(--hgt-theme-y));
  }
}
@media (prefers-reduced-motion: reduce) {
  ::view-transition-old(root),
  ::view-transition-new(root) {
    animation: none;
  }
}

.hgt-display {
  font-family: var(--hgt-font-display);
  font-weight: 600;
}
.hgt-body {
  font-family: var(--hgt-font-body);
}
.hgt-en {
  font-family: var(--hgt-font-en);
}
.hgt-mono {
  font-family: var(--hgt-font-mono);
}

::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}
::-webkit-scrollbar-track {
  background: transparent;
}
::-webkit-scrollbar-thumb {
  border-radius: 3px;
  background: var(--hgt-border-soft);
}
::-webkit-scrollbar-thumb:hover {
  background: var(--hgt-text-3);
}

.page-wraper {
  min-height: calc(100vh - var(--window-top));
  box-sizing: border-box;
  background: var(--hgt-bg);
}

/* shared button reset used across pages */
button::after {
  border: 0;
}
</style>
