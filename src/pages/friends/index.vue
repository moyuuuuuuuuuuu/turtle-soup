<script setup lang="ts">
import type { FriendLink } from '@/types/game'
import { friendLinkApi } from '@/api/turtle'
import { resolveAssetUrl } from '@/utils/assetUrl'

definePage({
  name: 'friends',
  layout: 'tabbar',
  style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } },
})

const heroBgSrc = resolveAssetUrl('/static/hgt/bg/bg_lighthouse_night.jpg')

const links = ref<FriendLink[]>([])
const loading = ref(true)
const applyVisible = ref(false)

const applyTemplate = `【MOYUU 海龟汤 · 友链申请】
站点名称：
站点地址：
Logo：
一句话简介：
回链地址：
联系邮箱：`

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

function logoHue(name: string) {
  let hash = 0
  for (let i = 0; i < name.length; i++)
    hash = (hash * 31 + name.charCodeAt(i)) | 0
  return Math.abs(hash) % 360
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
  try {
    const result = await friendLinkApi.list()
    links.value = result.items || []
  }
  catch {
    links.value = []
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
    <section class="friends-hero">
      <image
        class="hero-bg"
        :src="heroBgSrc"
        mode="aspectFill"
      />
      <view class="hero-veil" />
      <view class="hero-copy">
        <text class="hero-en">
          FRIEND LINKS
        </text>
        <text class="hero-title">
          友链
        </text>
        <text class="hero-quote">
          好的相遇，会让更多有趣的故事浮上来。
        </text>
        <text class="hero-sub">
          欢迎真诚、长期更新的站点。
        </text>
      </view>
    </section>

    <view class="content">
      <view v-if="loading" class="content-state">
        <HgtLoading text="正在整理航标…" size="md" />
      </view>

      <view v-else-if="!links.length" class="content-state">
        <text class="state-title">
          还没有友链记录
        </text>
        <text class="state-copy">
          如果你也在维护有趣的故事站点，欢迎申请互链。
        </text>
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
            :style="{ background: `linear-gradient(135deg, hsl(${logoHue(item.name)} 42% 38%), hsl(${logoHue(item.name)} 30% 22%))` }"
          >
            {{ initialOf(item.name) }}
          </view>
          <view class="friend-body">
            <view class="friend-head">
              <text class="friend-name">
                {{ item.name }}
              </text>
              <text class="friend-external">
                ↗
              </text>
            </view>
            <text class="friend-host">
              {{ hostOf(item.url) }}
            </text>
            <text v-if="item.description" class="friend-desc">
              {{ item.description }}
            </text>
          </view>
        </view>
      </view>

      <view class="footer-band">
        <view class="footer-copy">
          <text class="footer-title">
            申请互链
          </text>
          <text class="footer-note">
            请先添加本站友链，并提供名称、地址、Logo 与一句话简介。
          </text>
        </view>
        <button class="btn-ghost" @click="applyVisible = true">
          申请友链
        </button>
      </view>
    </view>

    <view v-if="applyVisible" class="apply-mask" @click="applyVisible = false">
      <view class="apply-panel" @click.stop>
        <text class="panel-title">
          申请友链
        </text>
        <view class="apply-rules">
          <text>· 站点可正常访问</text>
          <text>· 请先添加本站友链</text>
          <text>· 需提供：名称、地址、Logo、简介</text>
        </view>
        <view class="apply-actions">
          <button class="btn-primary" @click="copyTemplate">
            复制申请模板
          </button>
          <button class="btn-ghost" @click="applyVisible = false">
            关闭
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
  font-family: var(--hgt-font-body);
}

.friends-hero {
  position: relative;
  box-sizing: border-box;
  display: flex;
  min-height: clamp(280px, 42vh, 420px);
  padding: 48px 20px 56px;
  align-items: flex-end;
  overflow: hidden;
}

.hero-bg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: 70% center;
  /* 提亮灯塔原图，减少“太暗”感 */
  filter: brightness(1.35) contrast(0.92) saturate(1.08);
}

.hero-bg img {
  width: 100%;
  height: 100%;
  object-fit: cover !important;
  object-position: 70% center !important;
}

.hero-veil {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(90deg, rgba(4, 20, 24, 0.12) 0%, rgba(4, 20, 24, 0.04) 35%, transparent 60%),
    linear-gradient(180deg, rgba(6, 26, 32, 0) 0%, rgba(6, 26, 32, 0.04) 50%, rgba(6, 26, 32, 0.18) 78%, rgba(6, 26, 32, 0.42) 92%, var(--hgt-bg) 100%);
}

