import { readFileSync } from 'node:fs'
import vm from 'node:vm'
import ts from 'typescript'

export function loadModule(path, imports = {}, globals = {}, transform = source => source) {
  const source = transform(readFileSync(new URL(`../../${path}`, import.meta.url), 'utf8'))
  const { outputText } = ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
  })
  const exports = {}
  vm.runInNewContext(outputText, {
    exports,
    require: (name) => {
      if (!(name in imports))
        throw new Error(`Missing test dependency: ${name}`)
      return imports[name]
    },
    ...globals,
  }, { filename: path })
  return exports
}

export async function flushPromises() {
  for (let i = 0; i < 30; i++)
    await Promise.resolve()
}

export function createClock() {
  let nextId = 0
  const timers = new Map()
  return {
    setTimeout(fn, delay) {
      const id = ++nextId
      timers.set(id, { fn, delay })
      return id
    },
    clearTimeout: id => timers.delete(id),
    setInterval: () => ++nextId,
    clearInterval: () => {},
    run(delay) {
      for (const [id, timer] of [...timers]) {
        if (timer.delay === delay) {
          timers.delete(id)
          timer.fn()
        }
      }
    },
  }
}
