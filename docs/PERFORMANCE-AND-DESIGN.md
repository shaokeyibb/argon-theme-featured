# 资源与视觉规范化

本轮沿用 npm workspaces、Vite、WordPress `theme.json` / Global Styles、官方 `@wordpress/scripts` 和 Playwright；不增加外观预设、后台设置入口或依赖。Header 首次导航闪色修复已单独提交为 `96e8849`。

## 资源体积

基准为 `96e8849`，使用同一 Node.js 24.19.0 / `gzipSync` 默认参数测量构建文件。单位为字节；各独立 HTTP 资源分别压缩后相加。

| 范围 | 原始大小：之前 → 现在 | gzip：之前 → 现在 |
| --- | ---: | ---: |
| 前台基础 CSS（主题主样式与共享图标） | 462,878 → 425,435 | 83,930 → 78,667 |
| 前台入口及静态导入 JS | 120,564 → 120,450 | 38,662 → 38,628 |
| 编辑器专有 CSS | 125,601 → 14,240 | 29,400 → 3,311 |
| 编辑器 CSS 合计（含共享图标） | 125,601 → 120,864 | 29,400 → 29,784 |

前台基础 CSS 减少 8.1%，gzip 减少 6.3%。编辑器专有包变小主要来自图标共享，不能宣称编辑器首次总传输减少 89%：分文件压缩使其 CSS gzip 合计略有增加，但浏览过前台后可以复用同一 URL 的图标样式和字体。发行目录删除了 258,588 字节的重复字体副本。

这些数据不包含 WordPress 核心、插件、外部字体或按选项/内容加载的高亮、公式、画廊等资源；不等于真实网站的 LCP、INP、CLS 或完整页面传输量。`scripts/asset-metrics.mjs` 与 `tests/performance.test.mjs` 维护上述口径和预算。

Bootstrap 使用官方 Sass 分组件导入及 Utilities API，保留网格、表单、导航、弹窗、短代码和旧文章常用工具类，移除当前主题未使用的组件和部分 Bootstrap 5 专有工具类。没有根据仓库文本清除动态文章可能使用的图标；Font Awesome 保留完整名称、品牌和 v4 兼容映射。自行在文章或子主题中使用被移除的 Bootstrap 5 专有组件时，需自行加载相应官方样式。

## 现有外观选项的统一

`inc/design.php` 集中定义原有 12 种颜色，后台原有色板和原生编辑器调色板共用这份定义。站点当前主色、字体、圆角、阴影通过 `wp_theme_json_data_theme` 进入 WordPress Global Styles；子主题自定义色板条目继续保留。访客 Cookie 不改变编辑器的站点设置。

`theme.json` 提供字体、正文和标题、间距、表面样式的共用变量；前台桥接层继续使用现有主色变量和深色模式，原生主色区块也会跟随阅读器颜色。字体偏好在首帧恢复。编辑器使用同一字体和主色，作者区块内的显式字号、颜色和间距仍可覆盖默认值。没有新增整套风格切换器或新的颜色预设。

暗色模式同时桥接 WordPress 的正文色和 Bootstrap 的正文、标题、强调、次级文字与组件表面变量。Bootstrap 5 的卡片会显式读取 `--bs-body-color`，不能仅设置 `body` 或 `.post-content` 的 `color`；否则说说、小工具、metadata、评论和设置面板会保留浅色模式的深色文字。旧模板用于小工具和归档标题的 `text-black` 跟随标题变量，metadata 原有透明度继续保留。浏览器回归覆盖普通暗色、沉浸色与纯黑模式、切回浅色及作者显式指定的区块颜色。

历史导航回归发现返回页面时重播的卡片缩放入场动画会改变浏览器的滚动锚点坐标，导致后退偏移；仅在历史恢复时跳过卡片入场动画，保留浏览器原生滚动恢复和普通导航动画。

真实颜色选择器回归还发现并修复了 ES module 下的未声明变量、原生选择器小写 HEX 解析和灰度辅助函数递归问题。

## 验证

- Node 构建资源、颜色转换和体积预算：10 项通过。
- WordPress 6.6.10 / PHP 8.2.33 与 WordPress 7.0.7 / PHP 8.3.33：各 28 项现代化集成检查通过。
- 浏览器功能覆盖 Chromium 36 项、Firefox 19 项、WebKit 19 项；发现的问题修复后对应检查均已复测通过。Chromium/Firefox 在 Ubuntu 24.04 运行，WebKit 在 Windows 运行。
- Ubuntu 24.04 / 锁定版本 Chromium 的 4 组视觉测试、8 张 Header / 正文截图基线；已人工检查并执行正常比对。
- ZIP 安装检查通过：发行文件完整，主题激活、页面输出和区块注册正常。

导航测试检查实际后退/前进和重载后的状态，并记录 BFCache 标记及资源计时。Playground 默认服务不保证 HTTP 缓存或 BFCache 命中；真实 CDN 与实际 iOS/Safari 设备仍需在部署环境验证。所有测试使用隔离站点，不连接生产数据库。

## 官方依据

- [Bootstrap 优化与 Sass 分组件导入](https://getbootstrap.com/docs/5.3/customize/optimize/)
- [Font Awesome 官方 SCSS](https://docs.fontawesome.com/web/use-with/scss/)
- [WordPress theme.json](https://developer.wordpress.org/themes/global-settings-and-styles/introduction-to-theme-json/)
- [WordPress 自定义设计变量](https://developer.wordpress.org/news/2023/08/adding-and-using-custom-settings-in-theme-json/)
- [Playwright 浏览器项目](https://playwright.dev/docs/test-projects)与[截图断言](https://playwright.dev/docs/test-snapshots)
