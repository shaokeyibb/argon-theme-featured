# Argon 3 现代化调查与迁移报告

本次重构保留 PHP 模板、主题外观、内容格式和 fork 的功能，用现代资源管线及 WordPress 原生能力替换旧基础设施。运行基线为 WordPress 6.6、PHP 8.2，重点验证 WordPress 7。它是支持现代区块能力的经典主题；模板编辑与站点编辑器的全站区块主题不是此次迁移目标。

## 调查与处理

| 原有情况 | 处理结果 | 兼容性说明 |
| --- | --- | --- |
| 前台 Parcel、旧 pnpm 配置，编辑器单独依赖树 | npm workspaces、单一 lockfile；Vite 8 构建前台，官方 `@wordpress/scripts` 构建编辑器 | 根目录 `npm ci && npm run build`；运行环境无需 Node |
| 大量手工复制的旧前端库与重复构建产物 | 删除旧 vendor/source 副本，包管理器锁定版本，生成许可证清单 | 发行包只带运行资源 |
| jQuery 驱动导航、评论、设置、主题控件 | 原生 DOM、Fetch、事件委托、IntersectionObserver、CSS 与浏览器 API | 主题前台不加载 jQuery；WordPress 后台和其他插件仍可正常加载它 |
| Bootstrap 4 JavaScript 与附属旧插件 | Bootstrap 5 原生组件；保留旧属性到新组件的适配 | 原短代码和已保存区块继续使用；保留 Argon 基础 CSS 的外观契约 |
| 修改版 jquery-pjax-plus 替换局部 HTML，容易遗漏区块/插件资源 | 正常文档导航，支持时用跨文档 View Transitions 增强 | 浏览器历史、表单、插件脚本由正常页面生命周期处理 |
| Zoomify、Fancybox 等重复灯箱 | 按点击加载 PhotoSwipe 5，避开 WordPress 自带灯箱和交互区块 | 已保存图片与相册无需重写；原排除类继续识别 |
| 主题重写所有图片 HTML、自制 lazyload | 移除正文图片包装和强制懒加载，让 WordPress 保留 `srcset`、`sizes`、尺寸和加载优先级 | 避免给首屏高优先级图片强加 lazy；历史表情资源仍可按需载入 |
| MathJax 2/3、旧 KaTeX、远程数学脚本 | 本地 KaTeX 0.19，发现公式后再加载 | MathJax 选项迁移为 KaTeX，复杂宏与不支持的 TeX 命令需核对 |
| 旧 highlight.js、复制/行号插件 | highlight.js 11 按需加载，原生剪贴板和行号，保留源码文本 | 主题样式从当前包复制；已移除的样式名回退为 `vs2015` |
| 老字体图标和散落二维码/排版库 | Font Awesome 7 的官方 v4 兼容样式；二维码与 pangu 模块按需加载 | 保留旧图标类、已存短代码和分享能力 |
| 已弃维护的 ChatGPT PHP SDK、Guzzle 依赖链 | WordPress HTTP API，保留服务地址、密钥、模型与提示词选项 | 无 Composer 运行依赖；网络失败保留已有摘要，防并发和过期响应覆盖 |
| Parsedown beta 版本 | Parsedown 1.8.0，保留类名兼容及最终 KSES 输出过滤 | 评论 Markdown 与既有安全回归继续有效 |
| 上游更新器、安装遥测、外链 CDN 默认值 | 移除遥测及旧更新器；本地资源默认；使用 WP 原生更新接口，显式选择 fork Releases | 迁移时关闭旧更新源；仅接受正确命名的 fork 发行 ZIP |
| 页面读取时写阅读量/零值、给所有访客写 Cookie | 停止旧阅读计数写入；点赞读取不创建零值；访客令牌仅在评论流程需要时创建 | 历史数值保留；WP Statistics/Post Views Counter 的现有集成保留 |
| 旧区块 API、占位服务端注册、手工脚本依赖 | 九种区块均 API 3；每块 `block.json`；`useBlockProps`；官方依赖提取 | 冻结旧序列化器作为 deprecated 版本，使用真实旧内容 fixture 验证 |
| 古腾堡内沿用只适合经典编辑器的文章设置 | 文档设置面板和受权限保护的 REST 元数据，支持自动保存与修订 | 经典编辑器仍保留原有面板；Discourse 字段不再错误依赖 AI 开关 |
| 缺少现代主题声明、资源直接硬编码输出 | `theme.json` v3、宽幅/响应式嵌入/编辑器样式/原生 logo；WP enqueue/module API | 保留 PHP 模板和原有布局；核心区块样式按需加载 |
| 自改全局时区、固定 WordPress 路径、SEO 重复 | 使用 WP 时间语义和原生 URL；退订入口走 WordPress；识别常见 SEO 插件 | 支持子目录部署，避免重复输出主题 SEO 元数据 |
| HTML 整体 DOM 重写可能损坏脚本中的中文 | HTML 文档解析明确 UTF-8；外链计数使用 WP HTML Tag Processor，仅改锚点属性 | 保留 Unicode、脚本/CSS 字面量和核心交互属性 |
| 列表首图反复执行完整正文过滤器 | 直接从内容取首图，提供 `argon_first_image_source` 扩展过滤器 | 动态生成图片的插件可通过此过滤器提供源内容 |

