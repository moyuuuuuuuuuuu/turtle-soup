<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import type { LegalDocuments } from '@/api/player'
import { playerApi } from '@/api/player'
import { usePlayerStore } from '@/store/playerStore'
import LegalDocumentPopup from './LegalDocumentPopup.vue'

declare const tt: {
  openPrivacyContract?: (options: { fail?: (error: unknown) => void }) => void
}

type AuthMode = 'password' | 'code' | 'register' | 'reset'
type EmailCodePurpose = 'login' | 'register' | 'reset_password'

const props = withDefaults(defineProps<{ initialMode?: AuthMode }>(), { initialMode: 'password' })
const route = useRoute()
const store = usePlayerStore()
const mode = ref<AuthMode>(props.initialMode)
const busy = ref(false)
const codeBusy = reactive<Record<EmailCodePurpose, boolean>>({ login: false, register: false, reset_password: false })
const codeCountdown = reactive<Record<EmailCodePurpose, number>>({ login: 0, register: 0, reset_password: 0 })
const codeTimers: Partial<Record<EmailCodePurpose, ReturnType<typeof setInterval>>> = {}
const username = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const emailCode = ref('')
const legalDocuments = ref<LegalDocuments>({ service_terms: '', privacy_policy: '' })
const legalDocumentVisible = ref(false)
const legalDocumentKind = ref<keyof LegalDocuments>('service_terms')

const modeTitle = computed(() => ({
  password: '登录',
  code: '邮箱验证登录',
  register: '注册',
  reset: '找回密码',
} as const satisfies Record<AuthMode, string>)[mode.value])

watch(mode, () => { emailCode.value = '' })

onMounted(() => {
  void playerApi.legalDocuments().then((documents) => { legalDocuments.value = documents }).catch(() => {})
})

onUnmounted(() => {
  Object.values(codeTimers).forEach(timer => timer && clearInterval(timer))
})

