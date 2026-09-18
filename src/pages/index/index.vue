<script setup lang="ts">
import type { HomeStats, PublicQuestion, PublicTag } from '@/types/game'
import { ensureAnonymousSession, homeApi, questionApi, tagApi } from '@/api/turtle'
import { usePlayerStore } from '@/store/playerStore'
import { formatCount } from '@/utils'
import { supportsPublicRooms } from '@/utils/platform'
import { emptyNetworkUrl, emptyNoneUrl, questionCoverUrl } from '@/utils/questionCover'
import { openQuestionDetail } from '@/utils/questionRoute'

definePage({ name: 'home', layout: 'tabbar', style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } } })

const router = useRouter()
const player = usePlayerStore()
const featured = ref<PublicQuestion[]>([])
const stats = ref<HomeStats>({ question_count: 0, today_online: 0, success_rate: 0, average_duration_seconds: null })
const loading = ref(true)
const loadError = ref(false)
const randomLoading = ref(false)

interface HomeCategory { label: string, tagId: number | undefined }

const ALL_CATEGORY: HomeCategory = { label: '全部', tagId: undefined }
/** 接口失败时的兜底，tagId 与后端 turtle_tags 一致 */
const FALLBACK_TAGS: PublicTag[] = [
  { id: 20, name: '本格' },
  { id: 4, name: '逻辑推理' },
  { id: 3, name: '悬疑' },
  { id: 13, name: '超自然' },
  { id: 5, name: '犯罪案件' },
  { id: 8, name: '校园' },
  { id: 9, name: '家庭' },
  { id: 16, name: '短篇' },
]
const COLLAPSED_CATEGORY_COUNT = 5
const categories = ref<HomeCategory[]>([ALL_CATEGORY, ...FALLBACK_TAGS.map(tag => ({ label: tag.name, tagId: tag.id }))])
const activeTagId = ref<number | undefined>(undefined)
const categoryPage = ref(1)
const categoriesExpanded = ref(false)

const activeCategory = computed<HomeCategory>(() => categories.value.find(cat => cat.tagId === activeTagId.value) || ALL_CATEGORY)
const visibleCategories = computed(() => categoriesExpanded.value ? categories.value : categories.value.slice(0, COLLAPSED_CATEGORY_COUNT))
const showCategoryToggle = computed(() => categories.value.length > COLLAPSED_CATEGORY_COUNT)

/** 几乎每道题都带的基础标签，优先展示更有区分度的题材标签 */
const GENERIC_TAG_NAMES = new Set(['悬疑', '逻辑推理'])

function categoryTagLabel(item: PublicQuestion) {
  const tags = item.tags || []
  const activeId = activeCategory.value.tagId
  if (activeId !== undefined) {
    const matched = tags.find(tag => tag.id === activeId)
    if (matched)
      return matched.name
    return activeCategory.value.label
  }
  return tags.find(tag => !GENERIC_TAG_NAMES.has(tag.name))?.name || tags[0]?.name || '悬疑'
}

function toCategories(tags: PublicTag[]): HomeCategory[] {
  return [ALL_CATEGORY, ...tags.map(tag => ({ label: tag.name, tagId: tag.id }))]
}

async function loadCategories() {
  try {
    const result = await tagApi.list()
    if (result.items?.length)
      categories.value = toCategories(result.items)
  }
  catch {
    // 保留兜底分类，避免首页筛选整块不可用
  }
}

const difficulty = (level: number) => ['未知', '简单', '普通', '中等', '困难', '极难'][level] || '未知'
function difficultyClass(level: number) {
  if (level <= 2)
    return 'easy'
  if (level === 3)
    return 'mid'
  return 'hard'
}
const stars = (level: number) => '★'.repeat(Math.min(5, Math.max(1, level))) + '☆'.repeat(5 - Math.min(5, Math.max(1, level)))

function openQuestion(id: string) {
  void openQuestionDetail({ id })
}

async function loadStats() {
  try {
    stats.value = await homeApi.stats()
  }
  catch {}
}

