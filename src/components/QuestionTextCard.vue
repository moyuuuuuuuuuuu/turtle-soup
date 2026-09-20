<script setup lang="ts">
import type { PublicQuestion } from '@/types/game'
import { difficultyLabel, estimateMinutes, formatPlayCount, resolveDepth, surfaceExcerpt, tagSummary } from '@/utils/depth'

const props = withDefaults(defineProps<{
  question: PublicQuestion
  index?: number
  depthVariant?: 0 | 1 | 2
  statusLabel?: string
  statusColor?: string
  metaExtra?: string
  clickable?: boolean
}>(), {
  index: 0,
  depthVariant: undefined,
  statusLabel: '',
  statusColor: '',
  metaExtra: '',
  clickable: true,
})

const emit = defineEmits<{ click: [id: string] }>()

const depth = computed(() => resolveDepth(props.question.difficulty))
const variant = computed(() => {
  if (props.depthVariant !== undefined)
    return props.depthVariant
  return props.index % 3
})
const surface = computed(() => surfaceExcerpt(props.question.surface, 56))
const tags = computed(() => tagSummary(props.question.tags, 2))
const minutes = computed(() => estimateMinutes(props.question.difficulty, props.question.play_count))
const plays = computed(() => formatPlayCount(props.question.play_count))
const cardNo = computed(() => String((props.index % 99) + 1).padStart(2, '0'))

function onTap() {
  if (!props.clickable)
    return
  emit('click', props.question.id)
}
</script>

<template>
  <view
    class="q-card"
    :class="[`depth-v${variant}`, { clickable }]"
    hover-class="q-card-hover"
    @click="onTap"
  >
    <view class="q-card-top">
      <text class="q-no">
        {{ cardNo }}
      </text>
      <text
        class="q-diff"
        :class="depth.tone"
        :style="{ color: depth.color }"
      >
        {{ statusLabel || difficultyLabel(question.difficulty) }}
      </text>
    </view>

    <text class="q-title">
      {{ question.title }}
    </text>

    <text class="q-surface">
      {{ surface || '汤面暂未同步' }}
    </text>

    <text v-if="tags" class="q-tags">
      {{ tags }}
    </text>

    <view class="q-card-foot">
      <view class="q-meta">
        <text v-if="minutes" class="q-meta-item">
          {{ minutes }}
        </text>
        <text v-if="plays || metaExtra" class="q-meta-item">
          {{ minutes ? '· ' : '' }}{{ plays || metaExtra }}
        </text>
      </view>
      <text class="q-arrow" aria-hidden="true">
        →
      </text>
    </view>
  </view>
</template>

<style scoped>
.q-card {
  display: flex;
  box-sizing: border-box;
  height: 100%;
  min-height: 210px;
  padding: 18px 18px 16px;
  flex-direction: column;
  gap: 10px;
  border: 1px solid var(--hgt-border-soft);
  border-radius: var(--hgt-radius-md);
  background:
    linear-gradient(135deg, rgba(29, 91, 94, 0.16), rgba(7, 34, 40, 0.08));
  transition:
    transform var(--hgt-dur-fast) var(--hgt-ease-out),
    border-color var(--hgt-dur-fast) var(--hgt-ease-out);
}

.q-card.depth-v1 {
  background: var(--hgt-depth-bg-1);
}

.q-card.depth-v2 {
  background: var(--hgt-depth-bg-2);
}

.q-card.clickable {
  cursor: pointer;
}

.q-card-hover {
  transform: translateY(-3px);
  border-color: var(--hgt-border-hover);
}

.q-card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.q-no {
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-mono);
  font-size: 12px;
  letter-spacing: 0.12em;
}

.q-diff {
  font-family: var(--hgt-font-mono);
  font-size: 12px;
  letter-spacing: 0.04em;
}

.q-title {
  margin-top: 4px;
  color: var(--hgt-text-bright);
  font-family: var(--hgt-font-display);
  font-size: 18px;
  font-weight: 600;
  line-height: 1.35;
}

.q-surface {
  display: -webkit-box;
  overflow: hidden;
  min-height: 44px;
  color: var(--hgt-text-2);
  font-family: var(--hgt-font-display);
  font-size: 14px;
  line-height: 1.65;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
}

.q-tags {
  color: var(--hgt-text-3);
  font-size: 12px;
}

.q-card-foot {
  display: flex;
  margin-top: auto;
  align-items: flex-end;
  justify-content: space-between;
  gap: 8px;
}

.q-meta {
  display: flex;
  min-width: 0;
  flex-wrap: wrap;
  column-gap: 4px;
  color: var(--hgt-text-3);
  font-family: var(--hgt-font-body);
  font-size: 12px;
  line-height: 1.4;
}

.q-meta-item {
  white-space: nowrap;
}

.q-arrow {
  flex: none;
  color: var(--hgt-brand);
  font-family: var(--hgt-font-mono);
  font-size: 14px;
  opacity: 0.45;
  transition:
    transform var(--hgt-dur-fast) var(--hgt-ease-out),
    opacity var(--hgt-dur-fast) var(--hgt-ease-out);
}

.q-card-hover .q-arrow {
  transform: translateX(3px);
  opacity: 0.9;
}

@media screen and (max-width: 767px) {
  .q-card {
    min-height: 168px;
    padding: 14px 14px 12px;
  }

  .q-title {
    font-size: 16px;
  }

  .q-surface {
    min-height: 44px;
  }
}
</style>
