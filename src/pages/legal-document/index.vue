<script setup lang="ts">
import type { LegalDocuments } from '@/api/player'
import { playerApi } from '@/api/player'
import { storedTheme } from '@/utils/theme'

definePage({
  name: 'legal-document',
  style: { navigationBarTitleText: '服务条款' },
})

const route = useRoute()
const kind = computed<keyof LegalDocuments>(() => route.query.type === 'privacy_policy' ? 'privacy_policy' : 'service_terms')
const title = computed(() => kind.value === 'service_terms' ? '服务条款' : '隐私政策')
const content = ref('')
const loading = ref(true)
const light = ref(storedTheme() === 'light')

onMounted(async () => {
  uni.setNavigationBarTitle({ title: title.value })
  uni.setNavigationBarColor({
    frontColor: light.value ? '#000000' : '#ffffff',
    backgroundColor: light.value ? '#f8f8f7' : '#1a1a18',
  })
  try {
    const documents = await playerApi.legalDocuments()
    content.value = documents[kind.value]
  }
  catch (error) {
    uni.showToast({ title: (error as Error).message || '协议加载失败', icon: 'none' })
  }
  finally {
    loading.value = false
  }
})
</script>

<template>
  <view class="legal-page" :class="{ light }">
    <HgtFeedbackHost />
    <view class="legal-head">
      <text class="legal-eyebrow hgt-mono">
        ◇ LEGAL
      </text>
      <text class="legal-title hgt-display">
        {{ title }}
      </text>
    </view>
    <view v-if="loading" class="legal-state hgt-mono">
      正在加载…
    </view>
    <view v-else-if="!content" class="legal-state hgt-mono">
      协议内容暂未配置
    </view>
    <mp-html v-else class="legal-content" :content="content" selectable />
  </view>
</template>

<style scoped>
.legal-page {
  box-sizing: border-box;
  min-height: 100vh;
  padding: 36px 28px 60px;
  background: var(--hgt-bg);
  color: var(--hgt-text);
}
.legal-page.light {
  background: var(--hgt-bg);
  color: var(--hgt-text);
}
.legal-head {
  display: flex;
  max-width: 720px;
  margin: 0 auto;
  padding-bottom: 20px;
  border-bottom: 1px solid var(--hgt-border);
  gap: 8px;
  flex-direction: column;
}
.legal-eyebrow {
  color: var(--hgt-brand);
  font-size: 11px;
  letter-spacing: 0.22em;
}
.legal-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 28px;
  font-weight: 600;
}
.legal-state {
  padding: 64px 0;
  color: var(--hgt-text-2);
  font-size: 13px;
  text-align: center;
}
.legal-content {
  display: block;
  max-width: 720px;
  margin: 0 auto;
  padding: 24px 0;
  color: var(--hgt-text);
  font-size: 14px;
  line-height: 1.85;
}
</style>