保留的主要功能包括文章/页面/说说、菜单与侧栏、目录、深色模式与配色、短代码和八类旧内容区块、HTML 文档区块、评论回复/编辑/历史/置顶/点赞/验证码/表情/通知、分享与二维码、AI 摘要、Discourse 及第三方统计集成。旧阅读量不再增长，但未删除数据库中的历史记录。旧灯箱、平滑滚动插件和 PJAX 的配置控件已移除。

## 架构与性能

`inc/setup.php` 管理主题支持和一次性设置迁移，`inc/assets.php` 读取 Vite manifest 并通过 WordPress 加载版本化资源，`inc/editor.php` 定义受权限保护的文章元数据，`inc/updates.php` 负责可选 fork 更新。前台模块位于 `assets/src/modern/`，编辑器位于 `gutenberg/src/`。

代码高亮、数学、图片灯箱、二维码和排版模块按实际使用动态导入。正文图片由 WordPress 处理加载优化；主题不再统一包装每个图片节点。普通匿名访问不产生主题令牌 Cookie，也不会更新主题自建阅读数，有利于页面缓存。评论和点赞等写入仍需实时接口，不能整体缓存其响应。

仍保留 `argon-compat.css` 作为原版视觉样式基础，以及官方 Font Awesome 旧类名适配。移除这些样式会改变大量历史 HTML 的外观；本次移除了旧 JavaScript 行为依赖，没有声称彻底消除所有历史 CSS。动态模块也不意味着所有页面总字节数都变小，公式字体及完整图标覆盖仍有成本。没有在真实生产站进行 Core Web Vitals 基准，因此不提供未经测量的速度提升百分比。

页面具有键盘可操作的基本控件、跳过导航链接、移动端布局和减少动画偏好支持。无 JavaScript 时可正常阅读与导航；交互评论、数学增强和灯箱仍需要 JavaScript。

## 升级和自定义兼容

1. 备份数据库、主题设置与主题目录，在测试站先升级 PHP/WordPress 到支持基线。
2. 使用包含已构建资源的发行包完整替换主题目录。不要把旧 `vendor` 和旧编译文件混入新的发行包。
3. 清理页面与 CDN 缓存。自定义资源地址需要指向当前 fork 同一版本的完整资源树，并正确允许 ES module 跨域访问；默认本地资源无需此设置。
4. 检查高级 TeX、旧自定义 JavaScript、主题子主题和插件。主题不再提供全局 jQuery、`$.pjax`、`window.pjaxLoaded` 生命周期。自定义代码改用正常文档初始化；必须使用 jQuery 的扩展应通过 WordPress 自行声明依赖。
5. 检查自定义 CSS。原主要类名保留，但代码高亮、灯箱、弹窗和编辑器的内部结构已有变化。自定义 SEO 输出可以通过 `argon_should_render_seo_metadata` 过滤器控制。
6. 更新默认关闭；需要时在后台选择 fork GitHub 源。升级不批量改写文章、评论或历史计数。旧配置值保留以便核对，部分旧功能不再消费这些值。

