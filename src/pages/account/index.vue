<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
import type { PlayerSession } from '@/api/player'
import { playerApi } from '@/api/player'
import { gameApi } from '@/api/turtle'
import { usePlayerStore } from '@/store/playerStore'

definePage({ name: 'player-account', layout: 'tabbar', style: { navigationStyle: 'custom' } })
const router = useRouter()
const store = usePlayerStore(); const sessions = ref<PlayerSession[]>([]); const history = ref<Array<{ status: string }>>([]); const editing = ref(false); const username = ref(''); const bio = ref(''); const email = ref(''); const emailCode = ref(''); const emailCurrentPassword = ref(''); const passwordCurrentPassword = ref(''); const newPassword = ref('')
const loading = ref(true)
const passwordBusy = ref(false)
const completed = computed(() => history.value.filter(item => ['solved', 'finished'].includes(item.status)).length); const solved = computed(() => history.value.filter(item => item.status === 'solved').length); const completionRate = computed(() => history.value.length ? Math.round(solved.value / history.value.length * 100) : 0)
const achievements = computed(() => [{ icon: '◈', name: '初探真相', note: '完成第一个谜题', unlocked: solved.value >= 1 }, { icon: '◉', name: '侦探之眼', note: '10次游戏中胜率超过80%', unlocked: history.value.length >= 10 && completionRate.value >= 80 }, { icon: '◎', name: '快手侦探', note: '10分钟内完成困难谜题', unlocked: false }, { icon: '◇', name: '无懈可击', note: '不放弃完成20个谜题', unlocked: solved.value >= 20 }, { icon: '◆', name: '传奇推理师', note: '完成所有困难谜题', unlocked: false }]); const unlockedCount = computed(() => achievements.value.filter(item => item.unlocked).length)
onMounted(async () => {
  try {
    if (!store.user)
      await store.restore()
    if (!store.user)
      return
    username.value = store.user.username; bio.value = store.user.bio || ''; email.value = store.user.email; [sessions.value, history.value] = await Promise.all([playerApi.sessions(), gameApi.history()])
  }
  finally { loading.value = false }
})
async function saveProfile() { store.user = await playerApi.updateProfile(username.value, bio.value); editing.value = false; uni.showToast({ title: '资料已更新', icon: 'success' }) }
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
    await router.replace({ name: 'home' })
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
          <image v-if="store.user.avatar_url" :src="store.user.avatar_url" class="avatar" mode="aspectFill" /><view v-else class="avatar fallback">
            {{ store.user.username.slice(0, 1).toUpperCase() }}
          </view><text class="hgt-display username">
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
  <view v-else class="profile-page guest-page">
    <header class="page-head">
      <text class="hgt-mono eyebrow">
        ◇ 个人中心
      </text><text class="hgt-display page-title">
        我的
      </text>
    </header>
    <view v-if="!loading" class="guest-content">
      <view class="guest-symbol">
        ◇
      </view><text class="hgt-display guest-title">
        登录后保存你的推理足迹
      </text><text class="guest-copy">
        同步游戏记录、解锁成就，创建多人房间并邀请队友一起寻找真相。
      </text><view class="guest-actions">
        <button class="guest-primary hgt-mono" @click="router.push({ name: 'player-login' })">
          登录账号
        </button><button class="guest-secondary hgt-mono" @click="router.push({ name: 'player-register' })">
          创建账号
        </button><button class="guest-secondary hgt-mono" @click="router.push({ name: 'donate' })">
          支持项目
        </button>
      </view><text class="hgt-mono guest-note">
        未登录仍可体验单人推理
      </text>
    </view>
  </view>
</template>

