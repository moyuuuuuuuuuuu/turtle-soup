<script setup lang="ts">
import type { HgtToastTone } from '@/store/feedbackStore'
import { useFeedbackStore } from '@/store/feedbackStore'

const store = useFeedbackStore()

const tone = computed(() => store.toast.tone as HgtToastTone)
const visible = computed(() => store.toast.show && Boolean(store.toast.message))
const seq = computed(() => store.toast.seq)
</script>

<script lang="ts">
export default {
  options: {
    virtualHost: true,
    addGlobalClass: true,
    styleIsolation: 'shared',
  },
}
</script>

<template>
  <view v-if="visible" :key="seq" class="hgt-toast-root" :class="`tone-${tone}`">
    <view class="hgt-toast-card">
      <view v-if="tone === 'loading'" class="hgt-toast-spin" />
      <text class="hgt-toast-msg">
        {{ store.toast.message }}
      </text>
    </view>
  </view>
</template>

<style scoped>
.hgt-toast-root {
  position: fixed;
  z-index: 10020;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  display: flex;
  padding: 24px;
  align-items: center;
  justify-content: center;
  pointer-events: none;
}

.hgt-toast-card {
  box-sizing: border-box;
  display: flex;
  min-width: 120px;
  max-width: min(360px, 86vw);
  padding: 14px 20px;
  border: 1px solid rgba(117, 220, 211, 0.2);
  border-radius: 10px;
  background:
    linear-gradient(180deg, rgba(22, 62, 66, 0.22), transparent 48%),
    rgba(8, 28, 34, 0.94);
  box-shadow: 0 18px 48px rgba(0, 0, 0, 0.42);
  flex-direction: column;
  align-items: center;
  gap: 8px;
  animation: hgt-toast-in 0.22s cubic-bezier(0.22, 1, 0.36, 1);
}

.hgt-toast-spin {
  width: 14px;
  height: 14px;
  border: 1.5px solid rgba(94, 196, 184, 0.25);
  border-top-color: var(--hgt-brand);
  border-radius: 50%;
  animation: hgt-toast-spin 0.8s linear infinite;
}

.hgt-toast-msg {
  display: block;
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-body);
  font-size: 14px;
  line-height: 1.65;
  text-align: center;
  white-space: pre-wrap;
  word-break: break-word;
}

.tone-success .hgt-toast-card {
  border-color: rgba(94, 196, 184, 0.38);
}

.tone-error .hgt-toast-card {
  border-color: rgba(208, 90, 82, 0.42);
}

.tone-warning .hgt-toast-card {
  border-color: rgba(201, 164, 106, 0.42);
}

.tone-loading .hgt-toast-card {
  border-color: rgba(117, 220, 211, 0.24);
}

@keyframes hgt-toast-in {
  from {
    opacity: 0;
    transform: translateY(10px) scale(0.97);
  }
  to {
    opacity: 1;
    transform: none;
  }
}

@keyframes hgt-toast-spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
