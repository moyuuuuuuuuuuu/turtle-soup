<script setup lang="ts">
defineProps<{
  active: boolean
  expanding: boolean
  fading: boolean
  style: Record<string, string>
}>()
</script>

<template>
  <view
    v-if="active"
    class="hgt-theme-transition"
    :class="{ expanding, fading }"
    :style="style"
  />
</template>

<style scoped>
.hgt-theme-transition{position:fixed;z-index:9998;width:24px;height:24px;border-radius:50%;opacity:1;pointer-events:none;transform:scale(0);transform-origin:center;transition:transform .48s cubic-bezier(.2,.75,.2,1),opacity .18s ease}.hgt-theme-transition.expanding{transform:scale(var(--hgt-theme-scale))!important}.hgt-theme-transition.fading{opacity:0}
</style>
