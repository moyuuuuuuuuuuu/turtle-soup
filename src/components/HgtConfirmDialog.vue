<script setup lang="ts">
withDefaults(defineProps<{
  modelValue: boolean
  eyebrow?: string
  title: string
  description: string
  confirmText?: string
  cancelText?: string
  tone?: 'default' | 'warning' | 'danger'
}>(), {
  eyebrow: '请确认',
  confirmText: '确认',
  cancelText: '取消',
  tone: 'default',
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

function handleVisible(value: boolean) {
  emit('update:modelValue', value)
  if (!value)
    emit('cancel')
}
</script>

<template>
  <wd-popup
    :model-value="modelValue"
    position="center"
    :close-on-click-modal="true"
    :root-portal="true"
    custom-class="hgt-confirm-popup"
    custom-style="width:720rpx;max-width:calc(100vw - 40px);overflow:hidden;border-radius:0;"
    modal-style="background:rgba(0,0,0,.48);"
    @update:model-value="handleVisible"
  >
    <view class="confirm-card" :class="`tone-${tone}`">
      <text class="confirm-eyebrow hgt-mono">△ {{ eyebrow }}</text>
      <text class="confirm-title hgt-display">{{ title }}</text>
      <text class="confirm-description">{{ description }}</text>
      <view class="confirm-actions">
        <button class="confirm-button cancel hgt-mono" @click="close(false)">
          {{ cancelText }}
        </button>
        <button class="confirm-button submit hgt-mono" @click="close(true)">
          {{ confirmText }}
        </button>
      </view>
    </view>
  </wd-popup>
</template>

<style scoped>
:deep(.hgt-confirm-popup){border:1px solid var(--border);background:var(--background);box-shadow:0 22px 70px rgba(0,0,0,.28)}
.confirm-card{box-sizing:border-box;width:100%;padding:30px;color:var(--foreground);background:var(--background)}.confirm-eyebrow{display:block;padding-bottom:13px;border-bottom:1px solid var(--border);color:var(--muted-foreground);font-size:10px;letter-spacing:.2em}.confirm-title{display:block;margin-top:22px;font-size:30px;line-height:1.2}.confirm-description{display:block;margin-top:18px;padding:14px 16px;border-left:2px solid var(--foreground);background:var(--secondary);color:var(--muted-foreground);font-size:13px;line-height:1.85;white-space:pre-wrap}.confirm-actions{display:grid;grid-template-columns:1fr 1.35fr;gap:12px;margin-top:28px}.confirm-button{display:flex!important;box-sizing:border-box;height:48px;min-height:48px;margin:0!important;padding:0 12px!important;border:1px solid var(--foreground);border-radius:0;align-items:center!important;justify-content:center!important;font-size:12px;line-height:1!important;letter-spacing:.1em}.confirm-button::after{display:none}.cancel{color:var(--foreground);background:transparent}.submit,.tone-warning .submit,.tone-danger .submit{border-color:var(--foreground);color:var(--background);background:var(--foreground)}
@media(min-width:768px){.confirm-card{padding:34px}.confirm-title{font-size:32px}}
</style>
