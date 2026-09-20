import { existsSync, readdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs'
import { resolve } from 'node:path'
import process from 'node:process'

const outputDirectory = resolve('dist/build/mp-toutiao')
const maximumMainPackageSize = 4 * 1024 * 1024
const assetBaseUrl = (process.env.VITE_ASSET_BASE_URL || 'https://turtle-soup.bj.bcebos.com/src').replace(/\/$/, '')

if (!existsSync(outputDirectory))
  throw new Error(`Douyin build output was not found: ${outputDirectory}`)

// Design exports and oversized local art are not runtime assets of the mini program.
const removableStaticPaths = [
  'static/brand/miniapp-icons',
  'static/hgt',
  'static/logo.svg',
]
for (const relativePath of removableStaticPaths) {
  rmSync(resolve(outputDirectory, relativePath), { recursive: true, force: true })
}

// Keep only small brand marks locally if present; remote base serves the rest.
// Large logos under static/brand are optional local leftovers — strip non-essential files.
const brandDirectory = resolve(outputDirectory, 'static/brand')
if (existsSync(brandDirectory)) {
  for (const entry of readdirSync(brandDirectory, { withFileTypes: true })) {
    if (entry.isDirectory())
      continue
    const filePath = resolve(brandDirectory, entry.name)
    const { size } = statSync(filePath)
    // favicon / small marks only; multi-MB logos must come from BOS
    if (size > 200 * 1024)
      rmSync(filePath, { force: true })
  }
}

function walkFiles(directory, files = []) {
  for (const entry of readdirSync(directory, { withFileTypes: true })) {
    const entryPath = resolve(directory, entry.name)
    if (entry.isDirectory())
      walkFiles(entryPath, files)
    else files.push(entryPath)
  }
  return files
}

const rewriteExtensions = new Set(['.js', '.json', '.css', '.ttss', '.wxml', '.ttml', '.wxss', '.qss', '.qs', '.html'])
const staticPathPattern = /(?<!https?:\/\/[^\s"')\]]*)\/static\//g

for (const filePath of walkFiles(outputDirectory)) {
  const extension = filePath.slice(filePath.lastIndexOf('.')).toLowerCase()
  if (!rewriteExtensions.has(extension))
    continue
  const original = readFileSync(filePath, 'utf8')
  if (!original.includes('/static/'))
    continue
  const next = original.replace(staticPathPattern, `${assetBaseUrl}/static/`)
  if (next !== original)
    writeFileSync(filePath, next)
}

const appConfigPath = resolve(outputDirectory, 'app.json')
const appConfig = JSON.parse(readFileSync(appConfigPath, 'utf8'))
let standardNavigationPageCount = 0

for (const page of appConfig.pages || []) {
  const pageConfigPath = resolve(outputDirectory, `${page}.json`)
  if (!existsSync(pageConfigPath))
    continue

  const pageConfig = JSON.parse(readFileSync(pageConfigPath, 'utf8'))
  if (pageConfig.navigationStyle !== 'custom')
    continue

  delete pageConfig.navigationStyle
  writeFileSync(pageConfigPath, `${JSON.stringify(pageConfig, null, 2)}\n`)
  standardNavigationPageCount += 1
}

function directorySize(directory) {
  return readdirSync(directory, { withFileTypes: true }).reduce((total, entry) => {
    const entryPath = resolve(directory, entry.name)
    return total + (entry.isDirectory() ? directorySize(entryPath) : statSync(entryPath).size)
  }, 0)
}

const outputSize = directorySize(outputDirectory)
if (outputSize > maximumMainPackageSize) {
  throw new Error(`Douyin main package is ${(outputSize / 1024 / 1024).toFixed(2)} MiB; maximum is 4 MiB`)
}

console.log(`Douyin upload package prepared: ${(outputSize / 1024 / 1024).toFixed(2)} MiB, asset base ${assetBaseUrl}, ${standardNavigationPageCount} pages use standard navigation`)
