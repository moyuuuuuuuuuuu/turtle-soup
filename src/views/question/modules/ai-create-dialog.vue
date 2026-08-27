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
    <ElDescriptions
      v-if="task?.status === 'succeeded' && task.result"
      class="mt-4"
      :column="1"
      border
    >
      <ElDescriptionsItem label="标题">{{ primaryTranslation?.title }}</ElDescriptionsItem>
      <ElDescriptionsItem label="难度">
        {{ QUESTION_DIFFICULTY_LABELS[task.result.difficulty] || task.result.difficulty }}
      </ElDescriptionsItem>
      <ElDescriptionsItem label="建议人数">
        {{ task.result.min_players || 1 }}–{{ task.result.max_players || 8 }} 人
      </ElDescriptionsItem>
      <ElDescriptionsItem label="标签">
        <div v-if="task.result.suggested_tags?.length" class="flex flex-wrap gap-2">
          <ElTag v-for="tag in task.result.suggested_tags" :key="tag.slug || tag">
            {{ tag.name || tag }}
          </ElTag>
        </div>
        <span v-else>无</span>
      </ElDescriptionsItem>
      <ElDescriptionsItem label="汤面">{{ primaryTranslation?.surface }}</ElDescriptionsItem>
      <ElDescriptionsItem label="汤底">{{ primaryTranslation?.bottom }}</ElDescriptionsItem>
      <ElDescriptionsItem label="关键推理点">
        <ol class="m-0 pl-5">
          <li v-for="point in task.result.points" :key="point.sort">
            {{ contentOf(point.translations) }}
            <ElTag v-if="point.is_required" class="ml-2" size="small" type="danger">必需</ElTag>
            <span class="ml-2 text-gray-400">权重 {{ point.weight }}</span>
          </li>
        </ol>
      </ElDescriptionsItem>
      <ElDescriptionsItem label="三级提示">
        <ol class="m-0 pl-5">
          <li v-for="hint in sortedHints" :key="hint.level">
            第 {{ hint.level }} 级：{{ contentOf(hint.translations) }}
          </li>
        </ol>
      </ElDescriptionsItem>
      <ElDescriptionsItem label="风险等级">
        <ElTag :type="riskTagType">{{ riskLevelLabel }}</ElTag>
      </ElDescriptionsItem>
      <ElDescriptionsItem label="风险类型">
        <div v-if="riskTypeLabels.length" class="flex flex-wrap gap-2">
          <ElTag v-for="item in riskTypeLabels" :key="item" type="warning">{{ item }}</ElTag>
        </div>
        <span v-else>无</span>
      </ElDescriptionsItem>
      <ElDescriptionsItem label="风险说明">{{ task.result.risk_note || '无' }}</ElDescriptionsItem>
      <ElDescriptionsItem label="人工审核提示">
        <ul v-if="task.result.quality_warnings?.length" class="m-0 pl-5">
          <li v-for="warning in task.result.quality_warnings" :key="warning">{{ warning }}</li>
        </ul>
        <span v-else>无</span>
      </ElDescriptionsItem>
    </ElDescriptions>
    <template #footer>
      <ElButton @click="visible = false">关闭</ElButton>
      <ElButton v-if="task?.status === 'failed'" @click="retry">重试</ElButton>
      <ElButton v-if="task?.status === 'succeeded'" type="success" @click="adopt"
        >采纳为草稿</ElButton
      >
      <ElButton v-if="task?.status === 'succeeded'" type="primary" @click="resetForNewStory"
        >解析新题目</ElButton
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
  import {
    AI_TASK_STATUS_LABELS,
    QUESTION_DIFFICULTY_LABELS,
    QUESTION_RISK_LEVEL_LABELS,
    QUESTION_RISK_TYPE_LABELS,
    QuestionRiskLevelEnum,
    enumLabel
  } from '@/enums/questionEnum'
  const visible = defineModel<boolean>({ required: true })
  const emit = defineEmits<{ adopted: [question: QuestionPayload] }>()
  const story = ref(''),
    task = ref<Record<string, any> | null>(null),
    submitting = ref(false)
  let timer: ReturnType<typeof setTimeout> | undefined
  function stopPolling() {
    if (timer) clearTimeout(timer)
    timer = undefined
  }
  function resetForNewStory() {
    stopPolling()
    story.value = ''
    task.value = null
    submitting.value = false
  }
  const aiTaskStatusLabel = (status: unknown) => enumLabel(AI_TASK_STATUS_LABELS, status)
  const primaryTranslation = computed(() =>
    (task.value?.result?.translations || []).find(
      (translation: Record<string, unknown>) => translation.language === 'zh-CN'
    )
  )
  const contentOf = (translations: Array<Record<string, unknown>> = []) =>
    translations.find((translation) => translation.language === 'zh-CN')?.content || ''
  const sortedHints = computed(() =>
    [...(task.value?.result?.hints || [])].sort(
      (left: Record<string, number>, right: Record<string, number>) => left.level - right.level
    )
  )
  const riskLevelLabel = computed(() =>
    enumLabel(QUESTION_RISK_LEVEL_LABELS, task.value?.result?.risk_level)
  )
  const riskTypeLabels = computed(() =>
    (task.value?.result?.risk_types || []).map((value: unknown) =>
      enumLabel(QUESTION_RISK_TYPE_LABELS, value)
    )
  )
  const riskTagType = computed(() => {
    if (task.value?.result?.risk_level === QuestionRiskLevelEnum.Restricted) return 'danger'
    if (task.value?.result?.risk_level === QuestionRiskLevelEnum.Caution) return 'warning'
    return 'success'
  })
  async function poll() {
    const taskId = task.value?.id
    if (!taskId) return
    const latest = await api.aiTask(taskId)
    if (!visible.value || task.value?.id !== taskId) return
    task.value = latest
    if (['pending', 'processing'].includes(task.value.status)) {
      stopPolling()
      timer = setTimeout(poll, 1500)
    }
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
  watch(visible, (opened) => {
    if (opened) resetForNewStory()
    else stopPolling()
  })
  onUnmounted(stopPolling)
</script>
