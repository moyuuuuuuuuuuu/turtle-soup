<script setup lang="ts">
import type { HomeStats, PublicQuestion, PublicTag } from '@/types/game'
import { ensureAnonymousSession, homeApi, questionApi, tagApi } from '@/api/turtle'
import { usePlayerStore } from '@/store/playerStore'
import { formatCount } from '@/utils'
import { resolveAssetUrl } from '@/utils/assetUrl'
import { supportsPublicRooms } from '@/utils/platform'
import { openQuestionDetail } from '@/utils/questionRoute'

const stageBgStyle = { backgroundImage: `url(${resolveAssetUrl('/static/hgt/bg/bg_deep_ocean_hero.jpg')})` }

definePage({ name: 'home', layout: 'tabbar', style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } } })

const router = useRouter()
const player = usePlayerStore()

const featured = ref<PublicQuestion[]>([])
/** 英雄区背景压住的第一行卡片数：桌面3 / 平板2 / 移动1（列表 DOM 叠在背景图下沿） */
const firstRowCount = ref(3)
const featuredFirst = computed(() => featured.value.slice(0, firstRowCount.value))
const featuredRest = computed(() => featured.value.slice(firstRowCount.value))
const stats = ref<HomeStats>({
  question_count: 0,
  today_online: 0,
  success_rate: 0,
  average_duration_seconds: null,
})
const loading = ref(true)
const loadError = ref(false)
const randomLoading = ref(false)

function syncFirstRowCount() {
  // #ifdef H5
  if (typeof window === 'undefined')
    return
  const h5Width = window.innerWidth
  // 与 CSS 栅格一致：>=1200 三列，>=768 两列，更窄单列；列表压在背景图下沿
  firstRowCount.value = h5Width >= 1200 ? 3 : h5Width >= 768 ? 2 : 1
  // #endif
  // #ifndef H5
  const mpWidth = uni.getSystemInfoSync().windowWidth
  firstRowCount.value = mpWidth >= 1200 ? 3 : mpWidth >= 768 ? 2 : 1
  // #endif
}

interface HomeCategory { label: string, tagId: number | undefined }

const ALL_CATEGORY: HomeCategory = { label: '全部', tagId: undefined }

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

const PREFERRED_TAG_NAMES = ['悬疑', '逻辑推理', '本格', '清汤']
const COLLAPSED_CATEGORY_COUNT = 5
const preferredRank = new Map(PREFERRED_TAG_NAMES.map((name, index) => [name, index]))

function orderTags(tags: PublicTag[]): PublicTag[] {
  return [...tags].sort((a, b) => {
    const ra = preferredRank.get(a.name) ?? PREFERRED_TAG_NAMES.length
    const rb = preferredRank.get(b.name) ?? PREFERRED_TAG_NAMES.length
    return ra - rb
  })
}

function toCategories(tags: PublicTag[]): HomeCategory[] {
  return [ALL_CATEGORY, ...orderTags(tags).map(tag => ({ label: tag.name, tagId: tag.id }))]
}

const categories = ref<HomeCategory[]>(toCategories(FALLBACK_TAGS))
const activeTagId = ref<number | undefined>(undefined)
const categoryPage = ref(1)
const categoriesExpanded = ref(false)

const activeCategory = computed<HomeCategory>(
  () => categories.value.find(cat => cat.tagId === activeTagId.value) || ALL_CATEGORY,
)
const visibleCategories = computed(() =>
  categoriesExpanded.value ? categories.value : categories.value.slice(0, COLLAPSED_CATEGORY_COUNT),
)
const showCategoryToggle = computed(() => categories.value.length > COLLAPSED_CATEGORY_COUNT)

const exploreCountDisplay = computed(() => {
  const count = stats.value.question_count
  return count > 0 ? formatCount(count) : '…'
})

const featuredCountLabel = computed(() => {
  const count = stats.value.question_count
  return count > 0 ? formatCount(count) : ''
})

const STEPS = [
  { no: '01', title: '阅读汤面', copy: '从一段反常的故事开始。' },
  { no: '02', title: '不断提问', copy: '只能得到「是」「不是」「无关」。' },
  { no: '03', title: '接近真相', copy: '当所有线索逐渐拼合，给出你的答案。' },
] as const

