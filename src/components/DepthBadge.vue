<script setup lang="ts">
import { depthFraction, depthSegments, resolveDepth } from '@/utils/depth'

const props = withDefaults(defineProps<{
  difficulty?: number | null
  compact?: boolean
  showLabel?: boolean
}>(), {
  difficulty: 0,
  compact: false,
  showLabel: true,
})

const depth = computed(() => resolveDepth(props.difficulty))
const segments = computed(() => depthSegments(props.difficulty))
</script>

<template>
  <view class="depth-badge" :class="{ compact }">
    <text v-if="!compact" class="depth-kicker">
      推理深度
    </text>
    <text class="depth-code" :style="{ color: depth.color }">
      {{ depthFraction(difficulty) }}
    </text>
    <view class="depth-bars" aria-hidden="true">
      <text
        v-for="(seg, index) in segments"
        :key="index"
        class="depth-bar"
        :class="seg"
      />
    </view>
    <text v-if="showLabel" class="depth-label" :style="{ color: depth.color }">
      {{ depth.short }}
    </text>
  </view>
</template>

<style scoped>
.depth-badge {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.depth-badge.compact {
  display: inline-flex;
  flex-direction: row;
  align-items: center;
  gap: 8px;
}

.depth-kicker {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 11px;
  letter-spacing: 0.18em;
}

.depth-code {
  font-family: var(--hgt-font-mono);
  font-size: 20px;
  letter-spacing: 0.08em;
}

.depth-badge.compact .depth-code {
  font-size: 13px;
}

.depth-bars {
  display: flex;
  gap: 4px;
}

.depth-bar {
  width: 14px;
  height: 2px;
  background: rgba(232, 244, 242, 0.14);
}

.depth-bar.on {
  background: currentColor;
  color: inherit;
}

.depth-badge .depth-bar.on {
  background: var(--hgt-brand);
}

.depth-label {
  font-family: var(--hgt-font-body);
  font-size: 12px;
}
</style>
