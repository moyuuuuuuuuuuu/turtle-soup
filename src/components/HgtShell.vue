<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
const router = useRouter()
const route = useRoute()
const menuOpen = ref(false)
const light = ref(uni.getStorageSync('hgt_theme') === 'light')
const nav = [
  { name: 'home', label: '首页', icon: '◈' },
  { name: 'questions', label: '题库', icon: '◉' },
  { name: 'history', label: '记录', icon: '◎' },
  { name: 'player-account', label: '我的', icon: '◇' },
  { name: 'donate', label: '捐赠', icon: '◆' },
]
function go(name: string) { menuOpen.value = false; router.push({ name }) }
function toggleTheme() { light.value = !light.value; uni.setStorageSync('hgt_theme', light.value ? 'light' : 'dark') }
</script>

<template>
  <view class="hgt-app" :class="{ 'hgt-light': light }">
    <HgtParticleBackground />
    <aside class="hgt-sidebar">
      <view class="hgt-brand">
        <text class="hgt-display hgt-brand-title">
          海龟汤
        </text>
        <text class="hgt-mono hgt-brand-subtitle">
          LATERAL THINKING
        </text>
      </view>
      <view class="hgt-nav">
        <view v-for="item in nav" :key="item.name" class="hgt-nav-item" :class="{ active: route.name === item.name }" @click="go(item.name)">
          <text class="hgt-mono hgt-nav-icon">
            {{ item.icon }}
          </text><text>{{ item.label }}</text>
        </view>
      </view>
      <view class="hgt-sidebar-footer">
        <text class="hgt-mono">
          v0.1.0
        </text><button class="hgt-icon-button" @click="toggleTheme">
          {{ light ? '☾' : '☀' }}
        </button>
      </view>
    </aside>
    <header class="hgt-mobile-header">
      <text class="hgt-display">
        海龟汤
      </text>
      <view class="hgt-mobile-actions">
        <button class="hgt-icon-button" @click="toggleTheme">
          {{ light ? '☾' : '☀' }}
        </button><button class="hgt-menu-button" @click="menuOpen = !menuOpen">
          {{ menuOpen ? '×' : '☰' }}
        </button>
      </view>
    </header>
    <view v-if="menuOpen" class="hgt-mobile-menu">
      <view v-for="item in nav" :key="item.name" class="hgt-mobile-nav" @click="go(item.name)">
        <text class="hgt-mono">
          {{ item.icon }}
        </text><text class="hgt-display">
          {{ item.label }}
        </text>
      </view>
    </view>
    <main class="hgt-main">
      <slot />
    </main>
  </view>
</template>

<style scoped>
.hgt-app { --background:#080808;--foreground:#f0f0f0;--card:#111;--secondary:#1a1a1a;--muted-foreground:#666;--accent:#d4d4d4;--border:#222;min-height:100vh;background:var(--background);color:var(--foreground);font-family:Arial,sans-serif; }
.hgt-app.hgt-light { --background:#edeae4;--foreground:#1c1c1a;--card:#e4e0d9;--secondary:#d9d5ce;--muted-foreground:#7a7972;--accent:#2e2e2c;--border:#c8c4bc; }
.hgt-sidebar { position:fixed;left:0;top:0;bottom:0;width:224px;z-index:20;display:flex;flex-direction:column;background:var(--card);border-right:1px solid var(--border); }
.hgt-brand { padding:24px;border-bottom:1px solid var(--border);display:flex;flex-direction:column;gap:5px; }.hgt-brand-title{font-size:18px;letter-spacing:.2em}.hgt-brand-subtitle{font-size:10px;letter-spacing:.15em;color:var(--muted-foreground)}
.hgt-nav{padding:24px 0;flex:1}.hgt-nav-item{position:relative;padding:13px 24px;display:flex;gap:13px;align-items:center;color:var(--muted-foreground);font-size:12px;letter-spacing:.16em}.hgt-nav-item.active{color:var(--foreground);background:var(--secondary)}.hgt-nav-item.active::before{content:'';position:absolute;left:0;top:0;bottom:0;width:2px;background:var(--foreground)}
.hgt-sidebar-footer{padding:20px 24px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;color:var(--muted-foreground);font-size:11px}.hgt-icon-button,.hgt-menu-button{margin:0;padding:0;width:34px;height:34px;line-height:32px;border:1px solid var(--border);border-radius:0;background:transparent;color:var(--foreground);font-size:14px}.hgt-icon-button::after,.hgt-menu-button::after{display:none}
.hgt-main{position:relative;z-index:1;min-height:100vh;margin-left:224px}.hgt-mobile-header,.hgt-mobile-menu{display:none}
@media(max-width:767px){.hgt-sidebar{display:none}.hgt-main{margin-left:0;padding-top:56px}.hgt-mobile-header{position:fixed;z-index:25;left:0;right:0;top:0;height:56px;padding:0 16px;display:flex;align-items:center;justify-content:space-between;background:var(--card);border-bottom:1px solid var(--border);letter-spacing:.2em}.hgt-mobile-actions{display:flex;gap:10px}.hgt-mobile-menu{display:flex;position:fixed;z-index:24;inset:56px 0 0;background:var(--card);padding:28px 0;flex-direction:column}.hgt-mobile-nav{display:flex;gap:16px;padding:17px 30px;color:var(--muted-foreground);font-size:18px;letter-spacing:.15em}}
</style>