interface FooterLink { label: string, name: string, path: string, requiresAuth?: boolean }

const footerLinks = computed<FooterLink[]>(() => {
  const links: FooterLink[] = [
    { label: '题库', name: 'questions', path: '/pages/questions/index' },
    { label: '我的推理', name: 'history', path: '/pages/history/index', requiresAuth: true },
  ]
  if (supportsPublicRooms) {
    links.push({
      label: '多人',
      name: 'public-rooms',
      path: '/pages/public-rooms/index',
      requiresAuth: true,
    })
  }
  // #ifdef H5
  links.push({ label: '捐赠', name: 'donate', path: '/pages/donate/index' })
  links.push({ label: '友链', name: 'friends', path: '/pages/friends/index' })
  // #endif
  return links
})

async function loadCategories() {
  try {
    const result = await tagApi.list()
    if (result.items?.length)
      categories.value = toCategories(result.items)
  }
  catch {
    // 保留兜底分类
  }
}

function resolveListPageSize() {
  // #ifdef H5
  const isMobile = typeof window !== 'undefined' && window.matchMedia('(max-width: 767px)').matches
  // 移动端 3 张；桌面取 3 的倍数，避免 3+1 残缺行
  return isMobile ? 3 : 6
  // #endif
  // #ifndef H5
  return 6
  // #endif
}

/** PC 网格按 3 列对齐，不足 3 的余数卡片截掉 */
function alignFeaturedToThree(items: PublicQuestion[]) {
  let isMobile = false
  // #ifdef H5
  isMobile = typeof window !== 'undefined' && window.matchMedia('(max-width: 767px)').matches
  // #endif
  if (isMobile)
    return items
  const keep = Math.floor(items.length / 3) * 3
  return keep > 0 ? items.slice(0, keep) : items.slice(0, 3)
}

async function loadHome() {
  loading.value = true
  loadError.value = false
  try {
    await player.restore()
    await ensureAnonymousSession()
    const tagId = activeCategory.value.tagId
    const page = categoryPage.value
    const pageSize = resolveListPageSize()

    if (tagId !== undefined) {
      let result = await questionApi.list({ tag_id: tagId, page, page_size: pageSize })
      if (!result.items.length && page > 1) {
        categoryPage.value = 1
        result = await questionApi.list({ tag_id: tagId, page: 1, page_size: pageSize })
      }
      featured.value = alignFeaturedToThree(result.items)
      return
    }

    if (page > 1) {
      let result = await questionApi.list({ page, page_size: pageSize })
      if (!result.items.length) {
        categoryPage.value = 1
        result = await questionApi.list({ page: 1, page_size: pageSize })
      }
      featured.value = alignFeaturedToThree(result.items)
      return
    }

    const [featuredResult, latestResult] = await Promise.all([
      questionApi.list({ featured: 1, page_size: pageSize }),
      questionApi.list({ page_size: pageSize }),
    ])
    featured.value = alignFeaturedToThree([...featuredResult.items, ...latestResult.items]
      .filter((question, index, questions) => questions.findIndex(item => item.id === question.id) === index)
      .slice(0, pageSize))
  }
  catch {
    loadError.value = true
  }
  finally {
    loading.value = false
  }
}

async function loadStats() {
  try {
    stats.value = await homeApi.stats()
  }
  catch {}
}

