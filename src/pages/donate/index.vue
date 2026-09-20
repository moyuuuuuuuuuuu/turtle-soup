<script setup lang="ts">
import type { DonationPage } from '@/types/game'
import { donationApi } from '@/api/turtle'

definePage({ name: 'donate', layout: 'tabbar', style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } } })

/** 仅开发环境预览弹幕；生产构建经 tree-shaking / 环境判断不注入 */
const isDev = import.meta.env.DEV
const MOCK_DONATIONS = isDev
  ? [
      { id: 'm1', donor_name: '海风', amount: '6.66', donated_at: new Date(Date.now() - 3 * 60_000).toISOString() },
      { id: 'm2', donor_name: '汤勺', amount: '10', donated_at: new Date(Date.now() - 12 * 60_000).toISOString() },
      { id: 'm3', donor_name: '深海潜水员', amount: '18.88', donated_at: new Date(Date.now() - 40 * 60_000).toISOString() },
      { id: 'm4', donor_name: '匿名汤友', amount: '5', donated_at: new Date(Date.now() - 2 * 3600_000).toISOString() },
      { id: 'm5', donor_name: '灯塔守夜人', amount: '28', donated_at: new Date(Date.now() - 5 * 3600_000).toISOString() },
      { id: 'm6', donor_name: '月光收集者', amount: '9.99', donated_at: new Date(Date.now() - 8 * 3600_000).toISOString() },
      { id: 'm7', donor_name: '碎碎念', amount: '1', donated_at: new Date(Date.now() - 26 * 3600_000).toISOString() },
      { id: 'm8', donor_name: '漂流瓶', amount: '66', donated_at: new Date(Date.now() - 3 * 86400_000).toISOString() },
    ]
  : []

const data = ref<DonationPage>({ channels: [], recent_donations: [], supporter_count: 0 })
const selectedMethod = ref<'wechat' | 'alipay'>('wechat')
const done = ref(false)
const loading = ref(true)

const selected = computed(() =>
  data.value.channels.find(item => item.method === selectedMethod.value) || data.value.channels[0],
)

const displayDonations = computed(() => {
  if (data.value.recent_donations?.length)
    return data.value.recent_donations
  return isDev ? MOCK_DONATIONS : []
})

/** 移动端弹幕：复制一份列表，便于 CSS -50% 无缝循环 */
const marqueeItems = computed(() => {
  const list = displayDonations.value
  return list.length ? [...list, ...list] : []
})

const supporterCount = computed(() => {
  if (data.value.supporter_count)
    return data.value.supporter_count
  return displayDonations.value.length
})

function relative(value: string) {
  const seconds = Math.max(0, (Date.now() - new Date(value).getTime()) / 1000)
  if (seconds < 3600)
    return `${Math.max(1, Math.floor(seconds / 60))}分钟前`
  if (seconds < 86400)
    return `${Math.floor(seconds / 3600)}小时前`
  return `${Math.floor(seconds / 86400)}天前`
}

onMounted(async () => {
  try {
    data.value = await donationApi.page()
    if (data.value.channels[0])
      selectedMethod.value = data.value.channels[0].method
  }
  catch {
    data.value = { channels: [], recent_donations: [], supporter_count: 0 }
  }
  finally {
    loading.value = false
  }
})
</script>

