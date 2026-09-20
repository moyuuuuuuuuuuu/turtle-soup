/** 百度云 BOS 静态资源基址；末尾不含斜杠。默认对应 bucket 内 src/ 前缀。 */
const DEFAULT_ASSET_BASE = 'https://turtle-soup.bj.bcebos.com/src'

const configuredAssetBaseUrl = import.meta.env.VITE_ASSET_BASE_URL as string | undefined

export const assetBaseUrl = (configuredAssetBaseUrl || DEFAULT_ASSET_BASE).replace(/\/$/, '')

/**
 * 将 `/static/...` 解析为可公网访问的完整地址。
 * 已是 http(s) 的路径原样返回；未配置基址时回退本地路径。
 */
export function resolveAssetUrl(path: string) {
  if (!path)
    return ''
  if (/^https?:\/\//i.test(path))
    return path
  const normalized = path.startsWith('/') ? path : `/${path}`
  return `${assetBaseUrl}${normalized}`
}