function openQuestion(id: string) {
  void openQuestionDetail({ id })
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

function goToQuestions() {
  router.push({ name: 'questions' })
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

async function openFooterLink(link: FooterLink) {
  if (link.requiresAuth) {
    if (!player.ready)
      await player.restore()
    if (!player.user) {
      router.push({ name: 'player-login', query: { redirect: link.path } })
      return
    }
  }
  if (link.name === 'history') {
    router.push({ name: 'history' })
    return
  }
  if (link.name === 'questions') {
    router.push({ name: 'questions' })
    return
  }
  router.push(link.path)
}

onMounted(() => {
  syncFirstRowCount()
  // #ifdef H5
  if (typeof window !== 'undefined')
    window.addEventListener('resize', syncFirstRowCount)
  // #endif
  void loadCategories()
  void loadHome()
  void loadStats()
})

onUnmounted(() => {
  // #ifdef H5
  if (typeof window !== 'undefined')
    window.removeEventListener('resize', syncFirstRowCount)
  // #endif
})
</script>

<template>
  <view class="home-page">
    <view class="stage">
      <view
        class="stage-bg"
        aria-hidden="true"
        :style="stageBgStyle"
      />
      <view class="stage-veil" />

      <section class="hero">
        <view class="hero-inner">
          <text class="hero-kicker">
            MOYUU TURTLE SOUP
          </text>
          <text class="hero-title">
            谜题沉在水下，
          </text>
          <text class="hero-title">
            而你，正慢慢接近<text class="hero-accent">
              真相。
            </text>
          </text>
          <text class="hero-copy">
            一碗看似寻常的汤，可能藏着意想不到的故事。向下追问，直到接近真相。
          </text>
          <view class="hero-actions">
            <button class="btn-primary" @click="goToQuestions">
              开始推理 →
            </button>
            <button class="btn-ghost" @click="playRandom">
              随机一题 ↝
            </button>
          </view>
        </view>
      </section>

      <section class="featured">
        <view class="section-head">
          <view class="section-title-wrap">
            <text class="section-title">
              精选谜题
            </text>
            <text class="section-en">
              CURATED MYSTERIES
            </text>
          </view>
          <view class="section-actions">
            <button class="btn-link" @click="refreshFeatured">
              换一批
            </button>
            <button class="btn-link btn-link-strong" @click="goToQuestions">
              查看全部{{ featuredCountLabel ? ` ${featuredCountLabel}` : '' }} 题 →
            </button>
          </view>
        </view>

        <view class="cat-bar">
          <scroll-view
            class="cat-scroll"
            scroll-x
            :show-scrollbar="false"
          >
            <view class="cat-tabs">
              <view
                v-for="cat in visibleCategories"
                :key="String(cat.tagId)"
                class="cat-tab"
                :class="{ active: activeCategory.tagId === cat.tagId }"
                @click="selectCategory(cat)"
              >
                {{ cat.label }}
              </view>
            </view>
          </scroll-view>
          <view
            v-if="showCategoryToggle"
            class="cat-tab cat-more cat-toggle"
            @click="toggleCategoriesExpanded"
          >
            {{ categoriesExpanded ? '收起' : '更多' }}
            <text class="cat-more-icon">
              {{ categoriesExpanded ? '−' : '+' }}
            </text>
          </view>
        </view>

        <view v-if="loading" class="content-state">
          <HgtLoading text="正在铺开题卷…" size="md" />
        </view>
        <view v-else-if="loadError" class="content-state">
          <text>谜题暂时没有浮上来</text>
          <button class="btn-ghost-sm" @click="loadHome">
            重新加载
          </button>
        </view>
        <view v-else-if="!featured.length" class="content-state">
          <text>暂无谜题</text>
        </view>
        <template v-else>
          <!-- 第一排：英雄区背景延伸到这里 -->
          <view class="featured-grid featured-grid-first">
            <QuestionTextCard
              v-for="(item, index) in featuredFirst"
              :key="item.id"
              :question="item"
              :index="index"
              @click="openQuestion"
            />
          </view>
        </template>
      </section>
    </view>

    <!-- 第二排起：正常页面背景 -->
    <section v-if="!loading && !loadError && featuredRest.length" class="featured-rest">
      <view class="featured-grid featured-grid-rest">
        <QuestionTextCard
          v-for="(item, index) in featuredRest"
          :key="item.id"
          :question="item"
          :index="index + featuredFirst.length"
          @click="openQuestion"
        />
      </view>
    </section>

    <section class="explore">
      <view class="explore-glow" aria-hidden="true" />
      <view class="explore-inner">
        <view class="explore-left">
          <text class="explore-number">
            {{ exploreCountDisplay }}
          </text>
          <text class="explore-unit">
            个故事沉在水下。
          </text>
        </view>
        <view class="explore-right">
          <text class="explore-copy">
            有些荒诞，有些温柔，<br>
            有些真相直到最后才会浮现。
          </text>
          <view class="explore-rule" aria-hidden="true" />
          <text class="explore-note">
            从标题、汤面与标签中找到下一道想玩的谜题。
          </text>
          <button class="explore-link" @click="goToQuestions">
            探索全部谜题 →
          </button>
        </view>
      </view>
    </section>

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
        <view class="steps-line" aria-hidden="true" />
        <view v-for="step in STEPS" :key="step.no" class="step">
          <view class="step-node" aria-hidden="true" />
          <text class="step-no">
            {{ step.no }}
          </text>
          <text class="step-title">
            {{ step.title }}
          </text>
          <text class="step-copy">
            {{ step.copy }}
          </text>
        </view>
      </view>
    </section>

    <footer class="site-footer">
      <view class="footer-brand-block">
        <text class="footer-brand">
          墨鱼海龟汤
        </text>
        <text class="footer-tagline">
          谜题沉在水下，真相等待浮现。
        </text>
      </view>
      <view class="footer-links">
        <text
          v-for="link in footerLinks"
          :key="link.name"
          class="footer-link"
          @click="openFooterLink(link)"
        >
          {{ link.label }}
        </text>
      </view>
      <text class="footer-copy">
        © 2026 MOYUU Turtle Soup
      </text>
    </footer>
  </view>
</template>

<style scoped>
.home-page {
  /* 全页统一内容域：最大宽度 + 水平 gutter，各板块左右对齐 */
  --home-gutter: 20px;
  /* 背景图 DOM 高度：约半屏到三分之二之间 */
  --home-bg-h: clamp(360px, 56vh, 620px);
  /* 首行题目卡压进背景图的深度（约卡片高的 1/3~1/2） */
  --home-card-on-bg: 84px;
  min-height: 100%;
  background: var(--hgt-bg);
  color: var(--hgt-text);
  font-family: var(--hgt-font-body);
}

.stage {
  position: relative;
  overflow: hidden;
  background: var(--hgt-bg);
}

.stage-bg {
  position: absolute;
  top: 0;
  right: 0;
  left: 0;
  bottom: auto;
  z-index: 0;
  width: 100%;
  height: var(--home-bg-h);
  background-color: #041418;
  background-position: 62% 24%;
  background-repeat: no-repeat;
  background-size: cover;
  background-attachment: scroll;
  /* 底部羽化，便于首行卡片压图时过渡 */
  -webkit-mask-image: linear-gradient(180deg, #000 0%, #000 70%, rgba(0, 0, 0, 0.7) 86%, rgba(0, 0, 0, 0.28) 94%, transparent 100%);
  mask-image: linear-gradient(180deg, #000 0%, #000 70%, rgba(0, 0, 0, 0.7) 86%, rgba(0, 0, 0, 0.28) 94%, transparent 100%);
}

.stage-veil {
  position: absolute;
  top: 0;
  right: 0;
  left: 0;
  bottom: auto;
  z-index: 1;
  height: var(--home-bg-h);
  background:
    linear-gradient(90deg,
      rgba(4, 20, 24, 0.42) 0%,
      rgba(4, 20, 24, 0.2) 28%,
      rgba(4, 20, 24, 0.05) 52%,
      transparent 72%),
    linear-gradient(180deg,
      rgba(6, 26, 32, 0.02) 0%,
      transparent 35%,
      rgba(6, 26, 32, 0.08) 58%,
      rgba(6, 26, 32, 0.18) 78%,
      rgba(6, 26, 32, 0.34) 90%,
      rgba(6, 26, 32, 0.48) 96%,
      rgba(6, 26, 32, 0.55) 100%);
  pointer-events: none;
}

/* 背景图底部过渡：落在首行卡片压图区域 */
.stage::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  top: calc(var(--home-bg-h) - 64px);
  bottom: auto;
  z-index: 2;
  height: 64px;
  pointer-events: none;
  background: linear-gradient(
    180deg,
    transparent 0%,
    rgba(4, 20, 24, 0.2) 45%,
    rgba(4, 20, 24, 0.55) 75%,
    var(--hgt-bg) 100%
  );
}

.hero {
  position: relative;
  z-index: 2;
  display: flex;
  box-sizing: border-box;
  width: 100%;
  /*
   * 英雄区 ≈ 背景高度 - 列表标题/筛选条 - 首行卡片压图深度，
   * 使首行卡片约 1/3~1/2 落在背景图上。
   */
  min-height: max(260px, calc(var(--home-bg-h) - 220px));
  height: auto;
  padding: 36px 0 40px;
  align-items: center;
}

.hero-inner {
  position: relative;
  z-index: 2;
  display: flex;
  box-sizing: border-box;
  width: min(var(--hgt-content-max), 100%);
  margin: 0 auto;
  padding: 0 var(--home-gutter);
  align-items: flex-start;
  flex-direction: column;
}

.hero-kicker {
  margin-bottom: 18px;
  color: var(--hgt-brand);
  font-family: var(--hgt-font-en);
  font-size: 13px;
  letter-spacing: 0.28em;
}

.hero-title {
  max-width: 460px;
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-display);
  font-size: 28px;
  font-weight: 600;
  line-height: 1.35;
  letter-spacing: 0.04em;
}

.hero-accent {
  display: inline-block;
  color: var(--hgt-brand);
}

.hero-copy {
  max-width: 460px;
  margin: 18px 0 28px;
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-display);
  font-size: 14px;
  line-height: 1.85;
}

.hero-actions {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.btn-primary,
.btn-ghost {
  display: flex;
  height: 46px;
  margin: 0;
  align-items: center;
  justify-content: center;
  font-family: var(--hgt-font-display);
  font-size: 14px;
  letter-spacing: 0.1em;
  line-height: 1;
}

.btn-primary {
  padding: 0 24px;
  border: 0;
  border-radius: var(--hgt-radius-sm);
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-weight: 600;
}

.btn-ghost {
  padding: 0 20px;
  border: 1px solid rgba(232, 244, 242, 0.22);
  border-radius: var(--hgt-radius-sm);
  background: transparent;
  color: var(--hgt-text);
}

.btn-primary::after,
.btn-ghost::after,
.btn-link::after,
.btn-ghost-sm::after,
.explore-link::after {
  border: 0;
}

.featured {
  position: relative;
  z-index: 3;
  box-sizing: border-box;
  width: min(var(--hgt-content-max), 100%);
  /* 高度已由 hero 预留压图空间，此处不再额外负 margin */
  margin: 0 auto;
  padding: 4px var(--home-gutter) 20px;
}

.section-head {
  display: flex;
  margin-bottom: 18px;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.section-title-wrap {
  display: flex;
  align-items: baseline;
  gap: 10px;
}

.section-title {
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-display);
  font-size: 22px;
  font-weight: 600;
  letter-spacing: 0.08em;
}

.section-en {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 10px;
  letter-spacing: 0.16em;
}

.section-actions {
  display: flex;
  align-items: center;
  gap: 14px;
}

.btn-link {
  height: 28px;
  margin: 0;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-display);
  font-size: 13px;
}

.btn-link-strong {
  color: var(--hgt-text-2);
}

/* 筛选条：标签横向滚动，更多/收起固定在行末（视窗内容区右侧） */
.cat-bar {
  display: flex;
  width: 100%;
  margin-bottom: 20px;
  align-items: center;
  gap: 8px;
}

.cat-scroll {
  flex: 1 1 auto;
  min-width: 0;
  white-space: nowrap;
}

.cat-tabs {
  display: inline-flex;
  width: max-content;
  min-width: 100%;
  padding-right: 4px;
  gap: 20px;
  align-items: center;
  white-space: nowrap;
}

.cat-toggle {
  flex: 0 0 auto;
  padding-right: 0;
  padding-left: 6px;
}

.cat-tab {
  position: relative;
  display: inline-flex;
  padding: 8px 2px 10px;
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-display);
  font-size: 14px;
  white-space: nowrap;
  cursor: pointer;
}

