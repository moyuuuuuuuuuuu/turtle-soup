<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import { playerApi } from '@/api/player'; import { usePlayerStore } from '@/store/playerStore'

definePage({ name: 'player-register', style: { navigationBarTitleText: '注册玩家' } }); const store = usePlayerStore(); const username = ref(''); const email = ref(''); const password = ref(''); const code = ref(''); const busy = ref(false)
async function sendCode() { await playerApi.sendCode(email.value, 'register'); uni.showToast({ title: '验证码已发送', icon: 'success' }) }
async function submit() {
  busy.value = true; try { const result = await playerApi.register({ username: username.value, email: email.value, password: password.value, email_code: code.value }); store.accept(result); uni.switchTab({ url: '/pages/index/index' }) }
  catch (error) { uni.showToast({ title: (error as Error).message, icon: 'none' }) }
  finally { busy.value = false }
}
</script>

<template>
  <view class="min-h-screen bg-[#f5efe5] p-5">
    <view class="rounded-5 bg-white p-5">
      <wd-input v-model="username" label="显示名称" placeholder="可选；仅用于展示，不可用于登录" /><wd-input v-model="email" label="邮箱" /><wd-input v-model="password" label="密码" show-password /><view class="flex items-center gap-2">
        <wd-input v-model="code" label="验证码" /><wd-button size="small" plain @click="sendCode">
          发送
        </wd-button>
      </view><wd-button block class="mt-5" :loading="busy" @click="submit">
        注册并登录
      </wd-button>
    </view>
  </view>
</template>
