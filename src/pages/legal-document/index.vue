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
    backgroundColor: light.value ? '#edeae4' : '#111111',
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
.legal-page{--background:#080808;--foreground:#f0f0f0;--card:#111;--muted-foreground:#666;--border:#292929;box-sizing:border-box;min-height:100vh;padding:36px 28px 60px;background:var(--background);color:var(--foreground)}.legal-page.light{--background:#edeae4;--foreground:#1c1c1a;--card:#e4e0d9;--muted-foreground:#7a7972;--border:#c8c4bc}.legal-head{display:flex;padding-bottom:22px;border-bottom:1px solid var(--border);gap:8px;flex-direction:column}.legal-eyebrow{color:var(--muted-foreground);font-size:10px;letter-spacing:.22em}.legal-title{font-size:30px}.legal-state{padding:80px 0;color:var(--muted-foreground);font-size:12px;text-align:center}.legal-content{display:block;padding:28px 0;color:var(--foreground);font-size:14px;line-height:1.9}
</style>
