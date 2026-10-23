# Argon 3 重构后代码审查

审查日期：2026-10-24。上一轮完整重构先提交为 `196c8164cd97189cf4df66e7b3a09a42c2aacccb`，本轮以该提交为基线，检查行为兼容、WordPress/PHP 生命周期、运行依赖和发行构建。下列问题均直接修复，未改写已有文章或清除数据库设置。

## 发现与修复

| 严重性 | 发现 | 修复及回归依据 |
| --- | --- | --- |
| 高 | 自动空格调用了新版 Pangu 已不存在的方法，错误被可选模块处理吞掉，功能实际未运行 | 升级 Pangu 10，明确导入浏览器入口并使用 `spaceNode`；检查正文及 AJAX 新建/编辑评论的中英文空格 |
| 高 | `crypto.randomUUID()` 在普通 HTTP 页面不可用，外链计数初始化异常会阻断后续公式、灯箱等增强 | 使用 `crypto.getRandomValues` 生成随机 ID；浏览器主动移除 `randomUUID` 后验证完整初始化 |
| 高 | AI 自动摘要早于 REST 元数据保存，可能忽略同次请求中的退出生成选项或新提示词 | 改在 `wp_after_insert_post` 触发，覆盖文章和页面；检查 REST 发布时的退出设置和新提示词；撤回发布后排队任务不再发送内容 |
| 高 | 编辑器保存失败后，手动重新生成仍可能请求旧文章内容 | 检查 WordPress 保存结果，失败时显示错误并停止；浏览器拦截保存请求返回 403，确认没有 AI AJAX 请求 |
| 中 | 整份 2019 Argon Design System CSS 仍包含 Bootstrap 4、noUiSlider、日期选择器等旧组件 | 用 Bootstrap 5 Sass 取代整份旧底座；仅保留主题变量、横幅图形及保存内容所需的类名别名；检查旧组件选择器不进入发行包 |
| 中 | 代码行号在折行时被隐藏；透明背景开关失效；非原生全屏无法用 Escape 可靠退出 | 用 DOM Range 测量真实高亮文本的行位置；补齐透明背景、滚动及全屏退出；验证折行、复制原文和降级退出 |
| 中 | 评论时间忽略 YMD/MDY/DMY 设置，也不再随页面停留更新 | 恢复日期顺序、同年省略、昨天/前天时间和未来时钟容错；可见页面每分钟刷新，重新显示页面时更新；纯函数边界测试 |
| 中 | 画廊中非首张图片沿用缩略图尺寸，且排除条件与点击入口不一致 | 每张进入加载阶段的原图单独测量并缓存，统一排除规则；验证不同原图尺寸、非图片链接、排除属性及核心灯箱独立工作 |
| 中 | 原始内容取首图省略了 Markdown、短代码和动态区块生成的图片 | 有原始图片时直接读取；否则每篇文章每次请求缓存一次过滤结果，并用递归保护和临时 post 上下文维持插件兼容 |
| 中 | 全局搜索过滤器改变了插件自己的次级查询 | 仅修改前台主查询；说说首页过滤也收窄范围；回归检查次级 `WP_Query` 的文章类型不被替换 |
| 中 | 原生 Logo 在旧工具栏标题选项从未保存时被忽略 | 为选项指定空字符串默认值；以真实头部模板验证 WordPress 自定义 Logo 输出 |
| 中 | 表情分组在主题文件载入时触发翻译，产生 WordPress 提前加载 textdomain 警告 | 在 `init` 建立分组；新增加载前 MU 诊断，捕获 E_ALL 警告、弃用和 WordPress `doing_it_wrong` |
| 中 | 宽泛图片样式覆盖区块尺寸；`10vw` 内边距可将小图片挤成零高度 | 让核心图片样式控制宽高与裁剪，移除这条内边距；验证小图可点击及核心方形比例 |
| 中 | `wp-scripts` 默认保留 fonts 目录，旧 Font Awesome 4 字体仍混进新产物 | 在官方 Webpack 配置上启用完整输出清理；检查每个发行字体都被 CSS 引用，保留官方 Font Awesome 7 的旧类名兼容层 |
| 中 | 文档声明的 Node/npm 下限低于 Playground 工具链实际要求 | 根包、文档和 CI 统一为 Node ≥ 24.18.0、npm ≥ 11.16.0；更新 Babel parser 8、Archiver 8 并适配其 ESM 接口 |
| 中 | CI 本身仍使用旧代官方 Actions | 核对官方发布后升级 checkout 7.0.1、setup-node 7.1.0、upload-artifact 7.0.2；保留只读仓库权限。远端执行仍需后续 push/PR 触发 |
| 低 | 数学开关开启就加载公式模块，即使正文没有公式 | 检测公式起始分隔符后再导入 KaTeX；加载失败仍保留正文 |
| 低 | 菜单仍使用已删除的 Nucleo 图标类，许可证复制存在平台换行差异 | 菜单改用现有 Font Awesome；生成许可证时统一 LF，保持发行包跨构建稳定 |
| 中 | 桌面搜索遗留没有目标的 modal 属性；跨文档动画取消时可能产生未处理的 ready 拒绝 | 删除无效属性，搜索保留正常展开行为；只处理 `pageswap/pagereveal` 自身的可选动画拒绝，不屏蔽其他脚本异常；桌面展开和手机搜索均纳入浏览器检查 |

