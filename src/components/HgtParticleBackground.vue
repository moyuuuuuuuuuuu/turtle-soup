<script setup lang="ts">
/* eslint-disable style/max-statements-per-line */
interface Particle { x: number, y: number, vx: number, vy: number, size: number, life: number, maxLife: number, phase: number }

let stopAnimation: (() => void) | undefined

function createParticle(width: number, height: number, randomLife = false): Particle {
  const maxLife = Math.random() * 900 + 900
  return {
    x: Math.random() * width,
    y: Math.random() * height,
    vx: (Math.random() - 0.5) * 0.22,
    vy: (Math.random() - 0.5) * 0.18,
    size: Math.random() * 1.7 + 0.35,
    life: randomLife ? Math.random() * maxLife : 0,
    maxLife,
    phase: Math.random() * Math.PI * 2,
  }
}

function createParticles(width: number, height: number, count: number): Particle[] {
  return Array.from({ length: count }, () => createParticle(width, height, true))
}

function moveParticle(particle: Particle, width: number, height: number) {
  particle.x += particle.vx + Math.sin(particle.life * 0.012 + particle.phase) * 0.045
  particle.y += particle.vy + Math.cos(particle.life * 0.01 + particle.phase) * 0.035
  particle.life++

  const margin = 8
  if (particle.x < -margin)
    particle.x = width + margin
  else if (particle.x > width + margin)
    particle.x = -margin
  if (particle.y < -margin)
    particle.y = height + margin
  else if (particle.y > height + margin)
    particle.y = -margin
}

function particleOpacity(particle: Particle, strength: number): number {
  const lifetime = Math.sin((particle.life / particle.maxLife) * Math.PI)
  const twinkle = 0.72 + Math.sin(particle.life * 0.035 + particle.phase) * 0.28
  return Math.max(0, lifetime * twinkle * strength)
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
  const draw = () => {
    const lightTheme = document.documentElement.classList.contains('hgt-light-theme')
    const particleRgb = lightTheme ? '28,28,26' : '255,255,255'
    context.clearRect(0, 0, canvas.width, canvas.height)
    particles.forEach((particle, index) => {
      moveParticle(particle, canvas.width, canvas.height)
      const opacity = particleOpacity(particle, 0.55)
      context.beginPath()
      context.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2)
      context.fillStyle = `rgba(${particleRgb},${Math.max(0, opacity)})`
      context.fill()
      if (particle.life >= particle.maxLife)
        particles[index] = createParticle(canvas.width, canvas.height)
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
          context.strokeStyle = `rgba(${particleRgb},${(1 - distance / 80) * 0.08})`
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

  // #ifdef MP-WEIXIN || MP-TOUTIAO
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
        moveParticle(particle, item.width!, item.height!)
        const opacity = particleOpacity(particle, 0.5)
        context.beginPath()
        context.arc(particle.x, particle.y, particle.size, 0, Math.PI * 2)
        context.fillStyle = `rgba(255,255,255,${Math.max(0, opacity)})`
        context.fill()
        if (particle.life >= particle.maxLife)
          particles[index] = createParticle(item.width!, item.height!)
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
