<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import { useGameSocket } from '@/composables/useGameSocket'; import { useGameStore } from '@/store/gameStore'

definePage({ name: 'guess', style: { navigationBarTitleText: '最终猜测' } }); const route = useRoute(); const router = useRouter(); const store = useGameStore(); const socket = useGameSocket(); const guess = ref(''); const busy = ref(false); async function submit() {
  if (!guess.value.trim())
    return; busy.value = true; try { store.setGame(await socket.guess(String(route.params.id), guess.value)); router.replace({ name: 'result', params: { id: String(route.params.id) } }) }
  finally { busy.value = false }
}
</script>

<template>
  <view class="min-h-screen bg-[#f5efe5] p-5">
    <text class="block text-6 font-bold">
      说出完整真相
    </text><text class="my-3 block text-3 text-gray-500">
      最终猜测仅有一次，提交后立即结算。
    </text><wd-textarea v-model="guess" :maxlength="2000" show-word-limit placeholder="描述人物、事件和关键因果" /><wd-button block class="mt-6" :loading="busy" @click="submit">
      提交最终猜测
    </wd-button>
  </view>
</template>
