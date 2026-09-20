<script setup lang="ts">
import { roomApi } from '@/api/turtle'
import { useAnimatedTheme } from '@/composables/useAnimatedTheme'
import { useGameSocket } from '@/composables/useGameSocket'
import { usePlayerStore } from '@/store/playerStore'
import { resolveAssetUrl } from '@/utils/assetUrl'
import { returnToGame } from '@/utils/gameRoute'
import { resolveCapsuleRightPadding, resolveShellChromeMetrics } from '@/utils/navSafeArea'
import { supportsPublicRooms } from '@/utils/platform'

const router = useRouter()
const route = useRoute()
const player = usePlayerStore()
const socket = useGameSocket()
const { light, overlay, toggleTheme } = useAnimatedTheme()
const mobileHeaderStyle = ref<Record<string, string>>({})
/** 小程序：自定义导航需预留状态栏+胶囊；原生导航视口已扣除，offset=0 */
const topNav = resolveShellChromeMetrics()
const mobileHeaderOffset = ref(`${topNav.offset}px`)
/** 原生默认导航时隐藏自定义顶栏，避免与微信/抖音导航叠一层 */
const hideMobileChrome = ref(topNav.defaultNav)
/** 可视内容区高度 / 底部安全区：用系统 px，避免 100dvh、env() 在小程序端失效 */
const viewportHeightPx = ref(topNav.viewportHeight)
const safeBottomPx = ref(topNav.safeBottom)

const shellChromeStyle = computed(() => {
  const style: Record<string, string> = {
    '--hgt-mobile-header-offset': mobileHeaderOffset.value,
  }
  // H5 keeps the CSS 100dvh fallback so browser chrome changes remain dynamic.
  // #ifndef H5
  if (viewportHeightPx.value > 0)
    style['--hgt-viewport-h'] = `${viewportHeightPx.value}px`
  style['--hgt-safe-bottom'] = `${safeBottomPx.value}px`
  // #endif
  return style
})

function applyMobileNavChrome() {
  const metrics = resolveShellChromeMetrics()
  hideMobileChrome.value = metrics.defaultNav
  mobileHeaderOffset.value = `${metrics.offset}px`
  viewportHeightPx.value = metrics.viewportHeight
  safeBottomPx.value = metrics.safeBottom
  if (metrics.defaultNav || metrics.offset <= 0) {
    mobileHeaderStyle.value = {}
    return
  }
  mobileHeaderStyle.value = {
    height: `${metrics.offset}px`,
    paddingTop: `${metrics.statusBarHeight}px`,
    paddingRight: `${resolveCapsuleRightPadding()}px`,
  }
}

applyMobileNavChrome()

const activeRoom = computed(() => {
  const room = socket.roomSnapshot.value
  return room && ['waiting', 'playing'].includes(room.status) && room.game_id ? room : null
})
const showRoomReturn = computed(() => Boolean(player.user && activeRoom.value) && route.name !== 'game')

/** 推理页保留站点 Logo 导航；仅隐藏房间返回浮标与背景粒子，避免抢焦点 */
const immersiveGame = computed(() => {
  const name = String(route.name || '')
  const path = String(route.path || route.fullPath || '')
  return name === 'game' || path.includes('/pages/game/')
})

const navItems = [
  { name: 'home', path: '/pages/index/index', label: '首页', icon: 'H' },
  { name: 'questions', path: '/pages/questions/index', label: '题库', icon: 'Q' },
  { name: 'game', path: '/pages/game/index', label: '推理', icon: 'P', hideDesktop: true },
  ...(supportsPublicRooms
    ? [{ name: 'public-rooms', path: '/pages/public-rooms/index', label: '多人', icon: 'M', authenticated: true }]
    : []),
  { name: 'history', path: '/pages/history/index', label: '我的推理', icon: 'R' },
  { name: 'player-account', path: '/pages/account/index', label: '我的', icon: 'U', mobileOnly: true },
  // #ifdef H5
  { name: 'donate', path: '/pages/donate/index', label: '捐赠', icon: 'D' },
  { name: 'friends', path: '/pages/friends/index', label: '友链', icon: 'F' },
  // #endif
] as const