.cat-tab.active {
  color: var(--hgt-brand);
}

.cat-tab.active::after {
  position: absolute;
  right: 0;
  bottom: 0;
  left: 0;
  height: 2px;
  background: var(--hgt-brand);
  content: '';
}

.cat-more {
  color: var(--hgt-text-3);
}

.cat-more-icon {
  margin-left: 4px;
  color: var(--hgt-brand);
  font-family: var(--hgt-font-mono);
  font-size: 13px;
}

.content-state {
  display: flex;
  min-height: 200px;
  padding: 24px 8px;
  gap: 12px;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-display);
  font-size: 13px;
}

.btn-ghost-sm {
  display: flex;
  height: 36px;
  margin: 0;
  padding: 0 16px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  align-items: center;
  justify-content: center;
  background: transparent;
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-display);
  font-size: 12px;
  line-height: 1;
}

.featured-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}

.featured-rest {
  box-sizing: border-box;
  width: min(var(--hgt-content-max), 100%);
  margin: 0 auto;
  padding: 0 var(--home-gutter) 48px;
  background: var(--hgt-bg);
}

.featured-grid-first,
.featured-grid-rest {
  align-items: stretch;
}

/* 首行卡片压在背景图上：略实一点，保证可读 */
.featured-grid-first :deep(.q-card) {
  border-color: rgba(232, 244, 242, 0.16);
  background: linear-gradient(135deg, rgba(7, 34, 40, 0.78), rgba(4, 24, 29, 0.62));
  box-shadow: 0 12px 28px rgba(2, 12, 16, 0.28);
}

