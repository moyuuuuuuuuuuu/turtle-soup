<script lang="ts" setup>
</script>

<script lang="ts">
export default {
  options: {
    addGlobalClass: true,
    virtualHost: true,
    styleIsolation: 'shared',
  },
}
</script>

<template>
  <view class="hgt-default-layout">
    <slot />
    <HgtFeedbackHost />
  </view>
</template>

<style scoped>
.hgt-default-layout {
  min-height: 100vh;
  background: var(--hgt-bg);
}
</style>
