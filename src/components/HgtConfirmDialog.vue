<script setup lang="ts">
withDefaults(defineProps<{
  modelValue: boolean
  eyebrow?: string
  title: string
  description?: string
  confirmText?: string
  cancelText?: string
  tone?: 'default' | 'warning' | 'danger'
  showCancel?: boolean
}>(), {
  eyebrow: '',
  description: '',
  confirmText: '确认',
  cancelText: '取消',
  tone: 'default',
  showCancel: true,
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'confirm': []
  'cancel': []
}>()

function close(confirmed: boolean) {
  emit('update:modelValue', false)
  if (confirmed)
    emit('confirm')
  else
    emit('cancel')
}
</script>

<script lang="ts">
export default {
  options: {
    // #ifndef MP-TOUTIAO
    virtualHost: true,
    // #endif
    addGlobalClass: true,
    styleIsolation: 'shared',
  },
}
</script>

<template>
  <view
    v-if="modelValue"
    class="hgt-confirm-mask"
    @tap="close(false)"
  >
    <view class="hgt-confirm-panel" :class="`tone-${tone}`" @tap.stop>
      <view class="confirm-top">
        <text class="confirm-eyebrow hgt-mono">
          {{ eyebrow || (tone === 'warning' || tone === 'danger' ? 'RISK' : 'CONFIRM') }}
        </text>
        <text class="confirm-glyph" :class="`glyph-${tone}`">
          {{ tone === 'danger' ? '!' : tone === 'warning' ? '△' : '◇' }}
        </text>
      </view>

      <text class="confirm-title hgt-display">
        {{ title }}
      </text>

      <view v-if="description || $slots.default" class="confirm-body">
        <slot>
          <text class="confirm-description">
            {{ description }}
          </text>
        </slot>
      </view>

      <view class="confirm-actions" :class="{ single: !showCancel }">
        <button
          v-if="showCancel"
          class="confirm-button cancel hgt-mono"
          @tap.stop="close(false)"
        >
          {{ cancelText }}
        </button>
        <button
          class="confirm-button submit hgt-mono"
          :class="{ danger: tone === 'danger' }"
          @tap.stop="close(true)"
        >
          {{ confirmText }}
        </button>
      </view>
    </view>
  </view>
</template>

<style scoped>
.hgt-confirm-mask {
  position: fixed;
  z-index: 10010;
  top: 0;
  right: 0;
  bottom: 0;
  left: 0;
  display: flex;
  padding: 24px 16px;
  align-items: center;
  justify-content: center;
  background: rgba(3, 14, 18, 0.72);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  animation: hgt-confirm-fade 0.18s ease-out;
}

.hgt-confirm-panel {
  position: relative;
  box-sizing: border-box;
  width: min(400px, 100%);
  max-height: min(80vh, 640px);
  padding: 22px 22px 20px;
  overflow: auto;
  border: 1px solid rgba(117, 220, 211, 0.18);
  border-radius: 12px;
  background:
    linear-gradient(180deg, rgba(22, 62, 66, 0.2), transparent 42%),
    rgba(8, 28, 34, 0.96);
  color: var(--hgt-text);
  box-shadow: 0 24px 72px rgba(0, 0, 0, 0.48);
  animation: hgt-confirm-in 0.22s cubic-bezier(0.22, 1, 0.36, 1);
}

.hgt-confirm-panel.tone-warning {
  border-color: rgba(201, 164, 106, 0.4);
}

.hgt-confirm-panel.tone-danger {
  border-color: rgba(208, 90, 82, 0.45);
}

.confirm-top {
  display: flex;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--hgt-border-soft);
  align-items: center;
  justify-content: space-between;
}

.confirm-eyebrow {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 10px;
  letter-spacing: 0.28em;
}

.confirm-glyph {
  color: var(--hgt-brand);
  font-family: var(--hgt-font-mono);
  font-size: 14px;
  line-height: 1;
}

.glyph-warning {
  color: var(--hgt-warning);
}

.glyph-danger {
  color: var(--hgt-danger);
}

.confirm-title {
  display: block;
  margin-top: 16px;
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-display);
  font-size: 24px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: 0.04em;
}

.confirm-body {
  margin-top: 14px;
}

.confirm-description {
  display: block;
  padding: 12px 14px;
  border-left: 2px solid rgba(117, 220, 211, 0.28);
  border-radius: 0 8px 8px 0;
  background: rgba(15, 53, 57, 0.42);
  color: var(--hgt-text);
  font-family: var(--hgt-font-body);
  font-size: 14px;
  line-height: 1.85;
  white-space: pre-wrap;
  word-break: break-word;
}

.tone-warning .confirm-eyebrow {
  color: var(--hgt-warning);
}

.tone-warning .confirm-description {
  border-left-color: rgba(201, 164, 106, 0.7);
  background: rgba(201, 164, 106, 0.1);
}

.tone-danger .confirm-eyebrow {
  color: var(--hgt-danger);
}

.tone-danger .confirm-description {
  border-left-color: rgba(208, 90, 82, 0.7);
  background: rgba(208, 90, 82, 0.1);
}

.confirm-actions {
  display: flex;
  margin-top: 20px;
  gap: 10px;
}

.confirm-actions.single .confirm-button.submit {
  flex: 1;
}

.confirm-button {
  display: flex;
  box-sizing: border-box;
  height: 44px;
  min-height: 44px;
  margin: 0;
  padding: 0 12px;
  border: 1px solid var(--hgt-border);
  border-radius: 8px;
  align-items: center;
  justify-content: center;
  font-family: var(--hgt-font-mono);
  font-size: 12px;
  line-height: 1;
  letter-spacing: 0.08em;
  white-space: nowrap;
  overflow: hidden;
}

.confirm-button::after {
  display: none;
}

.confirm-button.cancel {
  flex: 1;
  background: transparent;
  color: var(--hgt-text-2);
}

.confirm-button.submit {
  flex: 1.35;
  border-color: var(--hgt-brand);
  background: var(--hgt-brand);
  color: var(--hgt-on-brand);
  font-weight: 600;
}

.confirm-button.submit.danger {
  border-color: var(--hgt-danger);
  background: var(--hgt-danger);
  color: #fff;
}

.tone-warning .confirm-button.submit {
  border-color: rgba(201, 164, 106, 0.85);
  background: var(--hgt-gold);
  color: var(--hgt-on-brand);
}

@keyframes hgt-confirm-fade {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes hgt-confirm-in {
  from {
    opacity: 0;
    transform: scale(0.96) translateY(8px);
  }
  to {
    opacity: 1;
    transform: none;
  }
}

@media (max-width: 360px) {
  .hgt-confirm-panel {
    padding: 20px 16px 16px;
  }

  .confirm-title {
    font-size: 22px;
  }

  .confirm-actions {
    flex-direction: column;
  }

  .confirm-button.cancel,
  .confirm-button.submit {
    flex: none;
    width: 100%;
  }
}

@media (min-width: 768px) {
  .hgt-confirm-panel {
    padding: 26px 26px 24px;
  }

  .confirm-title {
    font-size: 28px;
  }
}
</style>
