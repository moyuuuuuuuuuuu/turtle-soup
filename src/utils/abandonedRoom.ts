import type { GameSnapshot } from '@/types/game'
import { roomApi } from '@/api/turtle'

/** 放弃后选择返回题库才散队；选择再来一题仍可沿用原房间。 */
export async function exitAbandonedRoom(game: GameSnapshot | null): Promise<boolean> {
  if (game?.status !== 'abandoned' || game.mode !== 'multiplayer' || !game.room_id)
    return false
  const room = await roomApi.read(game.room_id)
  if (room.status !== 'closed') {
    if (room.is_owner)
      await roomApi.close(room.id)
    else
      await roomApi.leave(room.id)
  }
  return true
}
