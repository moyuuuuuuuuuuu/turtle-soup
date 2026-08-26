<template>
  <div class="page-content">
    <ElCard shadow="never">
      <div class="mb-4 flex flex-wrap gap-3">
        <ElInput v-model="search.keyword" placeholder="标题或汤面" clearable class="!w-56" />
        <ElSelect v-model="search.status" placeholder="状态" clearable class="!w-36">
          <ElOption label="草稿" value="draft" /><ElOption label="已发布" value="published" />
          <ElOption label="已下架" value="offline" />
        </ElSelect>
        <ElButton type="primary" @click="load">查询</ElButton>
        <ElButton v-permission="'question:edit'" @click="openEditor()">新增题目</ElButton>
        <ElButton v-permission="'question:ai:create'" @click="aiVisible = true">AI 创作</ElButton>
      </div>
      <ElTable v-loading="loading" :data="rows">
        <ElTableColumn prop="public_id" label="题目 ID" width="230" />
        <ElTableColumn label="标题" min-width="220">
          <template #default="{ row }">{{ titleOf(row) }}</template>
        </ElTableColumn>
        <ElTableColumn prop="difficulty" label="难度" width="80" />
        <ElTableColumn prop="source_type" label="来源" width="90" />
        <ElTableColumn prop="status" label="状态" width="100" />
        <ElTableColumn prop="version" label="版本" width="80" />
        <ElTableColumn label="操作" width="300" fixed="right">
          <template #default="{ row }">
            <ElButton link type="primary" @click="openEditor(row.id)">编辑</ElButton>
            <ElButton link @click="preview(row.id, false)">游戏预览</ElButton>
            <ElButton
              v-if="row.status !== 'published'"
              link
              type="success"
              @click="changeStatus(row.id, 'publish')"
              >发布</ElButton
            >
            <ElButton v-else link type="warning" @click="changeStatus(row.id, 'offline')"
              >下架</ElButton
            >
            <ElButton v-if="row.status === 'draft'" link type="danger" @click="remove(row.id)"
              >删除</ElButton
            >
          </template>
        </ElTableColumn>
      </ElTable>
      <ElPagination
        class="mt-4"
        v-model:current-page="page"
        v-model:page-size="pageSize"
        :total="total"
        @change="load"
      />
    </ElCard>

    <ElDrawer v-model="editorVisible" size="72%" title="题目编辑">
      <ElForm label-width="100px">
        <ElFormItem label="难度"><ElRate v-model="form.difficulty" /></ElFormItem>
        <ElFormItem label="玩家人数"
          ><ElInputNumber v-model="form.min_players" :min="1" /> 至
          <ElInputNumber v-model="form.max_players" :min="1"
        /></ElFormItem>
        <ElTabs>
          <ElTabPane v-for="item in form.translations" :key="item.language" :label="item.language">
            <ElFormItem label="标题"><ElInput v-model="item.title" /></ElFormItem>
            <ElFormItem label="汤面"
              ><ElInput v-model="item.surface" type="textarea" :rows="5"
            /></ElFormItem>
            <ElFormItem label="汤底"
              ><ElInput v-model="item.bottom" type="textarea" :rows="6"
            /></ElFormItem>
          </ElTabPane>
        </ElTabs>
        <ElDivider>关键推理点</ElDivider>
        <div v-for="(point, index) in form.points" :key="index" class="mb-3 flex gap-2">
          <ElCheckbox v-model="point.is_required">必需</ElCheckbox
          ><ElInputNumber v-model="point.weight" :min="1" />
          <ElInput v-model="point.translations[0].content" placeholder="中文关键点" />
          <ElButton type="danger" @click="form.points.splice(index, 1)">删除</ElButton>
        </div>
        <ElButton @click="addPoint">添加关键点</ElButton>
        <ElDivider>三级提示</ElDivider>
        <ElFormItem v-for="hint in form.hints" :key="hint.level" :label="`第 ${hint.level} 级`">
          <ElInput v-model="hint.translations[0].content" />
        </ElFormItem>
      </ElForm>
      <template #footer
        ><ElButton @click="editorVisible = false">取消</ElButton
        ><ElButton type="primary" @click="save">保存草稿</ElButton></template
      >
    </ElDrawer>

    <ElDialog v-model="previewVisible" title="题目预览" width="600px">
      <h3>{{ titleOf(previewData) }}</h3
      ><p>{{ translationOf(previewData)?.surface }}</p>
      <ElAlert
        v-if="translationOf(previewData)?.bottom"
        title="游戏已结束：汤底"
        type="success"
        :description="translationOf(previewData)?.bottom"
      />
      <template #footer
        ><ElButton @click="previewVisible = false">关闭</ElButton
        ><ElButton @click="preview(currentPreviewId, true)">模拟结算</ElButton></template
      >
    </ElDialog>

    <AiCreateDialog v-model="aiVisible" @adopted="handleAdopted" />
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox } from 'element-plus'
  import api, { type QuestionPayload } from '@/api/question'
  import AiCreateDialog from './modules/ai-create-dialog.vue'

  const emptyForm = (): QuestionPayload => ({
    difficulty: 3,
    min_players: 1,
    max_players: 8,
    source_type: 'manual',
    tag_ids: [],
    translations: [
      { language: 'zh-CN', title: '', surface: '', bottom: '' },
      { language: 'en-US', title: '', surface: '', bottom: '' }
    ],
    points: [],
    hints: [1, 2, 3].map((level) => ({ level, translations: [{ language: 'zh-CN', content: '' }] }))
  })
  const rows = ref<Record<string, any>[]>([]),
    total = ref(0),
    page = ref(1),
    pageSize = ref(20),
    loading = ref(false)
  const search = reactive({ keyword: '', status: '' }),
    form = ref<QuestionPayload>(emptyForm())
  const editorVisible = ref(false),
    previewVisible = ref(false),
    aiVisible = ref(false),
    previewData = ref<Record<string, any>>({}),
    currentPreviewId = ref(0)
  const translationOf = (row: Record<string, any>) =>
    row?.translations?.find((item: any) => item.language === 'zh-CN')
  const titleOf = (row: Record<string, any>) => translationOf(row)?.title || '未命名题目'
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
  async function openEditor(id?: number) {
    form.value = id ? await api.read(id) : emptyForm()
    editorVisible.value = true
  }
  function addPoint() {
    form.value.points.push({
      weight: 1,
      is_required: true,
      sort: form.value.points.length + 1,
      translations: [{ language: 'zh-CN', content: '' }]
    })
  }
  async function save() {
    if (form.value.id) await api.update(form.value)
    else await api.save(form.value)
    ElMessage.success('草稿已保存')
    editorVisible.value = false
    load()
  }
  async function changeStatus(id: number, action: 'publish' | 'offline') {
    await api[action](id)
    ElMessage.success(action === 'publish' ? '已发布' : '已下架')
    load()
  }
  async function remove(id: number) {
    await ElMessageBox.confirm('仅草稿可以删除，确定继续？')
    await api.remove(id)
    load()
  }
  async function preview(id: number, finished: boolean) {
    currentPreviewId.value = id
    previewData.value = await api.preview(id, finished)
    previewVisible.value = true
  }
  function handleAdopted(question: QuestionPayload) {
    aiVisible.value = false
    form.value = question
    editorVisible.value = true
    load()
  }
  onMounted(load)
</script>
