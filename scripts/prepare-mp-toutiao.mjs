import { existsSync, readdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs'
import { resolve } from 'node:path'

const outputDirectory = resolve('dist/build/mp-toutiao')
const designAssetsDirectory = resolve(outputDirectory, 'static/brand/miniapp-icons')
const maximumMainPackageSize = 4 * 1024 * 1024

if (!existsSync(outputDirectory))
  throw new Error(`Douyin build output was not found: ${outputDirectory}`)

// These source files are app-store icon exports, previews, and an archive. They
// are retained for release work but are not runtime assets of the mini program.
rmSync(designAssetsDirectory, { recursive: true, force: true })

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

console.log(`Douyin upload package prepared: ${(outputSize / 1024 / 1024).toFixed(2)} MiB, ${standardNavigationPageCount} pages use standard navigation`)
