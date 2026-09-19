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
  description?: string
  confirmText?: string
  cancelText?: string
  tone?: 'default' | 'warning' | 'danger'
}>(), {
  eyebrow: '请确认',
  description: '',
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
      <view v-if="description || $slots.default" class="confirm-body">
        <slot>
          <text class="confirm-description">
            {{ description }}
          </text>
        </slot>
      </view>
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
  background:rgba(0,0,0,.52);
  animation:hgt-confirm-fade .18s ease-out;
}
.hgt-confirm-panel{
  position:relative;
  box-sizing:border-box;
  width:min(400px,100%);
  max-height:min(80vh,640px);
  padding:28px 24px 24px;
  overflow:auto;
  border:1px solid var(--border);
  border-radius:var(--hgt-radius-md, 12px);
  background:var(--card);
  color:var(--foreground);
  box-shadow:0 22px 70px rgba(0,0,0,.34);
  animation:hgt-confirm-in .22s cubic-bezier(.2,.8,.2,1);
}
.hgt-confirm-panel.tone-warning{
  border-color:rgba(240,194,57,.45);
}
.hgt-confirm-panel.tone-danger{
  border-color:rgba(158,83,86,.5);
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
  margin-top:18px;
  font-size:26px;
  font-weight:600;
  line-height:1.25;
}
.confirm-body{
  margin-top:16px;
}
.confirm-description{
  display:block;
  padding:12px 14px;
  border-left:2px solid var(--border-soft, var(--border));
  border-radius:0 var(--hgt-radius-sm, 8px) var(--hgt-radius-sm, 8px) 0;
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
  height:44px;
  min-height:44px;
  margin:0;
  padding:0 12px;
  border:1px solid var(--border-soft, var(--border));
  border-radius:var(--hgt-radius-sm, 8px);
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
  color:var(--muted-foreground);
  background:transparent;
}
.confirm-button.submit{
  flex:1.35;
  border-color:var(--accent, var(--hgt-brand));
  color:var(--hgt-on-brand, var(--background));
  background:var(--accent, var(--hgt-brand));
  font-weight:600;
}
.tone-warning .confirm-eyebrow{color:var(--hgt-warning, #c49a55)}
.tone-warning .confirm-description{
  border-left-color:rgba(240,194,57,.7);
  background:rgba(240,194,57,.12);
  color:var(--foreground);
}
.tone-danger .confirm-eyebrow{color:var(--hgt-danger, #c94a55)}
.tone-danger .confirm-description{
  border-left-color:rgba(158,83,86,.7);
  background:rgba(158,83,86,.12);
  color:var(--foreground);
}
.tone-danger .confirm-button.submit{
  border-color:var(--hgt-danger, #c94a55);
  color:#fff;
  background:var(--hgt-danger, #c94a55);
}
@keyframes hgt-confirm-fade{from{opacity:0}to{opacity:1}}
@keyframes hgt-confirm-in{from{opacity:0;transform:scale(.96) translateY(8px)}to{opacity:1;transform:none}}
@media(max-width:360px){
  .hgt-confirm-panel{padding:22px 16px}
  .confirm-title{font-size:22px}
  .confirm-actions{flex-direction:column}
  .confirm-button.cancel,
  .confirm-button.submit{flex:none;width:100%}
}
@media(min-width:768px){
  .hgt-confirm-panel{padding:32px 28px 28px}
  .confirm-title{font-size:30px}
}
</style>
