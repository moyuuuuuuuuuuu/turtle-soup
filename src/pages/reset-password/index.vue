<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import { playerApi } from '@/api/player'; import { usePlayerStore } from '@/store/playerStore'

definePage({ name: 'player-reset-password', style: { navigationBarTitleText: '找回密码' } }); const store = usePlayerStore(); const email = ref(''); const code = ref(''); const password = ref(''); async function sendCode() { await playerApi.sendCode(email.value, 'reset_password'); uni.showToast({ title: '验证码已发送', icon: 'success' }) } async function submit() {
  try { const result = await playerApi.resetPassword(email.value, code.value, password.value); store.accept(result); uni.switchTab({ url: '/pages/index/index' }) }
  catch (error) { uni.showToast({ title: (error as Error).message, icon: 'none' }) }
}
</script>

<template>
  <view class="min-h-screen bg-[#f5efe5] p-5">
    <view class="rounded-5 bg-white p-5">
      <wd-input v-model="email" label="邮箱" /><view class="flex items-center gap-2">
        <wd-input v-model="code" label="验证码" /><wd-button size="small" plain @click="sendCode">
          发送
        </wd-button>
      </view><wd-input v-model="password" label="新密码" show-password /><wd-button block class="mt-5" @click="submit">
        重置并登录
      </wd-button>
    </view>
  </view>
</template>
