<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import type { PlayerSession } from '@/api/player'
import { playerApi } from '@/api/player'
import { gameApi } from '@/api/turtle'
import { usePlayerStore } from '@/store/playerStore'

definePage({ name: 'player-account', layout: 'tabbar', style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } } })
const router = useRouter()
const store = usePlayerStore(); const sessions = ref<PlayerSession[]>([]); const history = ref<Array<{ status: string }>>([]); const editing = ref(false); const username = ref(''); const bio = ref(''); const email = ref(''); const emailCode = ref(''); const emailCurrentPassword = ref(''); const passwordCurrentPassword = ref(''); const newPassword = ref('')
const passwordBusy = ref(false)
const avatarBusy = ref(false)
const completed = computed(() => history.value.filter(item => ['solved', 'finished'].includes(item.status)).length); const solved = computed(() => history.value.filter(item => item.status === 'solved').length); const completionRate = computed(() => history.value.length ? Math.round(solved.value / history.value.length * 100) : 0)
const achievements = computed(() => [{ icon: '◈', name: '初探真相', note: '完成第一个谜题', unlocked: solved.value >= 1 }, { icon: '◉', name: '侦探之眼', note: '10次游戏中胜率超过80%', unlocked: history.value.length >= 10 && completionRate.value >= 80 }, { icon: '◎', name: '快手侦探', note: '10分钟内完成困难谜题', unlocked: false }, { icon: '◇', name: '无懈可击', note: '不放弃完成20个谜题', unlocked: solved.value >= 20 }, { icon: '◆', name: '传奇推理师', note: '完成所有困难谜题', unlocked: false }]); const unlockedCount = computed(() => achievements.value.filter(item => item.unlocked).length)
onMounted(async () => {
  if (!store.user)
    await store.restore()
  if (!store.user) {
    router.replace({ name: 'player-login', query: { redirect: '/pages/account/index' } })
    return
  }
  username.value = store.user.username; bio.value = store.user.bio || ''; email.value = store.user.email || ''; [sessions.value, history.value] = await Promise.all([playerApi.sessions(), gameApi.history()])
})
async function saveProfile() { store.user = await playerApi.updateProfile(username.value, bio.value); editing.value = false; uni.showToast({ title: '资料已更新', icon: 'success' }) }
async function chooseAvatar() {
  if (avatarBusy.value)
    return
  try {
    const selection = await uni.chooseImage({ count: 1, sizeType: ['compressed'], sourceType: ['album', 'camera'] })
    const filePath = selection.tempFilePaths[0]
    if (!filePath)
      return
    avatarBusy.value = true
    store.user = await playerApi.uploadAvatar(filePath)
    uni.showToast({ title: '头像已更新', icon: 'success' })
  }
  catch (error) {
    const message = (error as { errMsg?: string, message?: string }).message || (error as { errMsg?: string }).errMsg || ''
    if (!message.includes('cancel'))
      uni.showToast({ title: message || '头像上传失败', icon: 'none' })
  }
  finally {
    avatarBusy.value = false
  }
}
async function sendChangeCode() {
  try { await playerApi.sendCode(email.value, 'change_email'); uni.showToast({ title: '验证码已发送', icon: 'success' }) }
  catch (error) { uni.showToast({ title: (error as Error).message, icon: 'none' }) }
}
async function saveEmail() { store.user = await playerApi.changeEmail(email.value, emailCurrentPassword.value, emailCode.value); uni.showToast({ title: '邮箱已更新', icon: 'success' }) }
async function savePassword() {
  if (passwordBusy.value)
    return
  if (newPassword.value.length < 8 || newPassword.value.length > 72) {
    uni.showToast({ title: '新密码长度需为 8–72 位', icon: 'none' })
    return
  }
  passwordBusy.value = true
  try {
    const result = await playerApi.changePassword(passwordCurrentPassword.value, newPassword.value)
    store.accept(result)
    passwordCurrentPassword.value = ''
    newPassword.value = ''
    sessions.value = await playerApi.sessions()
    uni.showToast({ title: '密码已更新', icon: 'success' })
  }
  catch (error) {
    uni.showToast({ title: (error as Error).message || '修改密码失败，请稍后重试', icon: 'none' })
  }
  finally {
    passwordBusy.value = false
  }
}
async function revoke(id: string) { await playerApi.revokeSession(id); sessions.value = await playerApi.sessions() }
async function logout(all = false) {
  try {
    await store.logout(all)
    uni.showToast({ title: all ? '已退出全部设备' : '已退出当前设备', icon: 'success' })
    await uni.switchTab({ url: '/pages/index/index' })
  }
  catch (error) {
    uni.showToast({ title: (error as Error).message || '退出失败，请稍后重试', icon: 'none' })
  }
}
</script>

