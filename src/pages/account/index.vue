<script setup lang="ts">
import type { PlayerSession } from '@/api/player'
import type { GameHistoryItem, GameHistoryResult } from '@/types/game'
import { playerApi } from '@/api/player'
import { gameApi } from '@/api/turtle'
import { usePlayerStore } from '@/store/playerStore'

definePage({ name: 'player-account', layout: 'tabbar', style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } } })

type Panel = 'main' | 'profile' | 'security'

const router = useRouter()
const store = usePlayerStore()

const panel = ref<Panel>('main')
const loading = ref(true)
const sessions = ref<PlayerSession[]>([])
const history = ref<GameHistoryResult | null>(null)
const continueItem = ref<GameHistoryItem | null>(null)

const username = ref('')
const bio = ref('')
const email = ref('')
const emailCode = ref('')
const emailCurrentPassword = ref('')
const passwordCurrentPassword = ref('')
const newPassword = ref('')
const passwordBusy = ref(false)
const avatarBusy = ref(false)
const profileBusy = ref(false)

const stats = computed(() => history.value?.stats || {
  played: 0,
  solved: 0,
  unfinished: 0,
  total_questions: 0,
  total_duration_seconds: 0,
})

const durationLabel = computed(() => {
  const total = Math.max(0, Number(stats.value.total_duration_seconds || 0))
  if (!total)
    return '—'
  const h = Math.floor(total / 3600)
  const m = Math.floor((total % 3600) / 60)
  if (h > 0)
    return `${h}h${String(m).padStart(2, '0')}m`
  return `${m}m`
})

const maskedEmail = computed(() => {
  const value = store.user?.email || ''
  if (!value)
    return '未绑定邮箱'
  const at = value.indexOf('@')
  if (at <= 0)
    return value
  const name = value.slice(0, at)
  const domain = value.slice(at)
  const head = name.slice(0, 1)
  const tail = name.length > 2 ? name.slice(-1) : ''
  return `${head}${'*'.repeat(Math.max(1, name.length - 1 - tail.length))}${tail}${domain}`
})

const handleLine = computed(() => store.user?.bio?.trim() || `@${store.user?.username || ''}`)

const panelTitle = computed(() => ({
  main: '我的',
  profile: '编辑资料',
  security: '账号与安全',
} as const satisfies Record<Panel, string>)[panel.value])

async function loadHistory() {
  const [historyResult, continueResult] = await Promise.all([
    gameApi.history({ page: 1, page_size: 1 }),
    gameApi.history({ continue_only: true, page: 1, page_size: 1 }),
  ])
  history.value = historyResult
  continueItem.value = continueResult.items?.[0] || null
}

function goHistory() {
  router.push({ name: 'history' })
}

function goContinue(item?: GameHistoryItem | null) {
  const target = item || continueItem.value
  if (!target?.id)
    return
  router.push({ name: 'game', params: { id: target.id } })
}

function openPanel(next: Panel) {
  panel.value = next
  if (next === 'profile' && store.user) {
    username.value = store.user.username
    bio.value = store.user.bio || ''
  }
}

function goLogin() {
  router.push({ name: 'player-login', query: { redirect: '/pages/account/index' } })
}

function goRegister() {
  router.push({ name: 'player-register', query: { redirect: '/pages/account/index' } })
}

function openLegal(kind: 'service_terms' | 'privacy_policy') {
  uni.navigateTo({ url: `/pages/legal-document/index?type=${kind}` })
}

function goNamed(name: string) {
  router.push({ name })
}

async function saveProfile() {
  if (profileBusy.value)
    return
  profileBusy.value = true
  try {
    store.user = await playerApi.updateProfile(username.value.trim(), bio.value.trim())
    uni.showToast({ title: '资料已更新', icon: 'success' })
  }
  catch (error) {
    uni.showToast({ title: (error as Error).message || '保存失败', icon: 'none' })
  }
  finally {
    profileBusy.value = false
  }
}

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
  if (!email.value.trim()) {
    uni.showToast({ title: '请填写新邮箱', icon: 'none' })
    return
  }
  try {
    await playerApi.sendCode(email.value.trim(), 'change_email')
    uni.showToast({ title: '验证码已发送', icon: 'success' })
  }
  catch (error) {
    uni.showToast({ title: (error as Error).message, icon: 'none' })
  }
}