async function loadHome() {
  loading.value = true
  loadError.value = false
  try {
    await player.restore()
    await ensureAnonymousSession()
    const tagId = activeCategory.value.tagId
    const page = categoryPage.value
    // 与首页网格对齐：PC 一行 4 个 → 8（两整行）；移动端一行 2 个 → 4
    // 单次声明，避免 uni-pages 解析 #ifdef/#ifndef 时重复声明
    let mobileLayout = true
    // #ifdef H5
    mobileLayout = typeof window !== 'undefined' && window.matchMedia('(max-width: 767px)').matches
    // #endif
    const listPageSize = mobileLayout ? 4 : 8

    if (tagId !== undefined) {
      let result = await questionApi.list({ tag_id: tagId, page, page_size: listPageSize })
      if (!result.items.length && page > 1) {
        categoryPage.value = 1
        result = await questionApi.list({ tag_id: tagId, page: 1, page_size: listPageSize })
      }
      featured.value = result.items
      return
    }

    if (page > 1) {
      let result = await questionApi.list({ page, page_size: listPageSize })
      if (!result.items.length) {
        categoryPage.value = 1
        result = await questionApi.list({ page: 1, page_size: listPageSize })
      }
      featured.value = result.items
      return
    }

    const [featuredResult, latestResult] = await Promise.all([
      questionApi.list({ featured: 1, page_size: listPageSize }),
      questionApi.list({ page_size: listPageSize }),
    ])
    featured.value = [...featuredResult.items, ...latestResult.items]
      .filter((question, index, questions) => questions.findIndex(item => item.id === question.id) === index)
      .slice(0, listPageSize)
  }
  catch {
    loadError.value = true
  }
  finally {
    loading.value = false
  }
}

function selectCategory(cat: HomeCategory) {
  if (activeTagId.value === cat.tagId)
    return
  activeTagId.value = cat.tagId
  categoryPage.value = 1
  void loadHome()
}

function toggleCategoriesExpanded() {
  categoriesExpanded.value = !categoriesExpanded.value
}

