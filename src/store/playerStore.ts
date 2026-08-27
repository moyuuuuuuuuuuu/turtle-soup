/* eslint-disable style/max-statements-per-line */
import type { PlayerUser } from '@/api/player'
import { defineStore } from 'pinia'
import { ensurePlayerAccessToken, playerApi } from '@/api/player'

export const usePlayerStore = defineStore('player', () => {
  const user = ref<PlayerUser | null>(null)
  const ready = ref(false)
  let restoring: Promise<void> | null = null
  async function restore() {
    if (ready.value && !user.value)
      return
    if (ready.value && user.value) {
      if (!await ensurePlayerAccessToken())
        user.value = null
      return
    }
    if (restoring)
      return restoring
    restoring = playerApi.restore()
      .then((result) => { user.value = result?.user || null; ready.value = true })
      .finally(() => { restoring = null })
    return restoring
  }
  async function load() { user.value = await playerApi.me() }
  function accept(result: { user: PlayerUser }) { user.value = result.user }
  async function logout(all = false) {
    try {
      await playerApi.logout(all)
    }
    finally {
      user.value = null
      ready.value = true
    }
  }
  return { user, ready, restore, load, accept, logout }
})
