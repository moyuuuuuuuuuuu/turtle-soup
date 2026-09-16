/**
 * 平台能力开关。
 * 个人主体小程序未开放「房间/多人联机」类目，打包微信/抖音小程序时隐藏公共房间入口。
 */
export const supportsPublicRooms = (() => {
  // #ifdef MP-WEIXIN || MP-TOUTIAO
  return false
  // #endif
  // #ifndef MP-WEIXIN || MP-TOUTIAO
  return true
  // #endif
})()
