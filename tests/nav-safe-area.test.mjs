import assert from 'node:assert/strict'
import test from 'node:test'
import { initPreContext, preJs } from '@dcloudio/uni-cli-shared/dist/preprocess/index.js'
import { loadModule } from './helpers/runtime.mjs'

function setup(platform) {
  const info = {
    windowHeight: 844,
    screenHeight: 1440,
    windowWidth: 390,
    statusBarHeight: 47,
    safeArea: { bottom: 844 },
    safeAreaInsets: { bottom: 0 },
  }
  initPreContext(platform)
  const module = loadModule('src/utils/navSafeArea.ts', {}, {
    uni: {
      getWindowInfo: () => info,
      getMenuButtonBoundingClientRect: () => ({ top: 51, height: 32, left: 287 }),
    },
  }, source => preJs(source, 'navSafeArea.ts'))
  return { ...module, info }
}

test('H5 does not treat the difference between screen and browser heights as a safe inset', () => {
  const runtime = setup('h5')
  assert.equal(runtime.resolveShellChromeMetrics().safeBottom, 0)
  runtime.info.windowHeight = 500
  assert.equal(runtime.resolveShellChromeMetrics().viewportHeight, 500)
  assert.equal(runtime.resolveShellChromeMetrics().safeBottom, 0)
})

test('WeChat keeps custom navigation when the keyboard or a desktop window reduces the viewport', () => {
  const runtime = setup('mp-weixin')
  runtime.info.screenHeight = 844
  runtime.info.safeAreaInsets.bottom = 34
  for (const height of [844, 500, 350]) {
    runtime.info.windowHeight = height
    const metrics = runtime.resolveShellChromeMetrics()
    assert.equal(metrics.offset, 87)
    assert.equal(metrics.defaultNav, false)
    assert.equal(metrics.viewportHeight, height)
    assert.equal(metrics.safeBottom, 34)
  }
  runtime.info.safeAreaInsets.bottom = 0
  assert.equal(runtime.resolveShellChromeMetrics().safeBottom, 0)
})

test('Douyin native navigation is never reserved a second time', () => {
  const runtime = setup('mp-toutiao')
  runtime.info.screenHeight = 844
  runtime.info.windowHeight = 756
  assert.equal(runtime.resolveShellChromeMetrics().offset, 0)
  assert.equal(runtime.resolveShellChromeMetrics().defaultNav, true)
  assert.equal(runtime.resolveShellChromeMetrics().viewportHeight, 756)
})
