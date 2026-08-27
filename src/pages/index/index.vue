<script setup lang="ts">
import type { HomeStats, PublicQuestion } from '@/types/game'
import { ensureAnonymousSession, homeApi, questionApi } from '@/api/turtle'
import { usePlayerStore } from '@/store/playerStore'

definePage({ name: 'home', layout: 'tabbar', style: { navigationStyle: 'custom' } })
const router = useRouter()
const player = usePlayerStore()
const featured = ref<PublicQuestion[]>([])
const stats = ref<HomeStats>({ question_count: 0, today_online: 0, success_rate: 0, average_duration_seconds: null })
const titleIndex = ref(0)
const titles = ['真相', '谜题', '汤底']
let timer: ReturnType<typeof setInterval> | undefined
onMounted(async () => {
  await player.restore()
  await ensureAnonymousSession()
  const [questions, homeStats] = await Promise.all([questionApi.list({ page_size: 3 }), homeApi.stats()])
  featured.value = questions.items
  stats.value = homeStats
  timer = setInterval(() => titleIndex.value = (titleIndex.value + 1) % titles.length, 3000)
})
onUnmounted(() => timer && clearInterval(timer))
const difficulty = (level: number) => ['未知', '简单', '普通', '中等', '困难', '极难'][level] || '未知'
const formatCount = (value: number) => new Intl.NumberFormat('zh-CN').format(value || 0)
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
</script>

<template>
  <view class="home-page">
    <section class="hero">
      <view class="hero-inner">
        <text class="eyebrow hgt-mono">
          ◈ 侧向思维游戏
        </text>
        <view class="hero-title hgt-display">
          <text>{{ titles[titleIndex] }}</text><text class="hero-muted">
            在深处
          </text>
        </view>
        <text class="hero-copy">
          通过提问来还原故事全貌。每一个问题，都是接近真相的一步。你，准备好了吗？
        </text>
        <view class="hero-actions">
          <button class="primary-action hgt-mono" @click="router.push({ name: 'questions' })">
            开始游玩
          </button><button class="secondary-action hgt-mono" @click="router.push({ name: 'history' })">
            游玩记录
          </button>
        </view>
      </view>
    </section>
    <section class="stats">
      <view v-for="item in [{ value: formatCount(stats.question_count), label: '题目总数' }, { value: formatCount(stats.today_online), label: '今日在线' }, { value: `${stats.success_rate}%`, label: '解谜成功率' }, { value: formatDuration(stats.average_duration_seconds), label: '平均用时' }]" :key="item.label" class="stat">
        <text class="stat-value hgt-display">
          {{ item.value }}
        </text><text class="stat-label hgt-mono">
          {{ item.label }}
        </text>
      </view>
    </section>
    <section class="featured">
      <view class="section-head">
        <text class="hgt-display section-title">
          精选谜题
        </text><text class="view-all hgt-mono" @click="router.push({ name: 'questions' })">
          查看全部 →
        </text>
      </view>
      <view class="puzzle-grid">
        <view v-for="(item, index) in featured" :key="item.id" class="puzzle-card" @click="router.push({ name: 'question-detail', params: { id: item.id } })">
          <view class="card-top">
            <text class="hgt-mono card-id">
              #{{ String(index + 1).padStart(3, '0') }}
            </text><text class="difficulty hgt-mono" :class="`level-${item.difficulty}`">
              {{ difficulty(item.difficulty) }}
            </text>
          </view>
          <text class="puzzle-title hgt-display">
            {{ item.title }}
          </text><view v-if="item.tags.length" class="tags">
            <text v-for="tag in item.tags" :key="tag.id" class="tag hgt-mono">
              {{ tag.name }}
            </text>
          </view><text class="hgt-mono plays">
            {{ formatCount(item.play_count) }} 次游玩
          </text>
        </view>
      </view>
    </section>
    <section class="support">
      <text class="hgt-mono">
        ◆ 支持我们
      </text><text>喜欢海龟汤？你的每一份支持都让更多谜题成为可能。</text><button class="secondary-action hgt-mono" @click="router.push({ name: 'donate' })">
        捐赠支持
      </button>
    </section>
  </view>