const desktopNav = computed(() =>
  navItems.filter((item) => {
    if ('hideDesktop' in item && item.hideDesktop)
      return false
    if ('mobileOnly' in item && item.mobileOnly)
      return false
    if ('authenticated' in item && item.authenticated && !player.user)
      return false
    return true
  }),
)

/** 手机底部：首页 / 题库 / 推理 / [多人] / 我的 */
const mobileNav = computed(() =>
  [
    { name: 'home', path: '/pages/index/index', label: '首页', icon: '⌂' },
    { name: 'questions', path: '/pages/questions/index', label: '题库', icon: '☰' },
    { name: 'history', path: '/pages/history/index', label: '推理', icon: '◈' },
    ...(supportsPublicRooms
      ? [{ name: 'public-rooms', path: '/pages/public-rooms/index', label: '多人', icon: '◎' }]
      : []),
    { name: 'player-account', path: '/pages/account/index', label: '我的', icon: '☺' },
  ],
)

const logoSrc = computed(() =>
  resolveAssetUrl(light.value ? '/static/brand/logo-mark-light.png' : '/static/brand/logo-mark-dark.png'),
)

async function go(item: { name: string, path: string }) {
  if (!player.ready)
    await player.restore()
  if (!player.user && ['public-rooms', 'history'].includes(item.name)) {
    router.push({ name: 'player-login', query: { redirect: item.path } })
    return
  }
  if (item.name === 'home') {
    uni.switchTab({ url: item.path })
    return
  }
  if (item.name === 'history') {
    router.push({ name: 'history' })
    return
  }
  router.push(item.path)
}

async function recoverActiveRoom() {
  if (!player.user || !supportsPublicRooms || activeRoom.value)
    return
  try {
    const rooms = await roomApi.mine()
    const room = rooms.find(item => ['waiting', 'playing'].includes(item.status) && item.game_id)
    if (room)
      await socket.roomJoin(room.id)
  }
  catch {}
}

async function returnToRoom() {
  if (!player.user || !activeRoom.value?.game_id)
    return
  try {
    await returnToGame(activeRoom.value.game_id)
  }
  catch (error) {
    uni.showToast({ title: (error as Error).message, icon: 'none' })
  }
}

function openSearch() {
  router.push({ name: 'questions' })
}

onMounted(async () => {
  // 挂载后再测一次：部分基础库 onLaunch 阶段 getMenuButton 尚不可用
  applyMobileNavChrome()
  uni.onWindowResize(applyMobileNavChrome)
  if (!player.ready)
    await player.restore()
  await recoverActiveRoom()
})

onUnmounted(() => {
  uni.offWindowResize(applyMobileNavChrome)
})
</script>

