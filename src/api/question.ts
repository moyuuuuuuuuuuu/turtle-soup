import request from '@/utils/http'
import { QuestionRiskLevelEnum, QuestionSourceEnum, QuestionStatusEnum } from '@/enums/questionEnum'

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
  risk_level: QuestionRiskLevelEnum
  risk_types: string[]
  risk_note?: string | null
  translations: Translation[]
  points: Array<{ weight: number; is_required: boolean; sort: number; translations: Translation[] }>
  hints: Array<{ level: number; translations: Translation[] }>
  tag_ids: number[]
}

export interface QuestionTag {
  id: number
  name: string
  slug: string
}
export interface QuestionHistory {
  id: number
  question_id: number
  version: number
  published_by: number
  published_at: string
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
  publish: (id: number, version: number, riskConfirmed = false) =>
    request.post({
      url: '/core/question/publish',
      data: { id, version, risk_confirmed: riskConfirmed }
    }),
  offline: (id: number) => request.post({ url: '/core/question/offline', data: { id } }),
  preview: (id: number) =>
    request.get<Record<string, any>>({ url: '/core/question/preview', params: { id } }),
  answerPreview: (id: number) =>
    request.get<Record<string, any>>({ url: '/core/question/answerPreview', params: { id } }),
  copy: (id: number) => request.post<QuestionPayload>({ url: '/core/question/copy', data: { id } }),
  history: (id: number) =>
    request.get<QuestionHistory[]>({ url: '/core/question/history', params: { id } }),
  historyRead: (id: number, versionId: number) =>
    request.get<Record<string, any>>({
      url: '/core/question/historyRead',
      params: { id, version_id: versionId }
    }),
  historyRestore: (id: number, versionId: number, version: number) =>
    request.post<QuestionPayload>({
      url: '/core/question/historyRestore',
      data: { id, version_id: versionId, version }
    }),
  tags: () => request.get<QuestionTag[]>({ url: '/core/questionTag/index' }),
  saveTag: (data: Partial<QuestionTag>) =>
    request.post<QuestionTag>({ url: '/core/questionTag/save', data }),
  removeTag: (id: number) => request.del({ url: '/core/questionTag/destroy', data: { id } }),
  createAiTask: (data: Record<string, unknown>) =>
    request.post<Record<string, any>>({ url: '/core/questionAi/create', data }),
  aiTask: (id: string) =>
    request.get<Record<string, any>>({ url: '/core/questionAi/read', params: { id } }),
  retryAiTask: (id: string) =>
    request.post<Record<string, any>>({ url: '/core/questionAi/retry', data: { id } }),
  adoptAiTask: (id: string) =>
    request.post<QuestionPayload>({ url: '/core/questionAi/adopt', data: { id } })
}
