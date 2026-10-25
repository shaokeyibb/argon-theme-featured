# 测试与发布

所有 PHP 和浏览器测试均创建独立的 WordPress Playground + SQLite 站点；只挂载本仓库读取源码并向 `temp/` 写报告，不连接现有 WordPress 数据库。测试会创建用户、文章和评论并修改主题选项，禁止把测试 PHP 脚本直接载入生产站点。邮件被拦截，AI 使用 WordPress HTTP API 的模拟响应。

## 准备与构建

使用 Node.js ≥ 24.18.0、npm ≥ 11.16.0，在根目录运行：

```sh
npm ci
npm run build
npm test
npm audit
```

不再需要 Composer、pnpm、Parcel 或全局 PHP。`npm test` 检查发行文件白名单、manifest 引用、全部本地 CSS 资源路径、孤立字体、区块 metadata、旧运行库意外回归，颜色转换、资源体积预算，以及评论时间格式与更新边界。

## PHP 集成测试

```sh
npm run test:php
node scripts/test-php.mjs security
node scripts/test-php.mjs html-document modernization
node scripts/test-php.mjs user-agent
```

默认 WordPress 7.0 / PHP 8.3，可通过环境变量切换。在 PowerShell 中：

```powershell
$env:ARGON_TEST_WP = '7.0'
$env:ARGON_TEST_PHP = '8.5'
npm run test:php
```

在 POSIX shell 中：

```sh
ARGON_TEST_WP=6.6 ARGON_TEST_PHP=8.2 npm run test:php
```

脚本要求命令成功、报告产生于本次执行且 `failed` 为零，结果保存在 `temp/<suite>-report-<php>-wp-<wp>.json`。同一工作目录不要并行运行两个 PHP 版本相同、WordPress 版本不同的任务，它们会共享中间报告名。版本选择如 `7.0` 会解析为该分支可用的补丁版，实际版本以报告为准。

| 套件 | 检查数 | 主要覆盖 |
| --- | ---: | --- |
| security | 102 | nonce、缓存页面 nonce 刷新和同源限制、对象权限、匿名入口、元数据白名单、KSES/XSS、验证码、评论写入/编辑/历史/点赞/置顶、远程请求失败 |
| html-document | 50 | HTML 文档权限、媒体源码保护、短代码和区块、修订预览、替代正文、隔离桥接、索引与摘要 |
| modernization | 28 | PHP 语法与启动期诊断、原生主题支持与 Logo、API 3、资源构建、现有配色与外观选项到 Global Styles 的映射、REST 修订元数据、无读取计数写入、Unicode 保真、图片/交互属性、AI 保存时序/撤回/失败、动态首图、主查询范围、分享链接编码和平台筛选 |
| user-agent | 127 | 123 个 UA/Client Hints 样本，以及评论请求头保存、旧评论回退、转义、隐藏选项与 PHP 8.5 空字段处理 |

现代化测试在主题加载前安装诊断 MU 插件，启用 E_ALL 并收集主题调用栈产生的 PHP 警告、弃用和 WordPress doing_it_wrong 通知；这些诊断均视为失败。AI 测试验证请求与响应处理，不代表实际服务凭据、配额或特定模型可用。

## 浏览器集成测试

```sh
npx playwright install --with-deps chromium firefox webkit
npm run test:browser
```

自动启动 `127.0.0.1:9400` 的一次性 WordPress 站点，使用 6 个 PHP worker，禁止 WP-Cron。测试等待 Playground 完成全部初始化步骤并输出就绪消息后开始，避免首页已响应但测试数据尚未生成的启动竞态。初始化脚本使用不含 `..` 的绝对路径创建输出目录，并检查写入结果，因此全新检出也无需预建 `temp/`。每次运行创建新站点，请保持测试端口空闲。Windows 已有 Chrome 时可设置 `$env:ARGON_BROWSER_CHANNEL = 'chrome'`。该站点的测试账号只用于此隔离实例。

