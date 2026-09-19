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
  { id: 17, name: '清汤' },
  { id: 18, name: '红汤' },
  { id: 19, name: '黑汤' },
  { id: 3, name: '悬疑' },
  { id: 4, name: '逻辑推理' },
  { id: 13, name: '超自然' },
  { id: 5, name: '犯罪案件' },
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

function difficulty(level: number) {
  return ['未知', '简单', '普通', '中等', '困难', '极难'][level] || '未知'
}

function difficultyClass(level: number) {
  if (level <= 2)
    return 'easy'
  if (level === 3)
    return 'mid'
  return 'hard'
}

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
    <!-- Hero · 水墨 -->
    <section class="hero">
      <image
        class="hero-bg"
        src="/static/hgt/ink/hero_ink_landscape.png"
        mode="aspectFill"
      />
      <view class="hero-veil" />
      <view class="hero-inner">
        <view class="hero-kicker-row">
          <text class="hero-kicker-en">
            MOYUU
          </text>
          <text class="hero-kicker-line" />
          <text class="hero-kicker-sub">
            TURTLE SOUP
          </text>
        </view>
        <text class="hero-title">
          雾里有故事，
        </text>
        <view class="hero-title-row">
          <text>
            你来找
          </text>
          <text class="hero-accent">
            真相
          </text>
          <text>
            。
          </text>
        </view>
        <text class="hero-copy">
          每一个看似寻常的片段，<br>
          都可能通向另一个世界。
        </text>
        <view class="hero-actions">
          <button class="btn-primary" @click="startPlay">
            开始探索 →
          </button>
          <button class="btn-ghost" :loading="randomLoading" @click="playRandom">
            <text class="btn-play-dot" />
            随机一题
          </button>
        </view>
        <view class="hero-stats">
          <view class="hero-stat">
            <text class="hero-stat-value">
              {{ stats.question_count ? formatCount(stats.question_count) : '1000+' }}
            </text>
            <text class="hero-stat-label">
              精选题目
            </text>
          </view>
          <view class="hero-stat">
            <text class="hero-stat-value">
              {{ stats.today_online ? formatCount(stats.today_online) : '在线' }}
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
              玩家评分
            </text>
          </view>
        </view>
      </view>
      <text class="hero-seal" aria-hidden="true">
        海龟汤
      </text>
    </section>

    <!-- Categories -->
    <section class="cat-band">
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
    </section>

    <!-- Featured -->
    <section class="featured">
      <view class="section-head">
        <view class="section-title-wrap">
          <text class="section-title">
            精选题库
          </text>
          <text class="section-en">
            CURATED
          </text>
        </view>
        <button class="btn-link" @click="refreshFeatured">
          换一批 ›
        </button>
      </view>

      <view v-if="loading" class="content-state">
        <HgtLoading text="正在铺开题卷…" size="md" />
      </view>
      <view v-else-if="loadError" class="content-state error-state">
        <image class="empty-img" :src="emptyNetworkUrl" mode="aspectFit" />
        <text>谜题暂时没有浮上来</text>
        <button class="btn-ghost btn-ghost-sm" @click="loadHome">
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
            <view class="cover-wash" />
          </view>
          <view class="card-body">
            <text class="card-title">
              {{ item.title }}
            </text>
            <view class="card-tags">
              <text class="tag tag-cat">
                {{ categoryTagLabel(item) }}
              </text>
              <text class="tag tag-diff" :class="difficultyClass(item.difficulty)">
                {{ difficulty(item.difficulty) }}
              </text>
            </view>
            <text class="card-surface">
              {{ item.surface }}
            </text>
            <view class="card-meta">
              <text class="meta-item">
                ♥ {{ formatCount(item.play_count) }}
              </text>
              <text class="meta-item">
                约 {{ 8 + item.difficulty * 3 }} 分钟
              </text>
            </view>
          </view>
        </view>
      </view>
    </section>

    <!-- How to -->
    <section class="how">
      <view class="section-title-wrap">
        <text class="section-title">
          三步还原真相
        </text>
        <text class="section-en">
          HOW IT WORKS
        </text>
      </view>
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
        <view class="multi-copy-wrap">
          <text class="multi-title">
            多人房间
          </text>
          <text class="multi-copy">
            和朋友一起推理同一碗汤
          </text>
        </view>
        <text class="multi-arrow">
          →
        </text>
      </view>
    </section>

    <footer class="site-footer">
      <view class="footer-brand">
        <text class="hgt-en footer-en">
          MOYUU
        </text>
        <text class="hgt-display footer-zh">
          海龟汤
        </text>
        <text class="footer-tag">
          每一个故事，都是一个小小的世界。
        </text>
      </view>
      <text class="footer-note">
        谜题在深处，等你浮上水面。
      </text>
    </footer>
  </view>
