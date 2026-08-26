<template>
  <div class="page-content"
    ><ElCard shadow="never"
      ><div class="mb-4 flex gap-3"
        ><ElInput
          v-model="query.keyword"
          clearable
          placeholder="房间名、ID 或邀请码"
          class="w-64"
        /><ElSelect v-model="query.status" clearable placeholder="状态" class="w-36"
          ><ElOption
            v-for="item in statuses"
            :key="item.value"
            :label="item.label"
            :value="item.value" /></ElSelect
        ><ElButton type="primary" @click="load">查询</ElButton></div
      ><ElTable v-loading="loading" :data="rows"
        ><ElTableColumn prop="public_id" label="房间 ID" min-width="220" /><ElTableColumn
          prop="name"
          label="房间名"
        /><ElTableColumn prop="invite_code" label="邀请码" /><ElTableColumn label="人数"
          ><template #default="{ row }"
            >{{ row.member_count }}/{{ row.max_players }}</template
          ></ElTableColumn
        ><ElTableColumn label="状态"
          ><template #default="{ row }"
            ><ElTag>{{ statusLabel(row.status) }}</ElTag></template
          ></ElTableColumn
        ><ElTableColumn prop="visibility" label="可见性" /><ElTableColumn
          prop="create_time"
          label="创建时间"
          min-width="170"
        /><ElTableColumn label="操作" width="150"
          ><template #default="{ row }"
            ><ElButton v-permission="'room:read'" link @click="read(row)">详情</ElButton
            ><ElButton
              v-if="!['closed', 'finished'].includes(row.status)"
              v-permission="'room:close'"
              link
              type="danger"
              @click="close(row)"
              >关闭</ElButton
            ></template
          ></ElTableColumn
        ></ElTable
      ><ElPagination
        class="mt-4 justify-end"
        layout="total, prev, pager, next"
        :total="total"
        :page-size="query.pageSize"
        @current-change="
          (page: number) => {
            query.page = page
            load()
          }
        " /></ElCard
    ><ElDrawer v-model="visible" title="房间详情" size="60%">
      <pre class="whitespace-pre-wrap">{{ JSON.stringify(detail, null, 2) }}</pre>
    </ElDrawer></div
  >
</template>
<script setup lang="ts">
  import { ElMessage, ElMessageBox } from 'element-plus'
  import { roomAdminApi, type RoomRow } from '@/api/operations'
  const statuses = [
    { label: '等待中', value: 'waiting' },
    { label: '游戏中', value: 'playing' },
    { label: '已结束', value: 'finished' },
    { label: '已关闭', value: 'closed' }
  ]
  const rows = ref<RoomRow[]>([]),
    total = ref(0),
    loading = ref(false),
    visible = ref(false),
    detail = ref<unknown>()
  const query = reactive({ keyword: '', status: '', page: 1, pageSize: 20 })
  const statusLabel = (value: string) =>
    statuses.find((item) => item.value === value)?.label || value
  async function load() {
    loading.value = true
    try {
      const data = await roomAdminApi.list(query)
      rows.value = data.items
      total.value = data.total
    } finally {
      loading.value = false
    }
  }
  async function read(row: RoomRow) {
    detail.value = await roomAdminApi.read(row.id)
    visible.value = true
  }
  async function close(row: RoomRow) {
    await ElMessageBox.confirm('确定强制关闭该房间？')
    await roomAdminApi.close(row.id)
    ElMessage.success('房间已关闭')
    await load()
  }
  onMounted(load)
</script>
