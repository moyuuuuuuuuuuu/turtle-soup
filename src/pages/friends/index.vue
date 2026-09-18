<script setup lang="ts">
import type { FriendLink } from '@/types/game'
import { friendLinkApi } from '@/api/turtle'

definePage({
  name: 'friends',
  layout: 'tabbar',
  style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } },
})

const links = ref<FriendLink[]>([])
const loading = ref(true)
const loadError = ref(false)
const applyVisible = ref(false)

const applyTemplate = `【墨鱼海龟汤 · 友链申请】
站点名称：
站点地址：
Logo：
一句话简介：
回链地址：
联系邮箱：`

const fallbackLinks: FriendLink[] = [
  { id: 'demo-1', name: '墨鱼的工具箱', url: 'https://tooldeck.moyuu.ink', logo_url: '', description: '精选实用的在线工具，让效率更进一步。' },
  { id: 'demo-2', name: 'LunaTV', url: 'https://lunatv.moyuu.ink', logo_url: '', description: '自建影视库，享受纯净的观影体验。' },
  { id: 'demo-3', name: '小墨的笔记', url: 'https://blog.moyuu.ink', logo_url: '', description: '记录技术、生活与思考，在这里沉淀更好的自己。' },
  { id: 'demo-4', name: 'Obsidian', url: 'https://obsidian.md', logo_url: '', description: '强大的知识管理工具，让思考形成体系。' },
  { id: 'demo-5', name: 'Vue.js', url: 'https://vuejs.org', logo_url: '', description: '渐进式 JavaScript 框架，构建更好的前端体验。' },
  { id: 'demo-6', name: 'Laravel', url: 'https://laravel.com', logo_url: '', description: '优雅的 PHP Web 开发框架，让开发更愉快。' },
  { id: 'demo-7', name: 'Cloudflare', url: 'https://cloudflare.com', logo_url: '', description: '更快、更安全的互联网基础设施。' },
  { id: 'demo-8', name: 'Docker', url: 'https://docker.com', logo_url: '', description: '让开发、部署和运行应用变得简单。' },
  { id: 'demo-9', name: 'GitHub', url: 'https://github.com', logo_url: '', description: '面向开发者的代码托管平台，创造、分享、一起构建。' },
  { id: 'demo-10', name: 'Vercel', url: 'https://vercel.com', logo_url: '', description: '为现代前端而生的部署平台。' },
  { id: 'demo-11', name: 'ChatGPT', url: 'https://openai.com', logo_url: '', description: '激发灵感，改变工作与生活方式。' },
  { id: 'demo-12', name: '稀土掘金', url: 'https://juejin.cn', logo_url: '', description: '高质量的技术社区，和开发者一起成长。' },
]