迁移 CSS 时一并验证并补齐移动导航搜索面板、下拉菜单、标签页、弹窗、原生勾选框、按钮、深色模式与原有主题横幅。历史区块的冻结序列化定义继续保留，不能为追求代码表面“全新”而删除内容兼容所需的迁移定义。

## 验证

本轮重新运行完整安全、HTML 文档和现代化检查；新增 Logo 检查后再次验证现代化套件。

| WordPress | PHP | 安全 / HTML / 现代化 |
| --- | --- | --- |
| 6.6.10 | 8.2.33 | 96 / 50 / 26 通过 |
| 7.0.7 | 8.2.33 | 96 / 50 / 26 通过 |
| 7.0.7 | 8.3.33 | 96 / 50 / 26 通过 |
| 7.0.7 | 8.4.25 | 96 / 50 / 26 通过 |
| 7.0.7 | 8.5.10 | 96 / 50 / 26 通过 |
| 7.1.3 | 8.3.33 | 96 / 50 / 26 通过 |

合计 **1,032 项 PHP 检查、7 项构建/单元检查、9 个浏览器流程通过**。浏览器检查无未处理脚本异常，桌面和 390px 手机截图已检查。截图复查还修复了导航栏把 RGB 分量直接当作颜色使用的问题，并加入背景色断言。

使用 Node 24.22.1 / npm 11.16.0 清洁执行 `npm ci` 后构建成功，全依赖审计为 **0 项已知漏洞**。官方构建工具仍有上游弃用、安装脚本策略及资源体积提示，这不等于运行错误，也未被描述成“零警告”。CI YAML 已解析验证，远端 GitHub Actions 尚未运行。

## 保留项与边界

- PHP 模板和 WordPress 原生经典编辑器集成仍保留；本主题是支持现代区块编辑器的经典主题，不是全站区块模板主题。
- 已保存 HTML 的类名、区块 deprecated 定义、Font Awesome 官方兼容样式是内容兼容层，不包含旧 Bootstrap/jQuery 运行库。WordPress 后台及第三方插件可以自行加载其依赖。
- 官方 `@wordpress/scripts` 仍使用 Webpack，部分间接开发依赖会有上游弃用提示；没有强行替换其内部依赖来制造“零旧包”。这些开发工具不进入发行 ZIP。
- AI 使用受控 HTTP 模拟响应验证生命周期、权限与失败行为，未调用真实付费服务。任意第三方插件、Discourse 服务端和生产站性能仍需站点环境验证。
- 高级 MathJax 扩展与旧 PJAX 自定义脚本的迁移要求沿用 [迁移说明](MODERNIZATION.md)。

## 核对的官方接口

- [WordPress `wp_after_insert_post`](https://developer.wordpress.org/reference/hooks/wp_after_insert_post/)：文章、分类和元数据全部保存后的生命周期。
- [WordPress 7.0 Field Guide](https://make.wordpress.org/core/2026/05/14/wordpress-7-0-field-guide/)：核心兼容性变化。
- [Bootstrap 5 迁移说明](https://getbootstrap.com/docs/5.3/migration/)：组件、表单、工具类及数据属性变化。
- [PhotoSwipe 事件](https://photoswipe.com/events/)：加载内容时更新图片数据。
- [GitHub checkout](https://github.com/actions/checkout/releases/tag/v7.0.1)、[setup-node](https://github.com/actions/setup-node/releases/tag/v7.1.0)、[upload-artifact](https://github.com/actions/upload-artifact/releases/tag/v7.0.2)：核对 CI 的发布版本和接口。
- [Chrome 跨文档 View Transitions](https://developer.chrome.com/docs/web-platform/view-transitions/cross-document)：处理被跳过的可选动画。

可复现命令和报告路径见 [测试说明](../tests/README.md)。
