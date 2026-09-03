<script setup lang="ts">
import { useAnimatedTheme } from '@/composables/useAnimatedTheme'

const { light, toggleTheme } = useAnimatedTheme()
</script>

<template>
  <button class="global-theme-toggle hgt-theme-trigger" :aria-label="light ? '切换到深色主题' : '切换到浅色主题'" @click="toggleTheme">
    {{ light ? '☾' : '☀' }}
  </button>
</template>

<style scoped>
.global-theme-toggle{position:fixed;z-index:100;top:max(20px,env(safe-area-inset-top));right:20px;display:flex;box-sizing:border-box;width:34px;height:34px;margin:0;padding:0;border:1px solid var(--border);border-radius:0;align-items:center;justify-content:center;background:var(--card);color:var(--foreground);font-size:14px;line-height:1;box-shadow:0 8px 24px #0002}.global-theme-toggle::after{display:none}@media(max-width:767px){.global-theme-toggle{top:max(11px,env(safe-area-inset-top));right:16px}}
</style>
