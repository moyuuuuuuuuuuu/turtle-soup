<template>
  <ElDialog v-model="visible" title="AI 解析故事" width="680px" :close-on-click-modal="false">
    <ElInput
      v-model="story"
      type="textarea"
      :rows="10"
      placeholder="输入完整故事，AI 将生成汤面、汤底、关键点和三级提示"
    />
    <ElProgress
      v-if="task"
      class="mt-4"
      :percentage="task.progress || 0"
      :status="task.status === 'failed' ? 'exception' : undefined"
    />
    <div v-if="task?.status" class="mt-2 text-sm text-gray-500">
      当前状态：{{ aiTaskStatusLabel(task.status) }}
    </div>
    <ElAlert
      v-if="task?.error"
      class="mt-3"
      type="error"
      :title="task.error.code"
      :description="task.error.message"
    />
    <ElAlert
      v-if="task?.status === 'succeeded'"
      class="mt-3"
      type="success"
      title="解析完成，请采纳为草稿后人工核对"
    />
    <template #footer>
      <ElButton @click="visible = false">关闭</ElButton>
      <ElButton v-if="task?.status === 'failed'" @click="retry">重试</ElButton>
      <ElButton v-if="task?.status === 'succeeded'" type="success" @click="adopt"
        >采纳为草稿</ElButton
      >
      <ElButton
        v-if="!task || task.status === 'failed'"
        type="primary"
        :loading="submitting"
        @click="start"
        >开始解析</ElButton
      >
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { ElMessage } from 'element-plus'
  import api, { type QuestionPayload } from '@/api/question'
  import { AI_TASK_STATUS_LABELS, enumLabel } from '@/enums/questionEnum'
  const visible = defineModel<boolean>({ required: true })
  const emit = defineEmits<{ adopted: [question: QuestionPayload] }>()
  const story = ref(''),
    task = ref<Record<string, any> | null>(null),
    submitting = ref(false)
  let timer: ReturnType<typeof setTimeout> | undefined
  const aiTaskStatusLabel = (status: unknown) => enumLabel(AI_TASK_STATUS_LABELS, status)
  async function poll() {
    if (!task.value?.id) return
    task.value = await api.aiTask(task.value.id)
    if (['pending', 'processing'].includes(task.value.status)) timer = setTimeout(poll, 1500)
  }
  async function start() {
    if (story.value.trim().length < 20) return ElMessage.warning('请至少输入 20 个字')
    submitting.value = true
    try {
      task.value = await api.createAiTask({
        story: story.value,
        source_language: 'zh-CN',
        target_languages: ['zh-CN', 'en-US']
      })
      poll()
    } finally {
      submitting.value = false
    }
  }
  async function retry() {
    if (!task.value) return
    task.value = await api.retryAiTask(task.value.id)
    poll()
  }
  async function adopt() {
    if (!task.value) return
    emit('adopted', await api.adoptAiTask(task.value.id))
  }
  onUnmounted(() => timer && clearTimeout(timer))
</script>