function refreshFeatured() {
  categoryPage.value += 1
  void loadHome()
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

function startPlay() {
  router.push({ name: 'questions' })
}

function openPublicRooms() {
  if (!supportsPublicRooms)
    return
  if (player.user)
    router.push({ name: 'public-rooms' })
  else router.push({ name: 'player-login', query: { redirect: '/pages/public-rooms/index' } })
}

onMounted(() => {
  void loadCategories()
  void loadHome()
  void loadStats()
})
</script>

<template>
  <view class="home-page">
    <!-- Hero -->
    <section class="hero">
      <!-- 换回水下灯塔（宽幅，顶部水波） -->
      <image class="hero-bg" src="/static/hgt/bg/bg_deep_ocean_hero.jpg" mode="aspectFill" />
      <image class="hero-bubbles" src="/static/hgt/ui/bubbles.png" mode="aspectFit" />
      <view class="hero-veil" />
      <view class="hero-inner">
        <text class="hero-kicker">
          TURTLE SOUP · LATERAL THINKING
        </text>
        <text class="hero-title">
          谜题沉在水下
        </text>
        <text class="hero-title hero-title-2">
          而你，正慢慢接近真相。
        </text>
        <text class="hero-copy">
          一碗看似寻常的汤，可能藏着意想不到的故事。<br>
          向下追问，直到接近真相。
        </text>
        <view class="hero-actions">
          <button class="btn-primary" @click="startPlay">
            开始推理 →
          </button>
          <button class="btn-ghost" :loading="randomLoading" @click="playRandom">
            随机一题
          </button>
        </view>
        <view class="hero-stats">
          <view class="hero-stat">
            <text class="hero-stat-value">
              {{ formatCount(stats.question_count) || '1000+' }}
            </text>
            <text class="hero-stat-label">
              精选题目
            </text>
          </view>
          <view class="hero-stat">
            <text class="hero-stat-value">
              {{ formatCount(stats.today_online) || '12.3w' }}
            </text>
            <text class="hero-stat-label">
              推理玩家
            </text>
          </view>
          <view class="hero-stat">
            <text class="hero-stat-value">
              4.8★
            </text>
            <text class="hero-stat-label">
              户主评分
            </text>
          </view>
        </view>
      </view>
    </section>

    <!-- Categories + Featured -->
    <section class="featured">
      <view class="section-head">
        <text class="section-title">
          热门推荐
        </text>
        <button class="btn-refresh" @click="refreshFeatured">
          换一批
        </button>
      </view>

      <view class="cat-block">
        <view v-if="categoriesExpanded" class="cat-row cat-row-wrap">
          <view
            v-for="cat in categories"
            :key="String(cat.tagId)"
            class="cat-chip"
            :class="{ active: activeCategory.tagId === cat.tagId }"
            @click="selectCategory(cat)"
          >
            {{ cat.label }}
          </view>
          <view class="cat-chip cat-more" @click="toggleCategoriesExpanded">
            收起
          </view>
        </view>
        <scroll-view
          v-else-if="showCategoryToggle"
          class="cat-scroll-view"
          scroll-x
          :show-scrollbar="false"
        >
          <view class="cat-row">
            <view
              v-for="cat in visibleCategories"
              :key="String(cat.tagId)"
              class="cat-chip"
              :class="{ active: activeCategory.tagId === cat.tagId }"
              @click="selectCategory(cat)"
            >
              {{ cat.label }}
            </view>
            <view class="cat-chip cat-more" @click="toggleCategoriesExpanded">
              展开
            </view>
          </view>
        </scroll-view>
        <view v-else class="cat-row">
          <view
            v-for="cat in categories"
            :key="String(cat.tagId)"
            class="cat-chip"
            :class="{ active: activeCategory.tagId === cat.tagId }"
            @click="selectCategory(cat)"
          >
            {{ cat.label }}
          </view>
        </view>
      </view>

      <view v-if="loading" class="content-state">
        <image class="empty-img" src="/static/hgt/empty/empty_loading.png" mode="aspectFit" />
        <text>正在潜入题库…</text>
      </view>
      <view v-else-if="loadError" class="content-state error-state">
        <image class="empty-img" :src="emptyNetworkUrl" mode="aspectFit" />
        <text>谜题暂时没有浮上来</text>
        <button class="btn-ghost" @click="loadHome">
          重新加载
        </button>
      </view>
      <view v-else-if="!featured.length" class="content-state">
        <image class="empty-img" :src="emptyNoneUrl" mode="aspectFit" />
        <text>暂无谜题</text>
      </view>
      <view v-else class="puzzle-grid">
        <view
          v-for="item in featured"
          :key="item.id"
          class="puzzle-card"
          @click="openQuestion(item.id)"
        >
          <view class="card-cover">
            <image class="cover-img" :src="questionCoverUrl(item)" mode="aspectFill" />
            <view class="cover-fallback" />
            <view class="card-tags">
              <text class="tag tag-cat">
                {{ categoryTagLabel(item) }}
              </text>
              <text class="tag tag-diff" :class="difficultyClass(item.difficulty)">
                {{ difficulty(item.difficulty) }}
              </text>
            </view>
          </view>
          <view class="card-body">
            <text class="card-title">
              {{ item.title }}
            </text>
            <text class="card-surface">
              {{ item.surface }}
            </text>
            <view class="card-meta">
              <text class="stars" :class="difficultyClass(item.difficulty)">
                {{ stars(item.difficulty) }}
              </text>
              <text class="meta-item">
                约 {{ 8 + item.difficulty * 3 }} 分钟
              </text>
              <text class="meta-item">
                {{ formatCount(item.play_count) }}人玩过
              </text>
            </view>
          </view>
        </view>
      </view>
    </section>

    <!-- How to -->
    <section class="how">
      <text class="section-title">
        三步还原真相
      </text>
      <view class="steps">
        <view class="step">
          <text class="step-no">
            01
          </text>
          <text class="step-title">
            阅读汤面
          </text>
          <text class="step-copy">
            从一段反常的故事开头寻找线索。
          </text>
        </view>
        <view class="step">
          <text class="step-no">
            02
          </text>
          <text class="step-title">
            不断提问
          </text>
          <text class="step-copy">
            用「是 / 否 / 无关」缩小真相范围。
          </text>
        </view>
        <view class="step">
          <text class="step-no">
            03
          </text>
          <text class="step-title">
            提交推理
          </text>
          <text class="step-copy">
            串联线索，说出完整故事真相。
          </text>
        </view>
      </view>
      <view v-if="supportsPublicRooms" class="multi-entry" @click="openPublicRooms">
        <text class="multi-title">
          多人房间
        </text>
        <text class="multi-copy">
          和朋友一起推理同一碗汤
        </text>
        <text class="multi-arrow">
          →
        </text>
      </view>
    </section>
  </view>
</template>

<style scoped>
.home-page {
  min-height: 100%;
  background: var(--hgt-bg);
  color: var(--hgt-text);
}

/* ===== Hero ===== */
.hero {
  position: relative;
  display: flex;
  box-sizing: border-box;
  /* 尽量拉高 hero；下方为上图下文的四列卡片列表 */
  width: 100%;
  min-height: max(52vh, 420px);
  height: calc(100vh - var(--hgt-header-h) - 420px);
  max-height: min(72vh, 780px);
  padding: 48px 48px 40px;
  align-items: center;
  overflow: hidden;
}
/* 底边与热门推荐衔接，避免生硬切边 */
.hero::after {
  content: '';
  position: absolute;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 1;
  height: 72px;
  background: linear-gradient(180deg, rgba(7, 20, 24, 0) 0%, var(--hgt-bg) 100%);
  pointer-events: none;
}
.hero-bg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  filter: var(--hgt-atmo-filter);
}
/* 水下灯塔：顶部水波 + 右侧灯塔 */
.hero-bg :deep(img) {
  object-fit: cover !important;
  object-position: 55% 12% !important;
}
.hero-bubbles {
  position: absolute;
  right: 4%;
  bottom: 8%;
  z-index: 0;
  width: min(360px, 42vw);
  height: auto;
  opacity: 0.18;
  pointer-events: none;
}

