<script setup lang="ts">
import { useGameSocket } from '@/composables/useGameSocket'
import { useGameStore } from '@/store/gameStore'

definePage({ name: 'guess', style: { navigationBarTitleText: '提交真相' } })

const route = useRoute()
const router = useRouter()
const store = useGameStore()
const socket = useGameSocket()
const guess = ref('')
const busy = ref(false)
const confirmOpen = ref(false)
const gameId = computed(() => String(route.query.id || route.params.id || ''))

async function submit() {
  if (!guess.value.trim() || busy.value)
    return
  confirmOpen.value = true
}

async function doSubmit() {
  confirmOpen.value = false
  busy.value = true
  try {
    store.setGame(await socket.guess(gameId.value, guess.value))
    router.replace({ name: 'game', params: { id: gameId.value }, query: { show_result: '1' } })
  }
  catch (error) {
    uni.showToast({ title: (error as Error).message || '提交失败', icon: 'none' })
  }
  finally {
    busy.value = false
  }
}

function goBack() {
  router.back()
}
</script>

<template>
  <view class="guess-page">
    <view class="guess-shell">
      <text class="guess-kicker">
        SUBMIT TRUTH
      </text>
      <text class="guess-title">
        你认为真相是什么？
      </text>
      <text class="guess-sub">
        把你目前推理出的完整故事写下来。提交后，主持人会根据汤底判断你的推理。
      </text>

      <view class="truth-panel">
        <text class="truth-label">
          真相
        </text>
        <textarea
          v-model="guess"
          class="truth-input"
          :maxlength="2000"
          placeholder="人物、事件与关键因果……"
        />
        <text class="truth-count">
          {{ guess.length }}/2000
        </text>
      </view>

      <view class="truth-actions">
        <button class="btn-ghost" @click="goBack">
          返回继续提问
        </button>
        <button class="btn-primary" :disabled="!guess.trim() || busy" :loading="busy" @click="submit">
          提交真相 →
        </button>
      </view>

      <text class="truth-note">
        ◇ 不完整也没关系，你可以继续推理后再次提交（若规则允许）。
      </text>
    </view>

    <HgtConfirmDialog
      v-model="confirmOpen"
      title="提交真相"
      content="提交后，主持人会根据汤底判断你的推理。"
      confirm-text="提交真相"
      cancel-text="再想想"
      @confirm="doSubmit"
    />
  </view>
</template>

<style scoped>
.guess-page {
  min-height: 100%;
  padding: 40px 16px 64px;
  background: var(--hgt-bg);
  color: var(--hgt-text);
}

.guess-shell {
  width: min(var(--hgt-reading-max), 100%);
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.guess-kicker {
  color: var(--hgt-brand);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
  letter-spacing: 0.22em;
}

.guess-title {
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-display);
  font-size: 28px;
  font-weight: 600;
  line-height: 1.3;
}

.guess-sub {
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-display);
  font-size: 14px;
  line-height: 1.7;
}

.truth-panel {
  position: relative;
  margin-top: 12px;
  padding: 18px;
  min-height: 280px;
  border: 1px solid var(--hgt-border-soft);
  border-radius: var(--hgt-radius-md);
  background: rgba(15, 53, 57, 0.2);
}

.truth-label {
  display: block;
  margin-bottom: 10px;
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
  letter-spacing: 0.16em;
}

.truth-input {
  box-sizing: border-box;
  width: 100%;
  min-height: 220px;
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-display);
  font-size: 16px;
  line-height: 1.9;
  background: transparent;
  border: none;
}

.truth-count {
  position: absolute;
  right: 16px;
  bottom: 12px;
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
}

.truth-actions {
  display: flex;
  margin-top: 8px;
  gap: 12px;
  justify-content: flex-end;
}

.btn-primary,
.btn-ghost {
  height: 48px;
  padding: 0 28px;
  border-radius: var(--hgt-radius-sm);
  font-family: var(--hgt-font-body);
  font-size: 15px;
  line-height: 48px;
}

.btn-primary {
  border: 1px solid var(--hgt-brand);
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
}

.btn-primary[disabled] {
  opacity: 0.45;
}

.btn-ghost {
  border: 1px solid var(--hgt-border);
  background: transparent;
  color: var(--hgt-text-2);
}

.truth-note {
  margin-top: 8px;
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-body);
  font-size: 12px;
}

@media screen and (max-width: 767px) {
  .guess-page {
    padding: 24px 16px 48px;
  }

  .guess-title {
    font-size: 24px;
  }

  .truth-actions {
    flex-direction: column-reverse;
  }

  .btn-primary,
  .btn-ghost {
    width: 100%;
  }
}
</style>