<template>
  <view
    class="hgt-app"
    :class="{
      'hgt-light': light,
      'is-immersive-game': immersiveGame,
      'mp-default-nav': hideMobileChrome,
    }"
    :style="shellChromeStyle"
  >
    <HgtThemeTransition v-bind="overlay" />
    <HgtParticleBackground />
    <HgtFlashlight :light="light" />

    <!-- PC / 平板 顶栏：推理页保留 Logo 导航 -->
    <header class="hgt-topbar">
      <view class="hgt-topbar-inner">
        <view class="hgt-topbar-brand" @click="go({ name: 'home', path: '/pages/index/index' })">
          <image class="hgt-topbar-logo" :src="logoSrc" mode="aspectFit" />
          <view class="hgt-topbar-brand-copy">
            <view class="hgt-topbar-title-row">
              <text class="hgt-en hgt-topbar-en">
                MOYUU
              </text>
              <text class="hgt-display hgt-topbar-title">
                海龟汤
              </text>
            </view>
            <text class="hgt-topbar-sub">
              谜题沉在水下，真相等待浮现。
            </text>
          </view>
        </view>

        <nav class="hgt-topbar-nav">
          <view
            v-for="item in desktopNav"
            :key="item.name"
            class="hgt-topbar-link"
            :class="{ active: route.name === item.name }"
            @click="go(item)"
          >
            {{ item.label }}
          </view>
        </nav>

        <view class="hgt-topbar-actions">
          <button class="hgt-icon-btn" aria-label="搜索" @click="openSearch">
            <view class="hgt-icon-btn-inner">
              <wd-icon name="search-line" size="18" />
            </view>
          </button>
          <button class="hgt-icon-btn hgt-theme-btn" :aria-label="light ? '切换到深色' : '切换到浅色'" @click="toggleTheme">
            <view class="hgt-icon-btn-inner">
              <wd-icon :name="light ? 'moon' : 'sun'" size="18" />
            </view>
          </button>
          <view
            class="hgt-avatar-btn"
            @click="go({ name: player.user ? 'player-account' : 'player-login', path: player.user ? '/pages/account/index' : '/pages/login/index' })"
          >
            <image
              v-if="player.user?.avatar_url"
              class="hgt-avatar-img"
              :src="player.user.avatar_url"
              mode="aspectFill"
            />
            <image
              v-else-if="player.user"
              class="hgt-avatar-img"
              :src="resolveAssetUrl('/static/hgt/avatars/avatar_default.png')"
              mode="aspectFill"
            />
            <text v-else class="hgt-avatar-fallback">
              客
            </text>
          </view>
        </view>
      </view>
    </header>

    <!-- 手机顶栏：推理页保留 Logo 导航 -->
    <header class="hgt-mobile-header" :style="mobileHeaderStyle">
      <view class="hgt-mobile-brand" @click="go({ name: 'home', path: '/pages/index/index' })">
        <image class="hgt-mobile-logo" :src="logoSrc" mode="aspectFit" />
        <text class="hgt-mobile-title">
          <text class="hgt-en">
            MOYUU
          </text>
          <text class="hgt-display">
            海龟汤
          </text>
        </text>
      </view>
      <view class="hgt-mobile-actions">
        <button class="hgt-icon-btn" aria-label="搜索" @click="openSearch">
          <view class="hgt-icon-btn-inner">
            <wd-icon name="search-line" size="18" />
          </view>
        </button>
        <button class="hgt-icon-btn hgt-theme-btn" :aria-label="light ? '切换到深色' : '切换到浅色'" @click="toggleTheme">
          <view class="hgt-icon-btn-inner">
            <wd-icon :name="light ? 'moon' : 'sun'" size="18" />
          </view>
        </button>
      </view>
    </header>

    <!-- 手机底栏：推理页保留 -->
    <nav class="hgt-tabbar">
      <view
        v-for="item in mobileNav"
        :key="item.name"
        class="hgt-tab"
        :class="{ active: route.name === item.name }"
        @click="go(item)"
      >
        <text class="hgt-tab-icon">
          {{ item.icon }}
        </text>
        <text class="hgt-tab-label">
          {{ item.label }}
        </text>
      </view>
    </nav>

    <main class="hgt-main">
      <slot />
    </main>

    <button v-if="showRoomReturn" class="hgt-room-return" @click="returnToRoom">
      <text class="hgt-room-return-icon">
        ↩
      </text>
      <view class="hgt-room-return-copy">
        <text class="hgt-mono">
          返回房间
        </text>
        <text>{{ activeRoom?.name }}</text>
      </view>
    </button>

    <HgtFeedbackHost />
  </view>
</template>

<style scoped>
.hgt-app {
  --hgt-shell-top: var(--hgt-header-h, 56px);
  --hgt-shell-bottom: 0px;
  position: relative;
  isolation: isolate;
  min-height: 100vh;
  background: var(--hgt-bg);
  color: var(--hgt-text);
  font-family: var(--hgt-font-body);
}