function message(error: unknown) { return error instanceof Error ? error.message : '操作失败，请稍后重试' }
function openManagedLegalDocument(kind: keyof LegalDocuments) {
  // #ifdef H5
  legalDocumentKind.value = kind
  legalDocumentVisible.value = true
  // #endif
  // #ifndef H5
  uni.navigateTo({ url: `/pages/legal-document/index?type=${kind}` })
  // #endif
}
function openMiniProgramPrivacy() {
  // #ifdef MP-WEIXIN
  wx.openPrivacyContract({
    fail: () => uni.showToast({ title: '暂时无法打开隐私保护指引', icon: 'none' }),
  })
  // #endif
  // #ifdef MP-TOUTIAO
  if (typeof tt.openPrivacyContract !== 'function') {
    uni.showToast({ title: '当前抖音版本暂不支持打开隐私政策', icon: 'none' })
    return
  }
  tt.openPrivacyContract({
    fail: () => uni.showToast({ title: '开发者工具不支持，请使用真机查看', icon: 'none' }),
  })
  // #endif
}
function normalizeRedirect(raw: string) {
  if (!raw)
    return ''
  let value = raw
  if (!value.startsWith('/pages/')) {
    try {
      value = decodeURIComponent(value)
    }
    catch {}
  }
  return value.startsWith('/pages/') ? value : ''
}
function finish(result: Awaited<ReturnType<typeof playerApi.passwordLogin>>) {
  store.accept(result)
  const notes: string[] = ['登录成功 · 欢迎回到深海']
  if (result.merged_games)
    notes.push(`已合并 ${result.merged_games} 局推理记录`)
  uni.showToast({ title: notes.join('\n'), icon: 'success', duration: 1400 })
  const redirect = normalizeRedirect(String(route.query.redirect || ''))
  setTimeout(() => {
    if (redirect) {
      uni.redirectTo({
        url: redirect,
        fail: () => uni.switchTab({ url: '/pages/index/index' }),
      })
      return
    }
    uni.switchTab({ url: '/pages/index/index' })
  }, 420)
}
function startCodeCountdown(purpose: EmailCodePurpose) {
  const currentTimer = codeTimers[purpose]
  if (currentTimer)
    clearInterval(currentTimer)
  codeCountdown[purpose] = 60
  codeTimers[purpose] = setInterval(() => {
    if (codeCountdown[purpose] <= 1) {
      codeCountdown[purpose] = 0
      clearInterval(codeTimers[purpose])
      delete codeTimers[purpose]
      return
    }
    codeCountdown[purpose]--
  }, 1000)
}
function codeButtonText(purpose: EmailCodePurpose) {
  if (codeBusy[purpose])
    return '发送中'
  return codeCountdown[purpose] > 0 ? `${codeCountdown[purpose]}s` : '发送验证码'
}
async function sendCode(purpose: EmailCodePurpose) {
  if (!email.value) {
    uni.showToast({ title: '请先填写邮箱', icon: 'none' })
    return false
  }
  if (codeBusy[purpose] || codeCountdown[purpose] > 0)
    return false
  codeBusy[purpose] = true
  try { await playerApi.sendCode(email.value, purpose); startCodeCountdown(purpose); uni.showToast({ title: '验证码已发送', icon: 'success' }); return true }
  catch (error) { uni.showToast({ title: message(error), icon: 'none' }); return false }
  finally { codeBusy[purpose] = false }
}
async function submit() {
  if (busy.value)
    return
  if (mode.value === 'register' && password.value !== passwordConfirmation.value)
    return uni.showToast({ title: '两次输入的密码不一致', icon: 'none' })
  const submittingMode = mode.value
  busy.value = true
  try {
    if (submittingMode === 'password')
      finish(await playerApi.passwordLogin(email.value, password.value))
    else if (submittingMode === 'code')
      finish(await playerApi.codeLogin(email.value, emailCode.value))
    else if (submittingMode === 'register')
      finish(await playerApi.register({ username: username.value, email: email.value, password: password.value, email_code: emailCode.value }))
    else finish(await playerApi.resetPassword(email.value, emailCode.value, password.value))
  }
  catch (error) {
    if (submittingMode === 'password' || submittingMode === 'code')
      mode.value = submittingMode
    uni.showToast({ title: message(error), icon: 'none' })
  }
  finally { busy.value = false }
}
type MiniProgramPlatform = 'wechat' | 'douyin'
function uniLogin(): Promise<{ code: string, anonymousCode?: string }> {
  return new Promise((resolve, reject) => uni.login({
    success: result => resolve(result as { code: string, anonymousCode?: string }),
    fail: reject,
  }))
}
async function authorizeMiniProgram(platform: MiniProgramPlatform) {
  if (busy.value)
    return
  busy.value = true
  try {
    const credential = await uniLogin()
    if (!credential.code)
      throw new Error('未获取到小程序登录凭证')
    finish(await playerApi.miniProgramLogin(platform, credential.code, credential.anonymousCode || ''))
  }
  catch (error) { uni.showToast({ title: message(error), icon: 'none' }) }
  finally { busy.value = false }
}
</script>

