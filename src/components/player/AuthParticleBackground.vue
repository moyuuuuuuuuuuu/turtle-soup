<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
interface Particle { x: number, y: number, vx: number, vy: number, size: number, opacity: number, life: number, maxLife: number }

let animationFrame = 0
let stop: (() => void) | undefined

onMounted(() => {
  // #ifdef H5
  const host = document.querySelector('#auth-particle-canvas') as HTMLElement | null
  const canvas = (host?.tagName === 'CANVAS' ? host : host?.querySelector('canvas')) as HTMLCanvasElement | null
  const context = canvas?.getContext('2d')
  if (!canvas || !context)
    return
  const particles: Particle[] = []
  const resize = () => { canvas.width = window.innerWidth; canvas.height = window.innerHeight }
  const spawn = (): Particle => ({ x: Math.random() * canvas.width, y: Math.random() * canvas.height, vx: (Math.random() - 0.5) * 0.3, vy: -Math.random() * 0.5 - 0.1, size: Math.random() * 1.5 + 0.3, opacity: 0, life: 0, maxLife: Math.random() * 300 + 200 })
  resize()
  window.addEventListener('resize', resize)
  for (let index = 0; index < 80; index++) {
    const particle = spawn(); particle.life = Math.random() * particle.maxLife; particle.opacity = Math.sin((particle.life / particle.maxLife) * Math.PI) * 0.6; particles.push(particle)
  }
  const draw = () => {
    context.clearRect(0, 0, canvas.width, canvas.height)
    for (let index = particles.length - 1; index >= 0; index--) {
      const particle = particles[index]; particle.x += particle.vx; particle.y += particle.vy; particle.life++
      particle.opacity = Math.sin((particle.life / particle.maxLife) * Math.PI) * 0.5
      context.beginPath(); context.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2); context.fillStyle = `rgba(255,255,255,${particle.opacity})`; context.fill()
      if (particle.life >= particle.maxLife)
        particles[index] = spawn()
    }
    for (let first = 0; first < particles.length; first++) {
      for (let second = first + 1; second < particles.length; second++) {
        const dx = particles[first].x - particles[second].x; const dy = particles[first].y - particles[second].y; const distance = Math.sqrt(dx * dx + dy * dy)
        if (distance < 80) { context.beginPath(); context.moveTo(particles[first].x, particles[first].y); context.lineTo(particles[second].x, particles[second].y); context.strokeStyle = `rgba(255,255,255,${(1 - distance / 80) * 0.06})`; context.lineWidth = 0.5; context.stroke() }
      }
    }
    animationFrame = requestAnimationFrame(draw)
  }
  draw()
  stop = () => { cancelAnimationFrame(animationFrame); window.removeEventListener('resize', resize) }
  // #endif
})

onUnmounted(() => stop?.())
</script>

<template>
  <canvas id="auth-particle-canvas" canvas-id="auth-particle-canvas" class="particle-canvas" />
</template>

<style scoped>
.particle-canvas{position:fixed;z-index:0;inset:0;width:100vw;height:100vh;opacity:.7;pointer-events:none}
</style>