<template>
  <view class="donate-page">
    <section class="donate-hero">
      <image
        class="hero-bg"
        src="/static/hgt/bg/bg_starry.jpg"
        mode="aspectFill"
      />
      <view class="hero-veil" />

      <!-- 移动端顶部弹幕：仅滚动条目，无标题 -->
      <view v-if="marqueeItems.length" class="donor-danmaku">
        <view class="danmaku-viewport">
          <view class="danmaku-track">
            <text
              v-for="(item, index) in marqueeItems"
              :key="`${item.id}-${index}`"
              class="danmaku-item"
            >
              <text class="danmaku-name">
                {{ item.donor_name }}
              </text>
              <text class="danmaku-amount">
                ¥{{ item.amount }}
              </text>
              <text class="danmaku-time">
                {{ relative(item.donated_at) }}
              </text>
            </text>
          </view>
        </view>
      </view>

      <view class="hero-copy">
        <text class="hero-en">
          SUPPORT
        </text>
        <text class="hero-title">
          支持项目
        </text>
        <text class="hero-quote">
          捐赠将用于服务器维护、内容创作与功能开发。感谢支持。
        </text>
        <text class="hero-sub">
          每一份支持，都会让故事继续浮上来。
        </text>
      </view>
    </section>

    <view v-if="loading" class="content loading-state">
      <HgtLoading text="正在载入支持信息…" size="md" />
    </view>

    <view v-else class="content">
      <view class="donate-main">
        <template v-if="!done">
          <view class="method-block">
            <text class="label">
              支付方式
            </text>
            <view class="methods">
              <button
                v-for="channel in data.channels"
                :key="channel.method"
                class="method"
                :class="{ active: selected?.method === channel.method }"
                @click="selectedMethod = channel.method"
              >
                {{ channel.name }}
              </button>
            </view>
          </view>

          <view class="qr-panel">
            <image v-if="selected?.qr_code_url" :src="selected.qr_code_url" class="qr" mode="aspectFit" />
            <view v-else class="qr-placeholder">
              <text>◇</text>
              <text>后台暂未配置收款码</text>
            </view>
            <text class="scan-text">
              {{ selected?.name || '扫码支付' }}
            </text>
          </view>

          <text class="note">
            支付在收款方客户端完成，本站无需二次确认。
          </text>
        </template>

        <view v-else class="thanks">
          <text class="thanks-title">
            感谢你的支持！
          </text>
          <text class="thanks-copy">
            每一份支持都是我们前进的动力。
          </text>
          <button class="btn-ghost" @click="done = false">
            再次捐赠
          </button>
        </view>
      </view>

      <!-- 桌面端：列表卡片 -->
      <view class="donor-card">
        <text class="label donor-title">
          最近捐赠
        </text>
        <view v-if="displayDonations.length">
          <view v-for="item in displayDonations" :key="item.id" class="donor">
            <view class="donor-copy">
              <text>{{ item.donor_name }}</text>
              <text class="donor-time">
                {{ relative(item.donated_at) }}
              </text>
            </view>
            <text class="amount">
              ¥{{ item.amount }}
            </text>
          </view>
        </view>
        <text v-else class="empty-line">
          暂无捐赠记录
        </text>
        <text class="support-count">
          共 {{ supporterCount }} 位支持者
        </text>
      </view>
    </view>
  </view>
</template>

<style scoped>
.donate-page {
  min-height: 100%;
  padding-bottom: 48px;
  background: var(--hgt-bg);
  color: var(--hgt-text);
  font-family: var(--hgt-font-body);
}
.donate-hero {
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
  object-position: center;
  filter: brightness(1.25) contrast(0.94) saturate(1.05);
}
.hero-bg img {
  width: 100%;
  height: 100%;
  object-fit: cover !important;
  object-position: center !important;
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
  padding: 8px 24px 20px;
  display: grid;
  gap: 28px;
  grid-template-columns: minmax(280px, 1.25fr) minmax(240px, 0.85fr);
  align-items: start;
}
.loading-state {
  display: flex;
  min-height: 200px;
  grid-template-columns: 1fr;
  gap: 10px;
  align-items: center;
  justify-content: center;
  color: var(--hgt-text-2);
  font-size: 13px;
}
.donate-main {
  display: flex;
  width: 100%;
  max-width: 520px;
  gap: 16px;
  flex-direction: column;
  align-items: center;
}
.method-block {
  display: flex;
  width: min(100%, 320px);
  gap: 8px;
  flex-direction: column;
}
.label {
  color: var(--hgt-brand);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
  letter-spacing: 0.16em;
}
.methods {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: flex-start;
}
.method {
  display: flex;
  height: 34px;
  margin: 0;
  padding: 0 14px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-full);
  align-items: center;
  justify-content: center;
  background: transparent;
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
  line-height: 1;
}
.method.active {
  border-color: var(--hgt-brand);
  background: var(--hgt-brand-soft);
  color: var(--hgt-brand);
}
.method::after,
.btn-primary::after,
.btn-ghost::after {
  border: 0;
}
.qr-panel {
  position: relative;
  display: flex;
  box-sizing: border-box;
  width: min(100%, 360px);
  aspect-ratio: 1;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  align-items: center;
  justify-content: center;
  overflow: hidden;
  background: rgba(244, 252, 250, 0.96);
  flex: none;
}
.qr {
  position: absolute;
  inset: 12px;
  width: calc(100% - 24px);
  height: calc(100% - 24px);
}
.qr-placeholder {
  position: absolute;
  inset: 12px;
  display: flex;
  border: 1px dashed var(--hgt-border-soft);
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  gap: 8px;
  flex-direction: column;
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
}
.qr-placeholder > text:first-child {
  font-size: 36px;
}
.scan-text {
  position: absolute;
  z-index: 2;
  right: 10px;
  bottom: 10px;
  padding: 4px 8px;
  border-radius: var(--hgt-radius-xs);
  background: rgba(4, 20, 24, 0.78);
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
}
.btn-primary,
.btn-ghost {
  display: flex;
  height: 44px;
  margin: 0;
  padding: 0 16px;
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  font-size: 14px;
  line-height: 1;
}
.btn-primary {
  border: 0;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-weight: 600;
}
.btn-primary:disabled {
  opacity: 0.55;
}
.btn-ghost {
  border: 1px solid var(--hgt-border);
  background: transparent;
  color: var(--hgt-text-2);
}
.btn-primary.full {
  width: 100%;
}
.note {
  width: min(100%, 360px);
  color: var(--hgt-text-3);
  font-size: 12px;
  line-height: 1.5;
  text-align: center;
}

