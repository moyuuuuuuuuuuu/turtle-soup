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
const filtersVisible = ref(false)
const excludedRiskTypes = ref<string[]>([])
const viewMode = ref<'grid' | 'list'>('grid')
const loading = ref(false)
const loadError = ref(false)
const page = ref(1)
const pageSize = 21
const total = ref(0)
const activeRiskId = ref<string | null>(null)
const filtered = computed(() => items.value.filter((item) => {
  const matchesKeyword = !keyword.value || item.title.includes(keyword.value) || item.surface.includes(keyword.value)
  const matchesRisk = !item.risk_types?.some(type => excludedRiskTypes.value.includes(type))
  return matchesKeyword && matchesRisk
}))
const activeFilterCount = computed(() => (difficulty.value ? 1 : 0) + excludedRiskTypes.value.length)
const loadmoreState = computed<'loading' | 'finished' | 'error'>(() => {
  if (loadError.value)
    return 'error'
  if (loading.value)
    return 'loading'
  return items.value.length >= total.value ? 'finished' : 'loading'
})
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
const riskTypeOptions = Object.entries(riskTypeLabels).map(([value, label]) => ({ value, label }))
const riskLevelLabel = (value: PublicQuestion['risk_level']) => riskLevelLabels[value] || value
const riskTypeText = (types: string[] | undefined) => types?.length ? types.map(type => riskTypeLabels[type] || type).join('、') : '无特别标注'
const formatCount = (value: number) => new Intl.NumberFormat('zh-CN').format(value || 0)
async function load(reset = false) {
  if (loading.value)
    return
  if (reset) {
    page.value = 1
    total.value = 0
    items.value = []
  }
  loading.value = true
  loadError.value = false
  try {
    const result = await questionApi.list({
      ...(difficulty.value ? { difficulty: difficulty.value } : {}),
      page: page.value,
      page_size: pageSize,
    })
    items.value = reset ? result.items : [...items.value, ...result.items]
    total.value = result.pagination.total || items.value.length
    if (items.value.length < total.value)
      page.value += 1
  }
  catch {
    loadError.value = true
  }
  finally {
    loading.value = false
  }
}
function loadMore() {
  if (!loading.value && items.value.length < total.value)
    void load()
}
function changeDifficulty(value?: number) {
  difficulty.value = value
  void load(true)
}
function toggleExcludedRiskType(value: string) {
  excludedRiskTypes.value = excludedRiskTypes.value.includes(value)
    ? excludedRiskTypes.value.filter(type => type !== value)
    : [...excludedRiskTypes.value, value]
}
function clearFilters() {
  excludedRiskTypes.value = []
  if (difficulty.value !== undefined)
    changeDifficulty()
}
onMounted(() => load(true))
onReachBottom(loadMore)
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
    <view class="filter-shell">
      <view class="filter-toolbar">
        <view class="search">
          <text class="search-icon">
            ⌕
          </text><input v-model="keyword" placeholder="搜索题目、汤面...">
        </view>
        <button class="filter-trigger" :class="{ active: filtersVisible || activeFilterCount }" @click="filtersVisible = !filtersVisible">
          <text>◎ 筛选</text><text v-if="activeFilterCount" class="filter-count">
            {{ activeFilterCount }}
          </text><text class="filter-arrow">
            {{ filtersVisible ? '↑' : '↓' }}
          </text>
        </button>
        <view class="view-toggle">
          <button :class="{ active: viewMode === 'grid' }" aria-label="网格视图" @click="viewMode = 'grid'">
            ⊞
          </button><button :class="{ active: viewMode === 'list' }" aria-label="列表视图" @click="viewMode = 'list'">
            ☰
          </button>
        </view>
      </view>
      <view v-if="filtersVisible" class="filter-panel">
        <view class="filter-group">
          <text class="filter-label">
            难度
          </text>
          <view class="filter-options">
            <button :class="{ active: difficulty === undefined }" @click="changeDifficulty()">
              全部
            </button><button v-for="level in [1, 2, 3, 4, 5]" :key="level" :class="[{ active: difficulty === level }, difficultyClass(level)]" @click="changeDifficulty(level)">
              {{ difficultyLabel(level) }}
            </button>
          </view>
        </view>
        <view class="filter-group risk-filter-group">
          <view class="filter-label-row">
            <text class="filter-label">
              排除风险类型
            </text><text class="filter-hint">
              可多选
            </text>
          </view>
          <view class="filter-options risk-options">
            <button v-for="option in riskTypeOptions" :key="option.value" :class="{ excluded: excludedRiskTypes.includes(option.value) }" @click="toggleExcludedRiskType(option.value)">
              <text class="option-mark">
                {{ excludedRiskTypes.includes(option.value) ? '×' : '+' }}
              </text>{{ option.label }}
            </button>
          </view>
        </view>
        <button v-if="activeFilterCount" class="clear-filter" @click="clearFilters">
          清除筛选
        </button>
      </view>
    </view>
    <view class="result-count">
      {{ keyword || excludedRiskTypes.length ? `当前匹配 ${filtered.length} 个谜题` : `已加载 ${items.length} / ${total} 个谜题` }}
    </view>
    <view v-if="loading && !items.length" class="empty">
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
    <wd-loadmore v-if="items.length" :state="loadmoreState" loading-text="正在加载更多谜题…" finished-text="已经到底了" error-text="加载失败，点击重试" @reload="loadMore" />
  </view>
