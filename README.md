# 聚合收款 SaaS

这是一套建立在 EasyPay 之上的轻量支付产品层，而不是单一收款页，也不是 EasyPay 后台副本。

系统包含：

- **用户收银台**：公开自定义金额、开发者固定金额、支付宝/微信支付、刷新与断网恢复、服务端真实到账结果。
- **本地运营台**：本系统订单、金额异常、取消后到账、回调、管理员通知、Webhook 投递与重试。
- **开发者平台**：应用与 API 凭证、签名 API、固定金额订单、查询/取消、Webhook 端点与投递记录。

## 可靠性边界

- 金额以整数分存储和比较，浏览器不能确认支付或修改开发者订单金额。
- 本地订单在请求 EasyPay 前创建；幂等键、外部订单号和数据库唯一约束共同防止重复下单。
- checkout token 用于刷新、断网和响应丢失后的订单恢复。
- EasyPay 回调必须通过商户号、签名、状态和金额校验；重复回调只产生一次状态变化和一次支付事件。
- 回调瞬时处理失败可以由 EasyPay 使用同一回调安全重放。
- 取消或过期订单仍会主动查询上游；后续到账进入 `paid_after_cancel`，不会被丢弃。
- Webhook 与管理员通知使用数据库任务表、固定退避和卡死任务恢复，语义为至少一次投递。
- EasyPay 继续负责通道、上游全量订单、余额、结算、退款和关闭订单能力；本系统不复制这些平台。

## 技术架构

- PHP 8.4+、Laravel 13、MySQL 5.6+（生产环境建议使用仍受安全支持的版本）。
- Blade + Alpine.js + Vite，设计令牌位于 `resources/css/tokens.css`。
- 模块化单体：支付域、网关适配器、开发者平台、投递服务和运营控制器均在同一应用内。
- 数据库队列与 Laravel Scheduler 负责 Webhook、管理员通知、订单过期和主动对账。
- EasyPay V2（RSA + 时间戳）为默认适配器；V1 仅用于迁移兼容。

## 首次安装

1. 安装 PHP 8.4、Composer、MySQL 5.6 或更高版本和 Node.js，并创建一个专用于本系统的空 MySQL 数据库。
2. 安装依赖、创建基础环境文件并构建前端：

   ```bash
   composer setup
   php artisan config:clear
   ```

3. 在服务器终端生成私有安装令牌：

   ```bash
   php artisan install:token
   ```

4. 启动 Web 服务，通过最终 HTTPS 域名访问 `/install`；本地开发可以通过 `http://127.0.0.1:8000/install` 访问。
5. 按向导依次完成环境检查、站点与 MySQL 连接测试、EasyPay V2、首个管理员、APP_KEY、迁移和初始化。
6. 安装成功后，分别运行 Web、队列和调度进程：

   ```bash
   php artisan serve
   php artisan queue:work --tries=3 --timeout=60
   php artisan schedule:work
   ```

生产环境可使用每分钟执行一次的 `php artisan schedule:run` 代替常驻 `schedule:work`。安装入口的令牌、同源校验、空库保护、密钥暂存和永久锁说明见 `docs/installer-security.md`。

## 必要配置

### 应用与数据库

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://pay.example.com`
- `APP_KEY`：由安装向导生成且不在页面回显，丢失后加密字段将无法读取。
- `DB_*`：MySQL 连接。
- `QUEUE_CONNECTION=database`
- `SESSION_DRIVER=database`
- `CACHE_STORE=database`

### EasyPay

- `PAYMENT_GATEWAY_VERSION=v2`
- `PAYMENT_GATEWAY_CREATION_LEASE_SECONDS=30`：必须大于 EasyPay 创建请求的最长超时时间，用于阻止并发重试重复创建上游订单；低于 30 秒的配置会按 30 秒执行。
- `EASYPAY_MERCHANT_ID`
- `EASYPAY_V2_BASE_URL`
- `EASYPAY_V2_MERCHANT_PRIVATE_KEY`
- `EASYPAY_V2_PLATFORM_PUBLIC_KEY`
- `EASYPAY_V2_TIMESTAMP_TOLERANCE_SECONDS`
- V1 兼容时配置 `EASYPAY_V1_BASE_URL` 和 `EASYPAY_V1_MERCHANT_KEY`。

RSA 密钥可写为 PEM，也可将换行写成字面量 `\n`。生产环境必须使用 HTTPS；否则生成给 EasyPay 的回调地址和同步返回地址不安全或不可用。

### 支付与投递

