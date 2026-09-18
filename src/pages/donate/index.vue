<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import type { DonationPage } from '@/types/game'
import { donationApi } from '@/api/turtle'

definePage({ name: 'donate', layout: 'tabbar', style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } } })
const data = ref<DonationPage>({ channels: [], recent_donations: [], supporter_count: 0 })
const selectedMethod = ref<'wechat' | 'alipay'>('wechat')
const done = ref(false)
const selected = computed(() => data.value.channels.find(item => item.method === selectedMethod.value) || data.value.channels[0])
onMounted(async () => {
  data.value = await donationApi.page(); if (data.value.channels[0])
    selectedMethod.value = data.value.channels[0].method
})
function relative(value: string) {
  const seconds = Math.max(0, (Date.now() - new Date(value).getTime()) / 1000)
  if (seconds < 3600)
    return `${Math.max(1, Math.floor(seconds / 60))}分钟前`
  if (seconds < 86400)
    return `${Math.floor(seconds / 3600)}小时前`
  return `${Math.floor(seconds / 86400)}天前`
}
</script>

<template>
  <view class="donate-page">
    <image class="donate-bg" src="/static/hgt/bg/bg_underwater_cave.jpg" mode="aspectFill" />
    <view class="donate-props" aria-hidden="true">
      <image src="/static/hgt/prop/prop_bottle.png" mode="aspectFit" />
      <image src="/static/hgt/prop/prop_letter.png" mode="aspectFit" />
      <image src="/static/hgt/prop/prop_photo.png" mode="aspectFit" />
    </view>
    <view class="page-head">
      <text class="eyebrow hgt-mono">
        ◆ 支持我们
      </text><text class="title hgt-display">
        捐赠
      </text>
    </view>
    <view class="donate-grid">
      <view class="donate-main">
        <view class="message">
          墨鱼海龟汤是一个由爱好者维护的公益项目。你的每一份捐赠都将直接用于服务器维护、内容创作和功能开发。感谢你让更多人能够享受推理的乐趣。
        </view>
        <template v-if="!done">
          <view>
            <text class="label hgt-mono">
              支付方式
            </text><view class="methods">
              <button v-for="channel in data.channels" :key="channel.method" class="method hgt-mono" :class="{ active: selected?.method === channel.method }" @click="selectedMethod = channel.method">
                {{ channel.name }}
              </button>
            </view>
          </view>
          <view class="qr-panel">
            <image v-if="selected?.qr_code_url" :src="selected.qr_code_url" class="qr" mode="aspectFit" /><view v-else class="qr-placeholder">
              <text>◇</text><text class="hgt-mono">
                后台暂未配置收款码
              </text>
            </view><text class="hgt-mono scan-text">
              {{ selected?.name || '扫码支付' }}
            </text>
          </view>
          <button class="done-button hgt-mono" :disabled="!selected" @click="done = true">
            我已完成支付
          </button>
        </template>
        <view v-else class="thanks">
          <text class="thanks-icon">
            ◈
          </text><text class="hgt-display thanks-title">
            感谢你的支持！
          </text><text>你的捐赠已收到。每一份支持都是我们前进的动力。</text><button class="method hgt-mono" @click="done = false">
            再次捐赠
          </button>
        </view>
      </view>
      <view class="donor-card">
        <text class="label donor-title hgt-mono">
          最近捐赠
        </text><view v-for="item in data.recent_donations" :key="item.id" class="donor">
          <view>
            <text>{{ item.donor_name }}</text><text class="donor-time hgt-mono">
              {{ relative(item.donated_at) }}
            </text>
          </view><text class="amount hgt-display">
            ¥{{ item.amount }}
          </text>
        </view><text class="support-count hgt-mono">
          共 {{ data.supporter_count }} 位支持者
        </text>
      </view>
    </view>
  </view>
</template>

