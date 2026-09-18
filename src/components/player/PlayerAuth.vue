<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import type { LegalDocuments } from '@/api/player'
import { playerApi } from '@/api/player'
import { useAnimatedTheme } from '@/composables/useAnimatedTheme'
import { usePlayerStore } from '@/store/playerStore'
import AuthParticleBackground from './AuthParticleBackground.vue'
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
const { light, overlay } = useAnimatedTheme()
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
  password: '账号登录',
  code: '邮箱验证',
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
  if (result.merged_games)
    uni.showToast({ title: `已合并 ${result.merged_games} 局记录`, icon: 'none' })
  const redirect = normalizeRedirect(String(route.query.redirect || ''))
  if (redirect) {
    uni.redirectTo({
      url: redirect,
      fail: () => uni.switchTab({ url: '/pages/index/index' }),
    })
    return
  }
  uni.switchTab({ url: '/pages/index/index' })
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
  return codeCountdown[purpose] > 0 ? `${codeCountdown[purpose]}s` : '发 送'
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
    // 登录/验证失败只提示错误，停留在当前表单，不主动进入找回密码
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
  <view class="auth-page" :class="{ light }">
    <image class="page-bg" src="/static/hgt/bg/bg_deep_ocean_hero.jpg" mode="aspectFill" />
    <view class="page-bg-veil" />
    <image class="page-deco page-deco-bubbles" src="/static/hgt/ui/bubbles.png" mode="aspectFit" />
    <image class="page-deco page-deco-vignette" src="/static/hgt/ui/vignette.png" mode="aspectFill" />
    <HgtThemeTransition v-bind="overlay" />
    <AuthParticleBackground />

    <view class="auth-layout">
      <view class="brand-panel">
        <text class="eyebrow">
          ◈ LATERAL THINKING
        </text>
        <view class="brand-copy">
          <image class="brand-logo" :src="light ? '/static/brand/logo-mark-light.png' : '/static/brand/logo-mark-dark.png'" mode="aspectFit" />
          <text class="brand-title">
            墨鱼海龟汤
          </text>
          <view class="brand-rule">
            <text>推理 · 探索 · 解谜</text>
          </view>
          <text class="brand-description">
            一个答案，无数问题。透过一问一答，拨开迷雾，抵达真相。
          </text>
        </view>
        <view class="brand-footer">
          <view><text>— 推理解谜</text><text>— 联机对战</text><text>— 社区共创</text></view>
          <text>v 0.1.0</text>
        </view>
      </view>

      <view class="form-panel">
        <view class="mobile-brand">
          <text class="eyebrow">
            ◈ LATERAL THINKING
          </text>
          <image class="mobile-logo" :src="light ? '/static/brand/logo-mark-light.png' : '/static/brand/logo-mark-dark.png'" mode="aspectFit" />
          <text class="mobile-title">
            墨鱼海龟汤
          </text>
          <view class="mobile-rule">
            <text>推理 · 探索 · 解谜</text>
          </view>
        </view>
        <view class="form-card">
          <!-- #ifdef H5 -->
          <view class="form-title">
            {{ modeTitle }}
          </view>
          <view :key="mode" class="form-body">
            <template v-if="mode === 'password'">
              <label class="field"><text>用户名</text><input v-model="email" type="text" placeholder="请输入用户名"></label>
              <label class="field"><text>密码</text><input v-model="password" type="text" password confirm-type="done" placeholder="请输入密码" @confirm="submit"></label>
              <view class="action-stack">
                <button class="primary" :disabled="busy" @click="submit">
                  {{ busy ? '登录中…' : '登 录' }}
                </button>
                <view class="divider">
                  <text>或</text>
                </view>
                <button class="ghost" @click="mode = 'code'">
                  邮箱验证码登录
                </button>
              </view>
              <view class="auth-links">
                <text class="auth-link" @click="mode = 'register'">
                  注册账号
                </text>
                <text class="auth-link-sep">
                  ·
                </text>
                <text class="auth-link" @click="mode = 'reset'">
                  忘记密码
                </text>
              </view>
              <text class="agreement">
                登录即表示同意 <text class="agreement-link" @click="openManagedLegalDocument('service_terms')">
                  服务条款
                </text> 与 <text class="agreement-link" @click="openManagedLegalDocument('privacy_policy')">
                  隐私政策
                </text>
              </text>
            </template>

            <template v-else-if="mode === 'code'">
              <label class="field"><text>邮箱地址</text><input v-model="email" type="text" placeholder="your@email.com"></label>
              <label class="field"><text>验证码</text></label>
              <view class="code-row">
                <input v-model="emailCode" type="number" :maxlength="6" confirm-type="done" placeholder="6 位验证码" @confirm="submit">
                <button :disabled="codeBusy.login || codeCountdown.login > 0" @click="sendCode('login')">
                  {{ codeButtonText('login') }}
                </button>
              </view>
              <button class="primary" :disabled="busy" @click="submit">
                {{ busy ? '验证中…' : '验证登录' }}
              </button>
              <text class="agreement">
                登录即表示同意 <text class="agreement-link" @click="openManagedLegalDocument('service_terms')">
                  服务条款
                </text> 与 <text class="agreement-link" @click="openManagedLegalDocument('privacy_policy')">
                  隐私政策
                </text>
              </text>
              <button class="ghost" @click="mode = 'password'">
                ← 返回账号登录
              </button>
            </template>

            <template v-else-if="mode === 'register'">
              <label class="field"><text>用户名</text><input v-model="username" type="text" placeholder="4–20 个字符"></label>
              <label class="field"><text>邮箱地址</text><input v-model="email" type="text" placeholder="your@email.com"></label>
              <view class="password-grid">
                <label class="field"><text>密码</text><input v-model="password" type="text" password placeholder="至少 8 位"></label>
                <label class="field"><text>确认密码</text><input v-model="passwordConfirmation" type="text" password placeholder="再次输入"></label>
              </view>
              <label class="field"><text>邮箱验证码</text></label>
              <view class="code-row">
                <input v-model="emailCode" type="number" :maxlength="6" confirm-type="done" placeholder="6 位验证码" @confirm="submit">
                <button :disabled="codeBusy.register || codeCountdown.register > 0" @click="sendCode('register')">
                  {{ codeButtonText('register') }}
                </button>
              </view>
              <text class="agreement">
                注册即表示同意 <text class="agreement-link" @click="openManagedLegalDocument('service_terms')">
                  服务条款
                </text> 与 <text class="agreement-link" @click="openManagedLegalDocument('privacy_policy')">
                  隐私政策
                </text>
              </text>
              <view class="register-actions">
                <button class="primary" :disabled="busy" @click="submit">
                  {{ busy ? '创建中…' : '创建账号' }}
                </button>
                <button class="ghost" @click="mode = 'password'">
                  已有账号？返回登录 →
                </button>
              </view>
            </template>

            <template v-else>
              <view class="notice">
                输入注册邮箱并验证身份，即可设置新密码。
              </view>
              <label class="field"><text>注册邮箱</text><input v-model="email" type="text" placeholder="your@email.com"></label>
              <label class="field"><text>邮箱验证码</text></label>
              <view class="code-row">
                <input v-model="emailCode" type="number" :maxlength="6" placeholder="6 位验证码">
                <button :disabled="codeBusy.reset_password || codeCountdown.reset_password > 0" @click="sendCode('reset_password')">
                  {{ codeButtonText('reset_password') }}
                </button>
              </view>
              <label class="field"><text>新密码</text><input v-model="password" type="text" password confirm-type="done" placeholder="至少 8 位" @confirm="submit"></label>
              <button class="primary" :disabled="busy" @click="submit">
                {{ busy ? '重置中…' : '重置并登录' }}
              </button>
              <button class="ghost" @click="mode = 'password'">
                ← 返回账号登录
              </button>
            </template>
          </view>
          <!-- #endif -->
          <!-- #ifdef MP-WEIXIN -->
          <view class="form-body mini-program-auth">
            <text class="mini-program-title">
              微信授权登录
            </text>
            <text class="mini-program-description">
              授权后即可同步游戏记录并进入多人房间
            </text>
            <button class="primary platform-login wechat-login" :disabled="busy" @click="authorizeMiniProgram('wechat')">
              <view class="platform-logo i-simple-icons-wechat" aria-hidden="true" />
              <text>{{ busy ? '授权中…' : '微信一键登录' }}</text>
            </button>
            <view class="agreement">
              <text>登录即表示同意 </text><text class="agreement-link" @tap.stop="openManagedLegalDocument('service_terms')">
                服务条款
              </text><text> 与 </text><text class="agreement-link" @tap.stop="openMiniProgramPrivacy">
                隐私保护指引
              </text>
            </view>
          </view>
          <!-- #endif -->
          <!-- #ifdef MP-TOUTIAO -->
          <view class="form-body mini-program-auth">
            <text class="mini-program-title">
              抖音授权登录
            </text>
            <text class="mini-program-description">
              授权后即可同步游戏记录并进入多人房间
            </text>
            <button class="primary platform-login douyin-login" :disabled="busy" @click="authorizeMiniProgram('douyin')">
              <view class="platform-logo i-simple-icons-tiktok" aria-hidden="true" />
              <text>{{ busy ? '授权中…' : '抖音一键登录' }}</text>
            </button>
            <view class="agreement">
              <text>登录即表示同意 </text><text class="agreement-link" @tap.stop="openManagedLegalDocument('service_terms')">
                服务条款
              </text><text> 与 </text><text class="agreement-link" @tap.stop="openMiniProgramPrivacy">
                隐私政策
              </text>
            </view>
          </view>
          <!-- #endif -->
          <text class="copyright">
            © 2024 墨鱼海龟汤 · 公益项目
          </text>
        </view>
      </view>
    </view>
    <LegalDocumentPopup v-model:visible="legalDocumentVisible" v-model:kind="legalDocumentKind" :documents="legalDocuments" :light="light" />
  </view>
