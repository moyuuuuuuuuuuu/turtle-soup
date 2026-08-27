<script setup lang="ts">
import type { PublicQuestion } from '@/types/game'
import { questionApi } from '@/api/turtle'

definePage({ name: 'questions', layout: 'tabbar', style: { navigationStyle: 'custom' } })
const router = useRouter()
const route = useRoute()
const roomId = computed(() => String(route.query.room_id || ''))
const items = ref<PublicQuestion[]>([])
const keyword = ref('')
const difficulty = ref<number>()
const viewMode = ref<'grid' | 'list'>('grid')
const loading = ref(false)
const activeRiskId = ref<string | null>(null)
const filtered = computed(() => items.value.filter(item => !keyword.value || item.title.includes(keyword.value) || item.surface.includes(keyword.value)))
const difficultyLabel = (value: number) => ['未知', '简单', '普通', '中等', '困难', '极难'][value] || `难度 ${value}`
const difficultyClass = (value: number) => ['unknown', 'easy', 'normal', 'medium', 'hard', 'extreme'][value] || 'unknown'
const riskLevelLabels: Record<PublicQuestion['risk_level'], string> = { safe: '安全', caution: '需注意', restricted: '受限内容' }
const riskTypeLabels: Record<string, string> = {
  death: '死亡',
  violence: '暴力',
  gore: '血腥',
  self_harm: '自伤',
  sexual: '性内容',
  child_safety: '未成年人',
  discrimination: '歧视',
  illegal: '违法',
  substance: '成瘾物',
  other: '其他',
}
const riskLevelLabel = (value: PublicQuestion['risk_level']) => riskLevelLabels[value] || value
const riskTypeText = (types: string[] | undefined) => types?.length ? types.map(type => riskTypeLabels[type] || type).join('、') : '无特别标注'
const formatCount = (value: number) => new Intl.NumberFormat('zh-CN').format(value || 0)
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
function openQuestion(id: string) {
  router.push({ path: '/pages/question-detail/index', query: { id, ...(roomId.value ? { room_id: roomId.value } : {}) } })
}
function toggleRisk(id: string) {
  activeRiskId.value = activeRiskId.value === id ? null : id
}
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
      <view v-for="(item, index) in filtered" :key="item.id" class="question-card" @click="openQuestion(item.id)">
        <view class="meta">
          <text>#{{ String(index + 1).padStart(3, '0') }}</text><text class="difficulty-tag" :class="difficultyClass(item.difficulty)">
            {{ difficultyLabel(item.difficulty) }}
          </text><view class="risk-wrap" :class="{ open: activeRiskId === item.id }" @click.stop="toggleRisk(item.id)">
            <text class="risk" :class="item.risk_level">
              {{ riskLevelLabel(item.risk_level) }}
            </text>
            <view class="risk-tip" @click.stop>
              <text class="risk-tip-title">
                风险类型：{{ riskTypeText(item.risk_types) }}
              </text>
              <text class="risk-tip-note">
                风险说明：{{ item.risk_note || item.risk_warning || '暂无具体说明' }}
              </text>
            </view>
          </view>
        </view>
        <text class="question-title">
          {{ item.title }}
        </text><text class="surface">
          {{ item.surface }}
        </text>
        <view v-if="item.tags?.length" class="tags">
          <text v-for="tag in item.tags" :key="tag.id" class="tag">
            {{ tag.name }}
          </text>
        </view>
        <view class="foot">
          <text>{{ formatCount(item.play_count) }} 次游玩</text><text class="enter">
            进入谜题 →
          </text>
        </view>
      </view>
    </view>
  </view>
</template>

