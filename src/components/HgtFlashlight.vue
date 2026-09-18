<script setup lang="ts">
/**
 * H5 鼠标手电筒：光束跟随指针，照亮中心并向周围漫反射。
 * 浅色主题、触屏粗指针、系统减动效时自动关闭。
 */
const props = withDefaults(defineProps<{
  light?: boolean
  /** 光束半径倍率，1 = 默认 */
  scale?: number
  /** 外围压暗强度 0–1（默认很轻，保证光束外仍可阅读） */
  dim?: number
}>(), {
  light: false,
  scale: 1,
  dim: 0.28,
})

const active = ref(false)
let stop: (() => void) | undefined

onMounted(() => {
  // #ifdef H5
  const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true
  const coarseOnly = window.matchMedia?.('(pointer: coarse)').matches
    && window.matchMedia?.('(pointer: fine)').matches === false
  if (reduceMotion || coarseOnly)
    return

  const host = document.querySelector<HTMLElement>('#hgt-flashlight')
  if (!host)
    return

  active.value = true
  host.style.setProperty('--hgt-flash-dim', String(props.dim))

  let targetX = window.innerWidth * 0.5
  let targetY = window.innerHeight * 0.45
  let x = targetX
  let y = targetY
  let radius = 0
  let beam = 0
  let pointerInside = false
  let frame = 0
  let running = true

  const measureRadius = () => {
    const base = Math.min(window.innerWidth, window.innerHeight)
    return Math.round(Math.min(340, Math.max(200, base * 0.26)) * props.scale)
  }

  const applyVars = () => {
    host.style.setProperty('--hgt-flash-x', `${x}px`)
    host.style.setProperty('--hgt-flash-y', `${y}px`)
    host.style.setProperty('--hgt-flash-r', `${radius}px`)
    host.style.setProperty('--hgt-flash-beam', String(beam))
  }

  const onMove = (event: MouseEvent) => {
    pointerInside = true
    targetX = event.clientX
    targetY = event.clientY
  }

  const onLeave = () => {
    pointerInside = false
  }

  const tick = () => {
    if (!running)
      return

    const lerp = 0.14
    x += (targetX - x) * lerp
    y += (targetY - y) * lerp

    const targetRadius = measureRadius()
    radius += (targetRadius - radius) * 0.08
    // 浅色主题只保留柔光，不压暗；光强仍跟随指针进入
    beam += ((pointerInside ? 1 : 0) - beam) * 0.08

    applyVars()
    frame = requestAnimationFrame(tick)
  }

  window.addEventListener('mousemove', onMove, { passive: true })
  document.documentElement.addEventListener('mouseleave', onLeave)
  window.addEventListener('resize', applyVars, { passive: true })
  tick()

  stop = () => {
    running = false
    cancelAnimationFrame(frame)
    window.removeEventListener('mousemove', onMove)
    document.documentElement.removeEventListener('mouseleave', onLeave)
    window.removeEventListener('resize', applyVars)
    active.value = false
  }
  // #endif
})

onUnmounted(() => stop?.())
</script>

<template>
  <!-- #ifdef H5 -->
  <view
    id="hgt-flashlight"
    class="hgt-flashlight"
    :class="{ 'is-light': light, 'is-active': active }"
  >
    <view class="hgt-flashlight-veil" />
    <view class="hgt-flashlight-beam" />
    <view class="hgt-flashlight-core" />
  </view>
  <!-- #endif -->
</template>

<style scoped>
.hgt-flashlight {
  position: fixed;
  inset: 0;
  z-index: 40;
  pointer-events: none;
  --hgt-flash-x: 50%;
  --hgt-flash-y: 45%;
  --hgt-flash-r: 240px;
  --hgt-flash-beam: 0;
  --hgt-flash-dim: 0.28;
}

.hgt-flashlight-veil,
.hgt-flashlight-beam,
.hgt-flashlight-core {
  position: absolute;
  inset: 0;
  opacity: 0;
}

/* 外围轻压暗：中心大范围保持原亮度，仅远处有薄雾感 */
.hgt-flashlight-veil {
  background: rgba(4, 12, 16, var(--hgt-flash-dim));
  opacity: var(--hgt-flash-beam);
  -webkit-mask-image: radial-gradient(
    circle var(--hgt-flash-r) at var(--hgt-flash-x) var(--hgt-flash-y),
    transparent 0%,
    transparent 52%,
    rgba(0, 0, 0, 0.2) 70%,
    rgba(0, 0, 0, 0.55) 86%,
    rgba(0, 0, 0, 0.75) 100%
  );
  mask-image: radial-gradient(
    circle var(--hgt-flash-r) at var(--hgt-flash-x) var(--hgt-flash-y),
    transparent 0%,
    transparent 52%,
    rgba(0, 0, 0, 0.2) 70%,
    rgba(0, 0, 0, 0.55) 86%,
    rgba(0, 0, 0, 0.75) 100%
  );
}

/* 品牌色漫反射光晕，向周围衰减（主要“照亮”来源） */
.hgt-flashlight-beam {
  opacity: calc(var(--hgt-flash-beam) * 0.85);
  background: radial-gradient(
    circle calc(var(--hgt-flash-r) * 1.15) at var(--hgt-flash-x) var(--hgt-flash-y),
    rgba(91, 200, 189, 0.22) 0%,
    rgba(91, 200, 189, 0.12) 28%,
    rgba(140, 220, 210, 0.05) 50%,
    transparent 72%
  );
  mix-blend-mode: screen;
}

/* 光束核心高光 */
.hgt-flashlight-core {
  opacity: calc(var(--hgt-flash-beam) * 0.7);
  background: radial-gradient(
    circle calc(var(--hgt-flash-r) * 0.42) at var(--hgt-flash-x) var(--hgt-flash-y),
    rgba(236, 255, 250, 0.14) 0%,
    rgba(170, 240, 228, 0.06) 40%,
    transparent 70%
  );
  mix-blend-mode: screen;
}

/* 浅色主题：不压暗页面，仅保留跟随鼠标的柔光漫反射 */
.hgt-flashlight.is-light .hgt-flashlight-veil {
  opacity: 0;
}
.hgt-flashlight.is-light .hgt-flashlight-beam {
  opacity: calc(var(--hgt-flash-beam) * 0.55);
  background: radial-gradient(
    circle calc(var(--hgt-flash-r) * 1.1) at var(--hgt-flash-x) var(--hgt-flash-y),
    rgba(46, 154, 144, 0.14) 0%,
    rgba(46, 154, 144, 0.06) 30%,
    transparent 68%
  );
  mix-blend-mode: multiply;
}
.hgt-flashlight.is-light .hgt-flashlight-core {
  opacity: calc(var(--hgt-flash-beam) * 0.35);
  background: radial-gradient(
    circle calc(var(--hgt-flash-r) * 0.35) at var(--hgt-flash-x) var(--hgt-flash-y),
    rgba(255, 255, 255, 0.35) 0%,
    rgba(230, 250, 245, 0.12) 45%,
    transparent 70%
  );
  mix-blend-mode: soft-light;
}
</style>
