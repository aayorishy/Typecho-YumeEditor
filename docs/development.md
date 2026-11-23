# YumeEditor 开发约定

本文档是 YumeEditor 的开发说明正文。用户安装与功能说明见根目录 `README.md`，语法行为见 `SYNTAX.md`。

## 项目概述

YumeEditor 是面向 Typecho 的 Markdown 编辑器插件，提供实时预览、代码块增强、围栏容器、Mermaid、数学公式与评论可见内容。后台视觉与 MoePanel 保持一致，前台排版可嵌入 Yume 主题。

## 技术栈

### 后端

- 语言：PHP，保持 PHP 7.2 语法兼容（当前在 PHP 8.4 下测试）。
- 框架：Typecho Plugin API（1.2+，对 1.3+ 有分支处理）。
- 命名空间：`TypechoPlugin\YumeEditor`。
- 类文件按 PSR-4 风格平铺在插件根目录，Typecho 的插件自动加载器按命名空间映射到同名文件。

### 前端

- 构建：Laravel Mix 6 + Webpack 5。
- 语言：原生 JavaScript（ES6+）。
- 样式：SCSS，使用 CSS 自定义属性承载设计令牌。
- 依赖管理：pnpm（`pnpm-lock.yaml`）。
- 代码高亮：Highlight.js 11；Markdown 解析：HyperDown；图表：Mermaid 11；公式：MathJax 3（本地镜像为 CHTML，远端为 SVG）。

## 项目结构

```text
YumeEditor/
├── Plugin.php              # 元信息、生命周期、设置入口
├── Config.php              # 设置页渲染、字段 schema、备份操作
├── Editor.php              # 写作页资源、工具栏注入、提交栏增强
├── Front.php               # 前台资源注入（样式、高亮、Mermaid、公式）
├── MathJax.php             # MathJax 配置与公式源码复制
├── Content.php             # Markdown 渲染、围栏后处理、评论可见性
├── Util.php                # 配置读取、资源 URL、镜像与工具函数
├── HyperDown.php           # Markdown 解析器
├── assets/
│   ├── src/
│   │   ├── _tokens.scss    # 设计令牌（对齐 MoePanel，含主题回退）
│   │   ├── _document.scss  # 正文排版 mixin
│   │   ├── _code.scss      # 代码块 mixin
│   │   ├── editor/         # 围栏、标签页、代码块细节 mixin
│   │   ├── main.scss       # 写作页与预览
│   │   ├── config/         # 设置页样式与交互
│   │   ├── front-*.scss    # 前台入口
│   │   └── utils/          # 代码块、Mermaid、标签页等前端模块
│   ├── dist/               # 构建产物，已纳入版本控制
│   ├── json/               # 表情、字符与符号配置
│   └── mirror/             # CDN 镜像资源
├── tests/                  # PHP / JavaScript 测试
├── docs/                   # 开发约定
└── webpack.mix.js          # Laravel Mix 配置
```

## 开发工作流

### 安装依赖

```bash
pnpm install
```

### 开发与构建

```bash
pnpm run dev      # 开发模式，BrowserSync 代理 localhost
pnpm run watch    # 监听源码
pnpm run build    # 生产构建，清理并生成 assets/dist
```

构建入口是 `assets/src/`，输出到 `assets/dist/`。生产构建会清理目标目录；提交前应确认构建产物与源码一致。

### 测试

```bash
pnpm test                      # 运行全部 PHP / JavaScript 测试
PHP_BINARY=/path/to/php pnpm test   # 指定 PHP 可执行文件
```

测试覆盖：配置页结构与备份接口安全、配置页前端交互（jsdom）、围栏解析（宫格 / 瀑布流 / 嵌套标签页）、代码块保护、图片与链接属性、代码高亮区间。

## 架构约定

### PHP

- `Plugin` 只负责元信息与生命周期，具体逻辑委托给 `Config` / `Util`。
- 视图型代码（设置页、写作页脚本）集中在对应类中，内容处理集中在 `Content`。
- 配置项统一用 `Util::pluginOption()` 读取：先读 `YumeEditor`，缺失时回退到旧版配置键，保证升级不丢设置。
- 资源路径统一走 `Util::pluginStatic()`，会追加内容哈希，避免浏览器缓存旧资源。
- 修改 `activate()` 中的钩子注册时，需同时检查 `tests/pass-comment-permission.php` 对回调类的断言。

### JavaScript

- 使用 ES6+；优先 `const`/`let`。
- 需要暴露给写作页的能力挂到 `window.YumeEditor*` 命名空间；事件统一使用 `YumeEditor*` 前缀。
- 编辑器预览的代码块渲染由 `utils/codeBlocks.js` 提供，`previewUtils.js` 在模块加载时注册，必须保持这一行为，否则后台预览不会高亮。

### SCSS

- 设计令牌集中在 `_tokens.scss`，一律通过 `var(--ym-*)` 使用；令牌优先引用宿主设计系统（`--paper`、`--ink`、`--accent` 等），其次回退到内置值。
- 前台通用排版必须包在 `:where()` 中，保持零优先级，让主题样式获胜；插件组件样式才使用正常优先级。
- 深色模式同时支持 `html[data-studio-theme="dark"]`（MoePanel）、`:root[data-theme="dark"]`（Yume）与系统偏好回退。
- 不引入 Tailwind 等需要 `@apply` 的语法；构建产物中出现 `@apply` 即为缺陷。

## 重要配置项

- jsDelivr 镜像：默认本地镜像，也可切换公共镜像。
- 附件上传：禁止特定后缀自动插入、一键插入全部图片。
- 停用行为：可选择保留或清理设置备份；修改清理逻辑时需同时检查 `Util::deactivate()` 与备份测试。

## 版本发布流程

1. 更新 `Plugin.php` 的 `@version`、`README.md`、`package.json` 与 `CHANGELOG.md` 的版本号。
2. 如果改动涉及界面，重新生成 `docs/preview/` 中的截图；截图使用独立的 Demo 站点，不包含个人数据。
3. `pnpm run build` 构建前端资源。
4. `pnpm test` 确认全部测试通过。
5. 检查 `assets/dist` 中不含 `@apply`、旧标识符或调试日志。
6. 打包发布，并确认压缩包排除了 `node_modules`、`.build`、`pnpm-lock.yaml` 等开发文件。
7. 许可证与仓库元信息保持一致：`LICENSE`、`package.json`、`README.md` 均为 Apache-2.0。
