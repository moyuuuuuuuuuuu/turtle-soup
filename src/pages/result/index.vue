<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import { gameApi } from '@/api/turtle'; import { useGameStore } from '@/store/gameStore'

definePage({ name: 'result', style: { navigationBarTitleText: '游戏结算' } }); const route = useRoute(); const router = useRouter(); const store = useGameStore(); const game = computed(() => store.current); onMounted(async () => store.setGame(await gameApi.read(String(route.params.id))))
</script>

<template>
  <view v-if="game" class="min-h-screen bg-[#f5efe5] p-5">
    <view class="rounded-5 bg-white p-5">
      <text class="block text-6 font-bold">
        {{ game.status === 'solved' ? '推理成功' : '本局结束' }}
      </text><text v-if="game.guess" class="mt-2 block">
        {{ game.guess.summary }}
      </text><text class="mt-6 block text-4 font-bold">
        汤底
      </text><text class="mt-2 block leading-7">
        {{ game.bottom }}
      </text><text class="mt-6 block text-4 font-bold">
        关键推理点
      </text><view v-for="point in game.points" :key="point.key" class="mt-2 rounded-3 bg-[#f7f4ed] p-3">
        {{ point.content }}
      </view>
    </view><wd-button block class="mt-6" @click="router.replace({ name: 'home' })">
      返回首页
    </wd-button>
  </view>
</template>