<template>
  <view class="auth-page">
    <image
      class="auth-bg"
      src="/static/hgt/bg/bg_deep_ocean_hero.jpg"
      mode="aspectFill"
    />
    <view class="auth-veil" />
    <view class="auth-shell">
      <view class="brand-block">
        <image class="brand-logo" src="/static/brand/logo-mark-dark.png" mode="aspectFit" />
        <view class="brand-text">
          <text class="brand-en">
            MOYUU
          </text>
          <text class="brand-zh">
            海龟汤
          </text>
        </view>
        <text class="brand-sub">
          谜题沉在水下，真相等待浮现。
        </text>
        <text class="brand-extra">
          登录后可同步推理记录、继续未完成的谜题。
        </text>
      </view>

      <view class="form-card">
        <text class="form-title">
          {{ modeTitle }}
        </text>

        <!-- #ifdef H5 -->
        <view :key="mode" class="form-body">
          <template v-if="mode === 'password'">
            <label class="field">
              <text class="field-label">用户名或邮箱</text>
              <input v-model="email" class="field-input" type="text" placeholder="请输入用户名或邮箱">
            </label>
            <label class="field">
              <text class="field-label">密码</text>
              <input v-model="password" class="field-input" password confirm-type="done" placeholder="请输入密码" @confirm="submit">
            </label>
            <button class="primary" :disabled="busy" @click="submit">
              {{ busy ? '登录中…' : '登录' }}
            </button>
            <view class="auth-links">
              <text class="auth-link" @click="mode = 'code'">
                邮箱验证码登录
              </text>
              <text class="auth-link-sep">
                ·
              </text>
              <text class="auth-link" @click="mode = 'register'">
                注册
              </text>
              <text class="auth-link-sep">
                ·
              </text>
              <text class="auth-link" @click="mode = 'reset'">
                忘记密码
              </text>
            </view>
            <text class="agreement">
              登录即表示同意
              <text class="agreement-link" @click="openManagedLegalDocument('service_terms')">
                服务条款
              </text>
              与
              <text class="agreement-link" @click="openManagedLegalDocument('privacy_policy')">
                隐私政策
              </text>
            </text>
          </template>

          <template v-else-if="mode === 'code'">
            <label class="field">
              <text class="field-label">邮箱</text>
              <input v-model="email" class="field-input" type="text" placeholder="your@email.com">
            </label>
            <label class="field">
              <text class="field-label">验证码</text>
              <view class="code-row">
                <input v-model="emailCode" class="field-input flex" type="number" :maxlength="6" confirm-type="done" placeholder="6 位验证码" @confirm="submit">
                <button class="code-btn" :disabled="codeBusy.login || codeCountdown.login > 0" @click="sendCode('login')">
                  {{ codeButtonText('login') }}
                </button>
              </view>
            </label>
            <button class="primary" :disabled="busy" @click="submit">
              {{ busy ? '验证中…' : '验证登录' }}
            </button>
            <text class="agreement">
              登录即表示同意
              <text class="agreement-link" @click="openManagedLegalDocument('service_terms')">
                服务条款
              </text>
              与
              <text class="agreement-link" @click="openManagedLegalDocument('privacy_policy')">
                隐私政策
              </text>
            </text>
            <button class="ghost" @click="mode = 'password'">
              ← 返回登录
            </button>
          </template>

          <template v-else-if="mode === 'register'">
            <label class="field">
              <text class="field-label">用户名</text>
              <input v-model="username" class="field-input" type="text" placeholder="4–20 个字符">
            </label>
            <label class="field">
              <text class="field-label">邮箱</text>
              <input v-model="email" class="field-input" type="text" placeholder="your@email.com">
            </label>
            <label class="field">
              <text class="field-label">密码</text>
              <input v-model="password" class="field-input" password placeholder="至少 8 位">
            </label>
            <label class="field">
              <text class="field-label">确认密码</text>
              <input v-model="passwordConfirmation" class="field-input" password placeholder="再次输入密码">
            </label>
            <label class="field">
              <text class="field-label">邮箱验证码</text>
              <view class="code-row">
                <input v-model="emailCode" class="field-input flex" type="number" :maxlength="6" confirm-type="done" placeholder="6 位验证码" @confirm="submit">
                <button class="code-btn" :disabled="codeBusy.register || codeCountdown.register > 0" @click="sendCode('register')">
                  {{ codeButtonText('register') }}
                </button>
              </view>
            </label>
            <button class="primary" :disabled="busy" @click="submit">
              {{ busy ? '创建中…' : '创建账号' }}
            </button>
            <text class="agreement">
              注册即表示同意
              <text class="agreement-link" @click="openManagedLegalDocument('service_terms')">
                服务条款
              </text>
              与
              <text class="agreement-link" @click="openManagedLegalDocument('privacy_policy')">
                隐私政策
              </text>
            </text>
            <button class="ghost" @click="mode = 'password'">
              已有账号？返回登录 →
            </button>
          </template>

          <template v-else>
            <text class="notice">
              输入注册邮箱并验证身份，即可设置新密码。
            </text>
            <label class="field">
              <text class="field-label">注册邮箱</text>
              <input v-model="email" class="field-input" type="text" placeholder="your@email.com">
            </label>
            <label class="field">
              <text class="field-label">邮箱验证码</text>
              <view class="code-row">
                <input v-model="emailCode" class="field-input flex" type="number" :maxlength="6" placeholder="6 位验证码">
                <button class="code-btn" :disabled="codeBusy.reset_password || codeCountdown.reset_password > 0" @click="sendCode('reset_password')">
                  {{ codeButtonText('reset_password') }}
                </button>
              </view>
            </label>
            <label class="field">
              <text class="field-label">新密码</text>
              <input v-model="password" class="field-input" password confirm-type="done" placeholder="至少 8 位" @confirm="submit">
            </label>
            <button class="primary" :disabled="busy" @click="submit">
              {{ busy ? '重置中…' : '重置并登录' }}
            </button>
            <button class="ghost" @click="mode = 'password'">
              ← 返回登录
            </button>
          </template>
        </view>
        <!-- #endif -->

        <!-- #ifdef MP-WEIXIN -->
        <view class="form-body mini-program-auth">
          <text class="mini-program-title">
            微信登录
          </text>
          <text class="mini-program-description">
            授权后可同步推理记录
          </text>
          <button class="primary platform-login wechat-login" :disabled="busy" @click="authorizeMiniProgram('wechat')">
            <view class="platform-logo i-simple-icons-wechat" aria-hidden="true" />
            <text>{{ busy ? '授权中…' : '微信一键登录' }}</text>
          </button>
          <view class="agreement">
            <text>登录即表示同意 </text>
            <text class="agreement-link" @tap.stop="openManagedLegalDocument('service_terms')">
              服务条款
            </text>
            <text> 与 </text>
            <text class="agreement-link" @tap.stop="openMiniProgramPrivacy">
              隐私保护指引
            </text>
          </view>
        </view>
        <!-- #endif -->

        <!-- #ifdef MP-TOUTIAO -->
        <view class="form-body mini-program-auth">
          <text class="mini-program-title">
            抖音登录
          </text>
          <text class="mini-program-description">
            授权后可同步推理记录
          </text>
          <button class="primary platform-login douyin-login" :disabled="busy" @click="authorizeMiniProgram('douyin')">
            <view class="platform-logo i-simple-icons-tiktok" aria-hidden="true" />
            <text>{{ busy ? '授权中…' : '抖音一键登录' }}</text>
          </button>
          <view class="agreement">
            <text>登录即表示同意 </text>
            <text class="agreement-link" @tap.stop="openManagedLegalDocument('service_terms')">
              服务条款
            </text>
            <text> 与 </text>
            <text class="agreement-link" @tap.stop="openManagedLegalDocument('privacy_policy')">
              隐私政策
            </text>
          </view>
        </view>
        <!-- #endif -->
      </view>
    </view>

    <LegalDocumentPopup
      v-model:visible="legalDocumentVisible"
      v-model:kind="legalDocumentKind"
      :documents="legalDocuments"
      :light="false"
    />
    <HgtFeedbackHost />
  </view>