.hgt-app.is-immersive-game {
  min-height: 100vh;
  min-height: 100dvh;
}

/* 推理页：保留站点导航，仅弱化粒子/浮标 */
.hgt-app.is-immersive-game .hgt-room-return,
.hgt-app.is-immersive-game .hgt-particle,
.hgt-app.is-immersive-game .hgt-flashlight {
  display: none !important;
}

.hgt-app.is-immersive-game .hgt-main {
  min-height: 0;
}

/* ===== Top bar (PC) ===== */
.hgt-topbar {
  position: sticky;
  z-index: 30;
  top: 0;
  display: block;
  box-sizing: border-box;
  height: var(--hgt-header-h);
  border-bottom: 1px solid var(--hgt-border);
  background: color-mix(in srgb, var(--hgt-bg-deep) 92%, transparent);
  backdrop-filter: blur(12px);
}
.hgt-topbar-inner {
  display: flex;
  box-sizing: border-box;
  width: min(var(--hgt-content-max), 100%);
  height: 100%;
  margin: 0 auto;
  padding: 0 28px;
  align-items: center;
  gap: 28px;
}
.hgt-topbar-brand {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 10px;
  cursor: pointer;
}
.hgt-topbar-logo {
  width: 36px;
  height: 36px;
  flex: none;
}
.hgt-topbar-brand-copy {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 2px;
}
.hgt-topbar-title-row {
  display: flex;
  align-items: baseline;
  gap: 8px;
  white-space: nowrap;
}
.hgt-topbar-en {
  color: var(--hgt-brand);
  font-size: 15px;
  letter-spacing: 0.18em;
}
.hgt-topbar-title {
  font-size: 16px;
  letter-spacing: 0.14em;
  white-space: nowrap;
  color: var(--hgt-text);
}
.hgt-topbar-sub {
  overflow: hidden;
  max-width: 220px;
  font-family: var(--hgt-font-display);
  font-size: 10px;
  letter-spacing: 0.04em;
  color: var(--hgt-text-3);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.hgt-topbar-nav {
  display: flex;
  min-width: 0;
  flex: 1;
  align-items: center;
  justify-content: center;
  gap: 6px;
}
.hgt-topbar-link {
  position: relative;
  padding: 8px 14px;
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-display);
  font-size: 14px;
  letter-spacing: 0.12em;
  transition: color var(--hgt-dur-fast) var(--hgt-ease-out);
}
.hgt-topbar-link:hover {
  color: var(--hgt-text);
}
.hgt-topbar-link.active {
  color: var(--hgt-brand);
}
.hgt-topbar-link.active::after {
  position: absolute;
  right: 14px;
  bottom: 2px;
  left: 14px;
  height: 2px;
  border-radius: 1px;
  background: var(--hgt-brand);
  content: '';
}
.hgt-topbar-actions {
  display: flex;
  flex: none;
  align-items: center;
  gap: 10px;
}
.hgt-icon-btn {
  position: relative;
  display: flex;
  box-sizing: border-box;
  width: 36px;
  height: 36px;
  margin: 0;
  padding: 0;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  overflow: hidden;
  background: transparent;
  color: var(--hgt-text-2);
  line-height: 1;
  transition: border-color var(--hgt-dur-fast), color var(--hgt-dur-fast), background var(--hgt-dur-fast);
}
.hgt-icon-btn-inner {
  display: flex;
  width: 100%;
  height: 100%;
  align-items: center;
  justify-content: center;
  line-height: 1;
  pointer-events: none;
}
.hgt-icon-btn-inner :deep(.wd-icon) {
  display: block;
  flex: none;
  line-height: 1;
}
.hgt-icon-btn:hover {
  border-color: var(--hgt-brand);
  color: var(--hgt-brand);
  background: var(--hgt-brand-soft);
}
.hgt-icon-btn::after {
  border: 0;
}
.hgt-avatar-btn {
  display: flex;
  box-sizing: border-box;
  width: 36px;
  height: 36px;
  border: 1px solid var(--hgt-border-soft);
  border-radius: var(--hgt-radius-full);
  align-items: center;
  justify-content: center;
  overflow: hidden;
  background: var(--hgt-card-2);
  cursor: pointer;
}
.hgt-avatar-img {
  width: 100%;
  height: 100%;
}
.hgt-avatar-fallback {
  color: var(--hgt-brand);
  font-size: 13px;
}

