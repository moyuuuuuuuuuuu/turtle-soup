<template>
  <div class="page-content">
    <ElCard shadow="never">
      <div class="mb-4 flex flex-wrap gap-3">
        <ElInput v-model="search.keyword" placeholder="标题或汤面" clearable class="!w-56" />
        <ElSelect v-model="search.status" placeholder="状态" clearable class="!w-36"
          ><ElOption v-for="item in QUESTION_STATUS_OPTIONS" :key="item.value" v-bind="item"
        /></ElSelect>
        <ElSelect v-model="search.is_featured" placeholder="是否精选" clearable class="!w-36">
          <ElOption label="精选" value="1" /><ElOption label="非精选" value="0" />
        </ElSelect>
        <ElButton type="primary" @click="load">查询</ElButton
        ><ElButton v-permission="'question:edit'" @click="openEditor()">新增题目</ElButton
        ><ElButton v-permission="'question:ai:create'" @click="aiVisible = true">AI 创作</ElButton>
      </div>
      <ElTable v-loading="loading" :data="rows">
        <ElTableColumn prop="public_id" label="题目 ID" width="230" /><ElTableColumn
          label="标题"
          min-width="200"
          ><template #default="{ row }">{{ titleOf(row) }}</template></ElTableColumn
        ><ElTableColumn label="难度" width="100"
          ><template #default="{ row }">{{
            QUESTION_DIFFICULTY_LABELS[row.difficulty]
          }}</template></ElTableColumn
        ><ElTableColumn label="来源" width="110"
          ><template #default="{ row }">{{
            enumLabel(QUESTION_SOURCE_LABELS, row.source_type)
          }}</template></ElTableColumn
        ><ElTableColumn prop="source_author" label="来源作者" min-width="180"
          ><template #default="{ row }">{{ row.source_author || '-' }}</template></ElTableColumn
        ><ElTableColumn prop="source_license" label="来源许可" width="130"
          ><template #default="{ row }">{{ row.source_license || '-' }}</template></ElTableColumn
        ><ElTableColumn label="原文链接" min-width="220"
          ><template #default="{ row }"
            ><ElLink
              v-if="row.source_url"
              :href="row.source_url"
              target="_blank"
              rel="noopener noreferrer"
              type="primary"
              >{{ row.source_url }}</ElLink
            ><span v-else>-</span></template
          ></ElTableColumn
        ><ElTableColumn label="状态" width="100"
          ><template #default="{ row }"
            ><ElTag>{{ enumLabel(QUESTION_STATUS_LABELS, row.status) }}</ElTag></template
          ></ElTableColumn
        ><ElTableColumn prop="version" label="版本" width="80" />
        <ElTableColumn label="精选" width="90">
          <template #default="{ row }"
            ><ElTag v-if="row.is_featured" type="warning">精选</ElTag
            ><span v-else>-</span></template
          >
        </ElTableColumn>
        <ElTableColumn label="操作" width="420" fixed="right"
          ><template #default="{ row }"
            ><ElButton link type="primary" @click="openEditor(row.id)">编辑</ElButton
            ><ElButton link @click="preview(row.id, false)">游戏预览</ElButton
            ><ElButton v-permission="'question:copy'" link @click="copyQuestion(row.id)"
              >复制</ElButton
            ><ElButton v-permission="'question:history'" link @click="openHistory(row)"
              >版本历史</ElButton
            ><ElButton
              v-if="row.status !== 'published'"
              link
              type="success"
              @click="publishQuestion(row)"
              >发布</ElButton
            ><ElButton v-else link type="warning" @click="offline(row.id)">下架</ElButton
            ><ElButton v-if="row.status === 'draft'" link type="danger" @click="remove(row.id)"
              >删除</ElButton
            ></template
          ></ElTableColumn
        > </ElTable
      ><ElPagination
        class="mt-4"
        v-model:current-page="page"
        v-model:page-size="pageSize"
        :total="total"
        @change="load"
      />
    </ElCard>
    <ElDrawer v-model="editorVisible" size="72%" title="题目编辑">
      <ElForm label-width="100px">
        <ElFormItem label="难度"><ElRate v-model="form.difficulty" /></ElFormItem
        ><ElFormItem label="玩家人数"
          ><ElInputNumber v-model="form.min_players" :min="1" /> 至
          <ElInputNumber v-model="form.max_players" :min="form.min_players"
        /></ElFormItem>
        <ElFormItem label="首页精选"><ElSwitch v-model="form.is_featured" /></ElFormItem>
        <template v-if="form.is_featured">
          <ElFormItem label="精选排序"
            ><ElInputNumber v-model="form.featured_sort" :min="0"
          /></ElFormItem>
          <ElFormItem label="展示时间">
            <ElDatePicker
              v-model="form.featured_starts_at"
              type="datetime"
              value-format="YYYY-MM-DD HH:mm:ss"
              placeholder="开始时间（可不填）"
            />
            <span class="mx-2">至</span>
            <ElDatePicker
              v-model="form.featured_ends_at"
              type="datetime"
              value-format="YYYY-MM-DD HH:mm:ss"
              placeholder="结束时间（可不填）"
            />
          </ElFormItem>
        </template>
        <ElFormItem label="标签"
          ><ElSelect v-model="form.tag_ids" multiple filterable class="w-full"
            ><ElOption
              v-for="tag in tags"
              :key="tag.id"
              :label="tag.name"
              :value="tag.id" /></ElSelect
          ><ElButton class="ml-2" @click="loadTags">刷新</ElButton></ElFormItem
        >
        <ElFormItem label="风险等级"
          ><ElSelect v-model="form.risk_level"
            ><ElOption
              v-for="item in QUESTION_RISK_LEVEL_OPTIONS"
              :key="item.value"
              v-bind="item" /></ElSelect></ElFormItem
        ><ElFormItem label="风险类型"
          ><ElSelect v-model="form.risk_types" multiple class="w-full"
            ><ElOption
              v-for="item in QUESTION_RISK_TYPE_OPTIONS"
              :key="item.value"
              v-bind="item" /></ElSelect></ElFormItem
        ><ElFormItem v-if="form.risk_level !== 'safe'" label="风险说明"
          ><ElInput v-model="form.risk_note" type="textarea"
        /></ElFormItem>
        <ElAlert
          :title="chineseComplete ? '中文内容完整' : '中文内容不完整，无法发布'"
          :type="chineseComplete ? 'success' : 'error'"
          :closable="false"
          class="mb-3"
        /><ElAlert
          v-if="!englishComplete"
          title="英文翻译不完整：仍可发布，但建议补齐"
          type="warning"
          :closable="false"
          class="mb-3"
        />
        <ElTabs v-model="activeLanguage"
          ><ElTabPane
            v-for="item in form.translations"
            :key="item.language"
            :label="languageLabel(item.language)"
            :name="item.language"
            ><ElFormItem label="标题"><ElInput v-model="item.title" /></ElFormItem
            ><ElFormItem label="汤面"
              ><ElInput v-model="item.surface" type="textarea" :rows="5" /></ElFormItem
            ><ElFormItem label="汤底"
              ><ElInput v-model="item.bottom" type="textarea" :rows="6" /></ElFormItem></ElTabPane
        ></ElTabs>
        <ElDivider>关键推理点</ElDivider
        ><div v-for="(point, index) in form.points" :key="index" class="mb-3 flex gap-2"
          ><ElCheckbox v-model="point.is_required">必需</ElCheckbox
          ><ElInputNumber v-model="point.weight" :min="1" /><ElButton
            :disabled="index === 0"
            @click="movePoint(index, -1)"
            >上移</ElButton
          ><ElButton :disabled="index === form.points.length - 1" @click="movePoint(index, 1)"
            >下移</ElButton
          ><ElInput
            :model-value="translationContent(point.translations, activeLanguage)"
            :placeholder="`${languageLabel(activeLanguage)}关键点`"
            @update:model-value="
              updateTranslationContent(point.translations, activeLanguage, $event)
            "
          /><ElButton type="danger" @click="form.points.splice(index, 1)">删除</ElButton></div
        ><ElButton @click="addPoint">添加关键点</ElButton> <ElDivider>三级提示</ElDivider
        ><ElFormItem v-for="hint in form.hints" :key="hint.level" :label="`第 ${hint.level} 级`"
          ><ElInput
            :model-value="translationContent(hint.translations, activeLanguage)"
            :placeholder="`${languageLabel(activeLanguage)}提示`"
            @update:model-value="
              updateTranslationContent(hint.translations, activeLanguage, $event)
            "
        /></ElFormItem> </ElForm
      ><template #footer
        ><ElButton @click="editorVisible = false">取消</ElButton
        ><ElButton type="primary" @click="save">保存草稿</ElButton></template
      >
    </ElDrawer>
    <ElDialog v-model="previewVisible" title="题目预览" width="600px"
      ><h3>{{ titleOf(previewData) }}</h3
      ><p>{{ translationOf(previewData)?.surface }}</p
      ><ElAlert
        v-if="translationOf(previewData)?.bottom"
        title="游戏已结束：汤底"
        type="success"
        :description="translationOf(previewData)?.bottom"
      /><template #footer
        ><ElButton @click="previewVisible = false">关闭</ElButton
        ><ElButton @click="preview(currentPreviewId, true)">模拟结算</ElButton></template
      ></ElDialog
    >
    <ElDrawer v-model="historyVisible" size="55%" title="发布版本历史"
      ><ElTable :data="historyRows"
        ><ElTableColumn prop="version" label="发布版本" /><ElTableColumn
          prop="published_at"
          label="发布时间"
        /><ElTableColumn prop="published_by" label="操作者 ID" /><ElTableColumn label="操作"
          ><template #default="{ row }"
            ><ElButton link @click="viewHistory(row.id)">查看</ElButton
            ><ElButton link type="warning" @click="restoreHistory(row.id)"
              >恢复为草稿</ElButton
            ></template
          ></ElTableColumn
        ></ElTable
      ></ElDrawer
    ><ElDialog v-model="historyDetailVisible" title="历史版本详情">
      <pre class="max-h-[60vh] overflow-auto whitespace-pre-wrap">{{ historyDetail }}</pre>
    </ElDialog>
    <AiCreateDialog v-model="aiVisible" @adopted="handleAdopted" />
  </div>
