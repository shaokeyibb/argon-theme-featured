# HTML 文档文章

HTML 文档文章保留 Argon 的导航、侧栏、AI 摘要、文章尾部、分享和评论，用一个隔离的完整 HTML 文档替换正文。默认保留主题文章标题和元信息，可按文章隐藏。

## 写文章

一篇文章放一个 `argon_html` 短代码或一个「Argon HTML 文档」区块。**声明以外的全部文章内容就是替代正文**，可以照常写 Markdown 或古腾堡区块。

替代正文用于 AI 摘要输入、文章卡片、SEO 描述、站内搜索、RSS、字数和阅读时间，以及文章下方可展开的「文本阅读」。未填写替代正文时，主题从 HTML 提取静态正文；不会在服务器执行 JavaScript 获取文字。主要由 JavaScript 生成内容的页面，请在声明外补充完整的文字版本。

WordPress 自带的「摘要」字段仍用于手写短摘要，优先于 AI 摘要和自动摘要；它不替代这份完整正文。没有新增需要手动编辑的「文本正文」metadata。

### 媒体库文件

通过 WordPress 原生「媒体 → 添加新媒体文件」，或文章中的媒体选择窗口上传 UTF-8 `.html` / `.htm` 文件（最大 1 MiB），使用其附件 ID：

```text
这是交互式文章的文字版本，可以写完整的介绍、结论和数据说明。

[argon_html file_id="123"]

这里也属于替代正文。
```

`file_id` 是普通的 WordPress 媒体库附件 ID。主题在标准上传流程中保护源码：在临时上传目录读取原文，将源码保存到受保护的附件数据中，公开上传目录中不会留下原始 HTML 文件。媒体库继续负责附件 ID、所有者、选择和删除。

源码下载只向有权限的登录编辑者提供，要求有效 nonce，按 `text/plain` 附件下载。文章展示另走检查原文章可见性、密码及预览权限的文档入口。附件独立页面返回 404；文档和源码响应均设置 `X-Robots-Tag: noindex, nofollow, noarchive` 及禁止公共缓存。

历史上已公开上传的 HTML 附件需要通过媒体库重新上传，才能获得这层保护；已有公开副本或 CDN 缓存需要另行清理。主题不会批量修改或删除历史附件。已经发布的文档展示内容本身仍可被读者查看，保护范围包括独立附件源码和未公开文章的内容。

### 内联 HTML 与 WP Githuber MD

Markdown 编辑界面提供「HTML 文档文章」面板：粘贴完整源码，点击「插入或更新文档声明」。工具会自动生成采用 Base64 封装的内联短代码，源码仍然保存在文章中；编码用于避免 Markdown、智能标点和编辑器原生预览破坏或执行源码，发布权限仍由服务端校验。

也可以手写可读的 HTML 围栏：

````text
## 文章的文字版本

这里照常写 Markdown。

[argon_html]
```html
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <title>交互式文章</title>
  <style>body { margin: 0; font-family: system-ui, sans-serif; }</style>
</head>
<body>
  <h1>交互式文章</h1>
  <p>文档自己的内容和布局。</p>
</body>
</html>
```
[/argon_html]

后续 Markdown 也属于替代正文。
````

Githuber MD 的保存链路会转义围栏内容。主题读取其原始 Markdown 并使用插件的源码恢复接口，分别处理前台、编辑和修订预览。围栏或行内代码中的短代码示例不会启动 HTML 模式。HTML 内部不递归执行 WordPress 短代码。

使用「安全预览」查看文档的实际效果。普通 Markdown 预览中的围栏按代码展示；自动生成的编码声明不会在后台执行。文档包含字面量 `[/argon_html]` 等分隔符时，使用面板自动生成的编码形式。

### 古腾堡

插入「Argon HTML 文档」区块，直接粘贴完整 HTML，或从原生媒体库选择 HTML 附件。区块提供安全预览、高度和隐藏主题标题设置。文章内其他区块都是替代正文。

源码以编码后的区块属性保存，跟随 WordPress 原生保存、自动保存和修订。预览读取当前用户的对应修订，预览内容不会写入已发布文章的文本索引。

## 短代码参数

| 参数 | 含义 |
| --- | --- |
| `file_id="123"` | 引用受保护的媒体库 HTML 附件；文件内容优先于短代码内部内容 |
| `encoding="base64"` | 内联源码编码方式，由编辑面板自动生成 |
| `height="auto"` | 默认值，随文档布局、图片、字体和动态内容调整高度 |
| `height="viewport"` | 使用视口高度并在文档内部滚动，适合仪表盘、全屏应用和固定导航布局 |
| `height="800"` | 固定高度，单位 px，有效范围 200–2000 |
| `hide_header="true"` | 隐藏该文章的主题标题和元信息，保留导航、侧栏、摘要和评论 |

移动端（屏幕宽度不超过 600px）会移除 HTML 文档外层卡片的横向留白，保留文档自己定义的内边距；标题、摘要和文本阅读区域仍保留阅读边距。自动高度支持宽窄屏双向切换和内容展开收起，短暂超过高度上限的文档恢复正常布局后会继续自动测高。依赖 `100vh` 等视口高度持续增长的应用建议显式使用 `height="viewport"`。

```text
[argon_html file_id="123" height="viewport" hide_header="true"]
```