</template>

<style scoped>
.auth-page {
  position: relative;
  display: flex;
  box-sizing: border-box;
  min-height: 100vh;
  min-height: 100dvh;
  padding: max(28px, env(safe-area-inset-top)) 20px max(32px, env(safe-area-inset-bottom));
  align-items: center;
  justify-content: center;
  overflow: hidden;
  background: var(--hgt-bg);
}

.auth-bg {
  position: absolute;
  inset: 0;
  z-index: 0;
  width: 100%;
  height: 100%;
}

.auth-bg img {
  width: 100%;
  height: 100%;
  object-fit: cover !important;
  object-position: 62% center !important;
}

.auth-veil {
  position: absolute;
  inset: 0;
  z-index: 1;
  background:
    linear-gradient(90deg,
      rgba(4, 20, 24, 0.82) 0%,
      rgba(4, 20, 24, 0.55) 40%,
      rgba(4, 20, 24, 0.35) 70%,
      rgba(4, 20, 24, 0.45) 100%),
    linear-gradient(180deg,
      rgba(6, 26, 32, 0.2) 0%,
      rgba(4, 20, 24, 0.35) 100%);
  pointer-events: none;
}

.auth-shell {
  position: relative;
  z-index: 2;
  display: flex;
  box-sizing: border-box;
  width: min(1040px, 100%);
  gap: 40px;
  align-items: center;
  justify-content: space-between;
}

