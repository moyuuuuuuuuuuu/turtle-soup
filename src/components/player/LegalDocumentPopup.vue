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
:global(.legal-document-popup) {
  box-sizing: border-box;
  width: min(680px, calc(100vw - 32px));
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-lg);
  background: var(--hgt-card);
  color: var(--hgt-text);
  overflow: hidden;
}
:global(.legal-document-popup-light) {
  background: var(--hgt-card);
  color: var(--hgt-text);
}
.legal-document-card {
  display: flex;
  box-sizing: border-box;
  height: min(76vh, 720px);
  flex-direction: column;
  background: var(--hgt-card);
  color: var(--hgt-text);
}
.legal-document-header {
  display: flex;
  height: 56px;
  padding: 0 18px;
  border-bottom: 1px solid var(--hgt-border);
  flex: none;
  align-items: center;
  justify-content: space-between;
}
.legal-document-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 18px;
  font-weight: 600;
}
.legal-document-close {
  display: flex;
  width: 32px;
  height: 32px;
  margin: 0;
  padding: 0;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 18px;
  line-height: 1;
}
.legal-document-close::after {
  border: 0;
}
.legal-document-scroll {
  min-height: 0;
  flex: 1;
  background: var(--hgt-card);
}
.legal-document-content {
  display: block;
  padding: 20px;
  color: var(--hgt-text);
  font-size: 14px;
  line-height: 1.85;
}
@media (max-width: 767px) {
  .legal-document-card {
    height: 80vh;
  }
  .legal-document-content {
    padding: 16px;
  }
}
</style>
