<template>
  <div class="page-content"
    ><ElRow :gutter="16" class="mb-4"
      ><ElCol :span="6"
        ><ElCard shadow="never"
          ><ElStatistic title="支持者" :value="stats.supporter_count" /></ElCard></ElCol
      ><ElCol :span="6"
        ><ElCard shadow="never"
          ><ElStatistic
            title="累计金额"
            :value="Number(stats.total_amount)"
            prefix="¥" /></ElCard></ElCol></ElRow
    ><ElCard shadow="never"
      ><div class="mb-4 flex gap-3"
        ><ElInput
          v-model="query.keyword"
          clearable
          placeholder="捐赠者名称"
          class="w-60"
        /><ElButton type="primary" @click="load">查询</ElButton
        ><ElButton v-permission="'donation:create'" @click="open()">新增记录</ElButton
        ><ElButton v-permission="'donation:channel'" @click="channelVisible = true"
          >收款码配置</ElButton
        ></div
      ><ElTable :data="rows"
        ><ElTableColumn prop="donor_name" label="捐赠者" /><ElTableColumn prop="amount" label="金额"
          ><template #default="{ row }">¥{{ row.amount }}</template></ElTableColumn
        ><ElTableColumn prop="method" label="方式" /><ElTableColumn
          prop="message"
          label="留言"
        /><ElTableColumn prop="donated_at" label="捐赠时间" min-width="170" /><ElTableColumn
          label="状态"
          ><template #default="{ row }"
            ><ElTag :type="row.status ? 'success' : 'info'">{{
              row.status ? '显示' : '隐藏'
            }}</ElTag></template
          ></ElTableColumn
        ><ElTableColumn label="操作"
          ><template #default="{ row }"
            ><ElButton link @click="open(row)">编辑</ElButton
            ><ElButton link type="danger" @click="remove(row)">删除</ElButton></template
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
    ><ElDialog v-model="editVisible" :title="form.id ? '编辑捐赠' : '新增捐赠'" width="520px"
      ><ElForm label-width="90px"
        ><ElFormItem label="捐赠者"><ElInput v-model="form.donor_name" /></ElFormItem
        ><ElFormItem label="金额"
          ><ElInputNumber v-model="form.amount" :min="0.01" :precision="2" /></ElFormItem
        ><ElFormItem label="方式"
          ><ElSelect v-model="form.method"
            ><ElOption label="微信" value="wechat" /><ElOption
              label="支付宝"
              value="alipay" /><ElOption label="其他" value="other" /></ElSelect></ElFormItem
        ><ElFormItem label="捐赠时间"
          ><ElDatePicker
            v-model="form.donated_at"
            type="datetime"
            value-format="YYYY-MM-DD HH:mm:ss" /></ElFormItem
        ><ElFormItem label="留言"><ElInput v-model="form.message" /></ElFormItem
        ><ElFormItem label="前台显示"><ElSwitch v-model="form.status" /></ElFormItem></ElForm
      ><template #footer
        ><ElButton @click="editVisible = false">取消</ElButton
        ><ElButton type="primary" @click="save">保存</ElButton></template
      ></ElDialog
    ><ElDrawer v-model="channelVisible" title="收款码配置" size="520px"
      ><div v-for="method in ['wechat', 'alipay']" :key="method" class="mb-8"
        ><h3>{{ method === 'wechat' ? '微信支付' : '支付宝' }}</h3
        ><img
          v-if="channel(method)?.qr_code_url"
          :src="channel(method)?.qr_code_url"
          class="my-3 h-40 w-40 object-contain"
        /><ElUpload
          :show-file-list="false"
          :http-request="(options) => uploadChannel(method as 'wechat' | 'alipay', options.file)"
          ><ElButton>上传收款码</ElButton></ElUpload
        ></div
      ></ElDrawer
    ></div
  >
</template>
<script setup lang="ts">
  import { ElMessage, ElMessageBox } from 'element-plus'
  import { donationAdminApi, type DonationChannel, type DonationRow } from '@/api/operations'
  const rows = ref<DonationRow[]>([]),
    channels = ref<DonationChannel[]>([]),
    total = ref(0),
    editVisible = ref(false),
    channelVisible = ref(false)
  const stats = reactive({ supporter_count: 0, total_amount: '0.00' }),
    query = reactive({ keyword: '', page: 1, pageSize: 20 })
  const form = reactive<{
    id?: number
    donor_name: string
    amount: number | string
    method: string
    donated_at: string
    message: string
    status: boolean
    sort: number
  }>({
    donor_name: '',
    amount: 0.01,
    method: 'wechat',
    donated_at: '',
    message: '',
    status: true,
    sort: 0
  })
  const channel = (method: string) => channels.value.find((item) => item.method === method)
  async function load() {
    const [data, summary, channelData] = await Promise.all([
      donationAdminApi.list(query),
      donationAdminApi.stats(),
      donationAdminApi.channels()
    ])
    rows.value = data.items
    total.value = data.total
    Object.assign(stats, summary)
    channels.value = channelData
  }
  function open(row?: DonationRow) {
    Object.assign(
      form,
      row || {
        id: undefined,
        donor_name: '',
        amount: 0.01,
        method: 'wechat',
        donated_at: '',
        message: '',
        status: true,
        sort: 0
      }
    )
    editVisible.value = true
  }
  async function save() {
    const payload = { ...form } as Record<string, unknown>
    if (form.id) {
      await donationAdminApi.update(payload)
    } else {
      await donationAdminApi.save(payload)
    }
    editVisible.value = false
    ElMessage.success('保存成功')
    await load()
  }
  async function remove(row: DonationRow) {
    await ElMessageBox.confirm('确定删除该捐赠记录？')
    await donationAdminApi.destroy([row.id])
    await load()
  }
  async function uploadChannel(method: 'wechat' | 'alipay', file: File) {
    const data = new FormData()
    data.append('file', file)
    data.append('method', method)
    data.append('name', method === 'wechat' ? '微信支付' : '支付宝')
    data.append('status', '1')
    await donationAdminApi.updateChannel(data)
    ElMessage.success('收款码已更新')
    await load()
  }
  onMounted(load)
</script>
