<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import { ensureAnonymousSession, gameApi } from '@/api/turtle'

definePage({ name: 'history', style: { navigationBarTitleText: '游戏记录' } }); const router = useRouter(); const items = ref<Array<{ id: string, status: string, title: string, difficulty: number }>>([]); onMounted(async () => { await ensureAnonymousSession(); items.value = await gameApi.history() })
</script>

<template>
  <view class="min-h-screen bg-[#f5efe5] p-4">
    <view v-for="item in items" :key="item.id" class="mb-3 rounded-4 bg-white p-4" @click="router.push({ name: ['solved', 'finished', 'abandoned'].includes(item.status) ? 'result' : 'game', params: { id: item.id } })">
      <text class="block font-bold">
        {{ item.title }}
      </text><text class="mt-2 block text-3 text-gray-500">
        难度 {{ item.difficulty }} · {{ item.status }}
      </text>
    </view>
    <view v-if="!items.length" class="py-20 text-center text-gray-400">
      还没有游戏记录
    </view>
  </view>
</template>
