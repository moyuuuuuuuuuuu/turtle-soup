<script setup lang="ts">
onLaunch(() => {})
</script>

<style lang="scss">
@use '@wot-ui/ui/styles/theme/index.scss' as *;
page { background:#080808;color:#f0f0f0; }
.hgt-display { font-family: Georgia, 'Times New Roman', serif; }
.hgt-mono { font-family: 'Courier New', monospace; }
.page-wraper {
  min-height: calc(100vh - var(--window-top));
  box-sizing: border-box;
  background: var(--wot-filled-content);
}
// .wot-theme-dark.page-wraper {
//   background: #222;
// }
</style>