</template>

<style scoped>
.auth-page {
  position: relative;
  min-height: 100vh;
  overflow: hidden;
  background: var(--hgt-bg);
  color: var(--hgt-text);
  transition: background var(--hgt-dur-base), color var(--hgt-dur-base);
}
.auth-layout {
  position: relative;
  z-index: 1;
  display: flex;
  width: 100%;
  min-height: 100vh;
}
.auth-page.light {
  background: var(--hgt-bg);
  color: var(--hgt-text);
}
.page-bg {
  position: absolute;
  z-index: 0;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  min-height: 100vh;
  pointer-events: none;
  filter: var(--hgt-atmo-filter);
}
.page-bg-veil {
  position: absolute;
  z-index: 0;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  min-height: 100vh;
  pointer-events: none;
  /* 与首页 hero 共用同一套氛围压暗 */
  background: var(--hgt-atmo-veil);
}
.page-deco {
  position: absolute;
  z-index: 0;
  pointer-events: none;
}
.page-deco-bubbles {
  top: 8%;
  right: 6%;
  width: min(220px, 42vw);
  height: min(280px, 50vh);
  opacity: 0.22;
}
.page-deco-vignette {
  inset: 0;
  width: 100%;
  height: 100%;
  min-height: 100vh;
  /* 降低 vignette，避免登录页比首页更暗 */
  opacity: 0.28;
  mix-blend-mode: multiply;
}
.theme-toggle {
  position: absolute;
  z-index: 4;
  top: max(20px, env(safe-area-inset-top));
  right: 20px;
  display: flex;
  box-sizing: border-box;
  width: 36px;
  height: 36px;
  margin: 0;
  padding: 0;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  background: var(--hgt-card);
  color: var(--hgt-text-2);
  font-size: 14px;
  line-height: 1;
}
.theme-toggle::after,
.ghost::after {
  border: 0;
}