</template>
<script setup lang="ts">
  import { ElMessage, ElMessageBox } from 'element-plus'
  import api, {
    type QuestionHistory,
    type QuestionPayload,
    type QuestionTag,
    type Translation
  } from '@/api/question'
  import {
    QUESTION_DIFFICULTY_LABELS,
    QUESTION_LANGUAGE_LABELS,
    QUESTION_RISK_LEVEL_OPTIONS,
    QUESTION_RISK_TYPE_OPTIONS,
    QUESTION_SOURCE_LABELS,
    QUESTION_STATUS_LABELS,
    QUESTION_STATUS_OPTIONS,
    QuestionRiskLevelEnum,
    QuestionSourceEnum,
    enumLabel
  } from '@/enums/questionEnum'
  import AiCreateDialog from './modules/ai-create-dialog.vue'
  const emptyForm = (): QuestionPayload => ({
    difficulty: 3,
    min_players: 1,
    max_players: 8,
    source_type: QuestionSourceEnum.Manual,
    risk_level: QuestionRiskLevelEnum.Safe,
    risk_types: [],
    risk_note: '',
    is_featured: false,
    featured_sort: 0,
    featured_starts_at: null,
    featured_ends_at: null,
    tag_ids: [],
    translations: [
      { language: 'zh-CN', title: '', surface: '', bottom: '' },
      { language: 'en-US', title: '', surface: '', bottom: '' }
    ],
    points: [],
    hints: [1, 2, 3].map((level) => ({
      level,
      translations: [
        { language: 'zh-CN', content: '' },
        { language: 'en-US', content: '' }
      ]
    }))
  })
  const rows = ref<Record<string, any>[]>([]),
    total = ref(0),
    page = ref(1),
    pageSize = ref(10),
    loading = ref(false),
    search = reactive({ keyword: '', status: '', is_featured: '' }),
    form = ref<QuestionPayload>(emptyForm()),
    activeLanguage = ref('zh-CN'),
    editorVisible = ref(false),
    previewVisible = ref(false),
    aiVisible = ref(false),
    previewData = ref<Record<string, any>>({}),
    currentPreviewId = ref(0),
    tags = ref<QuestionTag[]>([]),
    historyVisible = ref(false),
    historyDetailVisible = ref(false),
    historyRows = ref<QuestionHistory[]>([]),
    historyDetail = ref<Record<string, any>>({}),
    historyQuestion = ref({ id: 0, version: 0 })
  const translationOf = (row: Record<string, any>) =>
      row?.translations?.find((item: any) => item.language === 'zh-CN'),
    titleOf = (row: Record<string, any>) => translationOf(row)?.title || '未命名题目',
    languageLabel = (value: unknown) => enumLabel(QUESTION_LANGUAGE_LABELS, value),
    translationContent = (items: Translation[], language: string) =>
      items.find((item) => item.language === language)?.content || ''
  function updateTranslationContent(items: Translation[], language: string, content: string) {
    const item = items.find((value) => value.language === language)
    if (item) item.content = content
    else items.push({ language, content })
  }
  function riskTypesOf(value: unknown): string[] {
    if (Array.isArray(value))
      return value.filter((item): item is string => typeof item === 'string')
    if (typeof value !== 'string' || !value.trim()) return []
    try {
      const parsed: unknown = JSON.parse(value)
      return Array.isArray(parsed)
        ? parsed.filter((item): item is string => typeof item === 'string')
        : []
    } catch {
      return []
    }
  }
  async function load() {
    loading.value = true
    try {
      const data = await api.list({ ...search, page: page.value, pageSize: pageSize.value })
      rows.value = data.items
      total.value = data.total
    } finally {
      loading.value = false
    }
  }
  async function loadTags() {
    tags.value = await api.tags()
  }
  async function openEditor(id?: number) {
    if (id) {
      const question = await api.read(id)
      form.value = {
        ...question,
        tag_ids: question.tag_ids ?? question.tags?.map((tag) => tag.id) ?? [],
        risk_types: riskTypesOf(question.risk_types),
        risk_note: question.risk_note ?? ''
      }
    } else {
      form.value = emptyForm()
    }
    activeLanguage.value = 'zh-CN'
    editorVisible.value = true
  }
  function addPoint() {
    form.value.points.push({
      weight: 1,
      is_required: true,
      sort: form.value.points.length + 1,
      translations: form.value.translations.map(({ language }) => ({ language, content: '' }))
    })
  }
  function movePoint(index: number, offset: number) {
    const target = index + offset
    if (target < 0 || target >= form.value.points.length) return
    const [point] = form.value.points.splice(index, 1)
    form.value.points.splice(target, 0, point)
    form.value.points.forEach((item, sort) => (item.sort = sort + 1))
  }
  const complete = (language: string) => {
      const value = form.value.translations.find((item) => item.language === language)
      return Boolean(
        value?.title?.trim() &&
          value.surface?.trim() &&
          value.bottom?.trim() &&
          form.value.points.length &&
          form.value.points.every((item) =>
            translationContent(item.translations, language).trim()
          ) &&
          [1, 2, 3].every((level) => {
            const hint = form.value.hints.find((item) => item.level === level)
            return hint && translationContent(hint.translations, language).trim()
          })
      )
    },
    chineseComplete = computed(
      () => complete('zh-CN') && form.value.points.some((item) => item.is_required)
    ),
    englishComplete = computed(() => complete('en-US'))
  async function save() {
    form.value = form.value.id ? await api.update(form.value) : await api.save(form.value)
    ElMessage.success('草稿已保存')
    editorVisible.value = false
    await load()
  }
  async function publishQuestion(row: Record<string, any>) {
    form.value = await api.read(row.id)
    if (!chineseComplete.value) return void ElMessage.error('中文内容不完整，请先编辑补齐')
    const risky = form.value.risk_level !== QuestionRiskLevelEnum.Safe
    if (risky && !form.value.risk_note?.trim())
      return void ElMessage.error('风险内容必须填写风险说明')
    if (risky)
      await ElMessageBox.confirm('确认已人工审核风险内容并继续发布？', '风险确认', {
        type: 'warning'
      })
    else if (!englishComplete.value) await ElMessageBox.confirm('英文翻译不完整，仍发布中文版本？')
    await api.publish(row.id, Number(form.value.version), risky)
    ElMessage.success('已发布')
    await load()
  }
  async function offline(id: number) {
    await api.offline(id)
    await load()
  }
  async function remove(id: number) {
    await ElMessageBox.confirm('确定删除草稿？')
    await api.remove(id)
    await load()
  }
  async function preview(id: number, finished: boolean) {
    currentPreviewId.value = id
    previewData.value = finished ? await api.answerPreview(id) : await api.preview(id)
    previewVisible.value = true
  }
  async function copyQuestion(id: number) {
    await ElMessageBox.confirm('复制为新的人工草稿？')
    const copied = await api.copy(id)
    await load()
    await openEditor(copied.id)
  }
  async function openHistory(row: Record<string, any>) {
    historyQuestion.value = { id: row.id, version: row.version }
    historyRows.value = await api.history(row.id)
    historyVisible.value = true
  }
  async function viewHistory(versionId: number) {
    historyDetail.value = await api.historyRead(historyQuestion.value.id, versionId)
    historyDetailVisible.value = true
  }
  async function restoreHistory(versionId: number) {
    await ElMessageBox.confirm('恢复为新草稿？')
    const restored = await api.historyRestore(
      historyQuestion.value.id,
      versionId,
      historyQuestion.value.version
    )
    historyVisible.value = false
    await load()
    await openEditor(restored.id)
  }
  function handleAdopted(question: QuestionPayload) {
    aiVisible.value = false
    form.value = question
    editorVisible.value = true
    load()
  }
  onMounted(() => Promise.all([load(), loadTags()]))
</script>
