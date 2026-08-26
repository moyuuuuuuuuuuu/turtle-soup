<script setup lang="ts">
import type { PublicQuestion } from '@/types/game'
import { gameApi, questionApi } from '@/api/turtle'
import { useGameStore } from '@/store/gameStore'

definePage({ name: 'question-detail', style: { navigationBarTitleText: '题目详情' } })
const route = useRoute()
const router = useRouter()
const store = useGameStore()
const question = ref<PublicQuestion | null>(null)
const loading = ref(true)
const starting = ref(false)

async function load() {
  try {
    question.value = await questionApi.read(String(route.params.id))
  }
  finally {
    loading.value = false
  }
}

async function start() {
  if (!question.value)
    return
  let confirmed = false
  if (question.value.risk_level === 'caution') {
    confirmed = await new Promise<boolean>((resolve) => {
      uni.showModal({
        title: '内容提醒',
        content: question.value?.risk_warning || '该题目包含可能令人不适的情节。',
        success: result => resolve(result.confirm),
      })
    })
    if (!confirmed)
      return
  }
  starting.value = true
  try {
    store.setGame(await gameApi.create(question.value.id, confirmed))
    router.replace({ name: 'game', params: { id: store.current!.id } })
  }
  finally {
    starting.value = false
  }
}

onMounted(load)
</script>

<template>
  <view class="min-h-screen bg-[#f5efe5] p-5">
    <wd-loading v-if="loading" />
    <template v-else-if="question">
      <view class="rounded-5 bg-white p-5 shadow-sm">
        <view class="flex items-center justify-between gap-3">
          <text class="text-6 font-bold">
            {{ question.title }}
          </text>
          <wd-tag v-if="question.risk_level === 'caution'" type="warning">
            内容提醒
          </wd-tag>
        </view>
        <text class="mt-5 block whitespace-pre-wrap text-4 leading-7">
          {{ question.surface }}
        </text>
        <view class="mt-5 flex flex-wrap gap-2 text-3 text-gray-500">
          <text>难度 {{ question.difficulty }}</text>
          <text v-for="tag in question.tags" :key="tag.id">
            #{{ tag.name }}
          </text>
        </view>
      </view>
      <wd-button block size="large" class="mt-6" :loading="starting" @click="start">
        开始推理
      </wd-button>
    </template>
  </view>
</template>
