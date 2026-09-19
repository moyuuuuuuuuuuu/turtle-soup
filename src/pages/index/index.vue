<script setup lang="ts">
import type { HomeStats, PublicQuestion, PublicTag } from '@/types/game'
import { ensureAnonymousSession, homeApi, questionApi, tagApi } from '@/api/turtle'
import { usePlayerStore } from '@/store/playerStore'
import { formatCount } from '@/utils'
import { supportsPublicRooms } from '@/utils/platform'
import { openQuestionDetail } from '@/utils/questionRoute'

definePage({ name: 'home', layout: 'tabbar', style: { 'navigationStyle': 'custom', 'mp-toutiao': { navigationStyle: 'default' } } })

const router = useRouter()
const player = usePlayerStore()

const featured = ref<PublicQuestion[]>([])
const stats = ref<HomeStats>({
  question_count: 0,
  today_online: 0,
  success_rate: 0,
  average_duration_seconds: null,
})
const loading = ref(true)
const loadError = ref(false)
const randomLoading = ref(false)

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
  void loadCategories()
  void loadHome()
  void loadStats()
})
</script>

<template>
  <view class="home-page">
    <view class="stage">
      <view
        class="stage-bg"
        aria-hidden="true"
        :style="{ backgroundImage: 'url(/static/hgt/bg/bg_deep_ocean_hero.jpg)' }"
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
              真相
            </text>。
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

        <scroll-view
          v-if="showCategoryToggle && !categoriesExpanded"
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
            <view class="cat-tab cat-more" @click="toggleCategoriesExpanded">
              更多 <text class="cat-more-icon">
                +
              </text>
            </view>
          </view>
        </scroll-view>
        <view v-else class="cat-tabs cat-tabs-wrap">
          <view
            v-for="cat in categories"
            :key="String(cat.tagId)"
            class="cat-tab"
            :class="{ active: activeCategory.tagId === cat.tagId }"
            @click="selectCategory(cat)"
          >
            {{ cat.label }}
          </view>
          <view v-if="showCategoryToggle" class="cat-tab cat-more" @click="toggleCategoriesExpanded">
            收起 <text class="cat-more-icon">
              −
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
        <view v-else class="featured-grid">
          <QuestionTextCard
            v-for="(item, index) in featured"
            :key="item.id"
            :question="item"
            :index="index"
            @click="openQuestion"
          />
        </view>
      </section>
    </view>

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
      <text class="footer-brand">
        墨鱼海龟汤
      </text>
      <text class="footer-tagline">
        谜题沉在水下，真相等待浮现。
      </text>
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
  inset: 0;
  z-index: 0;
  width: 100%;
  height: 100%;
  background-color: #041418;
  background-image: url('/static/hgt/bg/bg_deep_ocean_hero.jpg');
  background-position: 62% 24%;
  background-repeat: no-repeat;
  background-size: cover;
  background-attachment: scroll;
}

.stage-veil {
  position: absolute;
  inset: 0;
  z-index: 1;
  background:
    linear-gradient(90deg,
      rgba(4, 20, 24, 0.55) 0%,
      rgba(4, 20, 24, 0.28) 28%,
      rgba(4, 20, 24, 0.08) 52%,
      transparent 72%),
    linear-gradient(180deg,
      rgba(6, 26, 32, 0.06) 0%,
      transparent 26%,
      rgba(6, 26, 32, 0.22) 56%,
      rgba(6, 26, 32, 0.62) 82%,
      #061a20 100%);
  pointer-events: none;
}

.hero {
  position: relative;
  z-index: 2;
  display: flex;
  box-sizing: border-box;
  width: 100%;
  min-height: clamp(420px, 50vh, 560px);
  height: clamp(420px, 50vh, 560px);
  padding: 40px 0 96px;
  align-items: center;
}

.hero-inner {
  position: relative;
  z-index: 2;
  display: flex;
  box-sizing: border-box;
  width: 100%;
  max-width: 1440px;
  margin: 0 auto;
  padding: 0 16px;
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
  margin: -72px auto 0;
  padding: 28px 20px 48px;
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

.cat-scroll {
  width: 100%;
  margin-bottom: 20px;
  white-space: nowrap;
}

.cat-tabs {
  display: flex;
  gap: 20px;
  align-items: center;
}

.cat-tabs-wrap {
  margin-bottom: 20px;
  flex-wrap: wrap;
  row-gap: 8px;
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
  height: 34px;
  margin: 0;
  padding: 0 14px;
  border: 1px solid var(--hgt-border);
  border-radius: var(--hgt-radius-sm);
  background: transparent;
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-display);
  font-size: 12px;
}

.featured-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}

.explore {
  position: relative;
  width: 100%;
  padding: 56px 20px 40px;
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
  width: min(var(--hgt-content-max), 100%);
  margin: 0 auto;
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
  width: min(var(--hgt-content-max), 100%);
  margin: 0 auto;
  padding: 24px 20px 64px;
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
  width: 100%;
  padding: 40px 20px 48px;
  flex-direction: column;
  gap: 12px;
  align-items: flex-start;
  background: #041418;
  border-top: 1px solid var(--hgt-border-soft);
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
  margin-top: 10px;
  gap: 12px;
  flex-direction: column;
  align-items: flex-start;
}

.footer-link {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-display);
  font-size: 13px;
  cursor: pointer;
}

.footer-copy {
  margin-top: 16px;
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
}

@media (min-width: 768px) {
  .stage-bg {
    background-position: 55% 22%;
  }

  .hero {
    padding: 48px 0 120px;
  }

  .hero-inner {
    padding: 0 clamp(24px, 4.5vw, 64px);
  }

  .hero-title {
    font-size: 36px;
  }

  .featured {
    margin-top: -88px;
    padding: 32px clamp(24px, 4.5vw, 64px) 56px;
  }

  .featured-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
  }

  .explore {
    padding: 72px clamp(24px, 4.5vw, 64px) 56px;
  }

  .explore-inner {
    grid-template-columns: minmax(220px, 0.9fr) minmax(280px, 1.1fr);
    gap: 40px;
  }

  .how {
    padding: 24px clamp(24px, 4.5vw, 64px) 80px;
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
    padding: 48px clamp(24px, 4.5vw, 64px) 48px;
  }
}

@media (min-width: 1200px) {
  .hero-title {
    font-size: 40px;
  }

  .featured-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .hero-inner {
    padding-left: clamp(32px, 3.5vw, 56px);
    padding-right: clamp(32px, 3.5vw, 56px);
  }
}

@media (max-width: 767px) {
  .stage-bg {
    background-position: 70% 30%;
  }

  .stage-veil {
    background:
      linear-gradient(90deg,
        rgba(4, 20, 24, 0.42) 0%,
        rgba(4, 20, 24, 0.2) 40%,
        transparent 75%),
      linear-gradient(180deg,
        rgba(6, 26, 32, 0.05) 0%,
        rgba(6, 26, 32, 0.2) 45%,
        rgba(6, 26, 32, 0.68) 78%,
        #061a20 100%);
  }
}
</style>