.brand-block {
  display: flex;
  width: min(420px, 46%);
  gap: 14px;
  flex-direction: column;
  align-items: flex-start;
}

.brand-logo {
  width: 88px;
  height: 88px;
  margin-bottom: 8px;
}

.brand-text {
  display: flex;
  align-items: baseline;
  gap: 14px;
}

.brand-en {
  color: var(--hgt-brand);
  font-family: var(--hgt-font-en);
  font-size: 20px;
  letter-spacing: 0.28em;
}

.brand-zh {
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-display);
  font-size: 40px;
  font-weight: 600;
  letter-spacing: 0.16em;
}

.brand-sub {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 18px;
  line-height: 1.8;
}

.brand-extra {
  margin-top: 6px;
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-display);
  font-size: 14px;
  line-height: 1.8;
}

.form-card {
  box-sizing: border-box;
  width: min(440px, 100%);
  padding: 28px 26px 24px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-lg);
  background: rgba(10, 36, 42, 0.82);
  backdrop-filter: blur(8px);
}

.form-title {
  display: block;
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-display);
  font-size: 24px;
  font-weight: 600;
  letter-spacing: 0.1em;
}

.form-body {
  display: flex;
  margin-top: 20px;
  gap: 14px;
  flex-direction: column;
}

.field {
  display: flex;
  width: 100%;
  gap: 8px;
  flex-direction: column;
}

.field-label {
  color: var(--hgt-text-2);
  font-size: 13px;
}

.field-input {
  box-sizing: border-box;
  width: 100%;
  height: 48px;
  padding: 0 14px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  background: rgba(15, 53, 57, 0.45);
  color: var(--hgt-text);
  font-size: 14px;
}

.field-input.flex {
  flex: 1;
  min-width: 0;
}

.code-row {
  display: flex;
  gap: 8px;
  align-items: center;
}

.code-btn {
  display: flex;
  box-sizing: border-box;
  height: 48px;
  margin: 0;
  padding: 0 14px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  flex: none;
  align-items: center;
  justify-content: center;
  background: transparent;
  color: var(--hgt-brand);
  font-size: 12px;
  white-space: nowrap;
}

.code-btn::after,
.primary::after,
.ghost::after {
  border: 0;
}

.primary,
.ghost {
  display: flex;
  box-sizing: border-box;
  width: 100%;
  margin: 4px 0 0;
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  line-height: 1;
}

.primary {
  height: 48px;
  padding: 0 16px;
  border: 0;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0.08em;
}

.primary:disabled {
  opacity: 0.55;
}

.ghost {
  height: 42px;
  padding: 0 12px;
  border: 1px solid var(--hgt-border);
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 13px;
}

.auth-links,
.agreement,
.notice,
.mini-program-title,
.mini-program-description {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  color: var(--hgt-text-3);
  font-size: 12px;
  text-align: center;
}

.notice {
  margin-bottom: 4px;
  color: var(--hgt-text-2);
  text-align: left;
}

.auth-link,
.agreement-link {
  color: var(--hgt-brand);
  cursor: pointer;
}

.auth-link-sep {
  color: var(--hgt-text-3);
  opacity: 0.6;
}

.mini-program-auth {
  gap: 12px;
}

.mini-program-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 16px;
  font-weight: 600;
}

.platform-login {
  gap: 8px;
}

@media (max-width: 860px) {
  .auth-shell {
    width: min(440px, 100%);
    gap: 24px;
    flex-direction: column;
  }

  .brand-block {
    width: 100%;
    align-items: flex-start;
  }

  .brand-logo {
    width: 64px;
    height: 64px;
  }

  .brand-zh {
    font-size: 30px;
  }

  .brand-en {
    font-size: 16px;
  }

  .brand-sub {
    font-size: 15px;
  }
}
</style>