数学默认只使用 KaTeX 支持的语法，无法对全部 MathJax 扩展无损替换；可修订特殊公式或由专门数学插件负责。原生完整页面导航会重新执行页面脚本，自定义代码应避免依赖旧局部替换事件。已保存旧区块在首次编辑保存时由 WordPress 使用兼容定义迁移到当前格式，不在主题升级时扫描重写所有文章。

HTML 文档仍使用独立、受权限保护且不可直接访问父页面的沙箱，保留替代正文、Markdown/古腾堡入口、修订预览及媒体源码保护。详细权限和历史公开附件处理见 [HTML 文档说明](html-document.md)。

## 验证范围

验证日期：2026-10-24。自动化入口见 [测试说明](../tests/README.md)。所有测试均运行于隔离环境。

| WordPress 实际版本 | PHP 实际版本 | 安全 / HTML 文档 / 现代化 |
| --- | --- | --- |
| 6.6.10 | 8.2.33 | 96 / 50 / 19 通过 |
| 7.0.7 | 8.2.33 | 96 / 50 / 19 通过 |
| 7.0.7 | 8.3.33 | 96 / 50 / 19 通过 |
| 7.0.7 | 8.4.25 | 96 / 50 / 19 通过 |
| 7.0.7 | 8.5.10 | 96 / 50 / 19 通过 |
| 7.1.3 | 8.3.33 | 96 / 50 / 19 通过 |

共 990 项 PHP 检查通过。清洁安装依赖后构建成功，4 项构建/资源检查通过，npm 全依赖审计为 0 项已知漏洞。ZIP 在新建 WordPress 7.0.7 / PHP 8.3.33 中安装、激活并渲染首页成功。区块编辑器构建保留一个资源体积提示（含 LTR/RTL 样式与图标兼容层），未把该提示隐藏为“无警告”。

6 项 Chromium/Chrome 浏览器流程全部通过：桌面/390px 手机布局、原生交互与灯箱键盘操作、评论提交/编辑/嵌套回复/置顶/历史、HTML 文档沙箱与主题同步、后台设置导入导出、八类旧区块兼容解析及 API 3 iframe 实际渲染、REST 元数据保存、无 JavaScript 阅读与导航。浏览器未出现未处理的脚本异常。CI 已配置，但未在本次本地工作中执行远端 GitHub Actions。

依赖审计涵盖 npm 生产与开发依赖，结果只代表审计时数据库中已收录的公告。官方构建工具的部分间接开发依赖仍有弃用提示；它们未包含在主题发行运行文件中。真实 AI 服务、Discourse 服务端和任意第三方插件组合需要站点层面联调，不能把隔离测试解释为对所有环境的绝对兼容保证。

## 技术依据

- [WordPress 7.0 Field Guide](https://make.wordpress.org/core/2026/05/14/wordpress-7-0-field-guide/)：按核心行为调整主题支持与编辑器集成。
- [WordPress / PHP 兼容矩阵](https://make.wordpress.org/core/handbook/references/php-compatibility-and-wordpress-versions/)：选择 PHP 8.2–8.5 验证范围。
- [区块 iframe 与 API 3 迁移](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-api-versions/block-migration-for-iframe-editor-compatibility/)：使用 API 3、块级包装及正确资源注册。
- [WordPress 图片加载优化接口](https://developer.wordpress.org/reference/functions/wp_get_loading_optimization_attributes/)：让核心决定懒加载与优先级，避免主题覆盖。
- [Vite 后端集成](https://vite.dev/guide/backend-integration.html)：通过 manifest 管理 PHP 模板引用的模块及 CSS。
- [Parsedown 正式发布](https://github.com/erusev/parsedown/releases)：替换捆绑 beta 解析器并保留许可证。
