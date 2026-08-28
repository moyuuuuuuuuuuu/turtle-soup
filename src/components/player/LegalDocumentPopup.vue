<script setup lang="ts">
import type { LegalDocuments } from '@/api/player'

const props = defineProps<{
  documents: LegalDocuments
  light: boolean
}>()

const visible = defineModel<boolean>('visible', { default: false })
const kind = defineModel<keyof LegalDocuments>('kind', { default: 'service_terms' })
const title = computed(() => kind.value === 'service_terms' ? '服务条款' : '隐私政策')
const content = computed(() => props.documents[kind.value || 'service_terms'] || '<p>协议内容暂未配置。</p>')
</script>

<template>
  <wd-popup v-model="visible" position="center" :close-on-click-modal="true" :custom-class="`legal-document-popup ${props.light ? 'legal-document-popup-light' : ''}`">
    <view class="legal-document-card">
      <view class="legal-document-header">
        <text class="legal-document-title hgt-display">
          {{ title }}
        </text>
        <button class="legal-document-close" aria-label="关闭协议" @click="visible = false">
          ×
        </button>
      </view>
      <scroll-view class="legal-document-scroll" scroll-y>
        <mp-html class="legal-document-content" :content="content" selectable />
      </scroll-view>
    </view>
  </wd-popup>
</template>

<style scoped>
:global(.legal-document-popup){--background:#080808;--foreground:#f0f0f0;--card:#111;--muted-foreground:#666;--border:#292929;width:min(680px,calc(100vw - 32px));border:1px solid var(--border);border-radius:0;background:var(--card);color:var(--foreground);overflow:hidden}:global(.legal-document-popup-light){--background:#edeae4;--foreground:#1c1c1a;--card:#e4e0d9;--muted-foreground:#7a7972;--border:#c8c4bc}.legal-document-card{display:flex;box-sizing:border-box;height:min(76vh,720px);flex-direction:column;background:var(--card);color:var(--foreground)}.legal-document-header{display:flex;height:62px;padding:0 20px;border-bottom:1px solid var(--border);flex:none;align-items:center;justify-content:space-between}.legal-document-title{font-size:22px}.legal-document-close{display:flex;width:34px;height:34px;margin:0;padding:0;border:1px solid var(--border);border-radius:0;align-items:center;justify-content:center;background:transparent;color:var(--foreground);font-size:20px;line-height:1}.legal-document-scroll{min-height:0;flex:1;background:var(--card)}.legal-document-content{display:block;padding:24px;color:var(--foreground);font-size:14px;line-height:1.9}@media(max-width:767px){.legal-document-card{height:80vh}.legal-document-header{height:54px;padding:0 16px}.legal-document-content{padding:18px}}
</style>
