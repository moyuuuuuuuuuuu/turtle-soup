<script setup lang="ts">
import { applyStoredTheme } from '@/utils/theme'

onLaunch(() => applyStoredTheme())
</script>

<style lang="scss">
@use '@wot-ui/ui/styles/theme/index.scss' as *;

/* ========== Design Tokens · 图2色板 + 宣纸底 #f8f8f7 ========== */
:root,
page {
  /* brand · 竹青主色 */
  --hgt-brand: #789262;
  --hgt-brand-deep: #5f754c;
  --hgt-brand-soft: rgba(120, 146, 98, 0.12);
  --hgt-on-brand: #f8f8f7;
  --hgt-ink: #2a2a28;

  /* accents from 图2 */
  --hgt-accent: #9e5356; /* 明茶褐 · 点缀 */
  --hgt-accent-soft: rgba(158, 83, 86, 0.12);
  --hgt-moon: #d6ecf0; /* 月白 · 浅色 */
  --hgt-ink-gray: #758a99; /* 墨灰 · 深色辅助 */
  --hgt-gold: #f0c239; /* 绀色样本 · 辅色 */
  --hgt-gold-soft: rgba(240, 194, 57, 0.16);

  /* surfaces */
  --hgt-bg: #f8f8f7;
  --hgt-bg-deep: #f1f0ed;
  --hgt-card: #ffffff;
  --hgt-card-2: #f3f2ef;
  --hgt-paper: #f4f2ec;
  --hgt-paper-ink: #2a2420;

  /* text · 墨 */
  --hgt-text: #2c2b28;
  --hgt-text-2: #5c5a56;
  --hgt-text-3: #8a8780;

  /* lines */
  --hgt-border: #e2e0da;
  --hgt-border-soft: #eceae4;

  /* status（贴近图2，难度用） */
  --hgt-success: #789262;
  --hgt-success-text: #5f754c;
  --hgt-warning: #c49a3d;
  --hgt-danger: #9e5356;
  --hgt-info: #758a99;
  --hgt-overlay: rgba(42, 42, 40, 0.38);

  /* radius */
  --hgt-radius-xs: 4px;
  --hgt-radius-sm: 8px;
  --hgt-radius-md: 12px;
  --hgt-radius-lg: 16px;
  --hgt-radius-full: 999px;

  /* shadow */
  --hgt-shadow-sm: 0 2px 8px rgba(42, 42, 40, 0.05);
  --hgt-shadow-md: 0 4px 16px rgba(42, 42, 40, 0.07);
  --hgt-shadow-lg: 0 8px 32px rgba(42, 42, 40, 0.09);
  --hgt-shadow-float: 0 16px 48px rgba(42, 42, 40, 0.12);

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
  --hgt-font-en: "Cormorant Garamond", "Times New Roman", Georgia, serif;
  --hgt-font-mono: "JetBrains Mono", "SF Mono", Consolas, monospace;

  /* shell */
  --hgt-header-h: 64px;
  --hgt-tabbar-h: 64px;
  --hgt-content-max: 1280px;

  /* 水墨氛围：满幅红日远山（图1） */
  --hgt-atmo-img: url('/static/hgt/ink/hero_ink_landscape.png');
  --hgt-atmo-filter: none;
  --hgt-atmo-veil:
    linear-gradient(90deg,
      rgba(248, 248, 247, 0.92) 0%,
      rgba(248, 248, 247, 0.72) 28%,
      rgba(248, 248, 247, 0.28) 48%,
      rgba(248, 248, 247, 0.06) 72%,
      rgba(248, 248, 247, 0) 100%);

  /* legacy aliases */
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

/* ========== 夜墨主题 ========== */
:root.hgt-dark-theme,
page.hgt-dark-theme {
  --hgt-brand: #8eaa74;
  --hgt-brand-deep: #789262;
  --hgt-brand-soft: rgba(142, 170, 116, 0.16);
  --hgt-on-brand: #1a1a18;
  --hgt-ink: #e8e6e0;

  --hgt-accent: #c48a8d;
  --hgt-accent-soft: rgba(196, 138, 141, 0.16);
  --hgt-moon: #3a4a52;
  --hgt-ink-gray: #8fa3b0;
  --hgt-gold: #d4a84a;
  --hgt-gold-soft: rgba(212, 168, 74, 0.14);

  --hgt-bg: #1a1a18;
  --hgt-bg-deep: #121211;
  --hgt-card: #222220;
  --hgt-card-2: #2a2a27;
  --hgt-paper: #2c2a26;
  --hgt-paper-ink: #e8e6e0;

  --hgt-text: #e8e6e0;
  --hgt-text-2: #a8a59e;
  --hgt-text-3: #7a776f;

  --hgt-border: #3a3935;
  --hgt-border-soft: #4a4843;

  --hgt-success: #8eaa74;
  --hgt-success-text: #a8c48e;
  --hgt-warning: #d4a84a;
  --hgt-danger: #c48a8d;
  --hgt-info: #8fa3b0;
  --hgt-overlay: rgba(10, 10, 9, 0.6);

  --hgt-shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.28);
  --hgt-shadow-md: 0 4px 16px rgba(0, 0, 0, 0.34);
  --hgt-shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.4);
  --hgt-shadow-float: 0 16px 48px rgba(0, 0, 0, 0.48);

  --hgt-atmo-filter: saturate(0.8) brightness(0.7) contrast(1.05);
  --hgt-atmo-veil:
    linear-gradient(90deg,
      rgba(18, 18, 17, 0.78) 0%,
      rgba(18, 18, 17, 0.55) 40%,
      rgba(18, 18, 17, 0.28) 72%,
      rgba(18, 18, 17, 0.12) 100%),
    linear-gradient(180deg,
      rgba(18, 18, 17, 0.08) 0%,
      rgba(18, 18, 17, 0.2) 48%,
      rgba(18, 18, 17, 0.45) 100%);

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

:root.hgt-light-theme,
page.hgt-light-theme {
  color-scheme: light;
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
  letter-spacing: 0.04em;
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

button::after {
  border: 0;
}
</style>
