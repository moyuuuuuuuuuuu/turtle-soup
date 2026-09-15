<script setup lang="ts">
import type { HomeStats, PublicQuestion } from '@/types/game'
import { ensureAnonymousSession, homeApi, questionApi } from '@/api/turtle'
import { usePlayerStore } from '@/store/playerStore'
import { formatCount } from '@/utils'

definePage({ name: 'home', layout: 'tabbar', style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } } })
const router = useRouter()
const player = usePlayerStore()
const featured = ref<PublicQuestion[]>([])
const stats = ref<HomeStats>({ question_count: 0, today_online: 0, success_rate: 0, average_duration_seconds: null })
const loading = ref(true)
const loadError = ref(false)
const randomLoading = ref(false)
const titleIndex = ref(0)
const titles = ['真相', '谜题', '汤底']
let timer: ReturnType<typeof setInterval> | undefined

const difficulty = (level: number) => ['未知', '简单', '普通', '中等', '困难', '极难'][level] || '未知'
function formatDuration(seconds: number | null) {
  if (seconds === null)
    return '—'
  if (seconds < 60)
    return `${seconds}秒`
  if (seconds < 3600)
    return `${Math.round(seconds / 60)}分钟`
  const hours = Math.floor(seconds / 3600)
  const minutes = Math.round((seconds % 3600) / 60)
  return minutes ? `${hours}小时${minutes}分` : `${hours}小时`
}
function openQuestion(id: string) {
  router.push({ path: '/pages/question-detail/index', query: { id } })
}
async function loadHome() {
  loading.value = true
  loadError.value = false
  try {
    await player.restore()
    await ensureAnonymousSession()
    const [featuredResult, latestResult, homeStats] = await Promise.all([
      questionApi.list({ featured: 1, page_size: 3 }),
      questionApi.list({ page_size: 3 }),
      homeApi.stats(),
    ])
    featured.value = [...featuredResult.items, ...latestResult.items]
      .filter((question, index, questions) => questions.findIndex(item => item.id === question.id) === index)
      .slice(0, 3)
    stats.value = homeStats
  }
  catch {
    loadError.value = true
  }
  finally {
    loading.value = false
  }
}
async function playRandom() {
  if (randomLoading.value)
    return
  randomLoading.value = true
  try {
    await ensureAnonymousSession()
    openQuestion((await questionApi.random()).id)
  }
  catch {
    uni.showToast({ title: '暂时无法获取谜题，请稍后重试', icon: 'none' })
  }
  finally {
    randomLoading.value = false
  }
}
function openPublicRooms() {
  if (player.user)
    router.push({ name: 'public-rooms' })
  else router.push({ name: 'player-login', query: { redirect: '/pages/public-rooms/index' } })
}
onMounted(() => {
  void loadHome()
  timer = setInterval(() => titleIndex.value = (titleIndex.value + 1) % titles.length, 3000)
})
onUnmounted(() => timer && clearInterval(timer))
</script>

