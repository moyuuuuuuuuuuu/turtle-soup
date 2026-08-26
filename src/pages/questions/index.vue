<script setup lang="ts">
import type { PublicQuestion } from '@/types/game'
import { questionApi } from '@/api/turtle'

definePage({ name: 'questions', layout: 'tabbar', style: { navigationStyle: 'custom' } })
const router = useRouter()
const items = ref<PublicQuestion[]>([])
const keyword = ref('')
const difficulty = ref<number>()
const viewMode = ref<'grid' | 'list'>('grid')
const loading = ref(false)
const filtered = computed(() => items.value.filter(item => !keyword.value || item.title.includes(keyword.value) || item.surface.includes(keyword.value)))
const difficultyLabel = (value: number) => ['未知', '简单', '简单', '中等', '困难', '困难'][value] || `难度 ${value}`
const difficultyClass = (value: number) => value <= 2 ? 'easy' : value === 3 ? 'medium' : 'hard'
async function load() {
  loading.value = true
  try {
    items.value = (await questionApi.list(difficulty.value ? { difficulty: difficulty.value } : {})).items
  }
  finally {
    loading.value = false
  }
}
onMounted(load)
</script>

<template>
  <view class="library-page">
    <view class="page-head">
      <text class="eyebrow">
        ◉ 题目库
      </text><text class="title">
        探索谜题
      </text>
    </view>
    <view class="filters">
      <view class="search">
        <text>⌕</text><input v-model="keyword" placeholder="搜索题目、汤面...">
      </view>
      <scroll-view scroll-x class="difficulty">
        <button :class="{ active: difficulty === undefined }" @click="difficulty = undefined; load()">
          全部
        </button><button v-for="level in [1, 2, 3, 4, 5]" :key="level" :class="[{ active: difficulty === level }, difficultyClass(level)]" @click="difficulty = level; load()">
          {{ difficultyLabel(level) }}
        </button>
      </scroll-view>
      <view class="view-toggle">
        <button :class="{ active: viewMode === 'grid' }" @click="viewMode = 'grid'">
          ⊞
        </button><button :class="{ active: viewMode === 'list' }" @click="viewMode = 'list'">
          ☰
        </button>
      </view>
    </view>
    <view class="result-count">
      找到 {{ filtered.length }} 个谜题
    </view>
    <view v-if="loading" class="empty">
      正在读取题库…
    </view>
    <view v-else-if="!filtered.length" class="empty">
      <text class="empty-icon">
        ◎
      </text><text>没有找到匹配的谜题</text>
    </view>
    <view v-else class="question-wrap" :class="viewMode">
      <view v-for="(item, index) in filtered" :key="item.id" class="question-card" @click="router.push({ name: 'question-detail', params: { id: item.id } })">
        <view class="meta">
          <text>#{{ String(index + 1).padStart(3, '0') }}</text><text class="difficulty-tag" :class="difficultyClass(item.difficulty)">
            {{ difficultyLabel(item.difficulty) }}
          </text><text v-if="item.risk_level === 'caution'" class="risk">
            内容提醒
          </text>
        </view>
        <text class="question-title">
          {{ item.title }}
        </text><text class="surface">
          {{ item.surface }}
        </text>
        <view class="foot">
          <text>难度 {{ item.difficulty }}/5</text><text>进入谜题 →</text>
        </view>
      </view>
    </view>
  </view>
</template>

<style scoped>
.library-page{min-height:100%;color:var(--hgt-fg);background:var(--hgt-bg)}.page-head{display:flex;flex-direction:column;padding:32px 36px;border-bottom:1px solid var(--hgt-border)}.eyebrow,.result-count,.meta,.foot{font-family:monospace;color:var(--hgt-muted);font-size:12px;letter-spacing:.14em}.eyebrow{letter-spacing:.5em}.title{margin-top:8px;font-family:Georgia,serif;font-size:34px;letter-spacing:.08em}.filters{display:flex;gap:14px;align-items:center;padding:18px 36px;background:var(--hgt-panel);border-bottom:1px solid var(--hgt-border)}.search{display:flex;align-items:center;gap:9px;width:290px;padding:9px 12px;border:1px solid var(--hgt-border)}.search input{flex:1;color:var(--hgt-fg);font-size:13px}.difficulty{flex:1;white-space:nowrap}.difficulty button,.view-toggle button{display:inline-flex;margin-right:5px;padding:8px 12px;color:var(--hgt-muted);background:transparent;border:1px solid var(--hgt-border);font:12px monospace}.difficulty button.active,.view-toggle button.active{color:var(--hgt-fg);border-color:var(--hgt-fg)}.difficulty button.easy.active,.difficulty-tag.easy{color:#4ade80;border-color:#4ade80}.difficulty button.medium.active,.difficulty-tag.medium{color:#facc15;border-color:#facc15}.difficulty button.hard.active,.difficulty-tag.hard{color:#f87171;border-color:#f87171}.view-toggle{display:flex}.result-count{padding:12px 36px;border-bottom:1px solid var(--hgt-border)}.question-wrap{padding:30px 36px}.question-wrap.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.question-card{padding:22px;border:1px solid var(--hgt-border);background:var(--hgt-card);transition:.2s}.question-card:active{border-color:var(--hgt-accent)}.meta{display:flex;gap:8px;align-items:center}.difficulty-tag,.risk{padding:2px 7px;border:1px solid}.risk{margin-left:auto;color:#f59e0b;border-color:#f59e0b}.question-title{display:block;margin:16px 0 10px;font:20px Georgia,serif}.surface{display:-webkit-box;min-height:42px;overflow:hidden;color:var(--hgt-muted);font-size:13px;line-height:1.7;-webkit-line-clamp:2;-webkit-box-orient:vertical}.foot{display:flex;justify-content:space-between;margin-top:18px;padding-top:13px;border-top:1px solid var(--hgt-border);letter-spacing:0}.list .question-card{display:grid;grid-template-columns:160px 220px 1fr 190px;align-items:center;padding:15px 20px}.list .question-title,.list .surface,.list .foot{margin:0}.list .surface{min-height:auto}.list .foot{border:0;padding:0}.empty{display:flex;flex-direction:column;align-items:center;gap:14px;padding:100px 20px;color:var(--hgt-muted);font:13px monospace}.empty-icon{font-size:42px;color:var(--hgt-border)}
@media(max-width:900px){.question-wrap.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.list .question-card{display:block}.list .question-title{margin:14px 0}.list .foot{margin-top:14px}}
@media(max-width:600px){.page-head{padding:24px 18px}.title{font-size:29px}.filters{align-items:stretch;flex-wrap:wrap;padding:14px 18px}.search{width:100%}.difficulty{width:calc(100% - 78px)}.result-count{padding:11px 18px}.question-wrap{padding:18px}.question-wrap.grid{grid-template-columns:1fr}}
</style>