</template>

<style scoped>
.home-page {
  min-height: 100%;
  background: var(--hgt-bg);
  color: var(--hgt-text);
}

/* ===== Hero · 满幅水墨背景（对齐图1） ===== */
.hero {
  position: relative;
  display: flex;
  box-sizing: border-box;
  width: 100%;
  min-height: max(52vh, 440px);
  height: calc(100vh - var(--hgt-header-h) - 240px);
  max-height: min(72vh, 780px);
  padding: 48px 48px 40px;
  align-items: center;
  overflow: hidden;
  background: var(--hgt-bg);
}
.hero::after {
  content: '';
  position: absolute;
  right: 0;
  bottom: 0;
  left: 0;
  z-index: 1;
  height: 72px;
  background: linear-gradient(180deg, rgba(248, 248, 247, 0) 0%, var(--hgt-bg) 100%);
  pointer-events: none;
}
.hero-bg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}
.hero-bg :deep(img) {
  width: 100%;
  height: 100%;
  object-fit: cover !important;
  /* 右侧保留枫叶/猫/亭，左侧留给文案 */
  object-position: 62% center !important;
}
.hero-veil {
  position: absolute;
  inset: 0;
  z-index: 1;
  background:
    linear-gradient(90deg,
      rgba(248, 248, 247, 0.92) 0%,
      rgba(248, 248, 247, 0.72) 28%,
      rgba(248, 248, 247, 0.28) 48%,
      rgba(248, 248, 247, 0.06) 72%,
      rgba(248, 248, 247, 0) 100%),
    linear-gradient(180deg,
      rgba(248, 248, 247, 0.12) 0%,
      rgba(248, 248, 247, 0) 40%,
      rgba(248, 248, 247, 0.35) 100%);
  pointer-events: none;
}
.hero-inner {
  position: relative;
  z-index: 1;
  display: flex;
  width: min(480px, 46%);
  flex-direction: column;
}
.hero-kicker-row {
  display: flex;
  margin-bottom: 18px;
  align-items: center;
  gap: 12px;
}
.hero-kicker-en {
  color: var(--hgt-brand);
  font-family: var(--hgt-font-en);
  font-size: 14px;
  letter-spacing: 0.28em;
}
.hero-kicker-line {
  width: 36px;
  height: 1px;
  background: var(--hgt-brand);
  opacity: 0.55;
}
.hero-kicker-sub {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
  letter-spacing: 0.22em;
}
.hero-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 40px;
  font-weight: 600;
  line-height: 1.25;
  letter-spacing: 0.06em;
}
.hero-title-row {
  display: flex;
  margin-top: 2px;
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 40px;
  font-weight: 600;
  line-height: 1.25;
  letter-spacing: 0.06em;
  flex-wrap: wrap;
}
.hero-accent {
  color: var(--hgt-brand);
}
.hero-copy {
  margin: 18px 0 24px;
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-display);
  font-size: 15px;
  line-height: 1.85;
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
  border-radius: 6px;
  align-items: center;
  justify-content: center;
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-family: var(--hgt-font-display);
  font-size: 15px;
  font-weight: 600;
  letter-spacing: 0.12em;
  transition: filter var(--hgt-dur-fast), transform var(--hgt-dur-fast);
}
.btn-primary:hover {
  filter: brightness(1.05);
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
  border: 1px solid var(--hgt-border);
  border-radius: 6px;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: rgba(255, 255, 255, 0.7);
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 14px;
  letter-spacing: 0.08em;
  transition: border-color var(--hgt-dur-fast), background var(--hgt-dur-fast);
}
.btn-ghost:hover {
  border-color: var(--hgt-brand);
  background: var(--hgt-brand-soft);
}
.btn-ghost::after {
  border: 0;
}
.btn-ghost-sm {
  height: 36px;
  padding: 0 14px;
  font-size: 12px;
}
.btn-play-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--hgt-brand);
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
  font-size: 22px;
  font-weight: 600;
}
.hero-stat-label {
  color: var(--hgt-text-3);
  font-size: 12px;
  letter-spacing: 0.12em;
}
.hero-seal {
  position: absolute;
  z-index: 2;
  left: 28px;
  bottom: 88px;
  padding: 8px 6px;
  border: 2px solid var(--hgt-accent);
  border-radius: 2px;
  color: var(--hgt-accent);
  font-family: var(--hgt-font-display);
  font-size: 12px;
  letter-spacing: 0.2em;
  writing-mode: vertical-rl;
  opacity: 0.9;
  transform: rotate(-6deg);
}