</template>

<style scoped>
.library-page{min-height:100%;color:var(--foreground);background:var(--background)}.page-head{display:flex;flex-direction:column;padding:32px 36px;border-bottom:1px solid var(--border)}.eyebrow,.result-count,.meta,.foot{font-family:monospace;color:var(--muted-foreground);font-size:12px;letter-spacing:.14em}.eyebrow{letter-spacing:.5em}.title{margin-top:8px;font-family:Georgia,serif;font-size:34px;letter-spacing:.08em}.filters{display:flex;gap:14px;align-items:center;padding:18px 36px;background:var(--card);border-bottom:1px solid var(--border)}.search{display:flex;align-items:center;gap:9px;width:290px;padding:9px 12px;border:1px solid var(--border)}.search input{flex:1;color:var(--foreground);font-size:13px}.difficulty{flex:1;white-space:nowrap}.difficulty button,.view-toggle button{display:inline-flex;margin-right:5px;padding:8px 12px;color:var(--muted-foreground);background:transparent;border:1px solid var(--border);font:12px monospace;white-space:nowrap}.difficulty button.active,.view-toggle button.active{color:var(--foreground);border-color:var(--foreground)}.view-toggle{display:flex}.result-count{padding:12px 36px;border-bottom:1px solid var(--border)}.question-wrap{padding:30px 36px}.question-wrap.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.question-card{padding:22px;border:1px solid var(--border);background:var(--card);transition:.2s}.question-card:active{border-color:var(--accent)}.meta{display:flex;gap:8px;align-items:center;min-width:0}.difficulty-tag,.risk{flex:none;padding:2px 7px;border:1px solid;line-height:1.4;white-space:nowrap}.risk-wrap{position:relative;margin-left:auto;cursor:pointer}.risk{display:block}.risk.safe{color:#16a34a;border-color:#16a34a}.risk.caution{color:#d97706;border-color:#d97706}.risk.restricted{color:#dc2626;border-color:#dc2626}.risk-tip{position:absolute;z-index:20;top:calc(100% + 9px);right:0;display:none;width:280px;padding:12px;border:1px solid var(--border);background:var(--popover,var(--card));box-shadow:0 12px 32px rgba(0,0,0,.35);color:var(--foreground);font:12px/1.6 sans-serif;letter-spacing:0;white-space:normal}.risk-tip::before{position:absolute;top:-5px;right:18px;width:8px;height:8px;background:inherit;border-top:1px solid var(--border);border-left:1px solid var(--border);content:'';transform:rotate(45deg)}.risk-tip-title,.risk-tip-note{display:block}.risk-tip-note{margin-top:5px;color:var(--muted-foreground)}.risk-wrap.open .risk-tip{display:block}.question-title{display:block;margin:16px 0 10px;font:20px Georgia,serif}.surface{display:-webkit-box;min-height:42px;overflow:hidden;color:var(--muted-foreground);font-size:13px;line-height:1.7;-webkit-line-clamp:2;-webkit-box-orient:vertical}.foot{display:flex;justify-content:space-between;margin-top:18px;padding-top:13px;border-top:1px solid var(--border);letter-spacing:0}.list .question-card{display:grid;grid-template-columns:190px minmax(150px,200px) minmax(240px,1fr) minmax(210px,auto) 140px;column-gap:20px;align-items:center;padding:15px 20px}.list .question-title,.list .surface,.list .foot,.list .tags{margin:0;min-width:0}.list .surface{min-height:auto}.list .tags{justify-content:flex-end}.list .foot{border:0;padding:0;white-space:nowrap}.list .risk-wrap{margin-left:0}.empty{display:flex;flex-direction:column;align-items:center;gap:14px;padding:100px 20px;color:var(--muted-foreground);font:13px monospace}.empty-icon{font-size:42px;color:var(--border)}
@media(max-width:900px){.question-wrap.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.list .question-card{display:block}.list .question-title{margin:14px 0}.list .foot{margin-top:14px}}
@media(max-width:600px){.page-head{padding:24px 18px}.title{font-size:29px}.filters{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:stretch;padding:14px 18px}.search{box-sizing:border-box;grid-column:1/-1;width:100%;min-height:44px}.difficulty{box-sizing:border-box;width:100%;min-width:0}.difficulty button,.view-toggle button{box-sizing:border-box;min-width:44px;min-height:44px;padding:8px 12px}.view-toggle{height:44px}.view-toggle button{margin-right:0;margin-left:5px}.result-count{padding:11px 18px}.question-wrap{padding:18px}.question-wrap.grid{grid-template-columns:1fr}.question-card{padding:18px}.risk-tip{width:280px;max-width:calc(100vw - 72px)}}
.library-page{background:transparent}.question-card{background:color-mix(in srgb,var(--card) 94%,transparent);transition:border-color .2s,background-color .2s,transform .2s}.tags{display:flex;flex-wrap:wrap;gap:6px;min-height:23px;margin-top:14px}.tag{flex:none;padding:3px 8px;border:1px solid color-mix(in srgb,var(--foreground) 24%,var(--border));background:color-mix(in srgb,var(--foreground) 4%,transparent);color:var(--foreground);font:10px monospace;line-height:1.4;white-space:nowrap}.foot{gap:14px;align-items:center}.enter{margin-left:auto}@media(hover:hover){.question-card:hover{border-color:var(--foreground);background:var(--secondary);transform:translateY(-2px)}}
.question-wrap.grid .foot{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;column-gap:12px;row-gap:9px}
.question-wrap.grid .foot>text{white-space:nowrap}
.question-wrap.grid .enter{grid-column:1/-1;justify-self:end;margin-left:0}
.difficulty button.easy.active,.difficulty-tag.easy{border-color:#16a34a;background:rgba(22,163,74,.1);color:#16a34a;font-weight:600}.difficulty button.normal.active,.difficulty-tag.normal{border-color:#0891b2;background:rgba(8,145,178,.1);color:#0891b2;font-weight:600}.difficulty button.medium.active,.difficulty-tag.medium{border-color:#d97706;background:rgba(217,119,6,.1);color:#d97706;font-weight:600}.difficulty button.hard.active,.difficulty-tag.hard{border-color:#ea580c;background:rgba(234,88,12,.1);color:#ea580c;font-weight:600}.difficulty button.extreme.active,.difficulty-tag.extreme{border-color:#dc2626;background:rgba(220,38,38,.1);color:#dc2626;font-weight:600}
@media(hover:hover){.risk-wrap:hover .risk-tip{display:block}}
.filter-shell{padding:18px 36px;background:var(--card);border-bottom:1px solid var(--border)}
.filter-toolbar{display:flex;gap:10px;align-items:stretch}.search{box-sizing:border-box;flex:1;width:auto;min-width:180px;padding:0 14px;background:var(--background)}.search input{height:42px}.search-icon{color:var(--muted-foreground)}
.filter-trigger,.filter-options button,.clear-filter{box-sizing:border-box;display:inline-flex;height:42px;margin:0;padding:0 14px;border:1px solid var(--border);border-radius:0;align-items:center;justify-content:center;color:var(--muted-foreground);background:transparent;font:12px monospace;white-space:nowrap}.filter-trigger::after,.filter-options button::after,.clear-filter::after{border:0}.filter-trigger.active{border-color:var(--foreground);color:var(--foreground);background:color-mix(in srgb,var(--foreground) 5%,transparent)}
.filter-count{display:inline-flex;width:19px;height:19px;margin-left:8px;border-radius:50%;align-items:center;justify-content:center;background:var(--foreground);color:var(--background);font-size:10px}.filter-arrow{margin-left:10px;color:var(--muted-foreground)}
.view-toggle{margin-left:4px}.view-toggle button{box-sizing:border-box;width:42px;height:42px;margin:0;padding:0;align-items:center;justify-content:center}.view-toggle button+button{margin-left:-1px}
.filter-panel{position:relative;display:grid;grid-template-columns:minmax(280px,.8fr) minmax(400px,1.2fr);margin-top:14px;padding:18px;border:1px solid var(--border);gap:18px 28px;background:color-mix(in srgb,var(--background) 62%,var(--card))}.filter-group{min-width:0}.filter-label-row{display:flex;align-items:center;gap:8px}.filter-label{display:block;margin-bottom:10px;color:var(--foreground);font:11px monospace;letter-spacing:.14em}.filter-hint{margin-bottom:10px;color:var(--muted-foreground);font-size:10px}.filter-options{display:flex;flex-wrap:wrap;gap:7px}.filter-options button{height:34px;padding:0 12px}.filter-options button.active{border-color:var(--foreground);color:var(--foreground);background:color-mix(in srgb,var(--foreground) 6%,transparent)}.risk-options button.excluded{border-color:#dc2626;color:#dc2626;background:rgba(220,38,38,.08)}.option-mark{display:inline-block;width:12px;margin-right:4px;font-size:14px}.clear-filter{position:absolute;top:12px;right:12px;height:28px;padding:0 8px;border:0;text-decoration:underline}
.filter-options button.easy.active{border-color:#16a34a;background:rgba(22,163,74,.1);color:#16a34a;font-weight:600}.filter-options button.normal.active{border-color:#0891b2;background:rgba(8,145,178,.1);color:#0891b2;font-weight:600}.filter-options button.medium.active{border-color:#d97706;background:rgba(217,119,6,.1);color:#d97706;font-weight:600}.filter-options button.hard.active{border-color:#ea580c;background:rgba(234,88,12,.1);color:#ea580c;font-weight:600}.filter-options button.extreme.active{border-color:#dc2626;background:rgba(220,38,38,.1);color:#dc2626;font-weight:600}
@media(max-width:900px){.filter-panel{grid-template-columns:1fr}}
@media(max-width:600px){.filter-shell{padding:14px 18px}.filter-toolbar{display:grid;grid-template-columns:minmax(0,1fr) auto}.filter-toolbar .search{grid-column:1/-1;width:100%;min-height:44px}.filter-trigger{justify-content:flex-start}.filter-arrow{margin-left:auto}.view-toggle{height:42px;margin-left:0}.filter-panel{margin-top:10px;padding:16px 14px;gap:20px}.filter-options{gap:8px}.filter-options button{min-height:38px}.clear-filter{position:static;width:max-content;height:32px;padding:0}}
</style>