async function saveEmail() {
  try {
    store.user = await playerApi.changeEmail(email.value.trim(), emailCurrentPassword.value, emailCode.value)
    email.value = ''
    emailCode.value = ''
    emailCurrentPassword.value = ''
    uni.showToast({ title: '邮箱已更新', icon: 'success' })
  }
  catch (error) {
    uni.showToast({ title: (error as Error).message || '邮箱更新失败', icon: 'none' })
  }
}

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

async function revoke(id: string) {
  try {
    await playerApi.revokeSession(id)
    sessions.value = await playerApi.sessions()
    uni.showToast({ title: '已撤销该设备', icon: 'success' })
  }
  catch (error) {
    uni.showToast({ title: (error as Error).message || '撤销失败', icon: 'none' })
  }
}

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

onMounted(async () => {
  try {
    if (!store.user)
      await store.restore()
    if (!store.user) {
      return
    }
    username.value = store.user.username
    bio.value = store.user.bio || ''
    email.value = store.user.email || ''
    const [sessionList] = await Promise.all([
      playerApi.sessions().catch(() => [] as PlayerSession[]),
      loadHistory().catch(() => {
        history.value = null
        continueItem.value = null
      }),
    ])
    sessions.value = sessionList
  }
  finally {
    loading.value = false
  }
})
</script>