<template>
  <view class="home-page">
    <section class="hero">
      <view class="hero-inner">
        <text class="eyebrow hgt-mono">
          ◈ 侧向思维推理游戏
        </text>
        <view class="hero-title hgt-display">
          <text>{{ titles[titleIndex] }}</text><text class="hero-muted">
            在深处
          </text>
        </view>
        <text class="hero-copy">
          阅读离奇的故事开头，用只能回答“是 / 否 / 无关”的问题，一步步还原隐藏的真相。
        </text>
        <view class="hero-actions">
          <button class="primary-action hgt-mono" @click="router.push({ name: 'questions' })">
            浏览题库
          </button>
          <button class="secondary-action hgt-mono" :loading="randomLoading" @click="playRandom">
            随机一题
          </button>
        </view>
        <view class="quick-links">
          <view class="quick-link" @click="router.push({ name: 'questions' })">
            <text class="quick-icon hgt-mono">
              01
            </text><view>
              <text class="quick-title">
                单人解谜
              </text><text class="quick-copy">
                无需登录即可开始
              </text>
            </view><text>→</text>
          </view>
          <view class="quick-link" @click="openPublicRooms">
            <text class="quick-icon hgt-mono">
              02
            </text><view>
              <text class="quick-title">
                多人房间
              </text><text class="quick-copy">
                和朋友共同推理
              </text>
            </view><text>→</text>
          </view>
        </view>
      </view>
    </section>

    <section class="stats">
      <view v-for="item in [{ value: formatCount(stats.question_count), label: '公开谜题' }, { value: formatCount(stats.today_online), label: '今日在线' }, { value: `${stats.success_rate}%`, label: '解谜成功率' }, { value: formatDuration(stats.average_duration_seconds), label: '平均用时' }]" :key="item.label" class="stat">
        <text class="stat-value hgt-display">
          {{ item.value }}
        </text><text class="stat-label hgt-mono">
          {{ item.label }}
        </text>
      </view>
    </section>

    <section class="featured">
      <view class="section-head">
        <view>
          <text class="section-kicker hgt-mono">
            PLAY NOW
          </text><text class="hgt-display section-title">
            现在就能玩的谜题
          </text>
        </view>
        <text class="view-all hgt-mono" @click="router.push({ name: 'questions' })">
          查看全部 →
        </text>
      </view>
      <view v-if="loading" class="content-state">
        正在读取今日谜题…
      </view>
      <view v-else-if="loadError" class="content-state error-state">
        <text>谜题暂时没有加载出来</text><button class="retry hgt-mono" @click="loadHome">
          重新加载
        </button>
      </view>
      <view v-else class="puzzle-grid">
        <view v-for="(item, index) in featured" :key="item.id" class="puzzle-card" @click="openQuestion(item.id)">
          <view class="card-top">
            <text class="hgt-mono card-id">
              #{{ String(index + 1).padStart(3, '0') }}
            </text><text class="difficulty hgt-mono" :class="`level-${item.difficulty}`">
              {{ difficulty(item.difficulty) }}
            </text>
          </view>
          <text class="puzzle-title hgt-display">
            {{ item.title }}
          </text>
          <text class="puzzle-surface">
            {{ item.surface }}
          </text>
          <view class="card-foot">
            <text class="hgt-mono plays">
              {{ formatCount(item.play_count) }} 次游玩
            </text><text class="enter hgt-mono">
              进入谜题 →
            </text>
          </view>
        </view>
      </view>
    </section>

    <section class="how-to">
      <view class="how-copy">
        <text class="section-kicker hgt-mono">
          HOW TO PLAY
        </text><text class="hgt-display section-title">
          三步还原故事真相
        </text>
        <text class="how-intro">
          海龟汤是一种情境推理游戏。你只需要根据简短的“汤面”提问，主持人会判断问题与真相的关系。
        </text>
      </view>
      <view class="steps">
        <view v-for="step in [{ no: '01', title: '阅读汤面', copy: '从一段反常、离奇的故事开头寻找线索。' }, { no: '02', title: '不断提问', copy: '提出可用“是、否、无关”回答的问题，缩小范围。' }, { no: '03', title: '提交推理', copy: '串联已知信息，说出你认为完整的故事真相。' }]" :key="step.no" class="step">
          <text class="step-no hgt-mono">
            {{ step.no }}
          </text><text class="step-title hgt-display">
            {{ step.title }}
          </text><text class="step-copy">
            {{ step.copy }}
          </text>
        </view>
      </view>
    </section>

    <section class="support">
      <text class="hgt-mono">
        ◆ 持续更新
      </text><text>题库会持续增加新故事。你也可以查看游玩记录，继续未完成的推理。</text>
      <view class="support-actions">
        <button class="secondary-action hgt-mono" @click="router.push({ name: 'history' })">
          游玩记录
        </button>
        <!-- #ifdef H5 -->
        <button class="secondary-action hgt-mono" @click="router.push({ name: 'donate' })">
          支持我们
        </button>
        <!-- #endif -->
      </view>
    </section>
  </view>
</template>