.featured-grid-first :deep(.q-card.depth-v1),
.featured-grid-first :deep(.q-card.depth-v2) {
  background: linear-gradient(135deg, rgba(7, 34, 40, 0.82), rgba(4, 24, 29, 0.68));
}

.explore {
  position: relative;
  width: 100%;
  padding: 56px 0 40px;
  overflow: hidden;
  background: var(--hgt-bg);
}

.explore-glow {
  position: absolute;
  top: 10%;
  right: 8%;
  width: min(420px, 60vw);
  height: min(420px, 60vw);
  border-radius: 50%;
  background: radial-gradient(circle, rgba(94, 196, 184, 0.12) 0%, rgba(94, 196, 184, 0.04) 42%, transparent 70%);
  pointer-events: none;
}

.explore-inner {
  position: relative;
  z-index: 1;
  display: grid;
  box-sizing: border-box;
  width: min(var(--hgt-content-max), 100%);
  margin: 0 auto;
  padding: 0 var(--home-gutter);
  grid-template-columns: 1fr;
  gap: 24px;
  align-items: end;
}

.explore-left {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.explore-number {
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-mono);
  font-size: clamp(72px, 16vw, 120px);
  font-weight: 500;
  line-height: 0.9;
  opacity: 0.18;
  user-select: none;
}