HTML 内的图片、CSS 和 JavaScript 使用绝对资源 URL 最方便。内联文档的相对资源以文章 URL 为基址，媒体库文件以其上传子目录 URL 为基址；上传一个 HTML 文件不会自动上传旁边的图片、脚本或样式目录。

## 主题选项与安全边界

「Argon 主题选项 → HTML 文档文章」提供以下全局设置；短代码不能扩大权限：

| 设置 | 默认 | 行为 |
| --- | --- | --- |
| 启用 HTML 文档文章 | 开启 | 关闭后展示替代正文，文档入口停止提供内容 |
| scripts | 开启 | 允许文档脚本；关闭时连同通信脚本一起禁用，改用固定高度及内部滚动 |
| popups | 关闭 | 允许打开弹窗；新窗口继续继承沙箱限制，不能逃离沙箱 |
| downloads | 关闭 | 允许通过链接或文档脚本触发下载 |
| modals | 关闭 | 允许 `alert`、`confirm`、`prompt`、`print` 等对话框 |
| 外部资源白名单 | 空 | 每行一个完整 HTTPS 来源，例如 `https://cdn.jsdelivr.net`，不接受通配符、路径或任意 CSP 指令 |

本站资源、内联样式和常规内联脚本默认可用，外部脚本、样式、图片、字体需配置白名单。接口网络请求、表单提交、嵌套 iframe、Worker 和 `eval` 始终受 CSP 限制。

同源权限、顶层跳转、弹窗逃离沙箱、相机、麦克风、定位、支付、剪贴板和全屏等权限不提供放开选项。iframe 属性和文档 HTTP CSP 同时执行约束，直接打开文档入口也不会获得本站同源权限。

编辑源码、激活新的文档和调整安全设置需要 `unfiltered_html` 及相应对象权限。服务端记录与确切源码绑定的批准校验，低权限用户写入编码源码或伪造文件 ID 不能激活它；修改已有批准文档的替代正文则不影响原文档。切换站点认证盐或跨站导入后应重新验证：内联文章由有权限的用户重新保存，文件来源可通过源码下载后重新上传并更新引用。

调整权限后清理页面及 CDN 缓存，使外层 iframe 权限与新设置同步；文档入口本身不使用公共缓存。

## 与主题功能的配合

- 首页、分类和搜索列表保持原有文章卡片，不加载 iframe 或运行文档脚本。
- AI 摘要继续使用现有模型、额外提示词、长度限制及异步/重复生成开关，只更换输入文本的提取方式。没有可读文字的文档不会发起摘要生成。
- 密码文章解锁前不显示文档、替代正文或 AI 摘要；私密文章、草稿和修订文档按 WordPress 权限检查。
- 目录通过受控消息同步文档内标题，支持定位；开启 scripts 时可用。消息只提供有限的展示能力，不接收任意父页面代码或 HTML。
- 保留主题评论、Discourse、分享、阅读量、相关文章和文章导航。文档内部的图片灯箱、数学渲染、代码高亮、外链点击角标等由文档自身负责，主题不改写其 DOM。
- 文档可响应 `html[data-argon-theme="dark"]` / `.argon-dark`，使用 `--argon-primary-color` 和 `--argon-viewport-height` 变量。主题不强制反转文档颜色。
- 文档应自行编写响应式布局。主题保证外层宽度适配，并为内部块级容器提供可收缩、长文本换行、代码局部滚动和媒体限宽的低优先级默认值；作者的显式 CSS 优先。自动高度提供限幅及循环增长回退，固定列宽、显式最小宽度及特殊布局仍由文档自身决定。

## 开发验证

只在独立临时 WordPress 环境运行测试，不能向已有站点加载这些 fixture。测试会创建文章、用户、媒体并修改选项，不调用真实 AI 服务。

```powershell
npx --yes @wp-playground/cli@3.1.60 run-blueprint --mount-dir (Get-Location).Path /wordpress/wp-content/themes/argon-theme --blueprint tests/html-document-blueprint.json
Get-Content temp/html-document-report-8.3.json
```

Playground CLI 3.1.60 需要 Node.js 24.18 或更高版本。将 blueprint 的 `preferredVersions.php` 改为 `8.2` 可以验证主题最低 PHP 版本。

浏览器测试 fixture 使用真实 WP Githuber MD 和原生媒体上传：

```powershell
npx --yes @wp-playground/cli@3.1.60 server --port 9403 --mount-dir (Get-Location).Path /wordpress/wp-content/themes/argon-theme --blueprint tests/html-document-browser-blueprint.json
```

文章和附件 ID 写入 `temp/html-document-browser-fixture.json`。仅该临时站点的测试账号为 `admin` / `argon-html-local-test`。

2026-10-23 验证记录：WordPress 7.1.3，PHP 8.1.34 / 8.3.33 各通过 50 项 HTML 文档功能回归；原有 96 项安全回归通过。Chromium 中使用真实 WP Githuber MD、古腾堡和原生媒体上传验证了保存、内联围栏及编码源码、受保护文件引用、预览、修订访问控制、手机布局、自动高度、目录、PJAX 前进后退、深浅色同步、脚本开关、受限弹窗和下载。未调用实际 AI 服务，摘要验证覆盖输入文本提取及现有摘要优先级。
