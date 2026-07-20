# 开发者 API 接入指南

本文档描述 `/api/v1` 的请求签名、固定金额订单、查询、取消和 Webhook 验证。所有生产请求必须使用 HTTPS。

## 1. 创建应用与凭证

登录开发者平台，在 `/developer/applications` 创建应用。系统返回：

- `app_id`：应用公开标识，对应请求头 `X-App-Id`。
- `key_id`：凭证标识，对应可选请求头 `X-Key-Id`。
- `secret`：HMAC 密钥，只显示一次。

应用可配置：

- 通知 URL 白名单；
- 用户返回 URL 白名单；
- IP 白名单；
- 最小和最大订单金额。

`notify_url` 必须命中通知白名单，并且解析为 HTTPS 443 公网地址。`return_url` 必须命中返回 URL 白名单。

## 2. 请求签名

每个受保护请求需要以下请求头：

| 请求头 | 说明 |
| --- | --- |
| `X-App-Id` | 应用公开标识 |
| `X-Key-Id` | 凭证标识；应用只有一个有效凭证时可省略 |
| `X-Timestamp` | 10 位 Unix 秒时间戳 |
| `X-Nonce` | 16–128 字符的一次性随机值 |
| `X-Signature` | HMAC-SHA256 十六进制签名，可带 `sha256=` 前缀 |
| `Idempotency-Key` | 创建订单时必填，16–128 字符 |

默认允许时间偏差为 300 秒；同一应用的 nonce 在有效期内只能使用一次。

### 2.1 规范字符串

按以下五行拼接，行之间使用单个换行符 `\n`，末尾不追加换行：

```text
{timestamp}
{nonce}
{UPPERCASE_METHOD}
{request_target}
{sha256_hex(raw_body)}
```

`request_target` 是原始路径和查询串，例如 `/api/v1/payments?expand=checkout`，不包含协议和域名。`raw_body` 必须是实际发送的原始字节；不要解析 JSON 后重新序列化再验签。

签名计算：

```text
signature = hex(HMAC-SHA256(secret, canonical_string))
```

### 2.2 Node.js 示例

```js
import crypto from 'node:crypto';

const timestamp = Math.floor(Date.now() / 1000).toString();
const nonce = crypto.randomBytes(18).toString('base64url');
const method = 'POST';
const requestTarget = '/api/v1/payments';
const body = JSON.stringify({
  external_order_no: 'ORDER-20260718-001',
  amount_cents: 1000,
  currency: 'CNY',
  subject: '测试订单',
});
const bodyHash = crypto.createHash('sha256').update(body).digest('hex');
const canonical = [timestamp, nonce, method, requestTarget, bodyHash].join('\n');
const signature = crypto.createHmac('sha256', process.env.PAYMENT_API_SECRET)
  .update(canonical)
  .digest('hex');
```

发送请求时必须复用上面参与签名的同一份 `body` 字符串。

## 3. 创建固定金额订单

`POST /api/v1/payments`

请求体：

```json
{
  "external_order_no": "ORDER-20260718-001",
  "amount_cents": 1000,
  "currency": "CNY",
  "subject": "测试订单",
  "description": "可选说明",
  "payment_type": "alipay",
  "notify_url": "https://merchant.example.com/payment/webhook",
  "return_url": "https://merchant.example.com/orders/ORDER-20260718-001",
  "metadata": {
    "customer_id": "C10001"
  }
}
```

规则：

- `amount_cents` 是整数分，不能使用浮点元。
- `currency` 当前仅支持 `CNY`。
- `payment_type` 可省略；省略后由付款人在统一收银台选择 `alipay` 或 `wxpay`。
- `external_order_no` 在同一应用内唯一，创建后金额、币种和业务映射不可修改。
- 网络超时或响应丢失时，使用相同 `Idempotency-Key` 和完全相同的业务参数重试。
- 相同幂等键或相同外部订单号配合不同参数会返回 `IDEMPOTENCY_CONFLICT`。

成功响应示例：

```json
{
  "data": {
    "order_no": "PAY20260718...",
    "external_order_no": "ORDER-20260718-001",
    "status": "creating",
    "expected_amount_cents": 1000,
    "paid_amount_cents": null,
    "amount_difference_cents": null,
    "currency": "CNY",
    "subject": "测试订单",
    "payment_type": "alipay",
    "channel_trade_no": null,
    "checkout_url": "https://pay.example.com/checkout/...",
    "created_at": "2026-07-18T12:00:00+08:00",
    "expires_at": "2026-07-18T12:15:00+08:00",
    "paid_at": null,
    "cancelled_at": null,
    "exception_code": null
  },
  "meta": {
    "idempotent_replay": false
  },
  "error": null
}
```

开发者服务端应把本地业务订单与 `order_no` 持久化，然后将用户重定向到 `checkout_url`。

## 4. 查询订单

`GET /api/v1/payments/{order_no}`

查询结果来自本系统数据库中的服务端事实。重要状态：