/* 与其它页共用的氛围压暗，保证跨页亮度一致 */
.hero-veil {
  position: absolute;
  inset: 0;
  z-index: 0;
  background: var(--hgt-atmo-veil);
}
.content-state {
  display: flex;
  min-height: 120px;
  padding: 16px 8px;
  gap: 8px;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  color: var(--hgt-text-2);
  font-size: 13px;
  text-align: center;
}
.content-state .empty-img {
  width: min(160px, 50vw);
  height: 100px;
  border-radius: var(--hgt-radius-lg);
  filter: drop-shadow(0 8px 24px rgba(4, 12, 14, 0.45));
}
.hero-inner {
  position: relative;
  z-index: 1;
  display: flex;
  width: min(640px, 100%);
  flex-direction: column;
}
.hero-kicker {
  margin-bottom: 16px;
  color: var(--hgt-brand);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
  letter-spacing: 0.28em;
}
.hero-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 36px;
  font-weight: 600;
  line-height: 1.25;
  letter-spacing: 0.04em;
}
.hero-title-2 {
  margin-top: 4px;
  color: var(--hgt-text-2);
}
.hero-copy {
  margin: 16px 0 22px;
  color: var(--hgt-text-2);
  font-size: 15px;
  line-height: 1.75;
}
.hero-actions {
  display: flex;
  margin-bottom: 28px;
  gap: 12px;
}
.btn-primary {
  display: flex;
  height: 48px;
  margin: 0;
  padding: 0 28px;
  border: 0;
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0.08em;
  transition: filter var(--hgt-dur-fast), transform var(--hgt-dur-fast);
}
.btn-primary:hover {
  filter: brightness(1.06);
  transform: translateY(-1px);
}
.btn-primary::after {
  border: 0;
}
.btn-ghost {
  display: flex;
  height: 48px;
  margin: 0;
  padding: 0 22px;
  border: 1px solid var(--hgt-border-soft);
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  background: rgba(15, 42, 45, 0.55);
  color: var(--hgt-text);
  font-size: 14px;
  transition: border-color var(--hgt-dur-fast), background var(--hgt-dur-fast);
}
.btn-ghost:hover {
  border-color: var(--hgt-brand);
  background: var(--hgt-brand-soft);
}
.btn-ghost::after {
  border: 0;
}
.hero-stats {
  display: flex;
  gap: 36px;
}
.hero-stat {
  display: flex;
  gap: 6px;
  flex-direction: column;
}
.hero-stat-value {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 24px;
  font-weight: 600;
}
.hero-stat-label {
  color: var(--hgt-text-3);
  font-size: 12px;
  letter-spacing: 0.12em;
}

