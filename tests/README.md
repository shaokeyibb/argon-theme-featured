# 安全回归测试与部署说明

## 本次修复

- `update_post_meta_ajax` 与 `regenerate_ai_post_summary` 移除匿名入口，要求登录、有效 nonce、有效文章/页面和对应对象编辑权限。元数据只能修改主题设置白名单中的键，并检查值类型和枚举值；相同值也必须先通过鉴权。
- 文末 HTML 与自定义 CSS 额外要求 `unfiltered_html`，同时注册元数据权限回调，覆盖主题 AJAX、普通保存与 WordPress 自定义字段入口。后台 textarea 和 Discourse 字段均按输出上下文转义。
- 评论编辑、置顶、历史、点赞、说说点赞与验证码 AJAX 增加请求校验。置顶要求评论管理权限；访问评论和历史同时检查文章可见性、密码、审核状态和悄悄话权限。普通评论提交也校验父评论归属与可见性。
- 评论 Markdown 最终输出经过 KSES，历史内容作为文本转义；修复短代码属性、GitHub 卡片、分享脚本、SEO、UA 信息、请求头和主题 Cookie 的注入入口。外链统计只接受页面实际生成过的链接凭据。
- 访客令牌改用 `random_bytes`，比较使用 `hash_equals`；新令牌 Cookie 设置 HttpOnly、SameSite=Lax，并在 HTTPS 下设置 Secure。验证码改用签名种子和服务端密钥计算，移除 PHP Session 和全局随机数重新播种。
- 修复 PHP 8 下数组输入、空返回值、缺失字段、排序回调和废弃编码参数的问题。远程请求使用 WordPress HTTP API 并设置超时；AI 未配置或调用失败时返回失败，日志不记录 SDK 请求中的密钥。
- 更新 Composer 锁定依赖：Guzzle 7.16.0、PSR-7 2.13.2、Promises 2.6.0，清除原有依赖安全公告。

## 运行 PHP 集成测试

只在临时 WordPress 环境运行。脚本会创建用户、文章和评论，修改主题选项，不能载入生产站点。Blueprint 使用独立的 WordPress Playground + SQLite，挂载此目录以读取主题并写入测试报告，不连接现有 WordPress 数据库。测试屏蔽邮件发送，不调用实际 AI 服务。

准备 Composer 生产依赖，并使用满足 `@wp-playground/cli` engines 要求的 Node.js。在仓库根目录运行（PowerShell）：

```powershell
composer install --no-dev --prefer-dist --no-interaction
npx --yes @wp-playground/cli@3.1.60 run-blueprint --mount-dir (Get-Location).Path /wordpress/wp-content/themes/argon-theme --blueprint tests/security-blueprint.json
Get-Content temp/security-report-8.3.json
```

Blueprint 默认测试 PHP 8.3。修改副本中的 `preferredVersions.php` 为 `8.1` 或 `8.5` 可测试其他版本。每次运行都会新建数据库，报告写入 `temp/security-report-<PHP主次版本>.json`；报告 `failed` 必须为 0，命令退出码必须为 0。

测试直接调用主题 AJAX 处理函数，通过 WordPress 的 `wp_die_ajax_handler` 读取响应。角色权限、nonce、KSES、文章/评论和元数据操作均使用真实 WordPress；另检查匿名 hook 已移除。它覆盖异常输入、越权和恶意 HTML，也覆盖合法元数据保存、匿名评论提交/编辑、验证码刷新、正常外链点击与页面渲染。主题 PHP 警告和弃用提示会使测试失败。

## 验证记录

2026-10-21，WordPress 7.1.3：

| PHP | 集成测试 |
| --- | --- |
| 8.1.34 | 96 项通过 |
| 8.3.33 | 96 项通过 |
| 8.5.10 | 96 项通过 |

另已检查全部已跟踪 PHP 文件及新增 PHP 文件的语法，运行前端 `pnpm run build`，并在真实浏览器 DOM 中验证 GitHub 卡片前端/后端两条路径对恶意描述与主页地址的处理（4 个用例）。

依赖审计命令：

```powershell
composer audit --locked --no-dev --abandoned=report
pnpm --dir assets audit --prod
pnpm --dir assets run build
```

本次 Composer 与 pnpm 生产依赖安全公告均为 0。`haozi-team/chatgpt-php` 仍被标记为 abandoned；本次保留现有 SDK 接口并修复其错误处理，后续应单独迁移 SDK。审计结果仅覆盖依赖数据库已收录的公告。

## 部署要点

1. 服务器至少使用 PHP 8.1，并保留主题已依赖的 DOM、mbstring 等扩展。`style.css` 已声明最低 PHP 版本。
2. 按更新后的 `composer.lock` 执行 `composer install --no-dev --prefer-dist --no-interaction`；仅复制 PHP 文件而沿用旧 `vendor` 不会获得依赖补丁。
3. 同时发布 PHP 文件与 `assets/dist/argon-theme.js`，清理页面缓存和 CDN 缓存。新增 nonce 参数要求前后端版本一致；长时间缓存的页面可能持有过期 nonce，页面缓存时间需短于站点 nonce 有效期。
4. 原始 HTML/CSS 现在只允许有 `unfiltered_html` 权限的用户修改。既有内容不会自动重写；如果站点曾开放不可信作者账号，应另行检查历史内容。

以上回归记录来自本地代码和隔离测试站点。真实 AI 服务成功响应、特定插件组合或生产缓存配置需在部署环境另行验证；公开点赞和计数接口的 nonce 也不等同于反刷限流。
