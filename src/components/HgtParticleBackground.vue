<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
interface Particle { x: number, y: number, vx: number, vy: number, size: number, life: number, maxLife: number }

let stopAnimation: (() => void) | undefined

function createParticles(width: number, height: number, count: number): Particle[] {
  return Array.from({ length: count }, () => ({
    x: Math.random() * width,
    y: Math.random() * height,
    vx: (Math.random() - 0.5) * 0.3,
    vy: -Math.random() * 0.5 - 0.1,
    size: Math.random() * 1.5 + 0.3,
    life: Math.random() * 500,
    maxLife: Math.random() * 300 + 200,
  }))
}

onMounted(() => {
  // #ifdef H5
  const host = document.querySelector<HTMLElement>('#hgt-particles')
  const canvas = host instanceof HTMLCanvasElement ? host : host?.querySelector<HTMLCanvasElement>('canvas')
  const context = canvas?.getContext('2d')
  if (!canvas || !context)
    return
  let frame = 0
  let particles: Particle[] = []
  const resize = () => {
    canvas.width = window.innerWidth
    canvas.height = window.innerHeight
    particles = createParticles(canvas.width, canvas.height, 80)
  }
  const spawn = (): Particle => ({ x: Math.random() * canvas.width, y: canvas.height + 4, vx: (Math.random() - 0.5) * 0.3, vy: -Math.random() * 0.5 - 0.1, size: Math.random() * 1.5 + 0.3, life: 0, maxLife: Math.random() * 300 + 200 })
  const draw = () => {
    context.clearRect(0, 0, canvas.width, canvas.height)
    particles.forEach((particle, index) => {
      particle.x += particle.vx
      particle.y += particle.vy
      particle.life++
      const opacity = Math.sin((particle.life / particle.maxLife) * Math.PI) * 0.5
      context.beginPath()
      context.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2)
      context.fillStyle = `rgba(255,255,255,${Math.max(0, opacity)})`
      context.fill()
      if (particle.life >= particle.maxLife)
        particles[index] = spawn()
    })
    for (let i = 0; i < particles.length; i++) {
      for (let j = i + 1; j < particles.length; j++) {
        const dx = particles[i].x - particles[j].x
        const dy = particles[i].y - particles[j].y
        const distance = Math.sqrt(dx * dx + dy * dy)
        if (distance < 80) {
          context.beginPath()
          context.moveTo(particles[i].x, particles[i].y)
          context.lineTo(particles[j].x, particles[j].y)
          context.strokeStyle = `rgba(255,255,255,${(1 - distance / 80) * 0.06})`
          context.lineWidth = 0.5
          context.stroke()
        }
      }
    }
    frame = requestAnimationFrame(draw)
  }
  resize()
  window.addEventListener('resize', resize)
  draw()
  stopAnimation = () => { cancelAnimationFrame(frame); window.removeEventListener('resize', resize) }
  // #endif

  // #ifdef MP-WEIXIN
  const query = uni.createSelectorQuery()
  query.select('#hgt-particles').fields({ node: true, size: true }, (result) => {
    const item = result as { node?: any, width?: number, height?: number } | undefined
    if (!item?.node || !item.width || !item.height)
      return
    const canvas = item.node
    const context = canvas.getContext('2d')
    const density = uni.getWindowInfo().pixelRatio || 1
    canvas.width = item.width * density
    canvas.height = item.height * density
    context.scale(density, density)
    const particles = createParticles(item.width, item.height, 36)
    let frame = 0
    const draw = () => {
      context.clearRect(0, 0, item.width, item.height)
      particles.forEach((particle, index) => {
        particle.x += particle.vx
        particle.y += particle.vy
        particle.life++
        const opacity = Math.sin((particle.life / particle.maxLife) * Math.PI) * 0.45
        context.beginPath()
        context.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2)
        context.fillStyle = `rgba(255,255,255,${Math.max(0, opacity)})`
        context.fill()
        if (particle.life >= particle.maxLife)
          particles[index] = createParticles(item.width!, item.height!, 1)[0]
      })
      frame = canvas.requestAnimationFrame(draw)
    }
    draw()
    stopAnimation = () => canvas.cancelAnimationFrame(frame)
  })
  query.exec()
  // #endif
})

onUnmounted(() => stopAnimation?.())
</script>

<template>
  <canvas id="hgt-particles" canvas-id="hgt-particles" type="2d" class="hgt-particles" />
</template>

<style scoped>
.hgt-particles { position: fixed; inset: 0; width: 100vw; height: 100vh; pointer-events: none; opacity: .7; z-index: 0; }
</style>