</template>

<style scoped>
.home-page{min-height:100vh}.hero{min-height:60vh;display:flex;align-items:center;padding:70px 64px;border-bottom:1px solid var(--border)}.hero-inner{max-width:680px;display:flex;flex-direction:column}.eyebrow{font-size:12px;letter-spacing:.45em;color:var(--muted-foreground);margin-bottom:28px}.hero-title{font-size:72px;line-height:1;display:flex;flex-direction:column;margin-bottom:28px}.hero-muted{color:var(--muted-foreground)}.hero-copy{max-width:540px;font-size:18px;line-height:1.9;color:var(--muted-foreground);margin-bottom:38px}.hero-actions{display:flex;gap:16px}.primary-action,.secondary-action{margin:0;border-radius:0;padding:0 30px;height:46px;line-height:44px;font-size:12px;letter-spacing:.16em}.primary-action{background:var(--foreground);color:var(--background);border:1px solid var(--foreground)}.secondary-action{background:transparent;color:var(--muted-foreground);border:1px solid var(--border)}button::after{display:none}.stats{display:grid;grid-template-columns:repeat(4,1fr);border-bottom:1px solid var(--border)}.stat{padding:26px 32px;border-right:1px solid var(--border);display:flex;flex-direction:column}.stat:last-child{border:0}.stat-value{font-size:38px}.stat-label{font-size:11px;letter-spacing:.15em;color:var(--muted-foreground);margin-top:5px}.featured{padding:48px 64px}.section-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px}.section-title{font-size:20px;letter-spacing:.16em}.view-all,.plays{font-size:11px;color:var(--muted-foreground)}.puzzle-grid{display:grid;grid-template-columns:repeat(3,1fr)}.puzzle-card{padding:24px;border:1px solid var(--border);margin:0 -1px -1px 0}.card-top{display:flex;justify-content:space-between;margin-bottom:20px}.card-id{font-size:11px;color:var(--muted-foreground)}.difficulty,.tag{font-size:10px;border:1px solid var(--border);padding:3px 8px}.level-1{color:#4ade80;border-color:#4ade80}.level-3,.level-2{color:#facc15;border-color:#facc15}.level-4,.level-5{color:#f87171;border-color:#f87171}.puzzle-title{font-size:20px;margin-bottom:14px;display:block}.tags{display:flex;gap:5px;margin-bottom:22px}.tag{color:var(--muted-foreground)}.support{border-top:1px solid var(--border);padding:46px;display:flex;flex-direction:column;align-items:center;gap:18px;color:var(--muted-foreground);font-size:13px}
@media(max-width:767px){.hero{min-height:500px;padding:70px 32px}.hero-title{font-size:50px}.hero-copy{font-size:15px}.hero-actions{flex-wrap:wrap}.stats{grid-template-columns:repeat(2,1fr)}.stat{padding:25px 32px;border-bottom:1px solid var(--border)}.stat-value{font-size:31px}.featured{padding:44px 32px}.puzzle-grid{grid-template-columns:1fr}.support{padding:42px 28px;text-align:center}}
.home-page{background:transparent}.puzzle-card{background:color-mix(in srgb,var(--card) 88%,transparent);transition:background-color .2s,color .2s,transform .2s}@media(hover:hover){.puzzle-card:hover{background:var(--secondary);color:var(--foreground);transform:translateY(-2px)}}
.level-1{border-color:#16a34a;background:rgba(22,163,74,.1);color:#16a34a;font-weight:600}.level-2,.level-3{border-color:#d97706;background:rgba(217,119,6,.1);color:#d97706;font-weight:600}.level-4,.level-5{border-color:#dc2626;background:rgba(220,38,38,.1);color:#dc2626;font-weight:600}
</style>
