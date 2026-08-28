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
const { light, overlay, toggleTheme } = useAnimatedTheme()
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

const tabs: Array<{ key: AuthMode, label: string }> = [
  { key: 'password', label: '账号登录' },
  { key: 'code', label: '邮箱验证' },
  { key: 'register', label: '注册' },
  { key: 'reset', label: '找回密码' },
]

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
function finish(result: Awaited<ReturnType<typeof playerApi.passwordLogin>>) {
  store.accept(result)
  if (result.merged_games)
    uni.showToast({ title: `已合并 ${result.merged_games} 局记录`, icon: 'none' })
  const redirect = String(route.query.redirect || '')
  if (redirect.startsWith('/pages/')) {
    uni.redirectTo({ url: redirect })
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
  busy.value = true
  try {
    if (mode.value === 'password')
      finish(await playerApi.passwordLogin(email.value, password.value))
    else if (mode.value === 'code')
      finish(await playerApi.codeLogin(email.value, emailCode.value))
    else if (mode.value === 'register')
      finish(await playerApi.register({ username: username.value, email: email.value, password: password.value, email_code: emailCode.value }))
    else finish(await playerApi.resetPassword(email.value, emailCode.value, password.value))
  }
  catch (error) { uni.showToast({ title: message(error), icon: 'none' }) }
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
    <HgtThemeTransition v-bind="overlay" />
    <AuthParticleBackground />
    <button class="theme-toggle hgt-theme-trigger" @click="toggleTheme">
      {{ light ? '☾' : '☀' }}
    </button>

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
      <view class="form-card">
        <!-- #ifdef H5 -->
        <scroll-view class="tabs" scroll-x :show-scrollbar="false">
          <view class="tabs-inner">
            <button v-for="tab in tabs" :key="tab.key" class="tab" :class="{ active: mode === tab.key }" @click="mode = tab.key">
              {{ tab.label }}
            </button>
          </view>
        </scroll-view>
        <!-- #endif -->

        <!-- #ifdef H5 -->
        <view :key="mode" class="form-body">
          <template v-if="mode === 'password'">
            <label class="field"><text>用户名</text><input v-model="email" type="text" placeholder="请输入用户名"></label>
            <label class="field">
              <view class="label-row"><text>密码</text><button @click="mode = 'reset'">忘记密码？</button></view>
              <input v-model="password" type="text" password confirm-type="done" placeholder="请输入密码" @confirm="submit">
            </label>
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
              <button class="ghost" @click="mode = 'register'">
                还没有账号？立即注册 →
              </button>
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
    <LegalDocumentPopup v-model:visible="legalDocumentVisible" v-model:kind="legalDocumentKind" :documents="legalDocuments" :light="light" />
  </view>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500;700&display=swap');
.auth-page{--bg:#080808;--fg:#f0f0f0;--card:#111;--muted:#707070;--border:#292929;position:relative;display:flex;min-height:100vh;overflow:hidden;background:var(--bg);color:var(--fg);transition:.25s}.auth-page.light{--bg:#edeae4;--fg:#1c1c1a;--card:#e4e0d9;--muted:#74736e;--border:#c8c4bc}.particles{position:absolute;inset:0;opacity:.32;background-image:radial-gradient(circle at 15% 20%,var(--muted) 0 1px,transparent 1.5px),radial-gradient(circle at 82% 68%,var(--muted) 0 1px,transparent 1.5px),linear-gradient(115deg,transparent 48%,var(--border) 49%,transparent 50%);background-size:38px 38px,54px 54px,100% 100%;pointer-events:none}.theme-toggle{position:absolute;z-index:4;top:max(20px,env(safe-area-inset-top));right:20px;width:34px;height:34px;padding:0;border:1px solid var(--border);border-radius:0;background:var(--card);color:var(--muted);font:14px 'Courier New',monospace}.theme-toggle::after,.tab::after,.ghost::after{border:0}.brand-panel{position:relative;z-index:1;box-sizing:border-box;display:flex;width:42%;min-height:100vh;padding:58px 64px 42px;flex-direction:column;justify-content:space-between;border-right:1px solid var(--border)}.eyebrow,.brand-rule,.brand-footer,.field>text,.label-row,.tab,.primary,.ghost,.agreement,.copyright{font-family:'Courier New',monospace}.eyebrow{font-size:11px;letter-spacing:.38em;color:var(--muted)}.brand-title{display:block;font-family:Georgia,'Times New Roman',serif;font-size:clamp(52px,7vw,88px);line-height:1;letter-spacing:.08em}.brand-rule{display:flex;align-items:center;gap:14px;margin-top:24px;font-size:11px;letter-spacing:.25em;color:var(--muted)}.brand-rule::before{width:64px;height:1px;background:var(--border);content:''}.brand-description{display:block;max-width:270px;margin-top:26px;font-size:14px;line-height:1.9;color:var(--muted)}.brand-footer{display:flex;align-items:flex-end;justify-content:space-between;font-size:11px;color:var(--muted)}.brand-footer view{display:flex;gap:10px;flex-direction:column}.form-panel{position:relative;z-index:1;box-sizing:border-box;display:flex;min-height:100vh;padding:72px 64px;flex:1;align-items:center;justify-content:center}.form-card{width:100%;max-width:448px}.tabs{width:100%;border-bottom:1px solid var(--border);white-space:nowrap}.tabs-inner{display:flex}.tab{position:relative;margin-right:24px;padding:0 0 14px;border:0;border-radius:0;background:transparent;color:var(--muted);font-size:11px;line-height:1.4;letter-spacing:.1em}.tab.active{color:var(--fg)}.tab.active::before{position:absolute;right:0;bottom:-1px;left:0;height:1px;background:var(--fg);content:''}.form-body{display:flex;margin-top:38px;gap:20px;flex-direction:column;animation:auth-in .4s ease}.field{display:flex;gap:7px;flex-direction:column}.field>text,.label-row{font-size:11px;letter-spacing:.16em;color:var(--muted)}.label-row{display:flex;align-items:center;justify-content:space-between}.label-row button{padding:0;border:0;background:transparent;color:var(--muted);font-size:11px}.field input,.code-row input{box-sizing:border-box;width:100%;height:46px;padding:0 15px;border:1px solid var(--border);border-radius:0;background:transparent;color:var(--fg);font-size:14px;outline:none}.field input:focus,.code-row input:focus{border-color:var(--fg)}.code-row{display:flex;gap:9px}.code-row input{flex:1}.code-row button{min-width:100px;padding:0 15px;border:1px solid var(--fg);border-radius:0;background:transparent;color:var(--fg);font:11px 'Courier New',monospace;letter-spacing:.12em}.code-row button[disabled]{border-color:var(--border);color:var(--muted)}.password-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}.primary{height:48px;margin-top:4px;border:1px solid var(--fg);border-radius:0;background:var(--fg);color:var(--bg);font-size:11px;letter-spacing:.24em}.primary[disabled]{opacity:.6}.ghost{height:34px;padding:0;border:0;background:transparent;color:var(--muted);font-size:11px;letter-spacing:.12em}.divider{display:flex;align-items:center;gap:16px;color:var(--muted);font:11px 'Courier New',monospace}.divider::before,.divider::after{height:1px;background:var(--border);content:'';flex:1}.agreement,.copyright{font-size:10px;line-height:1.7;color:var(--muted)}.notice{padding:2px 0 2px 15px;border-left:2px solid var(--border);font-size:12px;line-height:1.7;color:var(--muted)}.copyright{display:block;margin-top:36px;text-align:center}@keyframes auth-in{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}@media(max-width:767px){.auth-page{display:block;min-height:100vh}.brand-panel{width:100%;min-height:auto;padding:calc(54px + env(safe-area-inset-top)) 28px 34px;border-right:0;border-bottom:1px solid var(--border)}.eyebrow{margin-bottom:34px}.brand-title{font-size:50px}.brand-description,.brand-footer{display:none}.form-panel{min-height:auto;padding:38px 24px calc(40px + env(safe-area-inset-bottom))}.tabs{margin-right:-24px;width:calc(100% + 24px)}.password-grid{grid-template-columns:1fr}.form-body{margin-top:30px}}
.theme-toggle,.tab,.code-row button,.primary,.ghost{display:flex;box-sizing:border-box;align-items:center;justify-content:center;line-height:1}.primary{width:100%}
.auth-page{--muted:#666;--border:#222;font-family:Inter,sans-serif}.auth-page.light{--muted:#7a7972}.theme-toggle{width:32px;height:32px;font:13px/1 'JetBrains Mono',monospace}.brand-panel{padding:64px}.eyebrow,.brand-rule,.brand-footer,.field>text,.label-row,.tab,.primary,.ghost,.agreement,.copyright{font-family:'JetBrains Mono',monospace}.eyebrow{font-size:12px;letter-spacing:.4em}.brand-logo{display:block;width:150px;height:150px;margin-bottom:18px}.brand-title{font-family:Cinzel,serif;letter-spacing:.06em}.brand-rule{gap:12px;margin-top:20px;font-size:12px;letter-spacing:.28em}.brand-description{margin-top:24px;line-height:1.625}.brand-footer{font-size:12px}.tabs-inner{justify-content:flex-start}.tab{margin-right:24px;margin-left:0;padding-bottom:12px;font-size:12px;line-height:16px;letter-spacing:.05em}.form-body{margin-top:40px}.field{width:100%;gap:6px}.field>text,.label-row{font-size:12px;letter-spacing:.18em}.label-row{box-sizing:border-box;width:100%}.label-row button{margin-right:0;margin-left:auto;font:12px/16px 'JetBrains Mono',monospace;letter-spacing:normal}.field input,.code-row input{padding:0 16px;font-family:Inter,sans-serif;line-height:20px}.password-grid{gap:16px}.primary{height:44px;margin-top:0;font-size:12px;line-height:16px;letter-spacing:.25em}.ghost{width:100%;height:36px;font-size:12px;line-height:16px;letter-spacing:.15em}.action-stack{display:flex;margin-top:8px;gap:12px;flex-direction:column}.register-actions{display:flex;margin-top:4px;gap:12px;flex-direction:column}.divider{font:12px/16px 'JetBrains Mono',monospace}.agreement{font-size:12px;line-height:1.625}.agreement-link{color:var(--fg)}.copyright{margin-top:40px;font-size:12px;line-height:16px}
.mini-program-auth{align-items:stretch;text-align:center}.mini-program-title{font-family:Georgia,'Times New Roman',serif;font-size:28px;letter-spacing:.08em}.mini-program-description{color:var(--muted);font-size:13px;line-height:1.8}.platform-login{gap:12px;letter-spacing:.12em}.platform-logo{width:22px;height:22px;flex:none}.wechat-login{background:#07c160;border-color:#07c160;color:#fff}.douyin-login{background:#17171b;border-color:#17171b;color:#fff}.light .douyin-login{background:#17171b;color:#fff}
@media(max-width:767px){.brand-panel{padding:48px 40px}.eyebrow{margin-bottom:0}.brand-copy{margin:32px 0}.brand-logo{width:96px;height:96px;margin-bottom:14px}.brand-title{font-size:42px}.form-panel{padding:48px 32px}.tabs{margin-right:0;width:100%}.password-grid{grid-template-columns:1fr 1fr}.form-body{margin-top:40px}}
.brand-title{font-size:clamp(48px,5vw,76px);white-space:nowrap}
@media(min-width:768px) and (max-width:1180px){.brand-panel{padding-right:40px;padding-left:40px}.brand-title{font-size:clamp(38px,5vw,56px)}}
@media(max-width:767px){.brand-title{font-size:42px}}
</style>
