<script setup lang="ts">
import PlayerAuth from '@/components/player/PlayerAuth.vue'

definePage({ name: 'player-login', style: { 'navigationStyle': 'custom', 'navigationBarTitleText': '玩家登录', 'mp-toutiao': { navigationStyle: 'default' } } })
</script>

<template>
  <PlayerAuth />
</template>