- `PAYMENT_PUBLIC_MINIMUM_AMOUNT_CENTS` / `PAYMENT_PUBLIC_MAXIMUM_AMOUNT_CENTS`
- `PAYMENT_ORDER_EXPIRATION_MINUTES`
- `PAYMENT_CHECKOUT_TOKEN_TTL_MINUTES`
- `PAYMENT_CALLBACK_PROCESSING_TIMEOUT_SECONDS`
- `PAYMENT_DELIVERY_PROCESSING_TIMEOUT_SECONDS`
- `PAYMENT_ADMIN_NOTIFICATION_TARGETS`：JSON 数组，例如 `[{"channel":"http","destination":"https://ops.example.com/payment-events"}]`。

出站 Webhook 和管理员 HTTP 通知只允许 HTTPS 443、公网 IP，并通过 DNS 解析后固定目标地址，降低 SSRF 与 DNS 重绑定风险。

## 管理员初始化

首个管理员由 `/install` 安装向导直接创建，密码不会经过 `.env` 或 Seeder。运营入口为 `/operations`，开发者应用入口为 `/developer/applications`。

## 开发者 API

API 前缀为 `/api/v1`，提供：

- `POST /payments`：创建固定金额订单并返回统一收银台地址。
- `GET /payments/{order_no}`：查询服务端订单事实。
- `POST /payments/{order_no}/cancel`：本地取消并尽力关闭 EasyPay V2 上游订单。
- Webhook 端点与投递记录的创建、查询、停用和失败重试。

完整签名、请求和 Webhook 验证示例见 `docs/api-integration.md`。

## 后台进程要求

Scheduler 每分钟执行以下任务：

- 分发到期的 Webhook 和管理员通知；
- 将超过处理超时的 `processing` 投递恢复为 `retrying`；
- 主动查询创建中、待支付、已取消、已过期和近期已支付订单；
- 过期未支付订单；
- 清理过期 nonce 与幂等记录。

队列和 Scheduler 任一停止都会延迟通知、补偿和过期处理，但订单与待投递记录仍保存在数据库中，可在进程恢复后继续处理。

## 生产部署检查

- 使用服务器终端生成安装令牌，不将令牌放入 URL、部署日志、截图或工单；安装完成后确认 `/install` 返回 404。
- Web 根目录必须指向项目的 `public` 目录，公网安装前必须设置 `APP_DEBUG=false`，否则安装器拒绝继续。
- 持久化并备份 `storage/app/private/installed.lock`，发布过程不得清空 `storage/app/private`。
- 安装器只接受空数据库；迁移失败后恢复空库再重试，禁止通过删除个别表绕过保护。
- 反向代理正确传递 HTTPS，`APP_URL` 使用最终公网域名。
- EasyPay 回调路径 `/payments/callbacks/easypay/v1|v2` 可公网访问且不经过登录或 CSRF。
- Web 进程、队列进程和 Scheduler 独立守护并设置重启策略。
- 数据库定期备份，`APP_KEY` 和 EasyPay/API 密钥使用密钥管理系统保存。
- 日志中不得记录原始 API 密钥、Webhook 密钥、完整回调签名或未清洗的敏感载荷。
- 监控失败投递、金额异常、取消后到账、队列积压和调度器心跳。

GitHub Actions 构建、发布包、1Panel SSH 自动部署和所需 Secrets 的完整配置见 `docs/deployment.md`。

## 目录

- `app/Domain/Payment`：订单、状态机、幂等、回调、恢复、对账和支付事件。
- `app/Domain/Gateway`：统一网关契约、EasyPay V2/V1 适配器。
- `app/Domain/Developer`：应用、凭证、签名、URL 白名单和 Webhook 端点。
- `app/Domain/Delivery`：安全出站 HTTP、Webhook 签名和卡死投递恢复。
- `app/Http/Controllers/Operations`：聚焦本地订单与异常的运营台。
- `resources/views/checkout`：统一收银台。
- `docs/payment-system-prd.md`：产品需求。
- `docs/easypay-capability-review.md`：EasyPay 复用边界。

## 遗留路径兼容

旧的根目录静态收银台、`assets/`、`api/` 和 `payapi/` 实现已经删除。Laravel 数据库订单、Blade 收银台和网关适配器是唯一有效实现。

为避免易支付后台仍配置旧 V1 地址时丢失通知，Laravel 暂时保留 `/payapi/notify.php`、`/payapi/return.php`、`/api/notify.php` 和 `/api/return.php` 路由别名，并在 `public/` 下保留只加载 Laravel 的入口薄壳；这些路径不包含独立支付逻辑。新配置必须使用 `/payments/callbacks/easypay/v1|v2` 和 `/payments/return/easypay/v1|v2`。