基础流程覆盖桌面和手机页面及搜索、Banner 与分页几何尺寸、滚动导航、浮动按钮、目录分支跟随/折叠与锚点跳转、延迟图片加载后的折叠、响应式宽度过渡中的短内容与缩短后的自动取消折叠、展开按钮边界与键盘操作、缓存 nonce 刷新、分类菜单点击、日间/夜间组件配色、实际评论点赞前后的图标颜色、展开/收起和淡入淡出动画、快速反向操作与减少动态效果偏好、分享平台地址与无脚本回退、系统分享成功/取消/失败、带缓存查询参数的脚本与单次复制、无 jQuery/无 randomUUID 的前台、代码折行/行号/复制/全屏降级、公式、折叠块/下拉/标签页、混合尺寸画廊及核心灯箱、自动空格、深色模式持久化、评论提交与编辑/回复/管理、HTML 文档沙箱、嵌套网格在手机/桌面与无脚本时的宽度、长代码局部滚动、设置导入导出、区块编辑器与旧序列化兼容、文章元数据保存、保存失败阻止 AI 请求及无 JavaScript 阅读。旧区块和配色基准来自旧版真实构建产物，避免使用新实现生成自己的兼容性期望。

截图为 `temp/browser-desktop.png`、`temp/browser-mobile.png` 和 `temp/browser-editor.png`；失败 trace 位于 `temp/browser-results/`。Chromium 执行完整功能套件；Firefox 和 WebKit 执行 Header、布局、配色、外观选项及历史导航回归。WebKit 引擎测试不等同于真实 iOS/Safari 设备测试；所有插件组合和真实 CDN 环境不在自动验证范围内。

`tests/browser/header.spec.mjs` 另含 5 项 Header 回归：从首页真实文章链接导航，并暂停目标页的主题模块下载，在 `DOMContentLoaded` 前检查透明背景、无阴影和高度，覆盖桌面/手机及日间/夜间模式；恢复脚本后检查滚动着色、模糊和返回顶部/后退，并验证禁用 JavaScript 时保留可读的有色导航。PHP 现代化套件同时检查隐藏 Banner 时输出有色导航的初始状态。

## 视觉与资源回归

`visual-chromium` 项目在 Ubuntu 24.04 / 锁定版本 Chromium 上对桌面、手机、日间、夜间的 Header 和正文作截图比对。基线位于 `tests/browser/snapshots/`。有意修改外观后，在同一操作系统和字体环境生成并人工审阅新基线：

```sh
npx playwright test --project=visual-chromium --update-snapshots
npx playwright test --project=visual-chromium
```

`navigation.spec.mjs` 检查后退/前进后的滚动、工具栏交互、配色状态，以及重载时资源 URL 和字体偏好稳定；附件记录浏览器实际的 `pageshow.persisted` 和资源传输大小。Playground 不保证 HTTP 缓存或 BFCache 命中，不能把通过导航测试解读成生产缓存命中测试。

`npm test` 同时限制前台基础 CSS（主题主样式与共享图标）、静态导入的 JavaScript、编辑器专有 CSS 和编辑器总 CSS 的原始/gzip 体积。计量口径与本次结果见[资源与视觉规范化](../docs/PERFORMANCE-AND-DESIGN.md)。

## 官方性能插件集成

```sh
node scripts/test-php.mjs media
node scripts/test-php.mjs markdown-shortcodes
npx playwright test --config tests/media-playwright.config.mjs
```

隔离站点使用真实插件和 WordPress 图片生成流程，固定 Modern Image Formats 2.7.1、Image Placeholders 1.3.0、Enhanced Responsive Images 1.8.0；浏览器站点另启用 Optimization Detective 1.0.0-beta7、Image Prioritizer 1.0.0-beta4、Speculative Loading 1.7.0、Instant Back/Forward 1.3.3。这些版本的 WordPress 最低要求与主题不同，本套件在 WordPress 7.0 / PHP 8.3 上运行。