/* ===== Categories ===== */
.cat-band {
  position: relative;
  z-index: 2;
  margin: -8px 48px 8px;
  padding: 10px 12px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-md);
  background: color-mix(in srgb, var(--hgt-card) 92%, transparent);
  box-shadow: var(--hgt-shadow-sm);
  backdrop-filter: blur(8px);
}
.cat-scroll-view {
  width: 100%;
  white-space: nowrap;
}
.cat-row {
  display: flex;
  flex-wrap: nowrap;
  gap: 6px;
  align-items: center;
}
.cat-row-wrap {
  flex-wrap: wrap;
  row-gap: 8px;
}
.cat-chip {
  display: inline-flex;
  height: 34px;
  padding: 0 14px;
  border: 1px solid transparent;
  border-radius: 999px;
  align-items: center;
  background: var(--hgt-card-2);
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-display);
  font-size: 13px;
  white-space: nowrap;
  transition: all var(--hgt-dur-fast);
}
.cat-chip.active {
  border-color: var(--hgt-brand);
  background: var(--hgt-brand-soft);
  color: var(--hgt-brand-deep);
}
.cat-more {
  border-style: dashed;
  border-color: var(--hgt-border);
  background: transparent;
  cursor: pointer;
}

/* ===== Featured ===== */
.featured {
  padding: 20px 48px 8px;
}
.section-head {
  display: flex;
  margin-bottom: 14px;
  align-items: flex-end;
  justify-content: space-between;
}
.section-title-wrap {
  display: flex;
  align-items: baseline;
  gap: 10px;
}
.section-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 22px;
  font-weight: 600;
  letter-spacing: 0.08em;
}
.section-en {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 10px;
  letter-spacing: 0.18em;
}
.btn-link {
  height: 32px;
  margin: 0;
  padding: 0 4px;
  border: 0;
  background: transparent;
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-display);
  font-size: 13px;
}
.btn-link:hover {
  color: var(--hgt-brand);
}
.btn-link::after {
  border: 0;
}

.content-state {
  display: flex;
  min-height: 220px;
  padding: 24px 8px;
  gap: 8px;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  color: var(--hgt-text-2);
  font-size: 13px;
  text-align: center;
}
.empty-img {
  width: min(200px, 56vw);
  height: 140px;
  border-radius: var(--hgt-radius-lg);
  background: var(--hgt-card);
}

.puzzle-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 16px;
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
  border-color: color-mix(in srgb, var(--hgt-brand) 40%, var(--hgt-border));
  box-shadow: var(--hgt-shadow-md);
  transform: translateY(-2px);
}
.card-cover {
  position: relative;
  aspect-ratio: 16 / 10;
  overflow: hidden;
  background: var(--hgt-moon);
}
.cover-img {
  width: 100%;
  height: 100%;
}
.cover-wash {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(248, 248, 247, 0.04) 0%, rgba(42, 42, 40, 0.06) 100%);
  pointer-events: none;
}
.card-body {
  display: flex;
  min-width: 0;
  padding: 14px 14px 16px;
  gap: 8px;
  flex-direction: column;
}
.card-title {
  display: -webkit-box;
  overflow: hidden;
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 16px;
  font-weight: 600;
  line-height: 1.35;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}
