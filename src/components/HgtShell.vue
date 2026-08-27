<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import { useAnimatedTheme } from '@/composables/useAnimatedTheme'
import { usePlayerStore } from '@/store/playerStore'

const router = useRouter()
const route = useRoute()
const player = usePlayerStore()
const { light, overlay, toggleTheme } = useAnimatedTheme()
const navItems = [
  { name: 'home', path: '/pages/index/index', label: '首页', icon: '◈' },
  { name: 'questions', path: '/pages/questions/index', label: '题库', icon: '◉' },
  { name: 'public-rooms', path: '/pages/public-rooms/index', label: '公共房间', icon: '◐', authenticated: true },
  { name: 'history', path: '/pages/history/index', label: '记录', icon: '◎' },
  { name: 'player-account', path: '/pages/account/index', label: '我的', icon: '◇' },
  { name: 'donate', path: '/pages/donate/index', label: '捐赠', icon: '◆' },
]
const desktopNav = computed(() => navItems.filter(item => !item.authenticated || player.user))
const mobileNav = computed(() => navItems.filter(item => ['home', 'questions', 'public-rooms', 'history', 'player-account'].includes(item.name)))
function go(item: typeof navItems[number]) {
  if (item.name === 'home') {
    uni.switchTab({ url: item.path })
    return
  }
  router.push(item.path)
}
onMounted(async () => {
  if (!player.ready)
    await player.restore()
})
</script>

<template>
  <view class="hgt-app" :class="{ 'hgt-light': light }">
    <HgtThemeTransition v-bind="overlay" />
    <HgtParticleBackground />
    <aside class="hgt-sidebar">
      <view class="hgt-brand">
        <image class="hgt-brand-logo" :src="light ? '/static/brand/logo-mark-light.png' : '/static/brand/logo-mark-dark.png'" mode="aspectFit" />
        <view class="hgt-brand-copy">
          <text class="hgt-display hgt-brand-title">墨鱼海龟汤</text>
          <text class="hgt-mono hgt-brand-subtitle">MOYUU · LATERAL THINKING</text>
        </view>
      </view>
      <view class="hgt-nav">
        <view v-for="item in desktopNav" :key="item.name" class="hgt-nav-item" :class="{ active: route.name === item.name }" @click="go(item)">
          <text class="hgt-mono hgt-nav-icon">
            {{ item.icon }}
          </text><text>{{ item.label }}</text>
        </view>
      </view>
      <view class="hgt-sidebar-footer">
        <text class="hgt-mono">
          v0.1.0
        </text><button class="hgt-icon-button hgt-theme-trigger" @click="toggleTheme">
          {{ light ? '☾' : '☀' }}
        </button>
      </view>
    </aside>
    <header class="hgt-mobile-header">
      <view class="hgt-mobile-brand">
        <image class="hgt-mobile-logo" :src="light ? '/static/brand/logo-mark-light.png' : '/static/brand/logo-mark-dark.png'" mode="aspectFit" />
        <text class="hgt-display">墨鱼海龟汤</text>
      </view>
      <view class="hgt-mobile-actions">
        <button class="hgt-icon-button hgt-theme-trigger" @click="toggleTheme">
          {{ light ? '☾' : '☀' }}
        </button>
      </view>
    </header>
    <nav class="hgt-mobile-tabbar">
      <view v-for="item in mobileNav" :key="item.name" class="hgt-mobile-tab" :class="{ active: route.name === item.name }" @click="go(item)">
        <text class="hgt-mono hgt-mobile-tab-icon">
          {{ item.icon }}
        </text><text class="hgt-mobile-tab-label">
          {{ item.label }}
        </text>
      </view>
    </nav>
    <main class="hgt-main">
      <slot />
    </main>
  </view>
</template>

<style scoped>
.hgt-app { --background:#080808;--foreground:#f0f0f0;--card:#111;--secondary:#1a1a1a;--muted-foreground:#666;--accent:#d4d4d4;--border:#222;position:relative;isolation:isolate;min-height:100vh;background:var(--background);color:var(--foreground);font-family:Arial,sans-serif; }
.hgt-app.hgt-light { --background:#edeae4;--foreground:#1c1c1a;--card:#e4e0d9;--secondary:#d9d5ce;--muted-foreground:#7a7972;--accent:#2e2e2c;--border:#c8c4bc; }
.hgt-sidebar { position:fixed;left:0;top:0;bottom:0;width:224px;z-index:20;display:flex;flex-direction:column;background:var(--card);border-right:1px solid var(--border); }
.hgt-brand { padding:18px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}.hgt-brand-logo{width:44px;height:44px;flex:none}.hgt-brand-copy{display:flex;min-width:0;flex-direction:column;gap:4px}.hgt-brand-title{font-size:16px;letter-spacing:.12em;white-space:nowrap}.hgt-brand-subtitle{font-size:8px;letter-spacing:.08em;color:var(--muted-foreground);white-space:nowrap}
.hgt-nav{padding:24px 0;flex:1}.hgt-nav-item{position:relative;padding:13px 24px;display:flex;gap:13px;align-items:center;color:var(--muted-foreground);font-size:12px;letter-spacing:.16em}.hgt-nav-item.active{color:var(--foreground);background:var(--secondary)}.hgt-nav-item.active::before{content:'';position:absolute;left:0;top:0;bottom:0;width:2px;background:var(--foreground)}
.hgt-sidebar-footer{padding:20px 24px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;color:var(--muted-foreground);font-size:11px}.hgt-icon-button{margin:0;padding:0;width:34px;height:34px;line-height:32px;border:1px solid var(--border);border-radius:0;background:transparent;color:var(--foreground);font-size:14px}.hgt-icon-button::after{display:none}
.hgt-main{position:relative;z-index:1;min-height:100vh;margin-left:224px}.hgt-mobile-header,.hgt-mobile-tabbar{display:none}
@media(max-width:767px){.hgt-sidebar{display:none}.hgt-main{margin-left:0;padding-top:56px;padding-bottom:calc(64px + env(safe-area-inset-bottom))}.hgt-mobile-header{position:fixed;z-index:25;left:0;right:0;top:0;height:56px;padding:0 16px;display:flex;align-items:center;justify-content:space-between;background:var(--card);border-bottom:1px solid var(--border);letter-spacing:.12em}.hgt-mobile-brand{display:flex;align-items:center;gap:8px}.hgt-mobile-logo{width:34px;height:34px}.hgt-mobile-actions{display:flex;gap:10px}.hgt-mobile-tabbar{position:fixed;z-index:25;left:0;right:0;bottom:0;min-height:64px;padding-bottom:env(safe-area-inset-bottom);display:flex;align-items:stretch;background:color-mix(in srgb,var(--card) 94%,transparent);border-top:1px solid var(--border);backdrop-filter:blur(14px)}.hgt-mobile-tab{position:relative;display:flex;min-width:0;flex:1;flex-direction:column;align-items:center;justify-content:center;gap:4px;color:var(--muted-foreground)}.hgt-mobile-tab.active{color:var(--foreground)}.hgt-mobile-tab.active::before{content:'';position:absolute;top:-1px;left:28%;right:28%;height:2px;background:var(--foreground)}.hgt-mobile-tab-icon{font-size:16px;line-height:1}.hgt-mobile-tab-label{overflow:hidden;max-width:100%;font-size:10px;line-height:1.2;letter-spacing:.04em;white-space:nowrap;text-overflow:ellipsis}}
</style>
