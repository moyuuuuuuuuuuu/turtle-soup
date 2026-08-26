<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import { playerApi } from '@/api/player'
import { usePlayerStore } from '@/store/playerStore'

definePage({ name: 'player-login', style: { navigationBarTitleText: '玩家登录' } })
const router = useRouter(); const store = usePlayerStore(); const mode = ref<'password' | 'code'>('password'); const account = ref(''); const password = ref(''); const email = ref(''); const code = ref(''); const busy = ref(false)
async function sendCode() { await playerApi.sendCode(email.value, 'login'); uni.showToast({ title: '验证码已发送', icon: 'success' }) }
async function submit() {
  busy.value = true; try {
    const result = mode.value === 'password' ? await playerApi.passwordLogin(account.value, password.value) : await playerApi.codeLogin(email.value, code.value); store.accept(result); if (result.merged_games)
      uni.showToast({ title: `已合并 ${result.merged_games} 局记录`, icon: 'none' }); uni.switchTab({ url: '/pages/index/index' })
  }
  catch (error) { uni.showToast({ title: (error as Error).message, icon: 'none' }) }
  finally { busy.value = false }
}
</script>

<template>
  <view class="min-h-screen bg-[#f5efe5] p-5">
    <view class="rounded-5 bg-white p-5">
      <wd-tabs v-model="mode">
        <wd-tab name="password" title="密码登录" /><wd-tab name="code" title="邮箱验证码" />
      </wd-tabs>
      <template v-if="mode === 'password'">
        <wd-input v-model="account" label="账号" placeholder="用户名或邮箱" /><wd-input v-model="password" label="密码" show-password />
      </template>
      <template v-else>
        <wd-input v-model="email" label="邮箱" /><view class="flex items-center gap-2">
          <wd-input v-model="code" label="验证码" /><wd-button size="small" plain @click="sendCode">
            发送
          </wd-button>
        </view>
      </template>
      <wd-button block class="mt-5" :loading="busy" @click="submit">
        登录
      </wd-button>
      <view class="mt-4 flex justify-between text-3 text-blue-600">
        <text @click="router.push({ name: 'player-register' })">
          注册账号
        </text><text @click="router.push({ name: 'player-reset-password' })">
          忘记密码
        </text>
      </view>
    </view>
  </view>
</template>
