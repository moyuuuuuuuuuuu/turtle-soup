<script setup lang="ts">
import { applyStoredTheme } from '@/utils/theme'

onLaunch(() => applyStoredTheme())
</script>

<style lang="scss">
@use '@wot-ui/ui/styles/theme/index.scss' as *;

/* ========== 深海 Design Tokens · 70% 极简 + 20% 深海 + 10% 推理 ========== */
:root,
page {
  /* brand · 青绿主色 */
  --hgt-brand: #5ec4b8;
  --hgt-brand-deep: #3a9e94;
  --hgt-brand-soft: rgba(94, 196, 184, 0.14);
  --hgt-on-brand: #041418;
  --hgt-ink: #e8f4f2;

  /* accents */
  --hgt-accent: #d05a52; /* 朱红 · 针尖强调 */
  --hgt-accent-soft: rgba(208, 90, 82, 0.14);
  --hgt-moon: #7a9eb0; /* 灰蓝 · 无关 */
  --hgt-ink-gray: #6b8a96;
  --hgt-gold: #c9a46a; /* 暖金 · 中等深度 / 进行中 */
  --hgt-gold-soft: rgba(201, 164, 106, 0.16);

  /* surfaces · 深度层级 */
  --hgt-bg: #061a20;
  --hgt-bg-deep: #041418;
  --hgt-card: rgba(15, 53, 57, 0.38);
  --hgt-card-2: rgba(22, 62, 66, 0.28);
  --hgt-paper: rgba(15, 53, 57, 0.22); /* 极弱容器，非纸张 */
  --hgt-paper-ink: #e8f4f2;

  /* text 亮度分级 */
  --hgt-text: rgba(232, 244, 242, 0.92);
  --hgt-text-2: rgba(232, 244, 242, 0.62);
  --hgt-text-3: rgba(232, 244, 242, 0.42);
  --hgt-text-bright: rgba(244, 252, 250, 0.96); /* 汤面 */

  /* lines */
  --hgt-border: rgba(117, 220, 211, 0.12);
  --hgt-border-soft: rgba(117, 220, 211, 0.08);
  --hgt-border-hover: rgba(117, 220, 211, 0.24);

  /* status */
  --hgt-success: #5ec4b8;
  --hgt-success-text: #7fd4ca;
  --hgt-warning: #c9a46a;
  --hgt-danger: #d05a52;
  --hgt-info: #7a9eb0;
  --hgt-overlay: rgba(3, 14, 18, 0.55);

  /* depth tones */
  --hgt-depth-bg-1: rgba(18, 55, 60, 0.3);
  --hgt-depth-bg-2: rgba(12, 46, 52, 0.22);
  --hgt-depth-bg-3: rgba(24, 65, 67, 0.18);

  /* radius · 收敛大圆角 */
  --hgt-radius-xs: 3px;
  --hgt-radius-sm: 6px;
  --hgt-radius-md: 8px;
  --hgt-radius-lg: 10px;
  --hgt-radius-full: 999px;

  /* shadow · 几乎不用 */
  --hgt-shadow-sm: none;
  --hgt-shadow-md: none;
  --hgt-shadow-lg: none;
  --hgt-shadow-float: none;

  /* motion */
  --hgt-ease-out: ease-out;
  --hgt-ease-in-out: ease-in-out;
  --hgt-ease-emphasized: cubic-bezier(0.22, 1, 0.36, 1);
  --hgt-dur-fast: 150ms;
  --hgt-dur-base: 250ms;
  --hgt-dur-slow: 350ms;
  --hgt-dur-reveal: 500ms;

  /* type · 故事用衬线，系统信息用无衬线 */
  --hgt-font-display: "Noto Serif SC", "Source Han Serif SC", "Songti SC", "SimSun", Georgia, serif;
  --hgt-font-body: "Noto Sans SC", "Source Han Sans SC", "PingFang SC", "Microsoft YaHei", sans-serif;
  --hgt-font-en: "Cormorant Garamond", "Times New Roman", Georgia, serif;
  --hgt-font-mono: "JetBrains Mono", "SF Mono", Consolas, monospace;

  /* shell */
  --hgt-header-h: 56px;
  --hgt-tabbar-h: 64px;
  --hgt-content-max: 1360px;
  --hgt-reading-max: 680px;

  /* 深海氛围背景：由页面内联 style / BOS 远程地址提供，不再写死本地 static */
  --hgt-atmo-img: none;
  --hgt-atmo-filter: none;
  --hgt-atmo-veil:
    linear-gradient(90deg,
      rgba(4, 20, 24, 0.88) 0%,
      rgba(4, 20, 24, 0.62) 30%,
      rgba(4, 20, 24, 0.22) 55%,
      rgba(4, 20, 24, 0.06) 78%,
      transparent 100%),
    linear-gradient(180deg,
      rgba(4, 20, 24, 0.08) 0%,
      transparent 40%,
      rgba(6, 26, 32, 0.55) 78%,
      #061a20 100%);

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

/* ========== 更深一层：真相浮现 / 可选浅色对照 ========== */
:root.hgt-dark-theme,
page.hgt-dark-theme {
  --hgt-bg: #041418;
  --hgt-bg-deep: #030f13;
  --hgt-card: rgba(10, 40, 45, 0.42);
  --hgt-card-2: rgba(16, 48, 54, 0.32);
  --hgt-border: rgba(117, 220, 211, 0.1);
  --hgt-text: rgba(220, 240, 236, 0.9);
  --hgt-brand: #6fd0c4;
  color-scheme: dark;
}

:root.hgt-light-theme,
page.hgt-light-theme {
  color-scheme: dark;
}

/* 真相揭晓：全站唯一一次明显水光重逢 */
:root.hgt-truth-theme,
page.hgt-truth-theme {
  --hgt-bg: #0a2a30;
  --hgt-brand: #7fd9cd;
  --hgt-atmo-veil:
    linear-gradient(180deg,
      rgba(127, 217, 205, 0.12) 0%,
      rgba(10, 42, 48, 0.2) 40%,
      rgba(6, 26, 32, 0.55) 100%);
}

/* uni 默认 button 白底/禁用白底矫正 */
page button,
.hgt-app button,
.game-page button {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
  border: none;
  background: transparent;
  color: inherit;
  font-size: inherit;
  line-height: inherit;
}

page button::after,
.hgt-app button::after,
.game-page button::after {
  border: 0;
}

button[disabled],
button[loading],
.game-page button[disabled],
.game-page button[loading] {
  background: transparent !important;
  color: var(--hgt-text-3) !important;
}

.send-btn,
.btn-primary,
.btn-brand,
.primary,
.game-page .send-btn {
  background: var(--hgt-brand) !important;
  color: var(--hgt-on-brand) !important;
}

.send-btn[disabled],
.btn-primary[disabled],
.btn-brand[disabled],
.primary[disabled],
.send-btn[loading],
.btn-primary[loading] {
  background: var(--hgt-brand) !important;
  color: var(--hgt-on-brand) !important;
  opacity: 0.45;
}

.secondary-btn[disabled],
.hint-float[disabled],
.abandon-weak[disabled],
.ghost[disabled],
.btn-ghost[disabled] {
  background: transparent !important;
  color: var(--hgt-text-3) !important;
}

/* 推理页输入区次级操作 */
.game-page .secondary-btn {
  background: transparent !important;
  color: var(--hgt-text-2) !important;
}

/* 推理页沉浸布局（全局，覆盖小程序 slot 作用域）：
   main 定高，.game-page 绝对占满顶部导航（或 header）与 tabbar 之间 */
/* #ifdef H5 */
@media (max-width: 767px) {
/* #endif */
/* #ifndef H5 */
@media all {
/* #endif */
  .hgt-app.is-immersive-game .hgt-main {
    position: relative;
    min-height: 0;
    height: var(--hgt-viewport-h, 100vh);
    height: var(--hgt-viewport-h, 100dvh);
    box-sizing: border-box;
    overflow: hidden;
  }

  .hgt-app.is-immersive-game .hgt-main .game-page,
  .hgt-app.is-immersive-game .hgt-main .game-load-state {
    position: absolute;
    top: var(--hgt-mobile-header-offset, 56px);
    right: 0;
    bottom: calc(var(--hgt-tabbar-h, 64px) + var(--hgt-safe-bottom, env(safe-area-inset-bottom, 0px)));
    left: 0;
    height: auto !important;
    min-height: 0;
    max-height: none;
    overflow: hidden;
  }

  .hgt-app.is-immersive-game .game-page .composer {
    padding-bottom: 10px;
  }

  /* 原生默认导航（抖音等）：offset 已为 0，top 对齐视口顶；高度/底边用系统 px */
  .hgt-app.mp-default-nav.is-immersive-game .hgt-main {
    height: var(--hgt-viewport-h, 100vh);
    height: var(--hgt-viewport-h, 100dvh);
  }
  .hgt-app.mp-default-nav.is-immersive-game .hgt-main .game-page,
  .hgt-app.mp-default-nav.is-immersive-game .hgt-main .game-load-state {
    top: 0 !important;
    bottom: calc(var(--hgt-tabbar-h, 64px) + var(--hgt-safe-bottom, env(safe-area-inset-bottom, 0px))) !important;
  }
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

/* 深海主题下的 loading / button loading，避免白底 */
wd-loading,
.hgt-loading,
.uni-btn-loading,
button[loading] {
  color: var(--hgt-text-2) !important;
}

wd-loading .wd-loading__ring,
.wd-loading .wd-loading__ring,
.hgt-loading {
  background: transparent !important;
}

button[loading] {
  background: var(--hgt-brand) !important;
  color: var(--hgt-on-brand) !important;
}

button[loading] .uni-btn-loading,
button[loading] .uni-btn-loading-icon,
.uni-button-loading,
.uni-button-loading-icon {
  color: var(--hgt-on-brand) !important;
}

button[loading]::after,
.btn-primary[loading]::after,
.send-btn[loading]::after {
  border: 0 !important;
  background: transparent !important;
}

.text-btn[loading],
.ghost[loading],
.btn-ghost[loading] {
  background: transparent !important;
  color: var(--hgt-text-2) !important;
}

.text-btn[loading] .uni-btn-loading,
.ghost[loading] .uni-btn-loading,
.btn-ghost[loading] .uni-btn-loading {
  color: var(--hgt-text-2) !important;
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
}
</style>