14 项 PHP 检查覆盖 Markdown 附件识别、实际 WebP/占位色生成、原生响应式属性、封面模板、首图顺序、已有 picture 与原生区块、外链转义、子尺寸与现代格式 URL、对象缓存及关闭插件后的回退。18 个浏览器流程覆盖原生灯箱与 PhotoSwipe、桌面/手机布局、旧封面淡入后的可见性、无脚本 Banner、缓存重载、推测规则和检测脚本加载，以及 Githuber MD 嵌套短代码中的 Markdown、展开交互与响应式媒体。长图回归使用真实附件与插件生成的 picture，在正文、说说详情和首页说说中检查桌面高度上限、等比缩放、居中、手机无高度限制、作者尺寸与原生区块不被覆盖，并验证禁用脚本及灯箱浏览。图片排版增强还检查相邻段落和同段多图合并、内容宽度断点、混排正文/作者尺寸/原生区块/短代码边界、响应式来源与属性保留、超长图键盘展开/收起、灯箱键盘入口、延迟加载、关闭灯箱、打印完整显示，以及首页和详情说说。瀑布流回归覆盖 1–9、12、25 张图片，长图位于首/中/末、多张长图同时展开、较窄长图、小图、加载失败、容器隐藏恢复、打印，以及布局内顺序、完整性、无重叠和响应式切换。嵌套容器回归覆盖折叠块内的 Markdown、嵌套折叠块、提示框、原生 details 和普通内容容器，验证各自分组、初始隐藏/延迟图片、隐藏期间改变窗口、重新打开与原生区块/作者尺寸/表格/显式排除范围的保留。图注回归还覆盖 figure 与经典图注混排、浏览器修正后的空段落、中英文长文本和连续长链接的完整显示、字号变化后的高度重排、图文居中、低于旧高宽比阈值的超屏图片、桌面容器限宽与窄屏完整宽度。动画回归检查图片顶部离屏后真实点击展开时页面位置不变、向下展开（包括减少动态效果）、可见高度的中间帧、图注跟随、快速反向切换、瀑布流跨列及下文位移、窗口变化和打印中断，并覆盖减少动态效果。使用独立端口 9401，失败 trace 保存到 `temp/media-browser-results/`。

Markdown 短代码套件使用上游 GitHub 发行包中的 Githuber MD 1.17.0，17 项 PHP 检查覆盖块级与行内语法、嵌套、禁用嵌套、代码示例、原有 HTML、特殊数据格式、转义和 URL 安全、切换普通编辑器及插件共享解析器的隔离。另使用本站已安装的 1.17.1 副本在隔离环境验证，不把生产站点作为测试数据写入目标。

2026-10-25 扩展媒体浏览器检查时，在 WordPress 7.0.7 / 上述性能插件组合 / Playwright Firefox 1555 下发现原生 Image 区块灯箱的 import map 加载顺序错误，`@wordpress/interactivity` 无法解析；未修改的 3.0.8 源码也复现相同失败。该组合的完整媒体流程尚未全部通过，不能用长图尺寸与主题 PhotoSwipe 的通过结果代替原生灯箱兼容性结论。

手工预览必须使用与 WordPress 配置相同的来源地址。`localhost` 与 `127.0.0.1` 不同源：混用时普通样式可能仍能加载，但模块脚本、字体会被 CORS 拦截，图片增强也不会运行。若要提供 `localhost` 链接，启动 Playground 时传入 `--site-url=http://localhost:9401`，并按最终提供给用户的完整链接检查脚本加载、网格、展开、居中和控制台错误。内部兼容性测试页面含有刻意排除自动布局的样例，不应直接作为功能演示页。

格式文件由插件生成；测试固定图片允许保留较大的 WebP 以稳定检查包装流程，不修改正式站点的格式选择策略。Image Prioritizer 按真实访客数据逐步优化，测试不会把检测脚本加载成功等同于已完成长期优化。

## 发行包

```sh
npm run package
npm run test:package
```

包内顶层目录为 `argon-theme-featured/`，包含 PHP、区块 metadata、编译资源、语言、表情、许可证和文档。固定文件顺序、时间戳与权限，便于校验同一构建的产物。安装测试将 ZIP 安装到全新的 WordPress 实例，验证激活、页面输出、区块注册和必需文件，不从源码主题目录加载运行资源。

[CI 配置](../.github/workflows/modernization.yml) 执行 npm 审计、构建检查、浏览器测试，以及 WordPress 6.6 / PHP 8.2 和 WordPress 7.0 / PHP 8.2–8.5 的 PHP 矩阵。CI 配置本身不表示远端任务已执行；本地实际验证结果见[现代化报告](../docs/MODERNIZATION.md)。

## 升级检查

完整发布 PHP 与编译资源后清理页面和 CDN 缓存。3.0.1 的公共 AJAX 会先取得当前访客的有效 nonce，服务器须保持 admin-ajax.php POST 响应不被缓存。原始 HTML/CSS 仍要求 `unfiltered_html`，公开计数/点赞 nonce 仍只是请求校验，并不提供反刷限流。

2026-10-21 的旧验证针对重构前版本（Composer、pnpm、PHP 8.1）；其依赖安装方式与基线已被本文替代。原有 96 项安全检查和 50 项 HTML 文档检查仍保留并持续执行。
