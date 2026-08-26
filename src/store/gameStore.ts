/* eslint-disable style/max-statements-per-line */
import type { GameSnapshot } from '@/types/game'; import { defineStore } from 'pinia'

export const useGameStore = defineStore('game', () => { const current = ref<GameSnapshot | null>(null); function setGame(game: GameSnapshot) { current.value = game; uni.setStorageSync('current_game_id', game.id) } function clear() { current.value = null; uni.removeStorageSync('current_game_id') } return { current, setGame, clear } })
