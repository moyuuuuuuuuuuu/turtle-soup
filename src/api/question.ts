import request from '@/utils/http'
import { QuestionSourceEnum, QuestionStatusEnum } from '@/enums/questionEnum'

export interface Translation {
  language: string
  title?: string
  surface?: string
  bottom?: string
  content?: string
}

export interface QuestionPayload {
  id?: number
  version?: number
  difficulty: number
  min_players: number
  max_players: number
  source_type: QuestionSourceEnum
  status?: QuestionStatusEnum
  translations: Translation[]
  points: Array<{ weight: number; is_required: boolean; sort: number; translations: Translation[] }>
  hints: Array<{ level: number; translations: Translation[] }>
  tag_ids: number[]
}

export default {
  list: (params: Record<string, unknown>) =>
    request.get<{ items: Record<string, any>[]; total: number }>({
      url: '/core/question/index',
      params
    }),
  read: (id: number) =>
    request.get<QuestionPayload>({ url: '/core/question/read', params: { id } }),
  save: (data: QuestionPayload) =>
    request.post<QuestionPayload>({ url: '/core/question/save', data }),
  update: (data: QuestionPayload) =>
    request.put<QuestionPayload>({ url: '/core/question/update', data }),
  remove: (id: number) => request.del({ url: '/core/question/destroy', data: { id } }),
  publish: (id: number) => request.post({ url: '/core/question/publish', data: { id } }),
  offline: (id: number) => request.post({ url: '/core/question/offline', data: { id } }),
  preview: (id: number, finished: boolean) =>
    request.get<Record<string, any>>({ url: '/core/question/preview', params: { id, finished } }),
  tags: () => request.get<Record<string, any>[]>({ url: '/core/questionTag/index' }),
  createAiTask: (data: Record<string, unknown>) =>
    request.post<Record<string, any>>({ url: '/core/questionAi/create', data }),
  aiTask: (id: string) =>
    request.get<Record<string, any>>({ url: '/core/questionAi/read', params: { id } }),
  retryAiTask: (id: string) =>
    request.post<Record<string, any>>({ url: '/core/questionAi/retry', data: { id } }),
  adoptAiTask: (id: string) =>
    request.post<QuestionPayload>({ url: '/core/questionAi/adopt', data: { id } })
}
