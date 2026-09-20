/** Keep service error codes available for diagnostics, with player-facing messages. */
export function serviceErrorMessage(code: string, message: string): string {
  if (code === 'ai.workflow_timeout')
    return '主持人回应超时，请稍后重试'
  if (code.startsWith('ai.'))
    return '主持人暂时无法回应，请稍后重试'
  return message
}

export class GameSocketError extends Error {
  constructor(public readonly code: string) {
    super(serviceErrorMessage(code, code))
    this.name = 'GameSocketError'
  }
}