<style scoped>
.home-page{min-height:100vh}.hero{display:flex;min-height:560px;padding:70px 64px;border-bottom:1px solid var(--border);align-items:center}.hero-inner{display:flex;width:100%;max-width:760px;flex-direction:column}.eyebrow,.section-kicker{margin-bottom:22px;color:var(--muted-foreground);font-size:11px;letter-spacing:.35em}.hero-title{display:flex;margin-bottom:24px;flex-direction:column;font-size:72px;line-height:1}.hero-muted{color:var(--muted-foreground)}.hero-copy{max-width:590px;margin-bottom:32px;color:var(--muted-foreground);font-size:17px;line-height:1.9}.hero-actions,.support-actions{display:flex;gap:12px}.primary-action,.secondary-action,.retry{height:46px;margin:0;padding:0 28px;border-radius:0;font-size:12px;letter-spacing:.14em;line-height:44px}.primary-action{border:1px solid var(--foreground);background:var(--foreground);color:var(--background)}.secondary-action,.retry{border:1px solid var(--border);background:transparent;color:var(--foreground)}button::after{display:none}.quick-links{display:grid;max-width:680px;margin-top:38px;grid-template-columns:repeat(2,1fr)}.quick-link{display:flex;padding:18px 20px;border:1px solid var(--border);align-items:center;gap:14px;background:color-mix(in srgb,var(--card) 72%,transparent)}.quick-link+.quick-link{margin-left:-1px}.quick-link>view{display:flex;min-width:0;flex:1;flex-direction:column;gap:4px}.quick-icon,.quick-copy{color:var(--muted-foreground);font-size:10px}.quick-title{font-size:13px}.stats{display:grid;border-bottom:1px solid var(--border);grid-template-columns:repeat(4,1fr)}.stat{display:flex;padding:26px 32px;border-right:1px solid var(--border);flex-direction:column}.stat:last-child{border:0}.stat-value{font-size:38px}.stat-label{margin-top:5px;color:var(--muted-foreground);font-size:11px;letter-spacing:.15em}.featured,.how-to{padding:56px 64px}.section-head{display:flex;margin-bottom:30px;align-items:flex-end;justify-content:space-between}.section-head>view,.how-copy{display:flex;flex-direction:column}.section-kicker{margin-bottom:8px}.section-title{font-size:24px;letter-spacing:.12em}.view-all,.plays,.enter{color:var(--muted-foreground);font-size:11px}.puzzle-grid{display:grid;grid-template-columns:repeat(3,1fr)}.puzzle-card{display:flex;min-width:0;padding:24px;border:1px solid var(--border);margin:0 -1px -1px 0;flex-direction:column;background:color-mix(in srgb,var(--card) 88%,transparent);transition:.2s}.card-top,.card-foot{display:flex;align-items:center;justify-content:space-between}.card-top{margin-bottom:18px}.card-id{color:var(--muted-foreground);font-size:11px}.difficulty{padding:3px 8px;border:1px solid var(--border);font-size:10px}.level-1{border-color:#16a34a;background:rgba(22,163,74,.1);color:#16a34a}.level-2,.level-3{border-color:#d97706;background:rgba(217,119,6,.1);color:#d97706}.level-4,.level-5{border-color:#dc2626;background:rgba(220,38,38,.1);color:#dc2626}.puzzle-title{display:block;margin-bottom:12px;font-size:20px}.puzzle-surface{display:-webkit-box;min-height:72px;overflow:hidden;color:var(--muted-foreground);font-size:13px;line-height:1.8;-webkit-box-orient:vertical;-webkit-line-clamp:3}.card-foot{margin-top:20px;padding-top:14px;border-top:1px solid var(--border)}.enter{color:var(--foreground)}.content-state{display:flex;min-height:180px;border:1px solid var(--border);align-items:center;justify-content:center;color:var(--muted-foreground);font-size:13px}.error-state{flex-direction:column;gap:16px}.retry{height:38px;line-height:36px}.how-to{display:grid;border-top:1px solid var(--border);border-bottom:1px solid var(--border);grid-template-columns:minmax(230px,.7fr) 1.3fr;gap:60px}.how-intro{max-width:420px;margin-top:22px;color:var(--muted-foreground);font-size:13px;line-height:1.9}.steps{display:grid;grid-template-columns:repeat(3,1fr)}.step{display:flex;padding:8px 24px 8px 0;flex-direction:column}.step+.step{padding-left:24px;border-left:1px solid var(--border)}.step-no{margin-bottom:30px;color:var(--muted-foreground);font-size:10px}.step-title{margin-bottom:12px;font-size:18px}.step-copy{color:var(--muted-foreground);font-size:12px;line-height:1.8}.support{display:flex;padding:46px;flex-direction:column;align-items:center;gap:18px;color:var(--muted-foreground);font-size:13px;text-align:center}@media(hover:hover){.puzzle-card:hover,.quick-link:hover{background:var(--secondary)}.puzzle-card:hover{transform:translateY(-2px)}}
@media(max-width:767px){.hero{min-height:auto;padding:42px 22px 32px;align-items:flex-start}.eyebrow{margin-bottom:18px}.hero-title{margin-bottom:18px;font-size:48px}.hero-copy{margin-bottom:24px;font-size:14px;line-height:1.75}.hero-actions{width:100%}.hero-actions button{padding:0 18px;flex:1}.quick-links{width:100%;margin-top:28px;grid-template-columns:1fr}.quick-link{padding:14px 16px}.quick-link+.quick-link{margin-top:-1px;margin-left:0}.stats{grid-template-columns:repeat(2,1fr)}.stat{padding:20px 22px;border-bottom:1px solid var(--border)}.stat:nth-child(2){border-right:0}.stat-value{font-size:29px}.featured,.how-to{padding:38px 22px}.section-head{margin-bottom:22px;align-items:flex-start}.section-title{font-size:21px}.view-all{padding-top:20px}.puzzle-grid{grid-template-columns:1fr}.puzzle-card{padding:20px}.puzzle-surface{min-height:auto;-webkit-line-clamp:3}.how-to{display:block}.how-intro{margin-top:16px}.steps{margin-top:30px;grid-template-columns:1fr}.step{padding:18px 0}.step+.step{padding:18px 0;border-top:1px solid var(--border);border-left:0}.step-no{margin-bottom:10px}.support{padding:38px 22px}.support-actions{width:100%}.support-actions button{padding:0 12px;flex:1}}
</style>