<template>
  <view v-if="store.user" class="profile-page">
    <header class="page-head">
      <text class="hgt-mono eyebrow">
        ◇ 个人中心
      </text><text class="hgt-display page-title">
        个人信息
      </text>
    </header>
    <view class="profile-grid">
      <aside class="profile-column">
        <view class="identity-card">
          <button class="avatar-picker" :loading="avatarBusy" :disabled="avatarBusy" aria-label="更换头像" @click="chooseAvatar">
            <image v-if="store.user.avatar_url" :src="store.user.avatar_url" class="avatar" mode="aspectFill" /><view v-else class="avatar fallback">
              {{ store.user.username.slice(0, 1).toUpperCase() }}
            </view><view class="avatar-action hgt-mono">
              {{ avatarBusy ? '上传中' : '更换头像' }}
            </view>
          </button><text class="hgt-display username">
            {{ store.user.username }}
          </text><text class="hgt-mono email">
            {{ store.user.email }}
          </text><button class="hgt-mono outline" @click="editing = !editing">
            {{ editing ? '收起编辑' : '编辑资料' }}
          </button>
        </view>
        <view class="stats-card hgt-mono">
          <view><text>完成谜题</text><strong>{{ completed }}</strong></view><view><text>总游戏</text><strong>{{ history.length }}</strong></view><view><text>完成率</text><strong>{{ completionRate }}%</strong></view><view><text>最长连胜</text><strong>—</strong></view><view><text>累计用时</text><strong>—</strong></view>
        </view>
      </aside>
      <main class="detail-column">
        <view v-if="editing" class="settings">
          <view class="setting profile-setting">
            <text class="hgt-mono section-title">
              编辑资料
            </text><input v-model="username" :maxlength="24" placeholder="用户名"><textarea v-model="bio" :maxlength="200" placeholder="写一句个人简介" /><view class="bio-count hgt-mono">
              {{ bio.length }}/200
            </view><button @click="saveProfile">
              保存资料
            </button>
          </view>
          <view class="setting">
            <text class="hgt-mono section-title">
              换绑邮箱
            </text><input v-model="email" placeholder="新邮箱"><input v-model="emailCode" placeholder="验证码"><input v-model="emailCurrentPassword" password placeholder="当前密码"><view class="button-row">
              <button @click="sendChangeCode">
                发送验证码
              </button><button @click="saveEmail">
                确认换绑
              </button>
            </view>
          </view>
          <view class="setting">
            <text class="hgt-mono section-title">
              设置密码
            </text><input v-model="passwordCurrentPassword" password placeholder="当前密码（首次设置留空）"><input v-model="newPassword" password confirm-type="done" placeholder="新密码（8–72 位）" @confirm="savePassword"><button :loading="passwordBusy" :disabled="passwordBusy" @click="savePassword">
              {{ passwordBusy ? '保存中…' : '保存密码' }}
            </button>
          </view>
          <view class="setting sessions">
            <text class="hgt-mono section-title">
              登录设备（最多 3 台）
            </text><view v-for="item in sessions" :key="item.id">
              <text>{{ item.device_name }} · {{ item.platform }}</text><button @click="revoke(item.id)">
                撤销
              </button>
            </view>
          </view>
        </view>
        <view class="bio-card">
          <text class="hgt-mono section-title">
            个人简介
          </text><text>{{ store.user.bio || '热爱推理，喜欢在迷雾中寻找真相。' }}</text>
        </view>
        <view class="achievement-card">
          <text class="hgt-mono section-title">
            成就（{{ unlockedCount }}/{{ achievements.length }}）
          </text><view class="achievement-grid">
            <view v-for="item in achievements" :key="item.name" class="achievement" :class="{ locked: !item.unlocked }">
              <text class="symbol">
                {{ item.icon }}
              </text><view><strong>{{ item.name }}</strong><text>{{ item.note }}</text></view><text v-if="item.unlocked" class="check">
                ✓
              </text>
            </view>
          </view>
        </view>
        <!-- #ifdef H5 -->
        <view class="support-card" @click="router.push({ name: 'donate' })">
          <view>
            <text class="hgt-mono section-title">
              ◆ 支持项目
            </text><text class="support-copy">
              帮助我们持续维护服务、创作谜题和改进体验。
            </text>
          </view><text class="support-arrow">
            →
          </text>
        </view>
        <!-- #endif -->
        <view class="logout-row">
          <button @click="logout(false)">
            退出当前设备
          </button><button class="danger" @click="logout(true)">
            退出全部设备
          </button>
        </view>
      </main>
    </view>
  </view>