function hostOf(url: string) {
  try {
    return url.replace(/^https?:\/\//i, '').replace(/\/$/, '')
  }
  catch {
    return url
  }
}

function initialOf(name: string) {
  return (name || '友').trim().slice(0, 1)
}

function hueOf(name: string) {
  let hash = 0
  for (let i = 0; i < name.length; i++) hash = (hash * 31 + name.charCodeAt(i)) >>> 0
  return hash % 360
}

function openLink(item: FriendLink) {
  const url = item.url
  if (!url)
    return
  // #ifdef H5
  window.open(url, '_blank', 'noopener,noreferrer')
  // #endif
  // #ifndef H5
  uni.setClipboardData({
    data: url,
    success: () => uni.showToast({ title: '链接已复制', icon: 'success' }),
  })
  // #endif
}

function copyTemplate() {
  uni.setClipboardData({
    data: applyTemplate,
    success: () => {
      applyVisible.value = false
      uni.showToast({ title: '申请模板已复制', icon: 'success' })
    },
  })
}

async function loadLinks() {
  loading.value = true
  loadError.value = false
  try {
    const result = await friendLinkApi.list()
    links.value = result.items?.length ? result.items : fallbackLinks
  }
  catch {
    loadError.value = false
    links.value = fallbackLinks
  }
  finally {
    loading.value = false
  }
}

onMounted(() => {
  void loadLinks()
})
</script>

<template>
  <view class="friends-page">
    <view class="hero">
      <image class="hero-bg" src="/static/hgt/bg/bg_lighthouse_night.jpg" mode="aspectFill" />
      <image class="hero-props" src="/static/hgt/prop/prop_lantern.png" mode="aspectFit" />
      <view class="hero-veil" />
      <view class="hero-inner">
        <view class="hero-copy">
          <text class="hgt-mono hero-kicker">
            ◆ FRIENDS · LINKS
          </text>
          <view class="hero-title-row">
            <text class="hgt-display hero-title">
              友链
            </text>
            <text class="hero-en">
              Friends Links
            </text>
          </view>
          <text class="hero-lead">
            好的相遇，会让更多有趣的故事浮上来。
          </text>
          <text class="hero-desc">
            这里收录了一些值得访问的朋友们，<br>
            感谢每一个真诚、有趣、热爱发光的创作者。
          </text>
        </view>
        <view class="quote-card">
          <image class="quote-paper" src="/static/hgt/paper/paper_01.png" mode="aspectFill" />
          <view class="quote-body">
            <text class="hgt-display quote-text">
              独行或许能走得更快，
            </text>
            <text class="hgt-display quote-text">
              但一群人会看到更大的海。
            </text>
            <text class="hgt-mono quote-sign">
              —— MOYUU
            </text>
          </view>
        </view>
      </view>
    </view>

    <view class="content">
      <view v-if="loading" class="content-state">
        <image class="empty-img" src="/static/hgt/empty/empty_loading.png" mode="aspectFit" />
        <text>正在打捞友链…</text>
      </view>
      <view v-else-if="loadError" class="content-state">
        <image class="empty-img" src="/static/hgt/empty/empty_network.png" mode="aspectFit" />
        <text>友链暂时没有浮上来</text>
        <button class="btn-ghost" @click="loadLinks">
          重新加载
        </button>
      </view>
      <view v-else-if="!links.length" class="content-state">
        <image class="empty-img" src="/static/hgt/empty/empty_none.png" mode="aspectFit" />
        <text>还没有朋友停靠，欢迎来当第一位。</text>
        <button class="btn-primary" @click="applyVisible = true">
          申请友链
        </button>
      </view>

      <view v-else class="friends-grid">
        <view
          v-for="item in links"
          :key="item.id"
          class="friend-card"
          @click="openLink(item)"
        >
          <image v-if="item.logo_url" class="friend-logo" :src="item.logo_url" mode="aspectFill" />
          <view
            v-else
            class="friend-logo fallback"
            :style="{ background: `linear-gradient(145deg, hsl(${hueOf(item.name)} 42% 28%), hsl(${hueOf(item.name)} 36% 16%))` }"
          >
            {{ initialOf(item.name) }}
          </view>
          <view class="friend-body">
            <view class="friend-head">
              <text class="hgt-display friend-name">
                {{ item.name }}
              </text>
              <text class="friend-external">
                ↗
              </text>
            </view>
            <text class="hgt-mono friend-host">
              {{ hostOf(item.url) }}
            </text>
            <text class="friend-desc">
              {{ item.description || '值得顺路拜访的一站。' }}
            </text>
          </view>
        </view>
      </view>

      <view class="footer-row">
        <button class="apply-btn" @click="applyVisible = true">
          <text class="apply-plus">
            ＋
          </text>
          <text class="apply-label">
            申请友链
          </text>
          <text class="apply-arrow">
            ＞
          </text>
        </button>
        <view class="footer-copy">
          <text class="hgt-display footer-title">
            相遇本身，就是一种答案。
          </text>
          <text class="hgt-mono footer-sub">
            GOOD STORIES SINK DEEPER.
          </text>
          <text class="footer-script">
            More Good Friends, More Stories
          </text>
        </view>
      </view>
    </view>

    <view v-if="applyVisible" class="apply-mask" @click="applyVisible = false">
      <view class="apply-panel" @click.stop>
        <text class="hgt-mono apply-eyebrow">
          ◇ APPLY
        </text>
        <text class="hgt-display apply-title">
          申请友链
        </text>
        <text class="apply-lead">
          欢迎真诚、有趣、长期更新的站点加入墨鱼海龟汤友链。
        </text>
        <view class="apply-rules">
          <text>· 站点可正常访问，内容健康、无恶意软件</text>
          <text>· 请先添加本站友链，再提交申请</text>
          <text>· 需提供：名称、地址、Logo、一句话简介</text>
          <text>· 审核通过后会展示在本页</text>
        </view>
        <view class="apply-actions">
          <button class="btn-primary" @click="copyTemplate">
            复制申请模板
          </button>
          <button class="btn-ghost" @click="applyVisible = false">
            稍后再说
          </button>
        </view>
      </view>
    </view>
  </view>
</template>

<style scoped>
.friends-page {
  min-height: 100%;
  padding-bottom: 48px;
  background: var(--hgt-bg);
  color: var(--hgt-text);
}

.hero {
  position: relative;
  display: flex;
  box-sizing: border-box;
  width: 100%;
  min-height: max(42vh, 360px);
  padding: 40px 48px 36px;
  align-items: center;
  overflow: hidden;
}
.hero-bg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  filter: var(--hgt-atmo-filter);
}
.hero-props {
  position: absolute;
  right: 8%;
  top: 12%;
  z-index: 0;
  width: min(180px, 28vw);
  height: 220px;
  opacity: 0.28;
  pointer-events: none;
}
.hero-veil {
  position: absolute;
  inset: 0;
  z-index: 0;
  background:
    linear-gradient(90deg,
      rgba(7, 20, 24, 0.72) 0%,
      rgba(7, 20, 24, 0.42) 48%,
      rgba(7, 20, 24, 0.22) 100%),
    linear-gradient(180deg,
      rgba(7, 20, 24, 0.08) 0%,
      rgba(7, 20, 24, 0.28) 55%,
      var(--hgt-bg) 100%);
}
.hero-inner {
  position: relative;
  z-index: 1;
  display: flex;
  width: min(var(--hgt-content-max), 100%);
  margin: 0 auto;
  align-items: flex-end;
  justify-content: space-between;
  gap: 32px;
}
.hero-copy {
  display: flex;
  max-width: 560px;
  flex-direction: column;
  gap: 10px;
}
.hero-kicker {
  color: var(--hgt-brand);
  font-size: 11px;
  letter-spacing: 0.28em;
}
.hero-title-row {
  display: flex;
  align-items: baseline;
  gap: 14px;
  flex-wrap: wrap;
}
.hero-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 48px;
  font-weight: 700;
  letter-spacing: 0.18em;
  line-height: 1.1;
}
.hero-en {
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-display);
  font-size: 20px;
  font-style: italic;
  letter-spacing: 0.08em;
}
.hero-lead {
  margin-top: 6px;
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 18px;
  line-height: 1.6;
}
.hero-desc {
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1.8;
}
.quote-card {
  position: relative;
  box-sizing: border-box;
  width: min(280px, 100%);
  min-height: 140px;
  padding: 22px 20px;
  border: 1px solid rgba(208, 220, 182, 0.22);
  border-radius: var(--hgt-radius-md);
  overflow: hidden;
  background: rgba(208, 220, 182, 0.08);
  box-shadow: var(--hgt-shadow-lg);
}
.quote-paper {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  opacity: 0.35;
  mix-blend-mode: soft-light;
}
.quote-body {
  position: relative;
  z-index: 1;
  display: flex;
  gap: 6px;
  flex-direction: column;
}
.quote-text {
  color: var(--hgt-paper);
  font-family: var(--hgt-font-display);
  font-size: 15px;
  line-height: 1.7;
}
.quote-sign {
  margin-top: 10px;
  color: var(--hgt-text-3);
  font-size: 11px;
  letter-spacing: 0.16em;
}

