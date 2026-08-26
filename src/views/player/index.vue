<template>
  <div class="page-content"
    ><ElCard shadow="never"
      ><div class="mb-4 flex gap-3"
        ><ElInput
          v-model="query.keyword"
          clearable
          placeholder="用户名或邮箱"
          class="w-60"
        /><ElSelect v-model="query.status" clearable placeholder="状态" class="w-32"
          ><ElOption label="正常" value="active" /><ElOption
            label="已禁用"
            value="disabled" /></ElSelect
        ><ElButton type="primary" @click="load">查询</ElButton></div
      ><ElTable v-loading="loading" :data="rows"
        ><ElTableColumn prop="id" label="玩家 ID" min-width="220" /><ElTableColumn
          prop="username"
          label="用户名"
        /><ElTableColumn prop="email" label="邮箱" min-width="200" /><ElTableColumn label="状态"
          ><template #default="{ row }"
            ><ElTag :type="row.status === 'active' ? 'success' : 'danger'">{{
              row.status === 'active' ? '正常' : '已禁用'
            }}</ElTag></template
          ></ElTableColumn
        ><ElTableColumn prop="active_sessions" label="活跃设备" /><ElTableColumn
          label="操作"
          width="260"
          ><template #default="{ row }"
            ><ElButton
              v-permission="'player:status'"
              link
              :type="row.status === 'active' ? 'danger' : 'success'"
              @click="toggle(row)"
              >{{ row.status === 'active' ? '禁用' : '启用' }}</ElButton
            ><ElButton
              v-permission="'player:session:revoke'"
              link
              type="warning"
              @click="revoke(row)"
              >撤销会话</ElButton
            ><ElButton v-permission="'player:log'" link @click="logs(row)">日志</ElButton></template
          ></ElTableColumn
        ></ElTable
      ><ElPagination
        class="mt-4 justify-end"
        layout="total, prev, pager, next"
        :total="total"
        :page-size="query.pageSize"
        @current-change="
          (p) => {
            query.page = p
            load()
          }
        " /></ElCard
    ><ElDrawer v-model="logVisible" title="玩家日志" size="55%"
      ><h3>登录日志</h3
      ><ElTable :data="loginLogs"
        ><ElTableColumn prop="method" label="方式" /><ElTableColumn
          prop="result"
          label="结果" /><ElTableColumn prop="device_name" label="设备" /><ElTableColumn
          prop="create_time"
          label="时间" /></ElTable
      ><h3 class="mt-6">匿名历史合并</h3
      ><ElTable :data="mergeLogs"
        ><ElTableColumn prop="anonymous_session_id" label="匿名会话" /><ElTableColumn
          prop="merged_games"
          label="合并局数" /><ElTableColumn prop="result" label="结果" /><ElTableColumn
          prop="create_time"
          label="时间" /></ElTable></ElDrawer
  ></div>
</template>
<script setup lang="ts">
  import { ElMessage, ElMessageBox } from 'element-plus'
  import api, { type PlayerRow } from '@/api/player'
  const rows = ref<PlayerRow[]>([]),
    total = ref(0),
    loading = ref(false),
    logVisible = ref(false),
    loginLogs = ref<any[]>([]),
    mergeLogs = ref<any[]>([])
  const query = reactive({ keyword: '', status: '', page: 1, pageSize: 20 })
  async function load() {
    loading.value = true
    try {
      const data = await api.list(query)
      rows.value = data.items
      total.value = data.total
    } finally {
      loading.value = false
    }
  }
  async function toggle(row: PlayerRow) {
    const status = row.status === 'active' ? 'disabled' : 'active'
    await ElMessageBox.confirm(`确定${status === 'active' ? '启用' : '禁用'}该玩家？`)
    await api.status(row.database_id, status)
    ElMessage.success('操作成功')
    await load()
  }
  async function revoke(row: PlayerRow) {
    await ElMessageBox.confirm('确定撤销该玩家全部登录会话？')
    await api.revoke(row.database_id)
    ElMessage.success('已撤销')
    await load()
  }
  async function logs(row: PlayerRow) {
    ;[loginLogs.value, mergeLogs.value] = await Promise.all([
      api.loginLogs(row.database_id),
      api.mergeLogs(row.database_id)
    ])
    logVisible.value = true
  }
  onMounted(load)
</script>
