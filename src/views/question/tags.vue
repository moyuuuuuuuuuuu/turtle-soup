<template>
  <div class="page-content">
    <ElCard shadow="never">
      <template #header
        ><div class="flex items-center justify-between"
          ><span>题目标签</span
          ><ElButton v-permission="'question:tag:edit'" type="primary" @click="edit()"
            >新增标签</ElButton
          ></div
        ></template
      >
      <ElTable v-loading="loading" :data="rows"
        ><ElTableColumn prop="name" label="中文名称" /><ElTableColumn
          prop="slug"
          label="英文标识"
        /><ElTableColumn label="操作" width="180"
          ><template #default="{ row }"
            ><ElButton link @click="edit(row)">编辑</ElButton
            ><ElButton link type="danger" @click="remove(row.id)">删除</ElButton></template
          ></ElTableColumn
        ></ElTable
      >
    </ElCard>
    <ElDialog v-model="visible" :title="form.id ? '编辑标签' : '新增标签'" width="480px"
      ><ElForm label-width="90px"
        ><ElFormItem label="中文名称"><ElInput v-model="form.name" /></ElFormItem
        ><ElFormItem label="英文标识"
          ><ElInput v-model="form.slug" placeholder="如 classic_case" /></ElFormItem></ElForm
      ><template #footer
        ><ElButton @click="visible = false">取消</ElButton
        ><ElButton type="primary" @click="save">保存</ElButton></template
      ></ElDialog
    >
  </div>
</template>
<script setup lang="ts">
  import { ElMessage, ElMessageBox } from 'element-plus'
  import api, { type QuestionTag } from '@/api/question'
  const rows = ref<QuestionTag[]>([]),
    loading = ref(false),
    visible = ref(false),
    form = reactive<Partial<QuestionTag>>({})
  async function load() {
    loading.value = true
    try {
      rows.value = await api.tags()
    } finally {
      loading.value = false
    }
  }
  function edit(row?: QuestionTag) {
    Object.assign(form, row ? { ...row } : { id: undefined, name: '', slug: '' })
    visible.value = true
  }
  async function save() {
    if (!form.name?.trim() || !/^[a-z0-9_-]{1,64}$/.test(form.slug || ''))
      return void ElMessage.error('请填写名称，英文标识仅可使用小写字母、数字、下划线或短横线')
    await api.saveTag(form)
    visible.value = false
    ElMessage.success('已保存')
    await load()
  }
  async function remove(id: number) {
    await ElMessageBox.confirm('被题目占用的标签无法删除，确定继续？')
    await api.removeTag(id)
    ElMessage.success('已删除')
    await load()
  }
  onMounted(load)
</script>
