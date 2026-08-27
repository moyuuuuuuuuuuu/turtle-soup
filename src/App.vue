<script setup lang="ts">
import { applyStoredTheme } from '@/utils/theme'

onLaunch(() => applyStoredTheme())
</script>

<style lang="scss">
@use '@wot-ui/ui/styles/theme/index.scss' as *;
:root { --app-background:#080808;--app-foreground:#f0f0f0;--app-card:#111; }
:root.hgt-light-theme { --app-background:#edeae4;--app-foreground:#1c1c1a;--app-card:#e4e0d9; }
html,body,#app,page { min-height:100%;background:var(--app-background);color:var(--app-foreground); }
.hgt-display { font-family: Georgia, 'Times New Roman', serif; }
.hgt-mono { font-family: 'Courier New', monospace; }
::-webkit-scrollbar { width: 4px; height: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { border-radius: 2px; background: #222; }
::-webkit-scrollbar-thumb:hover { background: #666; }
.page-wraper {
  min-height: calc(100vh - var(--window-top));
  box-sizing: border-box;
  background: var(--app-background);
}
// .wot-theme-dark.page-wraper {
//   background: #222;
// }
</style>
