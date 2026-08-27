export enum QuestionStatusEnum {
  Draft = 'draft',
  Published = 'published',
  Offline = 'offline'
}

export enum QuestionSourceEnum {
  Manual = 'manual',
  Ai = 'ai',
  AuthorizedSeed = 'authorized_seed',
  LtdaCcBySa = 'ltda_cc_by_sa'
}

export enum AiTaskStatusEnum {
  Pending = 'pending',
  Processing = 'processing',
  Succeeded = 'succeeded',
  Failed = 'failed'
}

export enum QuestionLanguageEnum {
  SimplifiedChinese = 'zh-CN',
  English = 'en-US'
}

export enum QuestionRiskLevelEnum {
  Safe = 'safe',
  Caution = 'caution',
  Restricted = 'restricted'
}

export enum QuestionRiskTypeEnum {
  Death = 'death',
  Violence = 'violence',
  Gore = 'gore',
  SelfHarm = 'self_harm',
  Sexual = 'sexual',
  ChildSafety = 'child_safety',
  Discrimination = 'discrimination',
  Illegal = 'illegal',
  Substance = 'substance',
  Other = 'other'
}

export const QUESTION_STATUS_LABELS: Record<QuestionStatusEnum, string> = {
  [QuestionStatusEnum.Draft]: '草稿',
  [QuestionStatusEnum.Published]: '已发布',
  [QuestionStatusEnum.Offline]: '已下架'
}

export const QUESTION_SOURCE_LABELS: Record<QuestionSourceEnum, string> = {
  [QuestionSourceEnum.Manual]: '人工录入',
  [QuestionSourceEnum.Ai]: 'AI 生成',
  [QuestionSourceEnum.AuthorizedSeed]: '授权题库',
  [QuestionSourceEnum.LtdaCcBySa]: '小乌龟侦探社'
}

export const AI_TASK_STATUS_LABELS: Record<AiTaskStatusEnum, string> = {
  [AiTaskStatusEnum.Pending]: '等待中',
  [AiTaskStatusEnum.Processing]: '解析中',
  [AiTaskStatusEnum.Succeeded]: '已完成',
  [AiTaskStatusEnum.Failed]: '失败'
}

export const QUESTION_LANGUAGE_LABELS: Record<QuestionLanguageEnum, string> = {
  [QuestionLanguageEnum.SimplifiedChinese]: '简体中文',
  [QuestionLanguageEnum.English]: '英语'
}

export const QUESTION_DIFFICULTY_LABELS: Record<number, string> = {
  1: '非常简单',
  2: '简单',
  3: '中等',
  4: '困难',
  5: '非常困难'
}

export const QUESTION_RISK_LEVEL_LABELS: Record<QuestionRiskLevelEnum, string> = {
  [QuestionRiskLevelEnum.Safe]: '安全',
  [QuestionRiskLevelEnum.Caution]: '谨慎发布',
  [QuestionRiskLevelEnum.Restricted]: '限制内容'
}

export const QUESTION_RISK_TYPE_LABELS: Record<QuestionRiskTypeEnum, string> = {
  [QuestionRiskTypeEnum.Death]: '死亡',
  [QuestionRiskTypeEnum.Violence]: '暴力',
  [QuestionRiskTypeEnum.Gore]: '血腥',
  [QuestionRiskTypeEnum.SelfHarm]: '自伤',
  [QuestionRiskTypeEnum.Sexual]: '性内容',
  [QuestionRiskTypeEnum.ChildSafety]: '未成年人',
  [QuestionRiskTypeEnum.Discrimination]: '歧视',
  [QuestionRiskTypeEnum.Illegal]: '违法',
  [QuestionRiskTypeEnum.Substance]: '成瘾物',
  [QuestionRiskTypeEnum.Other]: '其他'
}

export const QUESTION_RISK_LEVEL_OPTIONS = Object.values(QuestionRiskLevelEnum).map((value) => ({
  value,
  label: QUESTION_RISK_LEVEL_LABELS[value]
}))

export const QUESTION_RISK_TYPE_OPTIONS = Object.values(QuestionRiskTypeEnum).map((value) => ({
  value,
  label: QUESTION_RISK_TYPE_LABELS[value]
}))

export const QUESTION_STATUS_OPTIONS = Object.values(QuestionStatusEnum).map((value) => ({
  value,
  label: QUESTION_STATUS_LABELS[value]
}))

export function enumLabel<T extends string>(labels: Record<T, string>, value: unknown): string {
  return typeof value === 'string' && value in labels ? labels[value as T] : String(value ?? '-')
}