<template>
  <view class="account-page">
    <!-- Guest -->
    <view v-if="!store.user" class="guest">
      <text class="page-title">
        我的
      </text>
      <view class="guest-card">
        <text class="guest-lead">
          登录后可以同步推理记录、继续未完成的谜题。
        </text>
        <view class="guest-actions">
          <button class="btn-primary" @click="goLogin">
            登录
          </button>
          <button class="btn-ghost" @click="goRegister">
            注册
          </button>
        </view>
      </view>
      <view class="list-block">
        <!-- #ifdef H5 -->
        <view class="list-row" @click="goNamed('friends')">
          <text>友链</text><text class="row-arrow">
            →
          </text>
        </view>
        <view class="list-row" @click="goNamed('donate')">
          <text>支持项目</text><text class="row-arrow">
            →
          </text>
        </view>
        <!-- #endif -->
        <view class="list-row" @click="openLegal('service_terms')">
          <text>服务条款</text><text class="row-arrow">
            →
          </text>
        </view>
        <view class="list-row" @click="openLegal('privacy_policy')">
          <text>隐私政策</text><text class="row-arrow">
            →
          </text>
        </view>
      </view>
    </view>

    <!-- Logged in · main -->
    <view v-else-if="panel === 'main'" class="panel-main">
      <text class="page-title">
        {{ panelTitle }}
      </text>

      <view class="identity">
        <view class="avatar-wrap">
          <image v-if="store.user.avatar_url" :src="store.user.avatar_url" class="avatar" mode="aspectFill" />
          <view v-else class="avatar fallback">
            {{ (store.user.username || '游').slice(0, 1) }}
          </view>
        </view>
        <view class="identity-copy">
          <text class="identity-name">
            {{ store.user.username }}
          </text>
          <text class="identity-handle">
            {{ handleLine }}
          </text>
        </view>
        <button class="row-link" @click="openPanel('profile')">
          <text>编辑资料</text><text class="row-arrow">
            →
          </text>
        </button>
      </view>

      <view class="summary">
        <view class="summary-item">
          <text class="summary-value">
            {{ stats.played }}
          </text>
          <text class="summary-label">
            玩过
          </text>
        </view>
        <view class="summary-item">
          <text class="summary-value">
            {{ stats.solved }}
          </text>
          <text class="summary-label">
            已解开
          </text>
        </view>
        <view class="summary-item">
          <text class="summary-value">
            {{ stats.total_questions }}
          </text>
          <text class="summary-label">
            提问
          </text>
        </view>
        <view class="summary-item">
          <text class="summary-value">
            {{ durationLabel }}
          </text>
          <text class="summary-label">
            用时
          </text>
        </view>
      </view>

      <view v-if="continueItem" class="continue-card">
        <text class="section-label">
          继续推理
        </text>
        <view class="continue-row" @click="goContinue(continueItem)">
          <text class="continue-dot">
            ●
          </text>
          <view class="continue-copy">
            <text class="continue-title">
              {{ continueItem.title || '未命名谜题' }}
            </text>
            <text class="continue-meta">
              {{ continueItem.question_count || 0 }} 次提问 · 进行中
            </text>
          </view>
          <text class="continue-cta">
            继续推理 →
          </text>
        </view>
      </view>

      <view class="list-block">
        <view class="list-row" @click="goHistory">
          <text>我的推理</text><text class="row-arrow">
            →
          </text>
        </view>
        <!-- #ifdef H5 -->
        <view class="list-row" @click="goNamed('friends')">
          <text>友链</text><text class="row-arrow">
            →
          </text>
        </view>
        <view class="list-row" @click="goNamed('donate')">
          <text>支持项目</text><text class="row-arrow">
            →
          </text>
        </view>
        <!-- #endif -->
      </view>

      <view class="list-block settings-block">
        <view class="list-row" @click="openPanel('profile')">
          <text>编辑资料</text><text class="row-arrow">
            →
          </text>
        </view>
        <view class="list-row" @click="openPanel('security')">
          <text>账号与安全</text><text class="row-arrow">
            →
          </text>
        </view>
      </view>
    </view>

    <!-- Profile panel -->
    <view v-else-if="panel === 'profile'" class="panel-sub">
      <button class="back-row" @click="openPanel('main')">
        ← 返回
      </button>
      <text class="page-title">
        {{ panelTitle }}
      </text>

      <view class="form-card">
        <view class="avatar-row">
          <view class="avatar-wrap sm">
            <image v-if="store.user.avatar_url" :src="store.user.avatar_url" class="avatar" mode="aspectFill" />
            <view v-else class="avatar fallback">
              {{ (store.user.username || '游').slice(0, 1) }}
            </view>
          </view>
          <button class="btn-ghost sm" :loading="avatarBusy" :disabled="avatarBusy" @click="chooseAvatar">
            {{ avatarBusy ? '上传中…' : '更换头像' }}
          </button>
        </view>

        <view class="field">
          <text class="field-label">
            用户名
          </text>
          <input v-model="username" class="field-input" :maxlength="24" placeholder="用户名">
        </view>
        <view class="field">
          <text class="field-label">
            个人简介
          </text>
          <textarea v-model="bio" class="field-input area" :maxlength="200" placeholder="写一句个人简介" />
          <text class="field-hint">
            {{ bio.length }}/200
          </text>
        </view>
        <view class="field">
          <text class="field-label">
            邮箱
          </text>
          <view class="email-mask-row">
            <text class="email-mask">
              {{ maskedEmail }}
            </text>
            <button class="link-btn" @click="openPanel('security')">
              前往安全设置 →
            </button>
          </view>
        </view>
        <button class="btn-primary" :loading="profileBusy" :disabled="profileBusy" @click="saveProfile">
          保存资料
        </button>
      </view>
    </view>

    <!-- Security panel -->
    <view v-else class="panel-sub">
      <button class="back-row" @click="openPanel('main')">
        ← 返回
      </button>
      <text class="page-title">
        {{ panelTitle }}
      </text>

      <view class="form-card">
        <text class="section-label">
          修改密码
        </text>
        <view class="field">
          <text class="field-label">
            当前密码
          </text>
          <input v-model="passwordCurrentPassword" class="field-input" password placeholder="当前密码（首次设置可留空）">
        </view>
        <view class="field">
          <text class="field-label">
            新密码
          </text>
          <input v-model="newPassword" class="field-input" password confirm-type="done" placeholder="新密码（8–72 位）" @confirm="savePassword">
        </view>
        <button class="btn-primary" :loading="passwordBusy" :disabled="passwordBusy" @click="savePassword">
          {{ passwordBusy ? '保存中…' : '保存密码' }}
        </button>
      </view>

      <view class="form-card">
        <text class="section-label">
          换绑邮箱
        </text>
        <view class="field">
          <text class="field-label">
            当前邮箱
          </text>
          <text class="field-static">
            {{ maskedEmail }}
          </text>
        </view>
        <view class="field">
          <text class="field-label">
            新邮箱
          </text>
          <input v-model="email" class="field-input" type="text" placeholder="新邮箱">
        </view>
        <view class="field">
          <text class="field-label">
            验证码
          </text>
          <view class="code-row">
            <input v-model="emailCode" class="field-input flex" :maxlength="6" placeholder="验证码">
            <button class="btn-ghost sm" @click="sendChangeCode">
              发送验证码
            </button>
          </view>
        </view>
        <view class="field">
          <text class="field-label">
            当前密码
          </text>
          <input v-model="emailCurrentPassword" class="field-input" password placeholder="当前密码">
        </view>
        <button class="btn-primary" @click="saveEmail">
          确认换绑
        </button>
      </view>

      <view class="form-card">
        <text class="section-label">
          登录设备
        </text>
        <view v-if="sessions.length" class="session-list">
          <view v-for="item in sessions" :key="item.id" class="session-row">
            <view class="session-copy">
              <text class="session-name">
                {{ item.device_name || '未知设备' }}
              </text>
              <text class="session-meta">
                {{ item.platform }}
              </text>
            </view>
            <button class="btn-ghost xs danger" @click="revoke(item.id)">
              撤销
            </button>
          </view>
        </view>
        <text v-else class="empty-line">
          暂无其他登录设备
        </text>
      </view>

      <view class="list-block settings-block">
        <view class="list-row" @click="logout(false)">
          <text>退出当前设备</text><text class="row-arrow">
            →
          </text>
        </view>
        <view class="list-row" @click="logout(true)">
          <text>退出全部设备</text><text class="row-arrow">
            →
          </text>
        </view>
      </view>
    </view>

    <view v-if="store.user && loading" class="loading-line">
      <text>加载中…</text>
    </view>
  </view>
