/* eslint-disable style/max-statements-per-line */
import type { PlayerUser } from '@/api/player'
import { defineStore } from 'pinia'
import { playerApi } from '@/api/player'

export const usePlayerStore = defineStore('player', () => {
  const user = ref<PlayerUser | null>(null)
  const ready = ref(false)
  async function restore() { const result = await playerApi.restore(); user.value = result?.user || null; ready.value = true }
  async function load() { user.value = await playerApi.me() }
  function accept(result: { user: PlayerUser }) { user.value = result.user }
  async function logout(all = false) { await playerApi.logout(all); user.value = null }
  return { user, ready, restore, load, accept, logout }
})