<style scoped>
.profile-page button{display:flex;box-sizing:border-box;padding-top:0;padding-bottom:0;align-items:center;justify-content:center;line-height:1}
.guest-content{display:flex;box-sizing:border-box;width:min(620px,calc(100% - 56px));min-height:390px;margin:70px auto;padding:56px;border:1px solid var(--border);align-items:center;justify-content:center;background:var(--card);text-align:center;flex-direction:column}.guest-symbol{display:flex;width:64px;height:64px;border:1px solid var(--border);align-items:center;justify-content:center;color:var(--muted-foreground);font-size:26px}.guest-title{margin-top:28px;font-size:28px}.guest-copy{max-width:440px;margin-top:16px;color:var(--muted-foreground);font-size:13px;line-height:1.9}.guest-actions{display:flex;width:100%;margin-top:30px;gap:12px}.guest-actions button{display:flex;height:44px;min-height:44px;margin:0;padding:0 20px;border:1px solid var(--foreground);border-radius:0;align-items:center;justify-content:center;font-size:11px;line-height:normal;letter-spacing:.14em;flex:1 1 0}.guest-primary{background:var(--foreground);color:var(--background)}.guest-secondary{background:transparent;color:var(--foreground)}.guest-note{margin-top:20px;color:var(--muted-foreground);font-size:9px;letter-spacing:.14em}
.profile-page{min-height:100vh;background:var(--background);color:var(--foreground)}.page-head{display:flex;padding:34px 48px 28px;border-bottom:1px solid var(--border);gap:8px;flex-direction:column}.eyebrow,.section-title,.email,.stats-card{font-size:11px;letter-spacing:.14em;color:var(--muted-foreground)}.page-title{font-size:40px}.profile-grid{display:grid;max-width:1080px;padding:32px 48px 64px;gap:32px;grid-template-columns:300px 1fr}.profile-column,.detail-column{display:flex;gap:24px;flex-direction:column}.identity-card,.stats-card,.bio-card,.achievement-card,.setting{border:1px solid var(--border);background:var(--card)}.identity-card{display:flex;padding:24px;align-items:center;flex-direction:column}.avatar{width:96px;height:96px;border:1px solid var(--border);border-radius:0}.fallback{display:flex;align-items:center;justify-content:center;font:40px Cinzel,serif}.username{margin-top:18px;font-size:22px}.email{margin-top:7px;letter-spacing:normal}.outline{width:100%;height:36px;margin-top:18px;border:1px solid var(--border);border-radius:0;background:transparent;color:var(--muted-foreground);font-size:11px}.outline::after,button::after{border:0}.stats-card view{display:flex;padding:13px 16px;border-bottom:1px solid var(--border);justify-content:space-between}.stats-card view:last-child{border:0}.stats-card strong{color:var(--foreground)}.bio-card{display:flex;padding:24px;gap:12px;flex-direction:column;font-size:13px}.achievement-card{padding-top:20px}.achievement-card>.section-title{display:block;padding:0 24px 18px}.achievement-grid{display:grid;border-top:1px solid var(--border);grid-template-columns:1fr 1fr}.achievement{display:flex;min-height:68px;padding:15px 20px;border-right:1px solid var(--border);border-bottom:1px solid var(--border);gap:14px;align-items:center}.achievement:nth-child(even){border-right:0}.achievement .symbol{font-size:22px}.achievement view{display:flex;gap:4px;flex:1;flex-direction:column}.achievement strong{font-size:13px}.achievement view text{font-size:10px;color:var(--muted-foreground)}.achievement.locked{opacity:.25}.check{color:#4ade80}.settings{display:grid;gap:16px;grid-template-columns:1fr 1fr}.setting{display:flex;padding:20px;gap:10px;flex-direction:column}.setting input,.setting textarea{box-sizing:border-box;width:100%;height:42px;padding:0 12px;border:1px solid var(--border)}.setting textarea{height:92px;padding:12px;line-height:1.6}.bio-count{margin-top:-5px;text-align:right;color:var(--muted-foreground);font-size:9px}.setting button,.logout-row button{height:38px;margin:0;border:1px solid var(--border);border-radius:0;background:var(--foreground);color:var(--background);font-size:11px}.button-row,.logout-row{display:flex;gap:12px}.button-row button,.logout-row button{flex:1}.sessions view{display:flex;align-items:center;justify-content:space-between}.sessions view button{padding:0 14px;background:transparent;color:#ef4444}.logout-row{grid-column:1/-1}.logout-row .danger{border-color:#7f1d1d;background:transparent;color:#ef4444}@media(max-width:767px){.page-head{padding:28px}.profile-grid{padding:24px 28px;grid-template-columns:1fr}.achievement-grid,.settings{grid-template-columns:1fr}.achievement{border-right:0}.logout-row{flex-direction:column}.guest-content{width:calc(100% - 40px);min-height:360px;margin:36px auto;padding:38px 24px}.guest-actions{gap:10px}.guest-actions button{height:48px;min-height:48px;padding:0 12px;font-size:12px}}
@media(max-width:767px){.logout-row{width:100%;flex-direction:row}.logout-row button{min-width:0}}
.support-card{display:flex;padding:20px 24px;border:1px solid var(--border);align-items:center;justify-content:space-between;background:var(--card)}.support-card>view{display:flex;gap:8px;flex-direction:column}.support-copy{color:var(--muted-foreground);font-size:12px}.support-arrow{font-size:22px}@media(max-width:767px){.guest-actions{flex-wrap:wrap}.guest-actions button:last-child{flex-basis:100%}}
</style>