</template>

<style scoped>
.account-page {
  min-height: 100%;
  padding-bottom: 48px;
  background: var(--hgt-bg);
  color: var(--hgt-text);
  font-family: var(--hgt-font-body);
}
.page-title {
  display: block;
  margin-bottom: 16px;
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 24px;
  font-weight: 600;
  letter-spacing: 0.08em;
}
.guest,
.panel-main,
.panel-sub {
  box-sizing: border-box;
  width: min(560px, 100%);
  margin: 0 auto;
  padding: 24px 16px 32px;
}
.guest-card {
  margin-bottom: 20px;
  padding: 18px 16px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  background: var(--hgt-card);
}
.guest-lead {
  display: block;
  margin-bottom: 14px;
  color: var(--hgt-text-2);
  font-size: 14px;
  line-height: 1.6;
}
.guest-actions {
  display: flex;
  gap: 10px;
}
.btn-primary,
.btn-ghost {
  display: flex;
  height: 42px;
  margin: 0;
  padding: 0 16px;
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  font-size: 14px;
  line-height: 1;
}
.btn-primary {
  border: 0;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-weight: 600;
}
.btn-primary:disabled {
  opacity: 0.55;
}
.btn-ghost {
  border: 1px solid var(--hgt-border);
  background: transparent;
  color: var(--hgt-text-2);
}
.btn-primary::after,
.btn-ghost::after,
.back-row::after,
.row-link::after,
.link-btn::after {
  border: 0;
}
.guest-actions .btn-primary,
.guest-actions .btn-ghost {
  flex: 1;
}
.btn-ghost.sm {
  height: 36px;
  padding: 0 12px;
  font-size: 13px;
}
.btn-ghost.xs {
  height: 30px;
  padding: 0 10px;
  font-size: 12px;
}
.btn-ghost.danger {
  border-color: rgba(158, 83, 86, 0.45);
  color: var(--hgt-danger);
}