.card-tags {
  display: flex;
  gap: 6px;
}
.tag {
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  line-height: 1.5;
}
.tag-cat {
  background: var(--hgt-card-2);
  color: var(--hgt-text-2);
}
.tag-diff.easy {
  background: var(--hgt-brand-soft);
  color: var(--hgt-brand-deep);
}
.tag-diff.mid {
  background: var(--hgt-gold-soft);
  color: #8a6a12;
}
.tag-diff.hard {
  background: var(--hgt-accent-soft);
  color: var(--hgt-accent);
}
.card-surface {
  display: -webkit-box;
  overflow: hidden;
  color: var(--hgt-text-2);
  font-size: 12px;
  line-height: 1.55;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}
.card-meta {
  display: flex;
  margin-top: 2px;
  align-items: center;
  gap: 12px;
  color: var(--hgt-text-3);
  font-size: 11px;
  white-space: nowrap;
}

/* ===== How ===== */
.how {
  padding: 28px 48px 48px;
}
.steps {
  display: grid;
  margin-top: 18px;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
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
  margin-top: 18px;
  padding: 20px 24px;
  border: 1px dashed var(--hgt-border-soft);
  border-radius: var(--hgt-radius-md);
  align-items: center;
  gap: 16px;
  background: var(--hgt-card);
  cursor: pointer;
}
.multi-entry:hover {
  border-color: var(--hgt-brand);
  background: var(--hgt-brand-soft);
}
.multi-copy-wrap {
  display: flex;
  flex: 1;
  gap: 6px;
  flex-direction: column;
}
.multi-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 15px;
  font-weight: 600;
}
.multi-copy {
  color: var(--hgt-text-2);
  font-size: 13px;
}
.multi-arrow {
  color: var(--hgt-brand);
  font-size: 18px;
}

/* ===== Footer ===== */
.site-footer {
  display: flex;
  margin: 0 48px;
  padding: 24px 0 40px;
  border-top: 1px solid var(--hgt-border);
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
}
.footer-brand {
  display: flex;
  align-items: baseline;
  gap: 10px;
  flex-wrap: wrap;
}
.footer-en {
  color: var(--hgt-brand);
  font-size: 16px;
  letter-spacing: 0.18em;
}
.footer-zh {
  font-size: 16px;
  letter-spacing: 0.12em;
}
.footer-tag {
  width: 100%;
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-display);
  font-size: 12px;
}
.footer-note {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-display);
  font-size: 13px;
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
  .hero-bg :deep(img) {
    object-position: 70% center !important;
  }
  .hero-veil {
    background:
      linear-gradient(180deg,
        rgba(248, 248, 247, 0.78) 0%,
        rgba(248, 248, 247, 0.55) 48%,
        rgba(248, 248, 247, 0.72) 100%);
  }
  .hero-inner {
    width: 100%;
  }
  .hero-title,
  .hero-title-row {
    font-size: 28px;
  }
  .hero-copy {
    margin: 14px 0 22px;
    font-size: 14px;
  }
  .hero-actions {
    width: 100%;
    margin-bottom: 24px;
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
    font-size: 18px;
  }
  .hero-seal {
    display: none;
  }
  .cat-band {
    margin: 0 16px 8px;
  }
  .featured {
    padding: 12px 16px 0;
  }
  .puzzle-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }
  .card-body {
    padding: 10px;
    gap: 6px;
  }
  .card-title {
    font-size: 14px;
  }
  .how {
    padding: 20px 16px 32px;
  }
  .steps {
    grid-template-columns: 1fr;
  }
  .site-footer {
    margin: 0 16px;
    padding-bottom: 28px;
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
