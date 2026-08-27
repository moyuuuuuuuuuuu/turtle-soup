<script setup lang="ts">
import PlayerAuth from '@/components/player/PlayerAuth.vue'

definePage({ name: 'player-reset-password', style: { navigationStyle: 'custom', navigationBarTitleText: '找回密码' } })
</script>

<template>
  <PlayerAuth initial-mode="reset" />
</template>