<style scoped>
.donate-page {
  position: relative;
  min-height: 100%;
  padding-bottom: 48px;
  background: var(--hgt-bg);
  color: var(--hgt-text);
  overflow: hidden;
}
.donate-bg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  opacity: 0.18;
  pointer-events: none;
}
.donate-props {
  position: absolute;
  right: 24px;
  top: 96px;
  z-index: 0;
  display: flex;
  width: 140px;
  gap: 8px;
  flex-direction: column;
  opacity: 0.35;
  pointer-events: none;
}
.donate-props image {
  width: 100%;
  height: 88px;
}
.donate-page > .page-head,
.donate-page > .donate-grid {
  position: relative;
  z-index: 1;
}
.page-head {
  display: flex;
  padding: 32px 48px 24px;
  border-bottom: 1px solid var(--hgt-border);
  gap: 8px;
  flex-direction: column;
}
.eyebrow,
.label {
  color: var(--hgt-brand);
  font-size: 11px;
  letter-spacing: 0.22em;
}
.title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 28px;
  font-weight: 600;
}
.donate-grid {
  display: grid;
  box-sizing: border-box;
  width: min(var(--hgt-content-max), 100%);
  margin: 0 auto;
  padding: 28px 48px;
  gap: 28px;
  grid-template-columns: 3fr 2fr;
}
.donate-main {
  display: flex;
  gap: 20px;
  flex-direction: column;
}
.message {
  padding: 20px 22px;
  border: 1px solid var(--hgt-border);
  border-left: 2px solid var(--hgt-brand);
  border-radius: var(--hgt-radius-md);
  background: var(--hgt-card);
  color: var(--hgt-text-2);
  font-size: 14px;
  line-height: 1.8;
}
.methods {
  display: flex;
  margin-top: 10px;
  padding: 4px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  gap: 6px;
  background: var(--hgt-card);
}
.method {
  display: flex;
  height: 40px;
  margin: 0;
  padding: 0 12px;
  border: 0;
  border-radius: var(--hgt-radius-xs);
  flex: 1;
  align-items: center;
  justify-content: center;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1;
}
.method.active {
  background: var(--hgt-brand-soft);
  color: var(--hgt-brand);
}
.method::after,
.done-button::after {
  border: 0;
}
.qr-panel {
  position: relative;
  display: flex;
  box-sizing: border-box;
  width: min(100%, 400px);
  aspect-ratio: 1;
  padding: 0;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  align-self: center;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  background: var(--hgt-card);
}
.qr {
  position: absolute;
  inset: 16px;
  width: calc(100% - 32px);
  height: calc(100% - 32px);
}
.qr-placeholder {
  position: absolute;
  inset: 16px;
  display: flex;
  width: calc(100% - 32px);
  height: calc(100% - 32px);
  border: 1px dashed var(--hgt-border-soft);
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  gap: 10px;
  flex-direction: column;
  color: var(--hgt-text-3);
}
.qr-placeholder > text:first-child {
  font-size: 40px;
}
.scan-text {
  position: absolute;
  z-index: 3;
  right: 16px;
  bottom: 16px;
  padding: 5px 8px;
  border-radius: var(--hgt-radius-xs);
  background: rgba(7, 20, 24, 0.8);
  color: var(--hgt-text-2);
  font-size: 11px;
}
.done-button {
  display: flex;
  height: 46px;
  margin: 0;
  padding: 0;
  border: 0;
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-size: 14px;
  font-weight: 600;
  line-height: 1;
}
.donor-card {
  height: max-content;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  background: var(--hgt-card);
  overflow: hidden;
}
.donor-title {
  display: block;
  padding: 16px 18px;
  border-bottom: 1px solid var(--hgt-border);
  color: var(--hgt-text);
}
.donor {
  display: flex;
  padding: 12px 18px;
  border-bottom: 1px solid var(--hgt-border);
  align-items: center;
  justify-content: space-between;
  font-size: 14px;
}
.donor-time {
  display: block;
  margin-top: 4px;
  color: var(--hgt-text-3);
  font-size: 11px;
}
.amount {
  color: var(--hgt-brand);
  font-family: var(--hgt-font-display);
  font-size: 18px;
  font-weight: 600;
}
.support-count {
  display: block;
  padding: 12px;
  color: var(--hgt-text-3);
  font-size: 11px;
  text-align: center;
}
.thanks {
  display: flex;
  padding: 32px 20px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  align-items: center;
  gap: 12px;
  flex-direction: column;
  background: var(--hgt-card);
  text-align: center;
}
.thanks-icon {
  color: var(--hgt-brand);
  font-size: 36px;
}
.thanks-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 22px;
  font-weight: 600;
}
@media (max-width: 767px) {
  .page-head,
  .donate-grid {
    padding-right: 16px;
    padding-left: 16px;
  }
  .donate-grid {
    grid-template-columns: 1fr;
  }
  .qr-panel {
    width: min(100%, 320px);
  }
}
</style>
