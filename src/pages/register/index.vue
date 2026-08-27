<script setup lang="ts">
import PlayerAuth from '@/components/player/PlayerAuth.vue'

definePage({ name: 'player-register', style: { navigationStyle: 'custom', navigationBarTitleText: '注册玩家' } })
</script>

<template>
  <PlayerAuth initial-mode="register" />
</template>
