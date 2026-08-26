<script setup lang="ts">
import { gameApi } from '@/api/turtle'
import { useGameSocket } from '@/composables/useGameSocket'
import { useGameStore } from '@/store/gameStore'

definePage({ name: 'game', style: { navigationBarTitleText: '推理中' } })
const route = useRoute()
const router = useRouter()
const store = useGameStore()
const socket = useGameSocket()
const question = ref('')
const busy = ref(false)
const errorMessage = ref('')
const game = computed(() => store.current)
const gameId = computed(() => String(route.params.id || route.query.id || ''))

async function refresh() {
  try {
    store.setGame(await socket.join(gameId.value))
  }
  catch {
    store.setGame(await gameApi.read(gameId.value))
  }
}
async function ask() {
  if (!question.value.trim())
    return
  busy.value = true
  errorMessage.value = ''
  try {
    store.setGame(await socket.ask(game.value!.id, question.value))
    question.value = ''
  }
  catch (error) {
    errorMessage.value = (error as Error).message
    uni.showToast({ title: 'AI 判定失败，可原样重试', icon: 'none' })
  }
  finally {
    busy.value = false
  }
}
async function hint(level: number) {
  try {
    store.setGame(await socket.hint(game.value!.id, level))
  }
  catch (error) {
    uni.showToast({ title: (error as Error).message, icon: 'none' })
  }
}
function abandon() {
  uni.showModal({
    title: '确认放弃？',
    content: '放弃后会立即展示汤底。',
    success: async (result) => {
      if (result.confirm) {
        store.setGame(await gameApi.abandon(game.value!.id))
        router.replace({ name: 'result', params: { id: game.value!.id } })
      }
    },
  })
}
onMounted(refresh)
</script>

<template>
  <view v-if="game" class="min-h-screen flex flex-col bg-[#f5efe5]">
    <view class="bg-[#2f493c] p-4 text-white">
      <text class="block text-5 font-bold">
        {{ game.title }}
      </text>
      <text class="mt-2 block text-3 opacity-85">
        {{ game.surface }}
      </text>
      <text class="mt-3 block text-3">
        剩余提问 {{ game.remaining_questions }} / {{ game.question_limit }}
      </text>
      <text class="mt-1 block text-3 opacity-75">
        {{ socket.connected.value ? 'AI 主持人在线' : socket.reconnecting.value ? '正在重新连接…' : '连接已断开' }}
      </text>
    </view>
    <scroll-view scroll-y class="h-0 flex-1 p-4">
      <view v-for="message in game.messages" :key="message.sequence" class="mb-3 flex" :class="message.role === 'player' ? 'justify-end' : 'justify-start'">
        <view class="max-w-[82%] rounded-4 px-4 py-3" :class="message.role === 'player' ? 'bg-[#46695a] text-white' : 'bg-white'">
          {{ message.content }}
        </view>
      </view>
    </scroll-view>
    <view class="border-t bg-white p-3">
      <wd-notice-bar v-if="errorMessage" type="warning" :text="`上次问题未扣次数：${errorMessage}`" closable @close="errorMessage = ''" />
      <view v-if="game.remaining_questions === 0" class="mb-3 rounded-3 bg-orange-50 p-3 text-3 text-orange-700">
        提问次数已经用完，请提交最终猜测。
      </view>
      <view class="mb-3 flex gap-2">
        <wd-button v-for="level in [1, 2, 3]" :key="level" size="small" plain :disabled="game.used_hints.includes(level)" @click="hint(level)">
          提示 {{ level }}
        </wd-button>
        <wd-button size="small" type="warning" plain @click="router.push({ name: 'guess', params: { id: game.id } })">
          最终猜测
        </wd-button>
        <wd-button size="small" type="danger" plain @click="abandon">
          放弃
        </wd-button>
      </view>
      <view class="flex gap-2">
        <wd-input v-model="question" placeholder="输入只能用是/否回答的问题" :disabled="game.remaining_questions === 0" />
        <wd-button :loading="busy" :disabled="game.remaining_questions === 0" @click="ask">
          提问
        </wd-button>
      </view>
    </view>
  </view>
</template>
