import assert from 'node:assert/strict'
import test from 'node:test'
import { loadModule } from './helpers/runtime.mjs'

const errors = loadModule('src/utils/serviceError.ts')
const imports = {
  '@/utils/serviceError': errors,
  '@/config/endpoints': { resolveApiBaseUrl: () => '/api/v1' },
}
const { PlayerApiError } = loadModule('src/api/player.ts', imports)
const { TurtleApiError } = loadModule('src/api/turtle.ts', { ...imports, '@/api/player': {} })

test('HTTP and socket errors show player messages while preserving diagnostic codes', () => {
  for (const ErrorType of [PlayerApiError, TurtleApiError, errors.GameSocketError]) {
    for (const code of ['ai.workflow_timeout', 'ai.invalid_response', 'ai.auth_failed', 'ai.workflow_failed', 'ai.future_error']) {
      const error = new ErrorType(code, 'AI 工作流执行失败')
      assert.equal(error.code, code)
      assert.doesNotMatch(error.message, /ai|人工智能/i)
      assert.match(error.message, /主持人.*重试/)
    }
  }
})

test('other errors keep their messages and socket codes for existing handling', () => {
  assert.equal(new PlayerApiError('auth.token_invalid', '登录已过期').message, '登录已过期')
  assert.equal(new TurtleApiError('room.full', '房间已满').message, '房间已满')
  assert.equal(new errors.GameSocketError('room.full').message, 'room.full')
})