.explore-unit {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 22px;
}

.explore-right {
  display: flex;
  gap: 14px;
  flex-direction: column;
  align-items: flex-start;
}

.explore-copy {
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-display);
  font-size: 15px;
  line-height: 1.9;
}

.explore-rule {
  width: 72px;
  height: 1px;
  background: linear-gradient(90deg, rgba(94, 196, 184, 0.55), transparent);
}

.explore-note {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-display);
  font-size: 13px;
  line-height: 1.7;
}

.explore-link {
  margin-top: 8px;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--hgt-brand);
  font-family: var(--hgt-font-display);
  font-size: 14px;
  cursor: pointer;
}

.how {
  box-sizing: border-box;
  width: min(var(--hgt-content-max), 100%);
  margin: 0 auto;
  padding: 24px var(--home-gutter) 64px;
}

.steps {
  position: relative;
  display: grid;
  margin-top: 28px;
  grid-template-columns: 1fr;
  gap: 28px;
}

.steps-line {
  position: absolute;
  top: 10px;
  bottom: 24px;
  left: 3px;
  width: 1px;
  background: linear-gradient(180deg, rgba(94, 196, 184, 0.35), rgba(94, 196, 184, 0.08));
}

.step {
  position: relative;
  display: flex;
  gap: 8px;
  flex-direction: column;
  padding-left: 20px;
}

.step-node {
  position: absolute;
  top: 6px;
  left: 0;
  width: 8px;
  height: 8px;
  border: 1px solid var(--hgt-brand);
  border-radius: 50%;
  background: var(--hgt-bg);
}

.step-no {
  color: var(--hgt-brand);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
}

.step-title {
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-display);
  font-size: 18px;
  font-weight: 600;
}

.step-copy {
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-display);
  font-size: 13px;
  line-height: 1.7;
}

.site-footer {
  display: flex;
  box-sizing: border-box;
  width: min(var(--hgt-content-max), 100%);
  margin: 0 auto;
  padding: 28px var(--home-gutter) 36px;
  align-items: center;
  justify-content: space-between;
  gap: 16px 32px;
  flex-wrap: wrap;
  background: transparent;
  border-top: 1px solid var(--hgt-border-soft);
}

