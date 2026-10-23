# Argon Theme Featured

**现代的、更多功能的 Argon Theme。**

延续 [Argon](https://github.com/solstice23/argon-theme) 轻盈、简洁的外观，把写作、阅读和互动能力带到现代 WordPress：原生 JavaScript 前台、现代区块编辑器、可交互的 HTML 文档文章、AI 摘要、丰富的评论功能，以及更完善的系统与浏览器识别。

[下载 3.0.3 正式版](https://github.com/shaokeyibb/argon-theme-featured/releases/tag/v3.0.3) · [安装与升级](#安装与升级) · [功能一览](#功能一览) · [开发与测试](#开发与测试) · [完整文档](#文档与支持)

3.0.3 修复首页封面闪现后消失，让 Markdown 图片、文章封面与 Banner 接入 WordPress 原生响应式图片和官方性能插件，并修复现代格式包装与核心灯箱的布局兼容。详见[图片与性能插件兼容说明](docs/3.0.3.md)。

3.0.2 修复短评论/说说在响应式切换后误折叠的问题，并调整展开按钮的尺寸，避免底部裁切。详见[折叠修复说明](docs/3.0.2.md)。

3.0.1 修复升级后的导航与间距、分页、搜索遮挡、组件配色、目录展开与过渡动画、延迟图片折叠和缓存验证码问题，并改善评论表单、更新分享平台，解决缓存插件导致的交互重复初始化。详见[修复与页面对照报告](docs/3.0.1.md)。

适合个人博客、技术写作、知识分享，以及希望在文章中嵌入交互演示、可视化或小工具的站点。主题保留经典 PHP 模板，同时支持现代 WordPress 区块能力。

## 3.0 带来了什么

| 方向 | 当前能力 |
| --- | --- |
| 现代前台 | 原生 DOM、Fetch 和浏览器 API；Bootstrap 5、Font Awesome 7；支持时启用跨文档 View Transitions |
| 按需加载 | 代码高亮、公式、图片灯箱、二维码和中英文排版按实际使用加载 |
| WordPress 原生集成 | `theme.json`、区块 API 3、编辑器 iframe、REST 元数据、自动保存与修订、原生图片加载优化 |
| 完整 HTML 文章 | 在主题外壳中运行隔离的 HTML/CSS/JavaScript 文档，保留摘要、分享、评论及替代正文 |
| 更丰富的写作与互动 | AI 文章摘要、Discourse 嵌入、外链点击统计、说说、短代码、评论编辑历史和多种互动功能 |
| 持续可维护 | npm workspaces + 单一 lockfile，Vite 前台构建，WordPress 官方区块构建工具，自动化回归及可复现安装包 |

## 功能一览

### 阅读体验与个性化

- **响应式布局**：桌面与手机适配，单栏、双栏及多列文章卡片/瀑布流布局，侧栏、菜单、搜索和返回顶部。
- **主题外观**：深色模式、主题色、字体、背景、Banner、圆角与阴影等设置；读者偏好可保留。
- **长文阅读**：文章目录、字数与预计阅读时间、头图、相关文章、文章过时提示、归档时间轴。
- **图片与相册**：PhotoSwipe 灯箱支持键盘及触控操作；与 WordPress 核心图片灯箱共存，保留响应式图片属性。
- **技术内容**：highlight.js 代码高亮、主题选择、行号、自动折行、复制、全屏；KaTeX 数学公式。
- **排版与分享**：Pangu 中英文自动空格；微信二维码、微博、X、Telegram、WhatsApp、Facebook、Reddit、Bluesky、复制链接与系统分享。文章、评论和说说可分别启用排版。
- **基础可访问性**：跳过导航、键盘操作、减少动画偏好；关闭 JavaScript 后仍可阅读正文及使用普通导航。

### 编辑器、区块与短代码

支持 WordPress 区块编辑器，并保留经典编辑器与已有短代码内容。九类 Argon 区块使用 API 3，八类历史内容区块保留旧序列化兼容定义。

| 区块 | 用途 |
| --- | --- |
| 提示条 / 警告块 | 突出提示、注释、说明和警告 |
| 折叠块 | 放置长说明、补充材料和可展开内容 |
| GitHub 卡片 | 展示仓库信息 |
| 时间线 | 展示按时间或步骤组织的内容 |
| 进度条 | 展示进度 |
| 待办列表 | 展示任务及完成状态 |
| 标签页 | 把多组内容放进可切换面板 |
| HTML 文档 | 嵌入完整的交互文档 |

短代码还覆盖标签、复选框、友链、隐藏/剧透内容、视频、引用、发布时间及修改时间等。主题提供的文章设置在区块编辑器中有独立面板，保存与修订跟随 WordPress 原生流程。

```text
[collapse title="展开补充说明"]这里是折叠内容。[/collapse]

[alert color="info"]这里是一条提示。[/alert]
```

### HTML 文档文章

让一篇文章容纳一份完整网页，适合交互图表、报告、演示和小工具。文档拥有自己的 HTML、CSS 和 JavaScript，主题继续提供导航、侧栏、摘要、文章尾部、分享和评论。

- **三种入口**：古腾堡「Argon HTML 文档」区块、经典/Markdown 编辑界面的文档面板、媒体库 HTML 文件。
- **安全预览与修订**：预览当前编辑内容，支持 WordPress 自动保存和修订；与 WP Githuber MD 的源码保存链路配合。
- **独立沙箱**：文档与父页面隔离；脚本、弹窗、下载、对话框和外部资源来源有独立控制。
- **灵活显示**：自动高度、视口高度或固定高度；可按文章隐藏主题标题；支持主题状态同步。
- **替代正文**：声明以外的文章内容用于 AI 摘要、SEO、搜索、RSS、卡片、字数与阅读时间，也提供可展开的文本阅读。
- **媒体源码保护**：新上传 HTML 通过受保护的数据存储和受权限校验的文档入口提供；源码下载面向有权限的编辑者。

```text
这里写文章的文字版本，供搜索、RSS 和文本阅读使用。

[argon_html file_id="123" height="auto"]
```

完整用法、权限和资源白名单见 [HTML 文档文章指南](docs/html-document.md)。

### AI 文章摘要

在主题选项中配置兼容的服务地址、API Key 和模型，可自动生成摘要，并选择用于文章摘要与 SEO 描述。支持后台异步生成、更新时保留已有摘要、手动重新生成，以及按文章配置。

摘要生成在文章正文与元数据完成保存后执行。HTML 文档文章使用替代正文作为输入；手动摘要有优先权。AI 功能需要自行配置服务，运行费用由对应服务决定。

### 评论与社区

| 功能 | 说明 |
| --- | --- |
| 原生 AJAX 评论 | 提交、嵌套回复、分页及评论编辑 |
| 编辑历史 | 查看允许访问的评论修改记录 |
| 评论管理 | 评论置顶、点赞、折叠长评论等 |
| 内容表达 | Markdown、表情/贴纸、QQ 头像等可配置能力 |
| 悄悄话 | 按主题权限规则限制可见范围 |
| 通知与验证码 | 邮件回复通知、退订及评论验证码选项 |
| 系统与浏览器标签 | 显示操作系统、浏览器及可确认的版本，支持 UA 与 Client Hints 合并 |
| Discourse | 在原生评论下方嵌入论坛评论，并支持文章与话题关联 |

UA 识别覆盖主流桌面和移动系统、多种 Linux 发行版、常见 Chromium/Firefox 衍生浏览器及国内浏览器/应用内置浏览器。新版本数字无需逐个添加白名单；macOS 名称更新至 27 Golden Gate。

浏览器可能隐藏或冻结版本号。主题会保留可确定的信息，例如 Windows 只有 `NT 10.0` 时显示 `10/11`，避免把冻结值误当成真实系统版本。识别范围与限制见 [UA 说明](docs/USER_AGENT.md)。

### 内容组织、统计与站点设置

- **说说**：独立内容类型、点赞、长内容折叠、首页混排，以及 REST、区块编辑和修订支持。
- **内容组织**：归档时间轴、分类与标签、友链短代码、自定义菜单、小工具和文章类型搜索过滤。
- **阅读统计集成**：支持 WP Statistics 和 Post Views Counter；已有主题历史阅读数继续显示。3.0 不再写入主题自建阅读计数。
- **外链点击统计**：按文章记录外部链接点击量并显示角标，可关闭。
- **SEO 与摘要**：主题元信息、关键词/描述、摘要和 HTML 文档文本索引；提供过滤器便于与 SEO 插件协调。
- **站点自定义**：WordPress 原生 Logo、自定义 CSS/脚本、文章附加内容、资源路径及本地/自定义 CDN 配置。
- **设置管理**：主题设置导入、导出；可选择此 fork 的 GitHub Releases 作为更新源，默认关闭更新检查。

## 安装与升级

### 运行环境

| 项目 | 要求 |
| --- | --- |
| WordPress | 6.6 或更新，重点适配 WordPress 7 |
| PHP | 8.2 或更新，需要 DOM、mbstring 扩展 |
| 浏览器 | 当前主流现代浏览器；部分增强功能按浏览器能力降级 |
| 构建工具 | 已构建发行包无需 Node.js、npm 或 Composer |

1. 从 [Releases](https://github.com/shaokeyibb/argon-theme-featured/releases) 下载 **`argon-theme-featured.zip`**。
2. 在 WordPress「外观 → 主题 → 添加新主题 → 上传主题」中安装并启用。
3. 打开 Argon 主题选项，配置外观、评论、文章和所需扩展功能。

发行包附带 SHA-256 校验文件。GitHub 自动生成的 Source code 压缩包不等同于经过安装验证的发行包。

### 从旧版迁移

升级前备份数据库、主题设置及自定义文件，先在测试站核对插件和自定义代码。使用完整新主题目录替换旧目录，并清理页面/CDN 缓存；自定义 CDN 必须同步同一版本的完整资源树。

文章、评论、短代码、历史阅读数和既有选项不会被批量删除或改写。3.0 的主要迁移点：

- 图片灯箱统一使用 PhotoSwipe，数学渲染使用本地 KaTeX。
- 使用浏览器原生文档导航及可选 View Transitions；依赖 `window.pjaxLoaded`、`$.pjax` 的自定义脚本需要调整。
- 主题前台使用原生 JavaScript；需要 jQuery 的自定义代码应自行通过 WordPress 声明依赖。
- 复杂 MathJax 宏、旧自定义 CSS、主题子主题和历史公开 HTML 附件需要按文档核对。

详见 [现代化与迁移指南](docs/MODERNIZATION.md)。此主题保留 PHP 模板架构，站点编辑器的全站区块模板不在当前功能范围内。

## 开发与测试

使用 **Node.js ≥ 24.18.0、npm ≥ 11.16.0**，在仓库根目录运行：

```sh
npm ci
npm run build
npm test
npm run package
```

前台与区块编辑器共享根目录 lockfile。发行包输出到 `release/argon-theme-featured.zip`，采用运行文件白名单、固定文件顺序、时间戳与权限，不包含开发依赖、测试数据或旧 `vendor`。

```sh
npm run dev                         # 监听前台资源，写入 assets/dist
npm run build --workspace gutenberg # 构建区块与编辑器面板
npm run test:php                    # 四套隔离 WordPress/PHP 回归
node scripts/test-php.mjs user-agent # 只检查 UA 与评论保存/显示
npx playwright install chromium
npm run test:browser                # 自动启动隔离 WordPress 站点
npm run test:package                # 将 ZIP 安装到全新 WordPress 验证
npm audit
```

开发模式由本地 WordPress 提供页面。主要目录：`assets/src/modern/` 为前台模块，`gutenberg/src/` 为区块和编辑器，`inc/` 为主题 PHP 功能，`tests/` 为回归测试。

3.0.0 发布验证覆盖 WordPress 6.6.10 / PHP 8.2.33、WordPress 7.0.7 / PHP 8.2–8.5，以及 WordPress 7.1.3 / PHP 8.3.33：每组包含 96 项安全、50 项 HTML 文档、26 项现代化和 127 项 UA 检查，合计 **1,794 项 PHP 检查通过**。另有 **9 个浏览器流程、7 项构建/时间格式检查、ZIP 独立安装验证**；发布时 npm 全依赖审计为 **0 项已知漏洞**。

浏览器自动化使用 Chromium/Chrome；真实 AI/Discourse 服务、任意插件组合及真实 CDN 仍需按站点环境验证。完整命令、环境选择及验证边界见 [测试说明](tests/README.md) 和 [审查报告](docs/REVIEW.md)。

## 文档与支持

- [现代化调查、架构与迁移](docs/MODERNIZATION.md)
- [重构审查与回归修复](docs/REVIEW.md)
- [HTML 文档文章指南](docs/html-document.md)
- [系统与浏览器识别](docs/USER_AGENT.md)
- [测试与发布](tests/README.md)
- [第三方组件和许可证](THIRD_PARTY_NOTICES.md)
- [提交问题或建议](https://github.com/shaokeyibb/argon-theme-featured/issues)
- [Argon 原版文档](https://argon-docs.solstice23.top/)：可参考原有功能说明，旧组件和构建方式以本仓库文档为准。

反馈问题时请附上 WordPress/PHP/主题版本、复现步骤、相关插件和脱敏后的报错信息。

## 致谢与许可

本项目基于 [solstice23/argon-theme](https://github.com/solstice23/argon-theme)，感谢原作者与社区贡献者。Argon Theme Featured 使用 [GPLv3](LICENSE)，第三方组件保留各自许可证。
