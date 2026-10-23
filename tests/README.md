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

不再需要 Composer、pnpm、Parcel 或全局 PHP。`npm test` 检查发行文件白名单、manifest 引用、全部本地 CSS 资源路径、孤立字体、区块 metadata、旧运行库意外回归，以及评论时间格式与更新边界。

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
| security | 96 | nonce、对象权限、匿名入口、元数据白名单、KSES/XSS、验证码、评论写入/编辑/历史/点赞/置顶、远程请求失败 |
| html-document | 50 | HTML 文档权限、媒体源码保护、短代码和区块、修订预览、替代正文、隔离桥接、索引与摘要 |
| modernization | 26 | PHP 语法与启动期诊断、原生主题支持与 Logo、API 3、资源构建、REST 修订元数据、无读取计数写入、Unicode 保真、图片/交互属性、AI 保存时序/撤回/失败、动态首图、主查询范围 |
| user-agent | 127 | 123 个 UA/Client Hints 样本，以及评论请求头保存、旧评论回退、转义、隐藏选项与 PHP 8.5 空字段处理 |

现代化测试在主题加载前安装诊断 MU 插件，启用 E_ALL 并收集主题调用栈产生的 PHP 警告、弃用和 WordPress doing_it_wrong 通知；这些诊断均视为失败。AI 测试验证请求与响应处理，不代表实际服务凭据、配额或特定模型可用。

## 浏览器集成测试

```sh
npx playwright install chromium
npm run test:browser
```

自动启动 `127.0.0.1:9400` 的一次性 WordPress 站点，使用 6 个 PHP worker，禁止 WP-Cron。Windows 已有 Chrome 时可设置 `$env:ARGON_BROWSER_CHANNEL = 'chrome'`。该站点的测试账号只用于此隔离实例。

九个流程覆盖桌面和手机页面及搜索、无 jQuery/无 randomUUID 的前台、代码折行/行号/复制/全屏降级、公式、折叠块/下拉/标签页、混合尺寸画廊及核心灯箱、自动空格、深色模式持久化、评论提交与编辑/回复/管理、HTML 文档沙箱、设置导入导出、区块编辑器与旧序列化兼容、文章元数据保存、保存失败阻止 AI 请求及无 JavaScript 阅读。旧区块 fixture 来自旧版真实构建产物的序列化结果，避免使用新实现生成自己的兼容性期望。

截图为 `temp/browser-desktop.png`、`temp/browser-mobile.png` 和 `temp/browser-editor.png`；失败 trace 位于 `temp/browser-results/`。默认测试浏览器为 Chromium；Firefox、Safari、所有插件组合和真实 CDN 环境不在本次自动验证范围内。

## 发行包

```sh
npm run package
npm run test:package
```

包内顶层目录为 `argon-theme-featured/`，包含 PHP、区块 metadata、编译资源、语言、表情、许可证和文档。固定文件顺序、时间戳与权限，便于校验同一构建的产物。安装测试将 ZIP 安装到全新的 WordPress 实例，验证激活、页面输出、区块注册和必需文件，不从源码主题目录加载运行资源。

[CI 配置](../.github/workflows/modernization.yml) 执行 npm 审计、构建检查、浏览器测试，以及 WordPress 6.6 / PHP 8.2 和 WordPress 7.0 / PHP 8.2–8.5 的 PHP 矩阵。CI 配置本身不表示远端任务已执行；本地实际验证结果见[现代化报告](../docs/MODERNIZATION.md)。

## 升级检查

完整发布 PHP 与编译资源后清理页面和 CDN 缓存，避免旧页面持有新接口不接受的 nonce。页面缓存有效期应短于站点 nonce 的有效期。原始 HTML/CSS 仍要求 `unfiltered_html`，公开计数/点赞 nonce 仍只是请求校验，并不提供反刷限流。

2026-10-21 的旧验证针对重构前版本（Composer、pnpm、PHP 8.1）；其依赖安装方式与基线已被本文替代。原有 96 项安全检查和 50 项 HTML 文档检查仍保留并持续执行。