| 状态 | 含义 |
| --- | --- |
| `creating` | 本地订单已创建，网关响应待恢复 |
| `pending` | 已生成支付动作，等待付款 |
| `paid` | 已验签且实收金额与预期一致 |
| `amount_mismatch` | 已验签，但实收金额与预期不一致，需要人工处理 |
| `cancelled` | 本地已取消，仍可能检测到后续到账 |
| `expired` | 已过期，仍可能检测到后续到账 |
| `paid_after_cancel` | 取消或过期后检测到真实到账，需要人工处理 |
| `failed` | 未建立可恢复的上游支付或配置失败 |
| `refunded` | 主动查询确认上游已退款 |

只有 `paid` 可直接作为正常履约条件。`amount_mismatch` 和 `paid_after_cancel` 必须进入人工异常流程。

## 5. 取消订单

`POST /api/v1/payments/{order_no}/cancel`

取消只接受 `creating` 或 `pending`。系统先持久化本地取消状态，再尽力调用 EasyPay V2 关闭订单；响应字段 `channel_order_closed` 表示上游关闭是否确认。

取消不是删除。若关闭请求失败或付款已在途，后续验签回调/主动查询会把订单标记为 `paid_after_cancel`。

## 6. Webhook 端点

受保护接口：

- `GET /api/v1/webhook-endpoints`
- `POST /api/v1/webhook-endpoints`
- `DELETE /api/v1/webhook-endpoints/{endpoint_id}`
- `GET /api/v1/webhook-deliveries`
- `POST /api/v1/webhook-deliveries/{delivery_id}/retry`

创建端点示例：

```json
{
  "name": "生产订单服务",
  "url": "https://merchant.example.com/payment/webhook",
  "subscribed_events": [
    "payment.paid",
    "payment.amount_mismatch",
    "payment.paid_after_cancel"
  ]
}
```

返回的 `signing_secret` 只显示一次。停用端点只阻止未来事件创建新投递，不删除历史投递。

支持的事件：

- `payment.paid`
- `payment.amount_mismatch`
- `payment.paid_after_cancel`
- `payment.expired`
- `payment.cancelled`
- `payment.refunded`

## 7. Webhook 验证

请求头：

| 请求头 | 说明 |
| --- | --- |
| `X-Webhook-Id` | 全局唯一事件 ID，也是业务幂等键 |
| `X-Webhook-Timestamp` | Unix 秒时间戳 |
| `X-Webhook-Signature` | HMAC-SHA256 十六进制签名 |

签名内容：

```text
signed_payload = timestamp + "." + raw_body
signature = hex(HMAC-SHA256(signing_secret, signed_payload))
```

接收方必须：

1. 读取原始请求体，不要先重新序列化 JSON。
2. 校验时间戳窗口，建议拒绝超过 5 分钟的请求。
3. 使用常量时间比较验证签名。
4. 以 `X-Webhook-Id`/载荷中的 `event_id` 建立唯一约束。
5. 在同一数据库事务内完成“记录事件 ID + 业务状态变更”。
6. 成功处理或确认已处理后返回任意 HTTP 2xx。

事件示例：

```json
{
  "event_id": "01J...",
  "event_type": "payment.paid",
  "created_at": "2026-07-18T12:01:12+08:00",
  "data": {
    "order_no": "PAY20260718...",
    "external_order_no": "ORDER-20260718-001",
    "status": "paid",
    "expected_amount_cents": 1000,
    "paid_amount_cents": 1000,
    "amount_difference_cents": 0,
    "currency": "CNY",
    "payment_type": "alipay",
    "channel_trade_no": "20260718...",
    "created_at": "2026-07-18T12:00:00+08:00",
    "paid_at": "2026-07-18T12:01:10+08:00",
    "metadata": {
      "customer_id": "C10001"
    }
  }
}
```

## 8. 重试语义

Webhook 为至少一次投递，接收方必须幂等。非 2xx、连接失败、DNS/安全校验失败或超时都会进入重试。

默认失败后等待：1 分钟、5 分钟、30 分钟、2 小时、6 小时、24 小时；下一次仍失败后状态变为 `failed`。进程中断导致的 `processing` 任务会在处理超时后自动恢复。开发者只能人工重试 `failed` 或 `retrying` 状态，已成功投递不会被“重试”接口重复发送。

## 9. 错误响应

统一格式：

```json
{
  "data": null,
  "meta": null,
  "error": {
    "code": "IDEMPOTENCY_CONFLICT",
    "message": "...",
    "details": null
  }
}
```

常见错误：`INVALID_SIGNATURE`、`REQUEST_EXPIRED`、`NONCE_REPLAYED`、`APP_DISABLED`、`INVALID_AMOUNT`、`IDEMPOTENCY_CONFLICT`、`ORDER_NOT_FOUND`、`ORDER_NOT_CANCELLABLE`、`CHANNEL_UNAVAILABLE`。