.brand-panel {
  display: flex;
  box-sizing: border-box;
  width: 42%;
  min-height: 100vh;
  padding: 56px 48px 40px;
  flex-direction: column;
  justify-content: space-between;
  background: transparent;
  color: #e5e8e3;
}
.brand-panel .eyebrow {
  color: var(--hgt-brand);
}
.brand-panel .brand-title {
  color: #e5e8e3;
}
.brand-panel .brand-description,
.brand-panel .brand-rule,
.brand-panel .brand-footer {
  color: rgba(229, 232, 227, 0.72);
}
.brand-panel .brand-rule::before {
  background: rgba(229, 232, 227, 0.28);
}
.eyebrow {
  color: var(--hgt-brand);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
  letter-spacing: 0.28em;
}
.brand-copy {
  display: flex;
  gap: 8px;
  flex-direction: column;
}
.brand-logo {
  display: block;
  width: 120px;
  height: 120px;
  margin-bottom: 12px;
}
.brand-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: clamp(36px, 5vw, 52px);
  font-weight: 600;
  line-height: 1.15;
  letter-spacing: 0.08em;
}
.brand-rule {
  display: flex;
  margin-top: 16px;
  align-items: center;
  gap: 12px;
  color: var(--hgt-text-2);
  font-size: 12px;
  letter-spacing: 0.2em;
}
.brand-rule::before {
  width: 48px;
  height: 1px;
  background: var(--hgt-border-soft);
  content: '';
}
.brand-description {
  display: block;
  max-width: 280px;
  margin-top: 20px;
  color: var(--hgt-text-2);
  font-size: 14px;
  line-height: 1.75;
}
.brand-footer {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  color: var(--hgt-text-3);
  font-size: 11px;
}
.brand-footer view {
  display: flex;
  gap: 8px;
  flex-direction: column;
}

