<script setup lang="ts">
import { resolveAssetUrl } from '@/utils/assetUrl'

const props = withDefaults(defineProps<{
  /** 文案；传空字符串则只显示 logo */
  text?: string
  size?: 'sm' | 'md' | 'lg'
  eyebrow?: boolean
  /** 撑满父容器（全页 loading） */
  block?: boolean
}>(), {
  text: '正在铺开题卷…',
  size: 'md',
  eyebrow: false,
  block: false,
})

const logoSrc = resolveAssetUrl('/static/brand/logo-mark-dark.png')

const showText = computed(() => props.text !== '' && props.text != null)
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
  <view
    class="hgt-loading"
    :class="[`is-${size}`, { 'is-block': block }]"
    role="status"
    aria-live="polite"
  >
    <view class="hgt-loading-stage">
      <view class="hgt-loading-halo" aria-hidden="true" />
      <image class="hgt-loading-logo" :src="logoSrc" mode="aspectFit" />
    </view>
    <text v-if="eyebrow" class="hgt-loading-eyebrow hgt-mono">
      LOADING
    </text>
    <text v-if="showText" class="hgt-loading-text">
      {{ text }}
    </text>
  </view>
</template>

<style scoped>
.hgt-loading {
  display: flex;
  width: 100%;
  gap: 6px;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  color: var(--hgt-text-2);
  text-align: center;
}

.hgt-loading.is-block {
  min-height: 100%;
  min-height: 100dvh;
  padding: 48px 24px;
}

.hgt-loading-stage {
  position: relative;
  display: flex;
  width: var(--hgt-loading-stage, 72px);
  height: var(--hgt-loading-stage, 72px);
  align-items: center;
  justify-content: center;
}

.hgt-loading.is-sm .hgt-loading-stage {
  --hgt-loading-stage: 52px;
}
.hgt-loading.is-md .hgt-loading-stage {
  --hgt-loading-stage: 72px;
}
.hgt-loading.is-lg .hgt-loading-stage {
  --hgt-loading-stage: 96px;
}

.hgt-loading-halo {
  position: absolute;
  inset: 6%;
  border-radius: 50%;
  background: color-mix(in srgb, var(--hgt-brand) 14%, transparent);
  animation: hgt-loading-halo 2.4s ease-in-out infinite;
  pointer-events: none;
}

.hgt-loading.is-lg .hgt-loading-halo {
  inset: 4%;
  background: color-mix(in srgb, var(--hgt-brand) 12%, transparent);
}

.hgt-loading-logo {
  position: relative;
  z-index: 1;
  width: 82%;
  height: 82%;
  animation: hgt-loading-float 2.4s ease-in-out infinite;
  filter: drop-shadow(0 6px 14px rgba(42, 36, 32, 0.12));
}

.hgt-loading.is-lg .hgt-loading-logo {
  width: 86%;
  height: 86%;
  filter: drop-shadow(0 10px 22px rgba(42, 36, 32, 0.16));
}

.hgt-loading-eyebrow {
  margin-top: 4px;
  color: var(--hgt-brand);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
  letter-spacing: 0.28em;
}

.hgt-loading-text {
  max-width: min(280px, 80vw);
  color: var(--hgt-text-2);
  font-size: 13px;
  line-height: 1.5;
}

.hgt-loading.is-lg .hgt-loading-text {
  font-size: 14px;
}

.hgt-loading.is-sm .hgt-loading-text {
  font-size: 12px;
}

@keyframes hgt-loading-float {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-5px);
  }
}

@keyframes hgt-loading-halo {
  0%,
  100% {
    opacity: 0.55;
    transform: scale(0.92);
  }
  50% {
    opacity: 1;
    transform: scale(1);
  }
}

@media (prefers-reduced-motion: reduce) {
  .hgt-loading-logo,
  .hgt-loading-halo {
    animation: none;
  }
}
</style>