/* 移动端顶部弹幕 */
.donor-danmaku {
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  z-index: 3;
  display: none;
  box-sizing: border-box;
  width: 100%;
  padding: 10px 0 6px;
  pointer-events: none;
}
.danmaku-viewport {
  position: relative;
  box-sizing: border-box;
  width: 100%;
  height: 34px;
  overflow: hidden;
  mask-image: linear-gradient(90deg, transparent, #000 10%, #000 90%, transparent);
  -webkit-mask-image: linear-gradient(90deg, transparent, #000 10%, #000 90%, transparent);
}
.danmaku-track {
  position: absolute;
  left: 0;
  top: 0;
  display: flex;
  height: 34px;
  width: max-content;
  gap: 24px;
  align-items: center;
  white-space: nowrap;
  animation: danmaku-scroll 48s linear infinite;
  will-change: transform;
}
.danmaku-item {
  display: inline-flex;
  flex-direction: row;
  align-items: center;
  gap: 8px;
  flex: none;
  padding: 4px 12px;
  border: 1px solid rgba(180, 220, 220, 0.18);
  border-radius: var(--hgt-radius-full);
  background: rgba(4, 24, 28, 0.42);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
  line-height: 1;
}
.danmaku-name {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 13px;
}
.danmaku-amount {
  color: var(--hgt-brand);
  font-weight: 600;
}
.danmaku-time {
  color: rgba(200, 220, 220, 0.7);
  font-size: 11px;
}
@keyframes danmaku-scroll {
  from {
    transform: translateX(0);
  }
  to {
    transform: translateX(-50%);
  }
}

.donor-card {
  display: none;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  background: var(--hgt-card);
  overflow: hidden;
}
.donor-title {
  display: block;
  padding: 14px 16px;
  border-bottom: 1px solid var(--hgt-border-soft);
  color: var(--hgt-text);
}
.donor {
  display: flex;
  padding: 12px 16px;
  border-bottom: 1px solid var(--hgt-border-soft);
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  font-size: 13px;
}
.donor-copy {
  display: flex;
  min-width: 0;
  gap: 2px;
  flex-direction: column;
}
.donor-time {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
}
.amount {
  color: var(--hgt-brand);
  font-family: var(--hgt-font-display);
  font-size: 16px;
  font-weight: 600;
  flex: none;
}
.empty-line {
  display: block;
  padding: 24px 16px;
  color: var(--hgt-text-3);
  font-size: 13px;
  text-align: center;
}
.support-count {
  display: block;
  padding: 0;
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
  text-align: center;
}
.donor-card .support-count {
  padding: 12px;
}
.thanks {
  display: flex;
  width: min(100%, 360px);
  padding: 28px 20px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  gap: 10px;
  align-items: center;
  flex-direction: column;
  background: var(--hgt-card);
  text-align: center;
}
.thanks-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 20px;
  font-weight: 600;
}
.thanks-copy {
  color: var(--hgt-text-2);
  font-size: 13px;
  margin-bottom: 8px;
}

@media (max-width: 767px) {
  .donate-page {
    padding-bottom: calc(24px + env(safe-area-inset-bottom, 0px));
  }
  .content {
    grid-template-columns: 1fr;
    padding: 8px 16px 24px;
  }
  .donate-main {
    margin: 0 auto;
    max-width: min(100%, 360px);
  }
  .method-block {
    width: 100%;
  }
  .methods {
    justify-content: center;
  }
  .qr-panel {
    width: min(100%, 280px);
  }
  .note {
    width: min(100%, 280px);
  }
  .thanks {
    width: min(100%, 280px);
  }
  .donor-danmaku {
    display: block;
  }
  .donor-card {
    display: none;
  }
}

@media (min-width: 768px) {
  .donate-hero {
    padding: 64px 32px 72px;
  }
  .content {
    padding: 12px 32px 32px;
  }
  .donate-main {
    align-items: flex-start;
  }
  .method-block {
    width: 100%;
    max-width: none;
  }
  .qr-panel {
    width: min(100%, 420px);
  }
  .note {
    width: min(100%, 420px);
    text-align: left;
  }
  .donor-danmaku {
    display: none !important;
  }
  .donor-card {
    display: block;
  }
}
</style>
