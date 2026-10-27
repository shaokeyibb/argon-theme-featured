<div align="center">

# Argon Theme Featured

轻盈的外观，丰富的表达。为现代 WordPress 打造的个人博客主题。

[![CI](https://github.com/shaokeyibb/argon-theme-featured/actions/workflows/modernization.yml/badge.svg?branch=dev)](https://github.com/shaokeyibb/argon-theme-featured/actions/workflows/modernization.yml)
[![Release](https://img.shields.io/github/v/release/shaokeyibb/argon-theme-featured)](https://github.com/shaokeyibb/argon-theme-featured/releases/latest)
[![WordPress](https://img.shields.io/badge/WordPress-6.6%2B-21759b)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPLv3-blue)](LICENSE)

[**下载主题**](https://github.com/shaokeyibb/argon-theme-featured/releases/latest/download/argon-theme-featured.zip) · [更新日志](https://github.com/shaokeyibb/argon-theme-featured/releases) · [使用文档](#文档) · [反馈问题](https://github.com/shaokeyibb/argon-theme-featured/issues)

</div>

![Argon 主题预览](screenshot.png)

## 为写作与阅读而设计

在 [Argon](https://github.com/solstice23/argon-theme) 的简洁外观上，完善现代编辑、图片阅读与评论互动。保留经典 PHP 模板，前台使用原生 JavaScript，增强功能按需加载。

| | |
| --- | --- |
| **舒适阅读** | 响应式布局、深色模式、文章目录、阅读时间、丰富的外观设置 |
| **图片叙事** | 连续图片瀑布流、长图预览与平滑展开、完整图注、触控灯箱 |
| **技术写作** | 代码高亮、复制与全屏、KaTeX 公式、中英文自动排版 |
| **自由表达** | 区块与短代码、说说、时间线、标签页、折叠块、AI 摘要 |
| **交互文档** | 在隔离沙箱中嵌入完整 HTML/CSS/JavaScript，保留主题导航与评论 |
| **社区互动** | AJAX 评论、嵌套回复、编辑历史、点赞、悄悄话、邮件通知、Discourse 集成 |

原生支持 WordPress 响应式图片、区块编辑器、自动保存与修订。保留经典编辑器及历史短代码兼容；AI 摘要、邮件与论坛等扩展功能需自行配置相应服务。

## 安装

需要 **WordPress 6.6+、PHP 8.2+**，并启用 PHP DOM、mbstring 扩展。使用已构建发行包无需安装 Node.js 或 Composer。

1. 下载最新版 [argon-theme-featured.zip](https://github.com/shaokeyibb/argon-theme-featured/releases/latest/download/argon-theme-featured.zip)。
2. 在 WordPress「外观 → 主题 → 添加新主题 → 上传主题」中安装并启用。
3. 打开 Argon 主题选项，配置外观、文章与评论功能。

升级前备份主题设置及自定义文件；更新后清理页面和 CDN 缓存。发行包附有 SHA-256 校验文件，请使用 release 附件，而非 GitHub 自动生成的 Source code 压缩包。

从旧版迁移时，自定义 jQuery/PJAX 脚本、复杂 MathJax 宏和历史 HTML 附件需按[迁移指南](docs/MODERNIZATION.md)核对。

## 文档

| 指南 | 内容 |
| --- | --- |
| [HTML 文档文章](docs/html-document.md) | 交互页面、沙箱权限、替代正文与资源控制 |
| [现代化与迁移](docs/MODERNIZATION.md) | 架构、兼容性及旧版升级 |
| [系统与浏览器识别](docs/USER_AGENT.md) | UA / Client Hints 支持范围与识别限制 |
| [开发、测试与发布](https://github.com/shaokeyibb/argon-theme-featured/blob/dev/tests/README.md) | 隔离 WordPress、浏览器回归与安装包验证 |
| [审查记录](docs/REVIEW.md) | 回归检查、修复与验证边界 |

亦可参考 [Argon 原版文档](https://argon-docs.solstice23.top/)；构建方式和新版特性以本仓库说明为准。反馈问题时，请附上主题、WordPress、PHP 和浏览器版本、复现步骤及脱敏后的报错。

## 开发

使用 **Node.js ≥ 24.18.0、npm ≥ 11.16.0**，在仓库根目录运行：

```sh
npm ci
npm run build
npm test
npm run package
```

前台源码位于 `assets/src/modern/`，编辑器位于 `gutenberg/src/`，PHP 功能位于 `inc/`。`npm run dev` 监听前台构建；发行包输出到 `release/`。浏览器、PHP 和安装包测试的准备步骤见[测试说明](https://github.com/shaokeyibb/argon-theme-featured/blob/dev/tests/README.md)。

## 致谢与许可

基于 [solstice23/argon-theme](https://github.com/solstice23/argon-theme)，感谢原作者与社区贡献者。项目采用 [GPLv3](LICENSE)，第三方组件及其许可见 [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md)。
