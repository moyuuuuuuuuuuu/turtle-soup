<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import type { PlayerSession } from '@/api/player'; import { playerApi } from '@/api/player'; import { usePlayerStore } from '@/store/playerStore'

definePage({ name: 'player-account', style: { navigationBarTitleText: '我的账号' } }); const store = usePlayerStore(); const sessions = ref<PlayerSession[]>([]); const username = ref(''); const email = ref(''); const emailCode = ref(''); const currentPassword = ref(''); const newPassword = ref('')
onMounted(async () => {
  if (!store.user)
    await store.load(); username.value = store.user?.username || ''; email.value = store.user?.email || ''; sessions.value = await playerApi.sessions()
})
async function saveUsername() { store.user = await playerApi.changeUsername(username.value); uni.showToast({ title: '用户名已更新', icon: 'success' }) }
async function sendChangeCode() { await playerApi.sendCode(email.value, 'change_email'); uni.showToast({ title: '验证码已发送', icon: 'success' }) }
async function saveEmail() { store.user = await playerApi.changeEmail(email.value, currentPassword.value, emailCode.value); uni.showToast({ title: '邮箱已更新', icon: 'success' }) }
async function savePassword() { const result = await playerApi.changePassword(currentPassword.value, newPassword.value); store.accept(result); uni.showToast({ title: '密码已更新', icon: 'success' }) }
async function revoke(id: string) { await playerApi.revokeSession(id); sessions.value = await playerApi.sessions() }
async function logout(all = false) { await store.logout(all); uni.switchTab({ url: '/pages/index/index' }) }
</script>

<template>
  <view class="min-h-screen bg-[#f5efe5] p-4">
    <view v-if="store.user" class="grid gap-4">
      <view class="rounded-4 bg-white p-4">
        <image v-if="store.user.avatar_url" :src="store.user.avatar_url" class="mb-3 h-16 w-16 rounded-full" mode="aspectFill" />
        <text class="text-5 font-bold">
          {{ store.user.username }}
        </text><text class="mt-1 block text-gray-500">
          {{ store.user.email }}
        </text>
      </view><view class="rounded-4 bg-white p-4">
        <text class="font-bold">
          修改用户名
        </text><wd-input v-model="username" /><wd-button size="small" @click="saveUsername">
          保存
        </wd-button>
      </view><view class="rounded-4 bg-white p-4">
        <text class="font-bold">
          换绑邮箱
        </text><wd-input v-model="email" /><wd-input v-model="emailCode" label="验证码" /><wd-input v-model="currentPassword" label="当前密码" show-password /><view class="flex gap-2">
          <wd-button size="small" plain @click="sendChangeCode">
            发送验证码
          </wd-button><wd-button size="small" @click="saveEmail">
            确认换绑
          </wd-button>
        </view>
      </view><view class="rounded-4 bg-white p-4">
        <text class="font-bold">
          修改密码
        </text><wd-input v-model="currentPassword" label="当前密码" show-password /><wd-input v-model="newPassword" label="新密码" show-password /><wd-button size="small" @click="savePassword">
          修改密码
        </wd-button>
      </view><view class="rounded-4 bg-white p-4">
        <text class="font-bold">
          登录设备（最多 3 台）
        </text><view v-for="item in sessions" :key="item.id" class="mt-3 flex items-center justify-between">
          <view>
            <text>{{ item.device_name }}</text><text class="block text-3 text-gray-400">
              {{ item.platform }}
            </text>
          </view><wd-button size="small" type="danger" plain @click="revoke(item.id)">
            撤销
          </wd-button>
        </view>
      </view><wd-button type="warning" plain block @click="logout(false)">
        退出当前设备
      </wd-button><wd-button type="danger" plain block @click="logout(true)">
        退出全部设备
      </wd-button>
    </view>
  </view>
</template>
