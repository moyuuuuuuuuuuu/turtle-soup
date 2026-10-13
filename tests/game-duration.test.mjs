import assert from 'node:assert/strict'
import test from 'node:test'
import { loadModule } from './helpers/runtime.mjs'

const { readGameDuration } = loadModule('src/utils/gameDuration.ts')

test('reopened records use fixed server timing across history pages', async () => {
  const pages = []
  const duration = await readGameDuration('finished', async ({ page }) => {
    pages.push(page)
    return {
      items: page === 1 ? [{ id: 'other', duration_seconds: 6 }] : [{ id: 'finished', duration_seconds: 53 }],
      pagination: { total_pages: 2 },
    }
  })
  assert.equal(duration, 53)
  assert.deepEqual(pages, [1, 2])
})

test('ongoing records restore cumulative time, including zero', async () => {
  for (const seconds of [0, 53, 3600]) {
    assert.equal(await readGameDuration('active', async () => ({
      items: [{ id: 'active', duration_seconds: seconds }],
      pagination: { total_pages: 1 },
    })), seconds)
  }
})

test('missing or invalid timing stays unavailable instead of starting from zero', async () => {
  for (const seconds of [undefined, -1, Number.NaN, Number.POSITIVE_INFINITY]) {
    assert.equal(await readGameDuration('finished', async () => ({
      items: [{ id: 'finished', duration_seconds: seconds }],
      pagination: { total_pages: 1 },
    })), null)
  }
  assert.equal(await readGameDuration('missing', async () => ({ items: [], pagination: { total_pages: 2 } })), null)
  await assert.rejects(readGameDuration('finished', async () => {
    throw new Error('offline')
  }), /offline/)
})

const { formatDuration } = loadModule('src/utils/gameStatus.ts')

test('zero is a real duration and unavailable values remain distinct', () => {
  assert.equal(formatDuration(0), '00:00')
  assert.equal(formatDuration(53), '00:53')
  assert.equal(formatDuration(null), '—')
  assert.equal(formatDuration(undefined), '—')
})

test('switching question and truth modes preserves independent drafts', () => {
  const state = loadModule('src/pages/game/index.vue', {}, {
    ref: value => ({ value }),
    canInput: { value: true },
    notesDrawerOpen: { value: false },
    mobileSurfaceOpen: { value: false },
  }, (source) => {
    const declarations = source.match(/const (?:question|truthDraft|inputMode) = ref[^\n]+/g).join('\n')
    const functions = ['openTruthStage', 'cancelTruthStage'].map(name => source.match(new RegExp(`function ${name}\\(\\) \\{[\\s\\S]*?\\n\\}`))[0]).join('\n')
    return `${declarations}\n${functions}\nexport { question, truthDraft, inputMode, openTruthStage, cancelTruthStage }`
  })
  state.question.value = '这是一道文字游戏吗？'
  state.openTruthStage()
  state.truthDraft.value = '完整故事和关键因果'
  state.cancelTruthStage()
  assert.equal(state.question.value, '这是一道文字游戏吗？')
  state.openTruthStage()
  assert.equal(state.truthDraft.value, '完整故事和关键因果')
})