</template>

<style scoped>
.profile-page {
  min-height: 100%;
  padding-bottom: 48px;
  background: var(--hgt-bg);
  color: var(--hgt-text);
}
.page-head {
  display: flex;
  padding: 32px 48px 24px;
  border-bottom: 1px solid var(--hgt-border);
  gap: 8px;
  flex-direction: column;
}
.eyebrow {
  color: var(--hgt-brand);
  font-size: 11px;
  letter-spacing: 0.22em;
}
.page-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 28px;
  font-weight: 600;
  letter-spacing: 0.06em;
}
.profile-grid {
  display: grid;
  box-sizing: border-box;
  width: min(var(--hgt-content-max), 100%);
  margin: 0 auto;
  padding: 28px 48px;
  gap: 24px;
  grid-template-columns: 300px minmax(0, 1fr);
}
.profile-column,
.detail-column {
  display: flex;
  gap: 16px;
  flex-direction: column;
}
.identity-card,
.stats-card,
.bio-card,
.achievement-card,
.setting {
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  background: var(--hgt-card);
}
.identity-card {
  display: flex;
  padding: 24px;
  align-items: center;
  gap: 12px;
  flex-direction: column;
}
.avatar-picker {
  position: relative;
  width: 96px;
  height: 96px;
  margin: 0;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: transparent;
  overflow: hidden;
}
.avatar {
  display: block;
  width: 96px;
  height: 96px;
  border: 1px solid var(--hgt-border-soft);
  border-radius: 50%;
  box-sizing: border-box;
}
.avatar-action {
  position: absolute;
  right: 0;
  bottom: 0;
  left: 0;
  display: flex;
  height: 26px;
  align-items: center;
  justify-content: center;
  background: rgba(7, 20, 24, 0.78);
  color: #fff;
  font-size: 10px;
}
.fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--hgt-card-2);
  color: var(--hgt-brand);
  font-family: var(--hgt-font-display);
  font-size: 36px;
}
.username {
  margin-top: 8px;
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 20px;
  font-weight: 600;
}
.email {
  color: var(--hgt-text-3);
  font-size: 12px;
}
.outline {
  width: 100%;
  height: 38px;
  margin-top: 12px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1;
}
.outline::after,
.setting button::after,
.button-row button::after {
  border: 0;
}
.stats-card view {
  display: flex;
  padding: 12px 16px;
  border-bottom: 1px solid var(--hgt-border);
  align-items: center;
  justify-content: space-between;
  color: var(--hgt-text-2);
  font-size: 13px;
}
.stats-card view:last-child {
  border-bottom: 0;
}
.stats-card strong {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 16px;
}
.section-title {
  display: block;
  margin-bottom: 12px;
  color: var(--hgt-text);
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.08em;
}
.setting,
.bio-card {
  display: flex;
  padding: 20px;
  gap: 10px;
  flex-direction: column;
  font-size: 13px;
}
.setting input,
.setting textarea {
  box-sizing: border-box;
  width: 100%;
  min-height: 42px;
  padding: 10px 12px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  background: var(--hgt-card-2);
  color: var(--hgt-text);
  font-size: 14px;
}
.setting textarea {
  min-height: 80px;
}
.setting button,
.button-row button {
  display: flex;
  height: 40px;
  margin: 0;
  padding: 0 14px;
  border: 0;
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-size: 13px;
  font-weight: 600;
  line-height: 1;
}
.button-row {
  display: flex;
  gap: 8px;
}
.button-row button {
  flex: 1;
}
.button-row button:first-child {
  border: 1px solid var(--hgt-border);
  background: transparent;
  color: var(--hgt-text);
  font-weight: 400;
}
.sessions view {
  display: flex;
  padding: 10px 0;
  border-bottom: 1px solid var(--hgt-border);
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  color: var(--hgt-text-2);
  font-size: 13px;
}
.sessions view:last-child {
  border-bottom: 0;
}
.sessions button {
  flex: none;
  height: 30px;
  padding: 0 10px;
  border: 1px solid rgba(201, 74, 85, 0.45);
  background: transparent;
  color: var(--hgt-danger);
  font-size: 12px;
}
.bio-count {
  align-self: flex-end;
  color: var(--hgt-text-3);
  font-size: 11px;
}
.achievement-card {
  padding: 20px;
}
.achievement-grid {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
}
.achievement-grid view {
  display: flex;
  padding: 12px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  gap: 8px;
  flex-direction: column;
  background: var(--hgt-card-2);
  color: var(--hgt-text-2);
  font-size: 12px;
}
.achievement-grid view.unlocked {
  border-color: rgba(91, 200, 189, 0.4);
  background: var(--hgt-brand-soft);
  color: var(--hgt-text);
}
.logout-row {
  display: flex;
  gap: 10px;
}
.logout-row button {
  flex: 1;
}
@media (max-width: 900px) {
  .profile-grid {
    padding-right: 16px;
    padding-left: 16px;
    grid-template-columns: 1fr;
  }
  .page-head {
    padding-right: 16px;
    padding-left: 16px;
  }
}

