<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import type { PublicQuestion } from '@/types/game'; import { questionApi } from '@/api/turtle'

definePage({ name: 'questions', style: { navigationBarTitleText: '选择题目' } }); const router = useRouter(); const items = ref<PublicQuestion[]>([]); const difficulty = ref<number>(); const loading = ref(false); async function load() {
  loading.value = true; try { items.value = (await questionApi.list(difficulty.value ? { difficulty: difficulty.value } : {})).items }
  finally { loading.value = false }
}onMounted(load)
</script>

<template>
  <view class="min-h-screen bg-[#f7f4ed] p-4">
    <view class="mb-4 flex gap-2 overflow-x-auto">
      <wd-button v-for="level in [1, 2, 3, 4, 5]" :key="level" size="small" :type="difficulty === level ? 'primary' : 'info'" @click="difficulty = difficulty === level ? undefined : level;load()">
        难度 {{ level }}
      </wd-button>
    </view><wd-loading v-if="loading" /><view v-for="item in items" :key="item.id" class="mb-4 rounded-4 bg-white p-4 shadow-sm" @click="router.push({ name: 'question-detail', params: { id: item.id } })">
      <view class="flex justify-between">
        <text class="font-bold">
          {{ item.title }}
        </text><wd-tag v-if="item.risk_level === 'caution'" type="warning">
          内容提醒
        </wd-tag>
      </view><text class="line-clamp-2 mt-2 block text-3 text-gray-500">
        {{ item.surface }}
      </text><text class="mt-3 block text-3">
        难度 {{ item.difficulty }}
      </text>
    </view>
  </view>
</template>
