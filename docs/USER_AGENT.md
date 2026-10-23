# 评论中的系统与浏览器识别

在「Argon 主题选项 → 评论者 UA 显示」选择显示平台、浏览器及版本。识别只用于评论信息展示，不用于身份校验、权限或页面功能判断。现有评论会直接使用新规则解析已保存的信息，无需改写评论。

## 3.0.0 的更新

- 共用一份系统版本格式化逻辑，补全 macOS 历史名称并更新至 27 Golden Gate；26 Tahoe、15 Sequoia 等继续识别。Android、iOS、iPadOS 和浏览器版本直接解析数字，不需要每发布一个版本就增加白名单。
- 支持 Windows、macOS、iPhone/iPad/iPod、Android、Chrome OS、明确标识的 HarmonyOS/OpenHarmony、Fuchsia、Tizen、KaiOS、webOS、BSD、Haiku，以及 Ubuntu、Debian、Fedora、Arch、openSUSE、Manjaro、Mint、Red Hat、Gentoo、Kali、Alpine、Rocky、AlmaLinux、NixOS、Pop!_OS、Deepin、UOS 等 Linux 发行版。仅在 UA 明确提供发行版时显示其名称。
- 扩展 Edge/Firefox/Chrome/Opera 的 iOS 标识，Brave、Vivaldi、DuckDuckGo、Samsung Internet、Firefox 衍生浏览器、Electron、Android WebView，以及华为、荣耀、小米、vivo、HeyTap、QQ、夸克、UC、联想、钉钉、支付宝等明确产品标识。保留微信、知乎、旧 IE/Opera、爬虫及命令行客户端回退。
- Safari 使用 `Version/` 的产品版本，不再误用 `Safari/604.1`、`605.1.15` 这类引擎构建号。
- 按字段合并 User-Agent 与 Client Hints。缺少完整版本列表、只收到系统字段、旧评论缺少部分 metadata 时，仍保留可用信息；Chrome/Chromium 兼容标识不会覆盖微信等更具体的产品名称。
- 处理 Client Hints 品牌随机顺序、GREASE 占位品牌和分隔符空格，完整版本优先，只有主版本的提示不覆盖同一产品的已有精确版本。
- 评论保存限制请求头类型与长度，保留 WordPress 请求转义和 metadata 存储语义；输出继续转义，修复 PHP 8.5 对未知平台/浏览器空数组键的弃用警告。

## 能显示到什么程度

主题已请求 `Sec-CH-UA`、`Sec-CH-UA-Platform`、`Sec-CH-UA-Platform-Version` 和 `Sec-CH-UA-Full-Version-List`，并在提交评论时保存浏览器实际发送的字段。高熵 Client Hints 通常需要 HTTPS 及浏览器支持，也可能受代理、缓存层、隐私设置或权限策略限制。旧评论未保存的信息无法事后恢复。

| 情况 | 显示方式 |
| --- | --- |
| Windows UA 只有 `Windows NT 10.0` | `Windows 10/11`；无法据此区分两个系统 |
| Windows Client Hints 平台版本主号 1–12 / 13+ | 分别显示 Windows 10 / 11；不把 API Contract 版本误当成 23H2、24H2 等发行版 |
| macOS UA 固定为 `10_15_7` 或兼容值 `10_16` | 省略无法确定的系统版本；有效 Client Hints 可恢复实际版本及名称 |
| Android UA 固定为 `Android 10; K` | 省略系统版本；有效 Client Hints 优先 |
| Chrome OS UA 固定为 `14541.0.0` | 省略被冻结的系统构建号 |
| Safari 26+ 的 iOS UA 仍报 `18_6` / `18_7` | 省略冻结的系统版本，显示实际 Safari 产品版本 |
| 浏览器隐藏品牌或使用另一浏览器的 UA | 显示实际可识别的信息；不会猜测 Brave、Arc、Zen 等隐藏品牌 |
| 未知的新系统版本 | 保留数字版本，不套用不匹配的旧名称 |

UA 可以被客户端伪造；这些标签表示请求所报告的信息。iPad 桌面模式、隐私模式及不带专用标识的 Chromium/Firefox 衍生浏览器，不保证能识别真实设备或品牌。

## 回归验证

`node scripts/test-php.mjs user-agent` 运行 123 个解析样本和 4 个 WordPress 保存/显示检查。样本包含冻结值、未来数字版本、缺失字段、畸形类型、完整版本合并、HTML 转义和旧评论；浏览器评论流程另检查提交及刷新后均显示相同的 Windows 11 与 Edge 完整版本。

## 规则依据

- [Apple：macOS 名称和版本](https://support.apple.com/en-us/109033)
- [Microsoft：通过 Client Hints 区分 Windows 10/11](https://learn.microsoft.com/en-us/microsoft-edge/web-platform/how-to-detect-win11)
- [WICG：User-Agent Client Hints](https://wicg.github.io/ua-client-hints/)
- [Chromium：User-Agent Reduction](https://www.chromium.org/updates/ua-reduction/)
- [WebKit：Safari 26 的 UA 变更](https://webkit.org/blog/17333/webkit-features-in-safari-26-0/)
- [WebKit：冻结 iOS 版本的修订](https://bugs.webkit.org/show_bug.cgi?id=298673)
- [Mozilla：Firefox UA 字符串](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/User-Agent/Firefox)

名称与规则核对日期：2026-10-24。