.achievement {
  position: relative;
  display: flex;
  padding: 12px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  gap: 10px;
  align-items: flex-start;
  background: var(--hgt-card-2);
  color: var(--hgt-text-2);
  font-size: 12px;
}
.achievement.locked {
  opacity: 0.55;
}
.achievement .symbol {
  color: var(--hgt-brand);
  font-size: 18px;
  line-height: 1;
}
.achievement strong {
  display: block;
  color: var(--hgt-text);
  font-size: 13px;
  margin-bottom: 4px;
}
.achievement .check {
  position: absolute;
  top: 10px;
  right: 12px;
  color: var(--hgt-brand);
}
.support-card {
  display: flex;
  padding: 18px 20px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  background: var(--hgt-card);
}
.support-card > view {
  display: flex;
  gap: 6px;
  flex-direction: column;
}
.support-copy {
  color: var(--hgt-text-2);
  font-size: 12px;
}
.support-arrow {
  color: var(--hgt-brand);
  font-size: 20px;
}
.logout-row {
  display: flex;
  margin-top: 4px;
  gap: 10px;
}
.logout-row button {
  display: flex;
  flex: 1;
  height: 42px;
  margin: 0;
  padding: 0 12px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1;
}
.logout-row button.danger {
  border-color: rgba(201, 74, 85, 0.45);
  color: var(--hgt-danger);
}
.logout-row button::after {
  border: 0;
}
</style>
