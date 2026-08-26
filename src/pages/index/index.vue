<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import { ensureAnonymousSession, gameApi, questionApi } from '@/api/turtle'; import { useGameStore } from '@/store/gameStore'

definePage({ name: 'home', layout: 'tabbar', style: { navigationBarTitleText: '海龟汤' } }); const router = useRouter(); const store = useGameStore(); const loading = ref(false); async function randomStart() {
  loading.value = true; try {
    await ensureAnonymousSession(); const q = await questionApi.random(); let confirmed = false; if (q.risk_level === 'caution') {
      confirmed = await new Promise(resolve => uni.showModal({ title: '内容提醒', content: q.risk_warning || '', success: r => resolve(r.confirm) })); if (!confirmed)
        return
    }store.setGame(await gameApi.create(q.id, confirmed)); router.push({ name: 'game', params: { id: store.current!.id } })
  }
  finally { loading.value = false }
}onMounted(ensureAnonymousSession)
</script>

<template>
  <view class="min-h-screen bg-[#f5efe5] px-5 py-10">
    <view class="rounded-6 bg-[#2f493c] p-6 text-white">
      <text class="block text-8 font-bold">
        海龟汤
      </text><text class="mt-3 block text-4 opacity-80">
        从一句离奇故事开始，问出隐藏的真相。
      </text>
    </view><view class="grid mt-8 gap-4">
      <wd-button block size="large" :loading="loading" @click="randomStart">
        随机开局
      </wd-button><wd-button plain block size="large" @click="router.push({ name: 'questions' })">
        浏览题库
      </wd-button><wd-button v-if="store.current" plain block @click="router.push({ name: 'game', params: { id: store.current.id } })">
        继续上局
      </wd-button><wd-button plain block @click="router.push({ name: 'history' })">
        游戏记录
      </wd-button>
    </view>
  </view>
</template>