.hero-copy {
  position: relative;
  z-index: 1;
  display: flex;
  box-sizing: border-box;
  width: min(1120px, 100%);
  margin: 0 auto;
  padding: 0 clamp(8px, 2vw, 24px);
  gap: 10px;
  flex-direction: column;
  align-items: flex-start;
}

.hero-en {
  color: var(--hgt-brand);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
  letter-spacing: 0.22em;
}

.hero-title {
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-display);
  font-size: 36px;
  font-weight: 600;
  letter-spacing: 0.12em;
}

.hero-quote {
  max-width: 520px;
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 16px;
  line-height: 1.8;
}

.hero-sub {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-display);
  font-size: 13px;
}

.content {
  box-sizing: border-box;
  width: min(1120px, 100%);
  margin: 0 auto;
  padding: 8px 20px 24px;
}

.content-state {
  display: flex;
  min-height: 220px;
  gap: 10px;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  color: var(--hgt-text-2);
}

.state-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 18px;
}

.state-copy {
  max-width: 360px;
  color: var(--hgt-text-3);
  font-size: 13px;
  line-height: 1.7;
  text-align: center;
}

.friends-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 14px;
}

.friend-card {
  display: flex;
  box-sizing: border-box;
  min-height: 108px;
  padding: 18px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  gap: 16px;
  background: linear-gradient(135deg, rgba(29, 91, 94, 0.18), rgba(7, 34, 40, 0.28));
  cursor: pointer;
  transition:
    border-color var(--hgt-dur-fast),
    transform var(--hgt-dur-fast);
}

.friend-card:hover {
  transform: translateY(-2px);
  border-color: var(--hgt-border-hover);
}

.friend-logo {
  display: flex;
  box-sizing: border-box;
  width: 56px;
  height: 56px;
  border: 1px solid var(--hgt-border-soft);
  border-radius: var(--hgt-radius-sm);
  flex: none;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  background: var(--hgt-card-2);
  color: #fff;
  font-family: var(--hgt-font-display);
  font-size: 22px;
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
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-display);
  font-size: 17px;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.friend-external {
  color: var(--hgt-text-3);
  font-size: 13px;
  flex: none;
}

.friend-host {
  overflow: hidden;
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.friend-desc {
  display: -webkit-box;
  margin-top: 2px;
  overflow: hidden;
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-display);
  font-size: 13px;
  line-height: 1.6;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.footer-band {
  display: flex;
  margin-top: 28px;
  padding: 22px 0 8px;
  border-top: 1px solid var(--hgt-border-soft);
  gap: 16px;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
}

.footer-copy {
  display: flex;
  gap: 6px;
  flex-direction: column;
}

.footer-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 16px;
  font-weight: 600;
}

.footer-note {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-display);
  font-size: 13px;
}

.btn-primary,
.btn-ghost {
  display: flex;
  height: 42px;
  margin: 0;
  padding: 0 18px;
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  font-size: 13px;
  line-height: 1;
}

.btn-primary {
  border: 0;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-weight: 600;
}

.btn-ghost {
  border: 1px solid var(--hgt-border);
  background: transparent;
  color: var(--hgt-text-2);
}

.btn-primary::after,
.btn-ghost::after {
  border: 0;
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
  width: min(420px, 100%);
  padding: 24px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-lg);
  background: #0a242a;
}

.panel-title {
  display: block;
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-display);
  font-size: 20px;
  font-weight: 600;
}

.apply-rules {
  display: flex;
  margin: 16px 0 18px;
  padding: 14px 16px;
  border-left: 2px solid var(--hgt-brand);
  border-radius: var(--hgt-radius-sm);
  gap: 8px;
  flex-direction: column;
  background: rgba(15, 53, 57, 0.35);
  color: var(--hgt-text-2);
  font-size: 13px;
}

.apply-actions {
  display: flex;
  gap: 10px;
}

.apply-actions .btn-primary,
.apply-actions .btn-ghost {
  flex: 1;
}

@media (min-width: 768px) {
  .friends-hero {
    padding: 64px 32px 72px;
  }

  .content {
    padding: 12px 32px 32px;
  }

  .friends-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
  }
}

@media (min-width: 1200px) {
  .friends-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
</style>
