<script setup lang="ts">
import { useGameSocket } from '@/composables/useGameSocket'
import { useGameStore } from '@/store/gameStore'

definePage({ name: 'guess', style: { navigationBarTitleText: '最终猜测' } })

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
</script>

<template>
  <view class="guess-page">
    <view class="guess-shell">
      <text class="guess-kicker">
        FINAL GUESS
      </text>
      <text class="guess-title">
        提交你的真相
      </text>
      <text class="guess-sub">
        最终猜测仅有一次，提交后立即结算。请尽量说出人物、事件与关键因果。
      </text>

      <view class="paper">
        <image class="paper-texture" src="/static/hgt/paper/paper_01.png" mode="aspectFill" />
        <view class="paper-veil" />
        <view class="paper-inner">
          <text class="paper-label">
            汤底
          </text>
          <textarea
            v-model="guess"
            class="paper-input"
            :maxlength="2000"
            placeholder="写下你认为完整的故事真相…"
          />
          <text class="paper-count">
            {{ guess.length }}/2000
          </text>
        </view>
      </view>

      <button class="btn-primary" :disabled="!guess.trim() || busy" :loading="busy" @click="submit">
        提交真相
      </button>
    </view>

    <HgtConfirmDialog
      v-model="confirmOpen"
      eyebrow="最终猜测"
      title="确认提交真相？"
      description="最终猜测仅能提交一次，提交后将立即揭晓汤底。"
      confirm-text="确认提交"
      tone="warning"
      @confirm="doSubmit"
      @cancel="confirmOpen = false"
    />
  </view>
</template>

<style scoped>
.guess-page {
  display: flex;
  box-sizing: border-box;
  min-height: 100%;
  padding: 48px 20px 64px;
  align-items: center;
  justify-content: center;
  background:
    linear-gradient(180deg, rgba(12, 32, 39, 0.88), rgba(7, 20, 24, 0.96)),
    url('/static/hgt/bg/bg_deep_ocean.jpg') center / cover;
  color: var(--hgt-text);
}
.guess-shell {
  display: flex;
  width: min(560px, 100%);
  gap: 14px;
  flex-direction: column;
}
.guess-kicker {
  color: var(--hgt-brand);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
  letter-spacing: 0.28em;
}
.guess-title {
  color: var(--hgt-text);
  font-family: var(--hgt-font-display);
  font-size: 28px;
  font-weight: 600;
}
.guess-sub {
  margin-bottom: 8px;
  color: var(--hgt-text-2);
  font-size: 14px;
  line-height: 1.7;
}
.paper {
  position: relative;
  min-height: 260px;
  border-radius: var(--hgt-radius-md);
  overflow: hidden;
  box-shadow: var(--hgt-shadow-md);
}
.paper-texture {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}
.paper-veil {
  position: absolute;
  inset: 0;
  background: rgba(208, 220, 182, 0.72);
}
.paper-inner {
  position: relative;
  z-index: 1;
  display: flex;
  padding: 20px;
  gap: 10px;
  flex-direction: column;
}
.paper-label {
  color: #5a5e48;
  font-size: 12px;
  letter-spacing: 0.28em;
}
.paper-input {
  box-sizing: border-box;
  width: 100%;
  min-height: 180px;
  padding: 0;
  border: 0;
  background: transparent;
  color: var(--hgt-paper-ink);
  font-family: var(--hgt-font-display);
  font-size: 16px;
  line-height: 1.8;
}
.paper-count {
  align-self: flex-end;
  color: #6a6e58;
  font-size: 11px;
}
.btn-primary {
  height: 48px;
  margin: 0;
  padding: 0;
  border: 0;
  border-radius: var(--hgt-radius-sm);
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-size: 15px;
  font-weight: 600;
}
.btn-primary::after {
  border: 0;
}
.btn-primary:disabled {
  opacity: 0.5;
}
</style>