.footer-brand-block {
  display: flex;
  min-width: 0;
  gap: 6px;
  flex-direction: column;
  align-items: flex-start;
}

.footer-brand {
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-display);
  font-size: 16px;
  font-weight: 600;
}

.footer-tagline {
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-display);
  font-size: 13px;
}

.footer-links {
  display: flex;
  margin: 0;
  gap: 8px 20px;
  flex-direction: row;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
}

.footer-link {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-display);
  font-size: 13px;
  white-space: nowrap;
  cursor: pointer;
}

.footer-link:hover {
  color: var(--hgt-brand);
}

.footer-copy {
  width: 100%;
  margin: 0;
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
}

@media (max-width: 767px) {
  .site-footer {
    flex-direction: column;
    align-items: flex-start;
  }

  .footer-links {
    justify-content: flex-start;
  }
}

@media (min-width: 768px) {
  .home-page {
    --home-gutter: clamp(24px, 4vw, 48px);
    /* PC：背景约占视口 56%~62%（半屏到三分之二之间） */
    --home-bg-h: clamp(440px, 58vh, 700px);
    --home-card-on-bg: 90px;
  }

  .stage-bg {
    background-position: 55% 22%;
  }

  .hero {
    min-height: max(280px, calc(var(--home-bg-h) - 220px));
    height: auto;
    padding: 40px 0 48px;
  }

  .hero-title {
    max-width: 640px;
    font-size: 36px;
  }

  .featured {
    margin-top: 0;
    padding-top: 8px;
  }

  .featured-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
  }

  .featured-rest {
    padding-bottom: 56px;
  }

  .explore {
    padding-top: 72px;
    padding-bottom: 56px;
  }

  .explore-inner {
    grid-template-columns: minmax(220px, 0.9fr) minmax(280px, 1.1fr);
    gap: 40px;
  }

  .how {
    padding-bottom: 80px;
  }

  .steps {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 32px;
  }

  .steps-line {
    top: 9px;
    right: 8%;
    bottom: auto;
    left: 0;
    width: auto;
    height: 1px;
  }

  .step {
    padding-left: 0;
    padding-top: 28px;
  }

  .site-footer {
    padding-top: 48px;
    padding-bottom: 48px;
  }
}

@media (min-width: 1200px) {
  .hero-title {
    font-size: 40px;
  }

  .featured-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 767px) {
  .home-page {
    /* 移动端：背景约占整屏 56%~60%（半屏多一点、不足 2/3） */
    --home-bg-h: calc(100vh * 0.58);
    --home-bg-h: calc(100dvh * 0.58);
    --home-card-on-bg: 78px;
  }

  .stage-bg {
    background-position: 70% 30%;
    -webkit-mask-image: linear-gradient(180deg, #000 0%, #000 66%, rgba(0, 0, 0, 0.72) 84%, rgba(0, 0, 0, 0.28) 93%, transparent 100%);
    mask-image: linear-gradient(180deg, #000 0%, #000 66%, rgba(0, 0, 0, 0.72) 84%, rgba(0, 0, 0, 0.28) 93%, transparent 100%);
  }

  .stage-veil {
    background:
      linear-gradient(90deg,
        rgba(4, 20, 24, 0.35) 0%,
        rgba(4, 20, 24, 0.14) 40%,
        transparent 75%),
      linear-gradient(180deg,
        rgba(6, 26, 32, 0.02) 0%,
        transparent 40%,
        rgba(6, 26, 32, 0.12) 70%,
        rgba(6, 26, 32, 0.28) 88%,
        rgba(6, 26, 32, 0.42) 100%);
  }

  .stage::after {
    top: calc(var(--home-bg-h) - 56px);
    height: 56px;
  }

  /* 英雄文案占背景上半段，精选首行卡片下压约 1/3~1/2 */
  .hero {
    min-height: max(240px, calc(var(--home-bg-h) - 200px));
    height: auto;
    padding: 28px 0 32px;
  }

  .featured {
    margin-top: 0;
    padding-top: 0;
  }

  .featured-rest {
    background: var(--hgt-bg);
  }
}
</style>