.form-panel {
  display: flex;
  box-sizing: border-box;
  width: 58%;
  min-height: 100vh;
  padding: 48px 40px;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 20px;
  background: transparent;
}
.mobile-brand {
  display: none;
}
.mobile-logo {
  width: 72px;
  height: 72px;
}
.mobile-title {
  color: #e5e8e3;
  font-family: var(--hgt-font-display);
  font-size: 28px;
  font-weight: 600;
  letter-spacing: 0.08em;
}
.mobile-rule {
  display: flex;
  align-items: center;
  gap: 10px;
  color: rgba(229, 232, 227, 0.72);
  font-size: 12px;
  letter-spacing: 0.18em;
}
.mobile-rule::before {
  width: 28px;
  height: 1px;
  background: rgba(229, 232, 227, 0.28);
  content: '';
}
.form-card {
  box-sizing: border-box;
  width: min(420px, 100%);
  padding: 28px 28px 24px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-lg);
  background: var(--hgt-card);
  box-shadow: var(--hgt-shadow-lg);
}
.form-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 22px;
  font-weight: 600;
  letter-spacing: 0.06em;
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
.field > text {
  color: var(--hgt-text-2);
  font-size: 13px;
}
.auth-links {
  display: flex;
  margin-top: 4px;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.auth-link {
  color: var(--hgt-text-3);
  font-size: 12px;
}
.auth-link-sep {
  color: var(--hgt-text-3);
  font-size: 12px;
}
.field input,
.code-row input {
  box-sizing: border-box;
  width: 100%;
  height: 44px;
  padding: 0 14px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  background: var(--hgt-card-2);
  color: var(--hgt-text);
  font-size: 14px;
  line-height: 20px;
}
.code-row {
  display: flex;
  gap: 8px;
}
.code-row input {
  flex: 1;
  min-width: 0;
}
.code-row button {
  display: flex;
  box-sizing: border-box;
  height: 44px;
  margin: 0;
  padding: 0 14px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  flex: none;
  align-items: center;
  justify-content: center;
  background: transparent;
  color: var(--hgt-brand);
  font-size: 13px;
  line-height: 1;
  white-space: nowrap;
}
.code-row button::after {
  border: 0;
}
.password-grid {
  display: grid;
  gap: 12px;
  grid-template-columns: 1fr 1fr;
}
.primary {
  display: flex;
  box-sizing: border-box;
  width: 100%;
  height: 46px;
  margin: 4px 0 0;
  padding: 0 16px;
  border: 0;
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-size: 15px;
  font-weight: 600;
  line-height: 1;
  letter-spacing: 0.08em;
}
.primary::after {
  border: 0;
}
.primary:disabled {
  opacity: 0.55;
}
.ghost {
  display: flex;
  box-sizing: border-box;
  width: 100%;
  height: 40px;
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
.action-stack,
.register-actions {
  display: flex;
  margin-top: 4px;
  gap: 10px;
  flex-direction: column;
}
.divider {
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--hgt-text-3);
  font-size: 12px;
}
.notice {
  padding: 12px 14px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  background: var(--hgt-card-2);
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1.6;
}
.agreement {
  color: var(--hgt-text-3);
  font-size: 12px;
  line-height: 1.6;
}
.agreement-link {
  color: var(--hgt-brand);
}
.copyright {
  margin-top: 20px;
  color: var(--hgt-text-3);
  font-size: 11px;
  text-align: center;
}
.mini-program-auth {
  align-items: stretch;
  text-align: center;
}
.mini-program-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 22px;
  font-weight: 600;
  letter-spacing: 0.06em;
}
.mini-program-description {
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1.7;
}
.platform-login {
  gap: 10px;
}
.platform-logo {
  width: 20px;
  height: 20px;
  flex: none;
}
.wechat-login {
  background: #07c160;
  color: #fff;
}
.douyin-login {
  background: #17171b;
  color: #fff;
}