<style scoped>
.library-page{min-height:100%;color:var(--foreground);background:var(--background)}.page-head{display:flex;flex-direction:column;padding:32px 36px;border-bottom:1px solid var(--border)}.eyebrow,.result-count,.meta,.foot{font-family:monospace;color:var(--muted-foreground);font-size:12px;letter-spacing:.14em}.eyebrow{letter-spacing:.5em}.title{margin-top:8px;font-family:Georgia,serif;font-size:34px;letter-spacing:.08em}.filters{display:flex;gap:14px;align-items:center;padding:18px 36px;background:var(--card);border-bottom:1px solid var(--border)}.search{display:flex;align-items:center;gap:9px;width:290px;padding:9px 12px;border:1px solid var(--border)}.search input{flex:1;color:var(--foreground);font-size:13px}.difficulty{flex:1;white-space:nowrap}.difficulty button,.view-toggle button{display:inline-flex;margin-right:5px;padding:8px 12px;color:var(--muted-foreground);background:transparent;border:1px solid var(--border);font:12px monospace;white-space:nowrap}.difficulty button.active,.view-toggle button.active{color:var(--foreground);border-color:var(--foreground)}.view-toggle{display:flex}.result-count{padding:12px 36px;border-bottom:1px solid var(--border)}.question-wrap{padding:30px 36px}.question-wrap.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.question-card{padding:22px;border:1px solid var(--border);background:var(--card);transition:.2s}.question-card:active{border-color:var(--accent)}.meta{display:flex;gap:8px;align-items:center;min-width:0}.difficulty-tag,.risk{flex:none;padding:2px 7px;border:1px solid;line-height:1.4;white-space:nowrap}.risk-wrap{position:relative;margin-left:auto;cursor:pointer}.risk{display:block}.risk.safe{color:#16a34a;border-color:#16a34a}.risk.caution{color:#d97706;border-color:#d97706}.risk.restricted{color:#dc2626;border-color:#dc2626}.risk-tip{position:absolute;z-index:20;top:calc(100% + 9px);right:0;display:none;width:280px;padding:12px;border:1px solid var(--border);background:var(--popover,var(--card));box-shadow:0 12px 32px rgba(0,0,0,.35);color:var(--foreground);font:12px/1.6 sans-serif;letter-spacing:0;white-space:normal}.risk-tip::before{position:absolute;top:-5px;right:18px;width:8px;height:8px;background:inherit;border-top:1px solid var(--border);border-left:1px solid var(--border);content:'';transform:rotate(45deg)}.risk-tip-title,.risk-tip-note{display:block}.risk-tip-note{margin-top:5px;color:var(--muted-foreground)}.risk-wrap.open .risk-tip{display:block}.question-title{display:block;margin:16px 0 10px;font:20px Georgia,serif}.surface{display:-webkit-box;min-height:42px;overflow:hidden;color:var(--muted-foreground);font-size:13px;line-height:1.7;-webkit-line-clamp:2;-webkit-box-orient:vertical}.foot{display:flex;justify-content:space-between;margin-top:18px;padding-top:13px;border-top:1px solid var(--border);letter-spacing:0}.list .question-card{display:grid;grid-template-columns:190px minmax(150px,200px) minmax(240px,1fr) minmax(210px,auto) 140px;column-gap:20px;align-items:center;padding:15px 20px}.list .question-title,.list .surface,.list .foot,.list .tags{margin:0;min-width:0}.list .surface{min-height:auto}.list .tags{justify-content:flex-end}.list .foot{border:0;padding:0;white-space:nowrap}.list .risk-wrap{margin-left:0}.empty{display:flex;flex-direction:column;align-items:center;gap:14px;padding:100px 20px;color:var(--muted-foreground);font:13px monospace}.empty-icon{font-size:42px;color:var(--border)}
@media(max-width:900px){.question-wrap.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.list .question-card{display:block}.list .question-title{margin:14px 0}.list .foot{margin-top:14px}}
@media(max-width:600px){.page-head{padding:24px 18px}.title{font-size:29px}.filters{align-items:stretch;flex-wrap:wrap;padding:14px 18px}.search{width:100%}.difficulty{width:calc(100% - 78px)}.result-count{padding:11px 18px}.question-wrap{padding:18px}.question-wrap.grid{grid-template-columns:1fr}}
.library-page{background:transparent}.question-card{background:color-mix(in srgb,var(--card) 94%,transparent);transition:border-color .2s,background-color .2s,transform .2s}.tags{display:flex;flex-wrap:wrap;gap:6px;min-height:23px;margin-top:14px}.tag{flex:none;padding:3px 8px;border:1px solid color-mix(in srgb,var(--foreground) 24%,var(--border));background:color-mix(in srgb,var(--foreground) 4%,transparent);color:var(--foreground);font:10px monospace;line-height:1.4;white-space:nowrap}.foot{gap:14px;align-items:center}.enter{margin-left:auto}@media(hover:hover){.question-card:hover{border-color:var(--foreground);background:var(--secondary);transform:translateY(-2px)}}
.question-wrap.grid .foot{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;column-gap:12px;row-gap:9px}
.question-wrap.grid .foot>text{white-space:nowrap}
.question-wrap.grid .enter{grid-column:1/-1;justify-self:end;margin-left:0}
.difficulty button.easy.active,.difficulty-tag.easy{border-color:#16a34a;background:rgba(22,163,74,.1);color:#16a34a;font-weight:600}.difficulty button.normal.active,.difficulty-tag.normal{border-color:#0891b2;background:rgba(8,145,178,.1);color:#0891b2;font-weight:600}.difficulty button.medium.active,.difficulty-tag.medium{border-color:#d97706;background:rgba(217,119,6,.1);color:#d97706;font-weight:600}.difficulty button.hard.active,.difficulty-tag.hard{border-color:#ea580c;background:rgba(234,88,12,.1);color:#ea580c;font-weight:600}.difficulty button.extreme.active,.difficulty-tag.extreme{border-color:#dc2626;background:rgba(220,38,38,.1);color:#dc2626;font-weight:600}
@media(hover:hover){.risk-wrap:hover .risk-tip{display:block}}
</style>