.identity {
  display: flex;
  min-height: 128px;
  margin-bottom: 16px;
  padding: 16px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  align-items: center;
  gap: 14px;
  background: var(--hgt-card);
}
.avatar-wrap {
  position: relative;
  width: 68px;
  height: 68px;
  flex: none;
}
.avatar-wrap.sm {
  width: 64px;
  height: 64px;
}
.avatar {
  display: block;
  width: 100%;
  height: 100%;
  border: 1px solid var(--hgt-border-soft);
  border-radius: 50%;
  box-sizing: border-box;
  background: var(--hgt-card-2);
}
.avatar.fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--hgt-brand);
  font-family: var(--hgt-font-display);
  font-size: 26px;
}
.identity-copy {
  display: flex;
  min-width: 0;
  flex: 1;
  gap: 4px;
  flex-direction: column;
}
.identity-name {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 18px;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.identity-handle {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.row-link {
  display: flex;
  margin: 0;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1;
  gap: 4px;
  align-items: center;
  flex: none;
}

.summary {
  display: grid;
  margin-bottom: 16px;
  padding: 14px 8px;
  border-top: 1px solid var(--hgt-border-soft);
  border-bottom: 1px solid var(--hgt-border-soft);
  grid-template-columns: repeat(4, 1fr);
  gap: 8px;
}
.summary-item {
  display: flex;
  gap: 4px;
  align-items: center;
  flex-direction: column;
  text-align: center;
}
.summary-value {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 18px;
  font-weight: 600;
}
.summary-label {
  color: var(--hgt-text-3);
  font-size: 11px;
}

.continue-card {
  margin-bottom: 16px;
}
.section-label {
  display: block;
  margin-bottom: 8px;
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
  letter-spacing: 0.08em;
}
.continue-row {
  display: flex;
  padding: 14px 16px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  align-items: center;
  gap: 10px;
  background: var(--hgt-card);
}
.continue-dot {
  color: var(--hgt-gold);
  font-size: 10px;
  flex: none;
}
.continue-copy {
  display: flex;
  min-width: 0;
  flex: 1;
  gap: 2px;
  flex-direction: column;
}
.continue-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 15px;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.continue-meta {
  color: var(--hgt-text-3);
  font-size: 12px;
}
.continue-cta {
  color: var(--hgt-brand);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
  flex: none;
}

.list-block {
  margin-bottom: 14px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  background: var(--hgt-card);
  overflow: hidden;
}
.settings-block {
  margin-top: 8px;
}
.list-row {
  display: flex;
  min-height: 48px;
  padding: 0 16px;
  border-bottom: 1px solid var(--hgt-border-soft);
  align-items: center;
  justify-content: space-between;
  color: var(--hgt-text);
  font-size: 14px;
}
.list-row:last-child {
  border-bottom: 0;
}
.row-arrow {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 14px;
}

.back-row {
  display: flex;
  width: max-content;
  height: 32px;
  margin: 0 0 8px;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
  line-height: 1;
  align-items: center;
}
.form-card {
  margin-bottom: 14px;
  padding: 16px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  background: var(--hgt-card);
  display: flex;
  gap: 12px;
  flex-direction: column;
}
.avatar-row {
  display: flex;
  margin-bottom: 4px;
  align-items: center;
  gap: 12px;
}
.field {
  display: flex;
  gap: 6px;
  flex-direction: column;
}
.field-label {
  color: var(--hgt-text-2);
  font-size: 12px;
}
.field-input {
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
.field-input.area {
  min-height: 80px;
}
.field-hint {
  align-self: flex-end;
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
}
.field-static {
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-mono);
  font-size: 13px;
}
.email-mask-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.email-mask {
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-mono);
  font-size: 13px;
}
.link-btn {
  margin: 0;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--hgt-brand);
  font-size: 12px;
  line-height: 1;
  flex: none;
}
.code-row {
  display: flex;
  gap: 8px;
  align-items: center;
}
.code-row .flex {
  flex: 1;
  min-width: 0;
}
.session-list {
  display: flex;
  gap: 0;
  flex-direction: column;
}
.session-row {
  display: flex;
  padding: 10px 0;
  border-bottom: 1px solid var(--hgt-border-soft);
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.session-row:last-child {
  border-bottom: 0;
}
.session-copy {
  display: flex;
  min-width: 0;
  gap: 2px;
  flex-direction: column;
}
.session-name {
  color: var(--hgt-text);
  font-size: 13px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.session-meta {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
}
.empty-line {
  color: var(--hgt-text-3);
  font-size: 13px;
}
.loading-line {
  padding: 8px 16px;
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
  text-align: center;
}
</style>
