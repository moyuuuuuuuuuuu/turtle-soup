<!-- 系统logo -->
<template>
  <div class="brand-logo flex-cc">
    <img :style="logoStyle" :src="logoLight" alt="墨鱼海龟汤" class="logo-light w-full h-full" />
    <img :style="logoStyle" :src="logoDark" alt="墨鱼海龟汤" class="logo-dark w-full h-full" />
  </div>
</template>

<script setup lang="ts">
  import logoDark from '@/assets/images/brand/logo-mark-dark.png'
  import logoLight from '@/assets/images/brand/logo-mark-light.png'

  defineOptions({ name: 'ArtLogo' })

  interface Props {
    /** logo 大小 */
    size?: number | string
  }

  const props = withDefaults(defineProps<Props>(), {
    size: 36
  })

  const logoStyle = computed(() => ({ width: `${props.size}px` }))
</script>

<style scoped>
  .brand-logo {
    position: relative;
  }

  .logo-dark {
    display: none;
  }

  :global(html.dark) .logo-light {
    display: none;
  }

  :global(html.dark) .logo-dark {
    display: block;
  }
</style>
