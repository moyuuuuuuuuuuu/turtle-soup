<script setup lang="ts">
import type { DonationPage } from '@/types/game'
import { donationApi } from '@/api/turtle'

definePage({ name: 'donate', layout: 'tabbar', style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } } })

const data = ref<DonationPage>({ channels: [], recent_donations: [], supporter_count: 0 })
const selectedMethod = ref<'wechat' | 'alipay'>('wechat')
const done = ref(false)
const loading = ref(true)

const selected = computed(() =>
  data.value.channels.find(item => item.method === selectedMethod.value) || data.value.channels[0],
)

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
    <view class="page-head">
      <text class="page-title">
        支持项目
      </text>
      <text class="page-sub">
        捐赠将用于服务器维护、内容创作与功能开发。感谢支持。
      </text>
    </view>

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

          <button class="btn-primary full" :disabled="!selected" @click="done = true">
            我已完成支付
          </button>
          <text class="note">
            支付在收款方客户端完成，本站仅记录你的确认。
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

      <view class="donor-card">
        <text class="label donor-title">
          最近捐赠
        </text>
        <view v-if="data.recent_donations.length">
          <view v-for="item in data.recent_donations" :key="item.id" class="donor">
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
          共 {{ data.supporter_count }} 位支持者
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
.page-head {
  box-sizing: border-box;
  width: min(1100px, 100%);
  margin: 0 auto;
  padding: 36px 24px 16px;
}
.page-title {
  display: block;
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-display);
  font-size: 28px;
  font-weight: 600;
  letter-spacing: 0.08em;
}
.page-sub {
  display: block;
  margin-top: 10px;
  max-width: 560px;
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-display);
  font-size: 14px;
  line-height: 1.7;
}
.content {
  box-sizing: border-box;
  width: min(1100px, 100%);
  margin: 0 auto;
  padding: 12px 24px 20px;
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
  max-width: 520px;
  gap: 16px;
  flex-direction: column;
}
.method-block {
  display: flex;
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
  width: min(100%, 420px);
  aspect-ratio: 1;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  align-items: center;
  justify-content: center;
  overflow: hidden;
  background: rgba(244, 252, 250, 0.96);
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
  right: 12px;
  bottom: 12px;
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
  color: var(--hgt-text-3);
  font-size: 12px;
  line-height: 1.5;
  text-align: center;
}
.donor-card {
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
  padding: 12px;
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
  text-align: center;
}
.thanks {
  display: flex;
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
  .content {
    grid-template-columns: 1fr;
  }
  .qr-panel {
    width: min(100%, 320px);
    align-self: flex-start;
  }
  .page-head,
  .content {
    padding-left: 16px;
    padding-right: 16px;
  }
}
</style>