@media (max-width: 900px) {
  .auth-page,
  .auth-layout,
  .form-panel {
    min-height: 100vh;
    min-height: 100dvh;
  }
  .brand-panel {
    display: none;
  }
  .mobile-brand {
    display: flex;
    width: 100%;
    max-width: 420px;
    align-items: center;
    gap: 8px;
    flex-direction: column;
    text-align: center;
  }
  .form-panel {
    width: 100%;
    padding: max(28px, env(safe-area-inset-top)) 20px max(32px, env(safe-area-inset-bottom));
  }
  .auth-layout {
    width: 100%;
  }
  .page-bg,
  .page-bg-veil,
  .page-deco-vignette {
    width: 100%;
    height: 100%;
    min-height: 100vh;
    min-height: 100dvh;
  }
  /* 移动端与首页共用 atmo，仅略加强底部保证表单可读 */
  .page-bg-veil {
    background:
      linear-gradient(180deg,
        rgba(7, 20, 24, 0.22) 0%,
        rgba(7, 20, 24, 0.40) 45%,
        rgba(7, 20, 24, 0.58) 100%);
  }
  .page-deco-bubbles {
    top: auto;
    right: -4%;
    bottom: 12%;
    width: min(180px, 48vw);
    height: min(220px, 36vh);
    opacity: 0.4;
  }
  .form-card {
    width: min(420px, 100%);
    border-color: rgba(91, 200, 189, 0.18);
    background: rgba(15, 42, 45, 0.82);
    box-shadow:
      0 12px 40px rgba(4, 12, 14, 0.45),
      inset 0 1px 0 rgba(255, 255, 255, 0.04);
  }
  .password-grid {
    grid-template-columns: 1fr;
  }
}
</style>