.content {
  box-sizing: border-box;
  width: min(var(--hgt-content-max), 100%);
  margin: 0 auto;
  padding: 28px 48px 20px;
}
.content-state {
  display: flex;
  min-height: 220px;
  padding: 28px 8px;
  gap: 12px;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  color: var(--hgt-text-2);
  font-size: 14px;
  text-align: center;
}
.content-state .empty-img {
  width: min(220px, 60vw);
  height: 160px;
  border-radius: var(--hgt-radius-lg);
  filter: drop-shadow(0 8px 24px rgba(4, 12, 14, 0.45));
}

.friends-grid {
  display: grid;
  gap: 16px;
  grid-template-columns: repeat(4, minmax(0, 1fr));
}
.friend-card {
  display: flex;
  box-sizing: border-box;
  min-height: 132px;
  padding: 16px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  gap: 14px;
  background:
    linear-gradient(160deg, rgba(91, 200, 189, 0.05), transparent 42%),
    var(--hgt-card);
  cursor: pointer;
  transition:
    border-color var(--hgt-dur-fast) var(--hgt-ease-out),
    transform var(--hgt-dur-fast) var(--hgt-ease-out),
    background var(--hgt-dur-fast) var(--hgt-ease-out);
}
.friend-card:hover {
  border-color: var(--hgt-border-soft);
  transform: translateY(-2px);
  background:
    linear-gradient(160deg, rgba(91, 200, 189, 0.1), transparent 48%),
    var(--hgt-card-2);
}
.friend-logo {
  display: flex;
  box-sizing: border-box;
  width: 48px;
  height: 48px;
  border: 1px solid var(--hgt-border);
  border-radius: 12px;
  flex: none;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  background: var(--hgt-bg-deep);
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 20px;
  font-weight: 600;
}
.friend-body {
  display: flex;
  min-width: 0;
  flex: 1;
  gap: 6px;
  flex-direction: column;
}
.friend-head {
  display: flex;
  min-width: 0;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.friend-name {
  min-width: 0;
  overflow: hidden;
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 16px;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.friend-external {
  color: var(--hgt-text-3);
  font-size: 13px;
  flex: none;
  transition: color var(--hgt-dur-fast);
}
.friend-card:hover .friend-external {
  color: var(--hgt-brand);
}
.friend-host {
  overflow: hidden;
  color: var(--hgt-text-3);
  font-size: 11px;
  letter-spacing: 0.04em;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.friend-desc {
  display: -webkit-box;
  margin-top: 2px;
  overflow: hidden;
  color: var(--hgt-text-2);
  font-size: 12px;
  line-height: 1.65;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.footer-row {
  display: flex;
  margin-top: 36px;
  padding-top: 28px;
  border-top: 1px solid var(--hgt-border);
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
}
.apply-btn {
  display: flex;
  box-sizing: border-box;
  height: 56px;
  margin: 0;
  padding: 0 22px;
  border: 1px solid var(--hgt-border-soft);
  border-radius: var(--hgt-radius-md);
  align-items: center;
  gap: 12px;
  background: rgba(15, 42, 45, 0.72);
  color: var(--hgt-text);
  line-height: 1;
}
.apply-btn::after {
  border: 0;
}
.apply-btn:hover {
  border-color: var(--hgt-brand);
  background: var(--hgt-brand-soft);
}
.apply-plus {
  color: var(--hgt-brand);
  font-size: 20px;
}
.apply-label {
  font-family: var(--hgt-font-display);
  font-size: 15px;
  letter-spacing: 0.12em;
}
.apply-arrow {
  color: var(--hgt-text-3);
  font-size: 14px;
}
.footer-copy {
  display: flex;
  max-width: 420px;
  gap: 6px;
  flex-direction: column;
  text-align: right;
}
.footer-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 20px;
  letter-spacing: 0.08em;
}
.footer-sub {
  color: var(--hgt-text-3);
  font-size: 10px;
  letter-spacing: 0.22em;
}
.footer-script {
  margin-top: 4px;
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-display);
  font-size: 13px;
  font-style: italic;
  opacity: 0.8;
}

.btn-primary,
.btn-ghost {
  display: flex;
  height: 44px;
  margin: 0;
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  font-size: 14px;
  line-height: 1;
}
.btn-primary {
  padding: 0 22px;
  border: 0;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-weight: 600;
}
.btn-primary::after,
.btn-ghost::after {
  border: 0;
}
.btn-ghost {
  padding: 0 18px;
  border: 1px solid var(--hgt-border-soft);
  background: transparent;
  color: var(--hgt-text-2);
}

.apply-mask {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: flex;
  padding: 24px;
  align-items: center;
  justify-content: center;
  background: var(--hgt-overlay);
}
.apply-panel {
  box-sizing: border-box;
  width: min(440px, 100%);
  padding: 28px 24px;
  border: 1px solid var(--hgt-border-soft);
  border-radius: var(--hgt-radius-lg);
  background: var(--hgt-card);
  box-shadow: var(--hgt-shadow-float);
}
.apply-eyebrow {
  color: var(--hgt-brand);
  font-size: 11px;
  letter-spacing: 0.22em;
}
.apply-title {
  display: block;
  margin-top: 8px;
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 24px;
  font-weight: 600;
}
.apply-lead {
  display: block;
  margin-top: 10px;
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1.7;
}
.apply-rules {
  display: flex;
  margin: 18px 0 22px;
  padding: 14px 16px;
  border-left: 2px solid var(--hgt-brand);
  border-radius: var(--hgt-radius-sm);
  gap: 8px;
  flex-direction: column;
  background: var(--hgt-card-2);
  color: var(--hgt-text-2);
  font-size: 12px;
  line-height: 1.7;
}
.apply-actions {
  display: flex;
  gap: 10px;
}
.apply-actions .btn-primary,
.apply-actions .btn-ghost {
  flex: 1;
}

@media (max-width: 1100px) {
  .friends-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
@media (max-width: 767px) {
  .hero {
    min-height: auto;
    padding: 28px 16px 24px;
  }
  .hero-inner {
    flex-direction: column;
    align-items: stretch;
  }
  .hero-title {
    font-size: 36px;
  }
  .hero-en {
    font-size: 16px;
  }
  .quote-card {
    width: 100%;
  }
  .content {
    padding: 20px 16px 16px;
  }
  .friends-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .footer-row {
    flex-direction: column;
    align-items: stretch;
  }
  .footer-copy {
    max-width: none;
    text-align: left;
  }
  .apply-actions {
    flex-direction: column;
  }
}
@media (max-width: 420px) {
  .friends-grid {
    grid-template-columns: 1fr;
  }
}
</style>
