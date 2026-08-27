<script setup lang="ts">
import { applyStoredTheme } from '@/utils/theme'

onLaunch(() => applyStoredTheme())
</script>

<style lang="scss">
@use '@wot-ui/ui/styles/theme/index.scss' as *;
:root,page { --app-background:#080808;--app-foreground:#f0f0f0;--app-card:#111;--background:#080808;--foreground:#f0f0f0;--card:#111;--secondary:#1a1a1a;--muted-foreground:#666;--accent:#d4d4d4;--border:#222; }
:root.hgt-light-theme { --app-background:#edeae4;--app-foreground:#1c1c1a;--app-card:#e4e0d9;--background:#edeae4;--foreground:#1c1c1a;--card:#e4e0d9;--secondary:#d9d5ce;--muted-foreground:#7a7972;--accent:#2e2e2c;--border:#c8c4bc; }
html,body,#app,page { min-height:100%;background:var(--app-background);color:var(--app-foreground); }
::view-transition-old(root),::view-transition-new(root){animation:none;mix-blend-mode:normal}
::view-transition-old(root){z-index:1}
::view-transition-new(root){z-index:9999;animation:hgt-theme-reveal .5s cubic-bezier(.2,.75,.2,1) both}
@keyframes hgt-theme-reveal{from{clip-path:circle(0 at var(--hgt-theme-x) var(--hgt-theme-y))}to{clip-path:circle(var(--hgt-theme-radius) at var(--hgt-theme-x) var(--hgt-theme-y))}}
@media(prefers-reduced-motion:reduce){::view-transition-old(root),::view-transition-new(root){animation:none}}
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
