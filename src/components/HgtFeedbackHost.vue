<script setup lang="ts">
import { useFeedbackStore } from '@/store/feedbackStore'

const store = useFeedbackStore()
const hostId = `hgt-feedback-${Math.random().toString(36).slice(2, 10)}`
const isPrimary = ref(false)

onMounted(() => {
  isPrimary.value = store.claimHost(hostId)
})

onUnmounted(() => {
  store.releaseHost(hostId)
})

function onConfirm() {
  store.settleConfirm(true)
}

function onCancel() {
  store.settleConfirm(false)
}
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
  <view v-if="isPrimary" class="hgt-feedback-host">
    <HgtLoadingOverlay />
    <HgtToast />
    <HgtConfirmDialog
      v-if="store.confirm.show"
      :model-value="store.confirm.show"
      :eyebrow="store.confirm.eyebrow"
      :title="store.confirm.title"
      :description="store.confirm.description"
      :confirm-text="store.confirm.confirmText"
      :cancel-text="store.confirm.cancelText"
      :tone="store.confirm.tone"
      :show-cancel="store.confirm.showCancel"
      @confirm="onConfirm"
      @cancel="onCancel"
      @update:model-value="(v: boolean) => { if (!v) onCancel() }"
    />
  </view>
</template>

<style scoped>
.hgt-feedback-host {
  position: fixed;
  z-index: 10000;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  pointer-events: none;
}

.hgt-feedback-host :deep(.hgt-confirm-mask) {
  pointer-events: auto;
}

.hgt-feedback-host :deep(.hgt-toast-root),
.hgt-feedback-host :deep(.hgt-loading-mask) {
  pointer-events: none;
}
</style>
