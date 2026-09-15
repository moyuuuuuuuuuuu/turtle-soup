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
</script>

<template>
  <view
    v-if="modelValue"
    class="hgt-confirm-mask"
    @tap="close(false)"
  >
    <view class="hgt-confirm-panel" :class="`tone-${tone}`" @tap.stop>
      <text class="confirm-eyebrow hgt-mono">
        △ {{ eyebrow }}
      </text>
      <text class="confirm-title hgt-display">
        {{ title }}
      </text>
      <text class="confirm-description">
        {{ description }}
      </text>
      <view class="confirm-actions">
        <button class="confirm-button cancel hgt-mono" @tap.stop="close(false)">
          {{ cancelText }}
        </button>
        <button class="confirm-button submit hgt-mono" @tap.stop="close(true)">
          {{ confirmText }}
        </button>
      </view>
    </view>
  </view>
</template>

<style scoped>
.hgt-confirm-mask{
  position:fixed;
  z-index:120;
  top:0;
  right:0;
  bottom:0;
  left:0;
  display:flex;
  padding:24px 16px;
  align-items:center;
  justify-content:center;
  background:rgba(0,0,0,.48);
  animation:hgt-confirm-fade .18s ease-out;
}
.hgt-confirm-panel{
  box-sizing:border-box;
  width:min(360px,100%);
  max-height:min(80vh,640px);
  padding:28px 24px;
  overflow:auto;
  border:1px solid var(--border);
  background:var(--background);
  color:var(--foreground);
  box-shadow:0 22px 70px rgba(0,0,0,.28);
  animation:hgt-confirm-in .22s cubic-bezier(.2,.8,.2,1);
}
.confirm-eyebrow{
  display:block;
  padding-bottom:12px;
  border-bottom:1px solid var(--border);
  color:var(--muted-foreground);
  font-size:10px;
  letter-spacing:.2em;
}
.confirm-title{
  display:block;
  margin-top:20px;
  font-size:28px;
  line-height:1.2;
}
.confirm-description{
  display:block;
  margin-top:16px;
  padding:12px 14px;
  border-left:2px solid var(--foreground);
  background:var(--secondary);
  color:var(--muted-foreground);
  font-size:13px;
  line-height:1.85;
  white-space:pre-wrap;
  word-break:break-word;
}
.confirm-actions{
  display:flex;
  margin-top:24px;
  gap:10px;
}
.confirm-button{
  display:flex;
  box-sizing:border-box;
  height:46px;
  min-height:46px;
  margin:0;
  padding:0 10px;
  border:1px solid var(--foreground);
  border-radius:0;
  align-items:center;
  justify-content:center;
  font-size:12px;
  line-height:1;
  letter-spacing:.08em;
  white-space:nowrap;
  overflow:hidden;
}
.confirm-button::after{display:none}
.confirm-button.cancel{
  flex:1;
  color:var(--foreground);
  background:transparent;
}
.confirm-button.submit,
.tone-warning .confirm-button.submit,
.tone-danger .confirm-button.submit{
  flex:1.35;
  border-color:var(--foreground);
  color:var(--background);
  background:var(--foreground);
}
.tone-warning .confirm-eyebrow{color:#d97706}
.tone-danger .confirm-eyebrow{color:#dc2626}
@keyframes hgt-confirm-fade{from{opacity:0}to{opacity:1}}
@keyframes hgt-confirm-in{from{opacity:0;transform:scale(.96) translateY(8px)}to{opacity:1;transform:none}}
@media(max-width:360px){
  .hgt-confirm-panel{padding:22px 16px}
  .confirm-title{font-size:24px}
  .confirm-actions{flex-direction:column}
  .confirm-button.cancel,
  .confirm-button.submit,
  .tone-warning .confirm-button.submit,
  .tone-danger .confirm-button.submit{flex:none;width:100%}
}
@media(min-width:768px){
  .hgt-confirm-panel{padding:34px}
  .confirm-title{font-size:32px}
}
</style>