/* ===== Featured ===== */
.featured {
  padding: 16px 48px 20px;
}
.section-head {
  display: flex;
  margin-bottom: 12px;
  align-items: center;
  justify-content: space-between;
}
.section-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 20px;
  font-weight: 600;
  letter-spacing: 0.06em;
}
.btn-refresh {
  height: 32px;
  margin: 0;
  padding: 0 12px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 12px;
}
.btn-refresh::after {
  border: 0;
}
.cat-block {
  width: 100%;
  margin-bottom: 12px;
}
.cat-scroll {
  width: 100%;
}
.cat-scroll-view {
  width: 100%;
  white-space: nowrap;
}
.cat-row {
  display: flex;
  flex-wrap: nowrap;
  padding-bottom: 2px;
  gap: 6px;
  align-items: center;
}
.cat-row-wrap {
  flex-wrap: wrap;
  row-gap: 8px;
}
.cat-chip {
  display: inline-flex;
  height: 30px;
  padding: 0 12px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-full);
  align-items: center;
  background: transparent;
  color: var(--hgt-text-2);
  font-size: 12px;
  white-space: nowrap;
  transition: all var(--hgt-dur-fast);
}
.cat-chip.active {
  border-color: var(--hgt-brand);
  background: var(--hgt-brand-soft);
  color: var(--hgt-brand);
}
.cat-more {
  border-style: dashed;
  cursor: pointer;
}
.empty-img {
  width: 96px;
  height: 96px;
  opacity: 0.9;
  border-radius: var(--hgt-radius-lg);
  filter: drop-shadow(0 6px 18px rgba(4, 12, 14, 0.35));
}
.puzzle-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 14px;
}
.puzzle-card {
  display: flex;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  flex-direction: column;
  overflow: hidden;
  background: var(--hgt-card);
  box-shadow: var(--hgt-shadow-sm);
  cursor: pointer;
  transition: transform var(--hgt-dur-fast), border-color var(--hgt-dur-fast), box-shadow var(--hgt-dur-fast);
}
.puzzle-card:hover {
  border-color: var(--hgt-border-soft);
  box-shadow: var(--hgt-shadow-md);
  transform: translateY(-2px);
}
.card-cover {
  position: relative;
  aspect-ratio: 16 / 10;
  overflow: hidden;
  background: var(--hgt-card-2);
}
.cover-img {
  width: 100%;
  height: 100%;
}
.cover-fallback {
  position: absolute;
  inset: 0;
  background: linear-gradient(145deg, #16383c, #0c2027);
  opacity: 0.35;
  pointer-events: none;
}
.card-tags {
  position: absolute;
  top: 8px;
  left: 8px;
  display: flex;
  gap: 4px;
}
.tag {
  padding: 2px 6px;
  border-radius: var(--hgt-radius-xs);
  font-size: 10px;
  line-height: 1.4;
}
.tag-cat {
  background: rgba(7, 20, 24, 0.75);
  color: var(--hgt-text);
}
.tag-diff.easy {
  background: rgba(94, 135, 135, 0.9);
  color: #fff;
}
.tag-diff.mid {
  background: rgba(196, 154, 85, 0.92);
  color: #1a1208;
}
.tag-diff.hard {
  background: rgba(201, 74, 85, 0.92);
  color: #fff;
}
.card-body {
  display: flex;
  min-width: 0;
  padding: 12px 12px 14px;
  gap: 6px;
  flex-direction: column;
}
.card-title {
  display: -webkit-box;
  overflow: hidden;
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 15px;
  font-weight: 600;
  line-height: 1.35;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}
.card-surface {
  display: -webkit-box;
  overflow: hidden;
  color: var(--hgt-text-2);
  font-size: 12px;
  line-height: 1.45;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}
.card-meta {
  display: flex;
  margin-top: 2px;
  align-items: center;
  gap: 8px;
  color: var(--hgt-text-3);
  font-size: 11px;
  white-space: nowrap;
}
.stars.easy {
  color: var(--hgt-success-text);
}
.stars.mid {
  color: var(--hgt-warning);
}
.stars.hard {
  color: var(--hgt-danger);
}

/* ===== How ===== */
.how {
  padding: 32px 48px 64px;
}
.steps {
  display: grid;
  margin-top: 24px;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
}
.step {
  display: flex;
  padding: 22px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  gap: 10px;
  flex-direction: column;
  background: var(--hgt-card);
}
.step-no {
  color: var(--hgt-brand);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
  letter-spacing: 0.16em;
}
.step-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 18px;
  font-weight: 600;
}
.step-copy {
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1.6;
}
.multi-entry {
  display: flex;
  margin-top: 20px;
  padding: 20px 24px;
  border: 1px dashed var(--hgt-border-soft);
  border-radius: var(--hgt-radius-md);
  align-items: center;
  gap: 16px;
  background: var(--hgt-brand-soft);
  cursor: pointer;
}
.multi-title {
  color: var(--hgt-text);
  font-size: 15px;
  font-weight: 600;
}
.multi-copy {
  flex: 1;
  color: var(--hgt-text-2);
  font-size: 13px;
}
.multi-arrow {
  color: var(--hgt-brand);
  font-size: 18px;
}

