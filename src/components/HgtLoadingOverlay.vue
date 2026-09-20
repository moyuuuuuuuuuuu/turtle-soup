<script setup lang="ts">
import { useFeedbackStore } from '@/store/feedbackStore'

const store = useFeedbackStore()
const visible = computed(() => store.loading.show)
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
  <view v-if="visible" class="hgt-loading-mask">
    <view class="hgt-loading-card">
      <view class="hgt-loading-ring" />
      <text class="hgt-loading-text">
        {{ store.loading.message || '加载中' }}
      </text>
    </view>
  </view>
</template>

<style scoped>
.hgt-loading-mask {
  position: fixed;
  z-index: 10005;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(3, 14, 18, 0.35);
  pointer-events: none;
}

.hgt-loading-card {
  display: flex;
  padding: 18px 22px;
  border: 1px solid rgba(117, 220, 211, 0.18);
  border-radius: 10px;
  background: rgba(8, 28, 34, 0.92);
  flex-direction: column;
  align-items: center;
  gap: 12px;
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.35);
}

.hgt-loading-ring {
  width: 22px;
  height: 22px;
  border: 2px solid rgba(94, 196, 184, 0.22);
  border-top-color: var(--hgt-brand);
  border-radius: 50%;
  animation: hgt-loading-spin 0.8s linear infinite;
}

.hgt-loading-text {
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-body);
  font-size: 13px;
  letter-spacing: 0.04em;
}

@keyframes hgt-loading-spin {
  to { transform: rotate(360deg); }
}
</style>