/* ===== Mobile header ===== */
.hgt-mobile-header {
  display: none;
}
.hgt-mobile-brand {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 8px;
}
.hgt-mobile-logo {
  width: 32px;
  height: 32px;
  flex: none;
}
.hgt-mobile-title {
  overflow: hidden;
  color: var(--hgt-text);
  font-size: 15px;
  letter-spacing: 0.08em;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.hgt-mobile-title .hgt-en {
  color: var(--hgt-brand);
  font-size: 13px;
  letter-spacing: 0.14em;
}
.hgt-mobile-actions {
  display: flex;
  gap: 8px;
}

/* ===== Main ===== */
.hgt-main {
  position: relative;
  z-index: 1;
  box-sizing: border-box;
  min-height: calc(100vh - var(--hgt-header-h));
}

/* ===== Mobile tabbar ===== */
.hgt-tabbar {
  display: none;
}

/* ===== Room return fab ===== */
.hgt-room-return {
  position: fixed;
  z-index: 24;
  right: 24px;
  bottom: 40px;
  display: flex;
  box-sizing: border-box;
  min-width: 170px;
  height: 52px;
  margin: 0;
  padding: 0 16px;
  border: 0;
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  gap: 12px;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  box-shadow: var(--hgt-shadow-float);
  text-align: left;
  transition: transform var(--hgt-dur-fast) var(--hgt-ease-out), filter var(--hgt-dur-fast);
}
.hgt-room-return:hover {
  filter: brightness(1.05);
  transform: translateY(-1px);
}
.hgt-room-return::after {
  border: 0;
}
.hgt-room-return-icon {
  font-size: 16px;
  line-height: 1;
}
.hgt-room-return-copy {
  display: flex;
  min-width: 0;
  gap: 3px;
  flex-direction: column;
}
.hgt-room-return-copy > text:first-child {
  font-size: 11px;
  letter-spacing: 0.12em;
  opacity: 0.8;
}
.hgt-room-return-copy > text:last-child {
  overflow: hidden;
  max-width: 160px;
  font-size: 12px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ===== Tablet ===== */
@media (max-width: 1199px) and (min-width: 768px) {
  .hgt-topbar-inner {
    padding: 0 20px;
    gap: 16px;
  }
  .hgt-topbar-link {
    padding: 8px 10px;
    font-size: 13px;
  }
  .hgt-topbar-sub {
    display: none;
  }
}

/* ===== Mobile ===== */
/* #ifdef H5 */
@media (max-width: 767px) {
/* #endif */
/* #ifndef H5 */
@media all {
/* #endif */
  .hgt-app {
    --hgt-shell-top: var(--hgt-mobile-header-offset, 56px);
    --hgt-shell-bottom: calc(var(--hgt-tabbar-h, 64px) + var(--hgt-safe-bottom, env(safe-area-inset-bottom, 0px)));
  }
  .hgt-topbar {
    display: none;
  }
  .hgt-mobile-header {
    position: fixed;
    z-index: 25;
    top: 0;
    right: 0;
    left: 0;
    display: flex;
    box-sizing: border-box;
    height: var(--hgt-mobile-header-offset, 56px);
    padding: 0 12px;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    border-bottom: 1px solid var(--hgt-border);
    background: color-mix(in srgb, var(--hgt-bg-deep) 94%, transparent);
    backdrop-filter: blur(12px);
  }
  .hgt-mobile-brand {
    flex: 1 1 auto;
    min-width: 0;
  }
  .hgt-mobile-title {
    overflow: visible;
    color: var(--hgt-text);
    font-size: 13px;
    letter-spacing: 0.04em;
    text-overflow: clip;
    white-space: nowrap;
  }
  .hgt-mobile-title .hgt-en {
    font-size: 12px;
  }
  .hgt-mobile-logo {
    width: 28px;
    height: 28px;
  }
  .hgt-mobile-actions {
    flex: none;
  }
  .hgt-main {
    min-height: 100vh;
    padding-top: var(--hgt-mobile-header-offset, 56px);
    padding-bottom: calc(var(--hgt-tabbar-h, 64px) + var(--hgt-safe-bottom, env(safe-area-inset-bottom, 0px)));
  }

  /* 推理页：main 定高铺满视口，页面根节点绝对占满 header 与 tabbar 之间，
     避免 padding 与页面自身 100dvh 计算重复扣减 tabbar，造成输入框下方空隙 */
  .hgt-app.is-immersive-game .hgt-main {
    position: relative;
    min-height: 0;
    height: var(--hgt-viewport-h, 100vh);
    height: var(--hgt-viewport-h, 100dvh);
    box-sizing: border-box;
    overflow: hidden;
  }

  .hgt-app.is-immersive-game .hgt-main :deep(.game-page),
  .hgt-app.is-immersive-game .hgt-main :deep(.game-load-state) {
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

  .hgt-tabbar {
    position: fixed;
    z-index: 25;
    right: 0;
    bottom: 0;
    left: 0;
    display: flex;
    box-sizing: border-box;
    height: var(--hgt-shell-bottom);
    padding-bottom: var(--hgt-safe-bottom, env(safe-area-inset-bottom, 0px));
    align-items: stretch;
    border-top: 1px solid var(--hgt-border);
    background: color-mix(in srgb, var(--hgt-bg-deep) 94%, transparent);
    backdrop-filter: blur(14px);
  }
  .hgt-tab {
    position: relative;
    display: flex;
    min-width: 0;
    flex: 1;
    gap: 4px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--hgt-text-2);
    transition: color var(--hgt-dur-fast);
  }
  .hgt-tab.active {
    color: var(--hgt-brand);
  }
  .hgt-tab.active::before {
    position: absolute;
    top: -1px;
    right: 30%;
    left: 30%;
    height: 2px;
    border-radius: 0 0 2px 2px;
    background: var(--hgt-brand);
    content: '';
  }
  .hgt-tab-icon {
    font-size: 16px;
    line-height: 1;
  }
  .hgt-tab-label {
    overflow: hidden;
    max-width: 100%;
    font-size: 10px;
    line-height: 1.2;
    letter-spacing: 0.04em;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .hgt-room-return {
    right: 14px;
    bottom: calc(var(--hgt-shell-bottom) + 14px);
    min-width: 0;
    width: auto;
    max-width: calc(100vw - 28px);
    height: 48px;
    padding: 0 14px;
  }
  .hgt-room-return-copy > text:last-child {
    max-width: 140px;
  }

  /* 原生默认导航：视口已在导航下方，不再绘制自定义顶栏/二次 padding */
  .hgt-app.mp-default-nav .hgt-mobile-header {
    display: none !important;
  }
  .hgt-app.mp-default-nav .hgt-main {
    padding-top: 0 !important;
  }
  /* 原生导航下 game 区：top 归零，高度/底边用系统 px（--hgt-viewport-h / --hgt-safe-bottom） */
  .hgt-app.mp-default-nav.is-immersive-game .hgt-main {
    height: var(--hgt-viewport-h, 100vh);
    height: var(--hgt-viewport-h, 100dvh);
  }
  .hgt-app.mp-default-nav.is-immersive-game .hgt-main :deep(.game-page),
  .hgt-app.mp-default-nav.is-immersive-game .hgt-main :deep(.game-load-state) {
    top: 0 !important;
    bottom: calc(var(--hgt-tabbar-h, 64px) + var(--hgt-safe-bottom, env(safe-area-inset-bottom, 0px))) !important;
  }
}
</style>