/* ===== Responsive ===== */
@media (max-width: 1199px) {
  .puzzle-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 767px) {
  .hero {
    min-height: auto;
    height: auto;
    max-height: none;
    padding: 36px 20px 32px;
    align-items: flex-start;
  }
  /* 小屏文案占满宽度：用同一套 atmo，仅略加强底部 */
  .hero-veil {
    background:
      linear-gradient(90deg,
        rgba(7, 20, 24, 0.64) 0%,
        rgba(7, 20, 24, 0.42) 48%,
        rgba(7, 20, 24, 0.24) 100%),
      linear-gradient(180deg,
        rgba(7, 20, 24, 0.08) 0%,
        rgba(7, 20, 24, 0.22) 55%,
        rgba(7, 20, 24, 0.40) 100%);
  }
  .hero-title {
    font-size: 28px;
  }
  .hero-copy {
    margin: 14px 0 22px;
    font-size: 14px;
  }
  .hero-actions {
    width: 100%;
    margin-bottom: 28px;
  }
  .btn-primary,
  .btn-ghost {
    flex: 1;
    padding: 0 12px;
  }
  .hero-stats {
    width: 100%;
    gap: 0;
    justify-content: space-between;
  }
  .hero-stat-value {
    font-size: 20px;
  }
  .featured,
  .how {
    padding-right: 16px;
    padding-left: 16px;
  }
  .section-title {
    font-size: 18px;
  }
  .puzzle-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }
  .card-cover {
    aspect-ratio: 16 / 10;
  }
  .card-body {
    padding: 10px;
    gap: 4px;
  }
  .card-title {
    font-size: 13px;
  }
  .card-surface {
    font-size: 11px;
    -webkit-line-clamp: 2;
  }
  .card-meta {
    gap: 6px;
    font-size: 10px;
    flex-wrap: wrap;
    white-space: normal;
  }
  .steps {
    grid-template-columns: 1fr;
  }
}
</style>
