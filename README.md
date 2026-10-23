# Argon Theme Featured 3

基于 Argon 的 WordPress 主题，保留经典 PHP 模板与原有视觉风格，使用原生 JavaScript、Vite 和 WordPress 官方区块构建工具。支持现代区块编辑器、HTML 文档文章、AI 摘要、Discourse 评论集成和第三方阅读统计。

## 环境

- WordPress 6.6 或更新版本；重点适配 WordPress 7。
- PHP 8.2 或更新版本，需要 DOM、mbstring 扩展。
- 开发构建推荐 Node.js 24；最低 22.22.2。已构建发行包无需 Node.js 或 Composer。

## 安装与升级

从 [Releases](https://github.com/shaokeyibb/argon-theme-featured/releases) 下载包含构建资源的 `argon-theme-featured.zip`，在 WordPress「外观 → 主题」中上传安装。GitHub 自动生成的 Source code 压缩包不等同于经过验证的发行包。

从旧版升级前备份数据库和主题设置，先在测试站验证自定义 CSS、脚本与插件。用新版本完整替换主题目录，并清理页面/CDN 缓存。无需执行 `composer install`。自定义 CDN 必须同步整个构建目录；默认直接使用本地资源。更新检查默认关闭，可在主题设置中选择此 fork 的 GitHub Releases。

文章、评论、短代码、历史阅读数和既有选项不会被批量删除或改写。旧灯箱由 PhotoSwipe 接替；MathJax 配置迁移为 KaTeX；PJAX 改为浏览器原生导航；主题自建阅读计数停止写入。自定义 `window.pjaxLoaded`、jQuery 脚本和高级 TeX 命令需要按[迁移说明](docs/MODERNIZATION.md)核对。

## 从源码构建

在仓库根目录运行：

```sh
npm ci
npm run build
npm test
npm run package
```

输出为 `release/argon-theme-featured.zip` 及 SHA-256 校验文件。打包使用运行文件白名单，不包含开发依赖、测试数据和旧 `vendor`。前台与区块编辑器共用根目录的 npm lockfile。

```sh
npm run dev              # 监听前台资源变化，写入 assets/dist
npm run build --workspace gutenberg
npm run test:php         # 隔离 WordPress Playground，默认 WP 7.0 / PHP 8.3
npx playwright install chromium
npm run test:browser     # 自动启动隔离站点并运行浏览器集成测试
npm audit
```

开发模式仍由本地 WordPress 提供页面，不额外启动 Vite HTML 站点。完整测试方式及环境变量见 [tests/README.md](tests/README.md)。

## 文档

- [现代化调查、架构变更、迁移与验证](docs/MODERNIZATION.md)
- [HTML 文档文章](docs/html-document.md)
- [测试和发布](tests/README.md)
- [第三方组件与许可证](THIRD_PARTY_NOTICES.md)
- [Argon 原版文档](https://argon-docs.solstice23.top/)（旧组件和构建说明不再适用）

## 协议

Argon Theme Featured 使用 [GPLv3](LICENSE)。第三方组件保留各自许可证。
