<script setup lang="ts">
import type { PublicQuestion } from '@/types/game'
import { ensureAnonymousSession, questionApi } from '@/api/turtle'
import { usePlayerStore } from '@/store/playerStore'

definePage({ name: 'home', layout: 'tabbar', style: { navigationStyle: 'custom' } })
const router = useRouter()
const player = usePlayerStore()
const featured = ref<PublicQuestion[]>([])
const titleIndex = ref(0)
const titles = ['真相', '谜题', '汤底']
let timer: ReturnType<typeof setInterval> | undefined
onMounted(async () => {
  await player.restore()
  await ensureAnonymousSession()
  featured.value = (await questionApi.list({ pageSize: 3 })).items
  timer = setInterval(() => titleIndex.value = (titleIndex.value + 1) % titles.length, 3000)
})
onUnmounted(() => timer && clearInterval(timer))
const difficulty = (level: number) => ['未知', '简单', '普通', '中等', '困难', '极难'][level] || '未知'
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
      <view v-for="item in [{ value: featured.length ? '1+' : '0', label: '题目总数' }, { value: '—', label: '今日在线' }, { value: '—', label: '解谜成功率' }, { value: '—', label: '平均用时' }]" :key="item.label" class="stat">
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
          </text><view class="tags">
            <text v-for="tag in item.tags" :key="tag.id" class="tag hgt-mono">
              {{ tag.name }}
            </text>
          </view><text class="hgt-mono plays">
            点击查看谜面
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
.home-page{min-height:100vh}.hero{min-height:60vh;display:flex;align-items:center;padding:70px 64px;border-bottom:1px solid var(--border)}.hero-inner{max-width:680px;display:flex;flex-direction:column}.eyebrow{font-size:12px;letter-spacing:.45em;color:var(--muted-foreground);margin-bottom:28px}.hero-title{font-size:72px;line-height:1;display:flex;flex-direction:column;margin-bottom:28px}.hero-muted{color:var(--muted-foreground)}.hero-copy{max-width:540px;font-size:18px;line-height:1.9;color:var(--muted-foreground);margin-bottom:38px}.hero-actions{display:flex;gap:16px}.primary-action,.secondary-action{margin:0;border-radius:0;padding:0 30px;height:46px;line-height:44px;font-size:12px;letter-spacing:.16em}.primary-action{background:var(--foreground);color:var(--background);border:1px solid var(--foreground)}.secondary-action{background:transparent;color:var(--muted-foreground);border:1px solid var(--border)}button::after{display:none}.stats{display:grid;grid-template-columns:repeat(4,1fr);border-bottom:1px solid var(--border)}.stat{padding:26px 32px;border-right:1px solid var(--border);display:flex;flex-direction:column}.stat:last-child{border:0}.stat-value{font-size:38px}.stat-label{font-size:11px;letter-spacing:.15em;color:var(--muted-foreground);margin-top:5px}.featured{padding:48px 64px}.section-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px}.section-title{font-size:20px;letter-spacing:.16em}.view-all,.plays{font-size:11px;color:var(--muted-foreground)}.puzzle-grid{display:grid;grid-template-columns:repeat(3,1fr);border-left:1px solid var(--border);border-top:1px solid var(--border)}.puzzle-card{padding:24px;border-right:1px solid var(--border);border-bottom:1px solid var(--border)}.card-top{display:flex;justify-content:space-between;margin-bottom:20px}.card-id{font-size:11px;color:var(--muted-foreground)}.difficulty,.tag{font-size:10px;border:1px solid var(--border);padding:3px 8px}.level-1{color:#4ade80;border-color:#4ade80}.level-3,.level-2{color:#facc15;border-color:#facc15}.level-4,.level-5{color:#f87171;border-color:#f87171}.puzzle-title{font-size:20px;margin-bottom:14px;display:block}.tags{display:flex;gap:5px;margin-bottom:22px}.tag{color:var(--muted-foreground)}.support{border-top:1px solid var(--border);padding:46px;display:flex;flex-direction:column;align-items:center;gap:18px;color:var(--muted-foreground);font-size:13px}
@media(max-width:767px){.hero{min-height:500px;padding:70px 32px}.hero-title{font-size:50px}.hero-copy{font-size:15px}.hero-actions{flex-wrap:wrap}.stats{grid-template-columns:repeat(2,1fr)}.stat{padding:25px 32px;border-bottom:1px solid var(--border)}.stat-value{font-size:31px}.featured{padding:44px 32px}.puzzle-grid{grid-template-columns:1fr}.support{padding:42px 28px;text-align:center}}
</style>
