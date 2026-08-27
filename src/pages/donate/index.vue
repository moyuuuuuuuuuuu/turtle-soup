<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import type { DonationPage } from '@/types/game'
import { donationApi } from '@/api/turtle'

definePage({ name: 'donate', layout: 'tabbar', style: { navigationStyle: 'custom' } })
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
          海龟汤是一个由爱好者维护的公益项目。你的每一份捐赠都将直接用于服务器维护、内容创作和功能开发。感谢你让更多人能够享受推理的乐趣。
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
.donate-page{min-height:100vh}.page-head{padding:34px 48px;border-bottom:1px solid var(--border);display:flex;flex-direction:column;gap:8px}.eyebrow,.label{font-size:11px;letter-spacing:.3em;color:var(--muted-foreground)}.title{font-size:38px}.donate-grid{padding:40px 48px;max-width:980px;display:grid;grid-template-columns:3fr 2fr;gap:32px}.donate-main{display:flex;flex-direction:column;gap:30px}.message{border-left:2px solid var(--foreground);padding-left:24px;line-height:1.9;color:var(--accent);font-size:14px}.methods{display:flex;gap:12px;margin-top:15px}.method{margin:0;flex:1;height:44px;line-height:42px;border-radius:0;border:1px solid var(--border);background:transparent;color:var(--muted-foreground);font-size:12px}.method.active{border-color:var(--foreground);color:var(--foreground);background:var(--secondary)}button::after{display:none}.qr-panel{border:1px solid var(--border);background:var(--card);padding:30px;display:flex;flex-direction:column;align-items:center;gap:15px}.qr{width:210px;height:210px}.qr-placeholder{width:210px;height:210px;border:1px solid var(--border);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;color:var(--muted-foreground)}.qr-placeholder>text:first-child{font-size:50px}.scan-text{font-size:11px;color:var(--muted-foreground)}.done-button{margin:0;height:48px;line-height:48px;border-radius:0;background:var(--foreground);color:var(--background);font-size:12px;letter-spacing:.15em}.donor-card{height:max-content;border:1px solid var(--border);background:var(--card)}.donor-title{display:block;padding:17px 20px;border-bottom:1px solid var(--border)}.donor{padding:15px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;font-size:14px}.donor-time{display:block;color:var(--muted-foreground);font-size:10px;margin-top:5px}.amount{font-size:18px}.support-count{display:block;text-align:center;padding:13px;color:var(--muted-foreground);font-size:10px}.thanks{border:1px solid #4ade80;background:var(--card);padding:40px;display:flex;flex-direction:column;gap:18px;align-items:center;text-align:center;color:var(--muted-foreground)}.thanks-icon{font-size:48px;color:#4ade80}.thanks-title{font-size:26px;color:var(--foreground)}
.donate-main{gap:22px}.message{padding:22px 24px;border:1px solid var(--border);border-left:2px solid var(--foreground);background:linear-gradient(110deg,var(--card),transparent)}.methods{gap:8px;padding:5px;border:1px solid var(--border);background:var(--card)}.method{display:flex;padding:0;align-items:center;justify-content:center;line-height:1}.qr-panel{position:relative;box-sizing:border-box;overflow:hidden;width:min(100%,420px);aspect-ratio:1/1;padding:0;align-self:center;justify-content:center;background:var(--card)}.qr-panel::before{position:absolute;z-index:2;inset:12px;border:1px solid var(--border);pointer-events:none;content:''}.qr,.qr-placeholder{position:absolute;inset:12px;width:calc(100% - 24px);height:calc(100% - 24px)}.scan-text{position:absolute;z-index:3;right:20px;bottom:20px;padding:5px 8px;background:color-mix(in srgb,var(--card) 86%,transparent);letter-spacing:.16em}.done-button{display:flex;padding:0;align-items:center;justify-content:center;line-height:1}
@media(max-width:767px){.page-head{padding:28px 30px}.donate-grid{padding:30px;grid-template-columns:1fr}.qr-panel{width:min(100%,360px)}}
</style>
