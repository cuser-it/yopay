# Payment Business Rules

## Mandatory Instruction

Payment correctness, data integrity, and security override convenience and visual behavior.

Never trust the browser to determine payment amount, order status, payment success, callback validity, or business fulfillment.

The complete product context is available in `docs/payment-system-prd.md`.

## 1. Supported Payment Modes

The system supports two modes.

### Public Collection

- The payer enters an amount.
- The payer chooses Alipay or WeChat Pay.
- The server validates and stores the amount before creating the gateway order.
- Once created, the amount on that order is immutable.
- To change an amount, abandon the current order and create a new one.

### Developer API Checkout

- A developer creates the order server-to-server.
- The amount is supplied in a signed API request.
- The checkout loads the amount from the server and displays it as read-only.
- The payer must never be allowed to modify the API-controlled amount.
- The checkout is accessed through an opaque, high-entropy token.

Only `alipay` and `wxpay` are supported. Do not add or restore QQ Wallet unless explicitly requested.

## 2. Order States

Use these canonical states:

| State | Meaning |
| --- | --- |
| `creating` | Local order exists and gateway creation is in progress |
| `pending` | Payment information exists and payment is awaited |
| `paid` | Callback/query is verified and actual amount matches expected amount |
| `expired` | Payment validity period ended without confirmed payment |
| `cancelled` | The local order was abandoned or cancelled |
| `failed` | Gateway creation or internal creation failed |
| `amount_mismatch` | Verified actual amount differs from expected amount |
| `paid_after_cancel` | A cancelled or expired order later received verified payment |
| `refunded` | Payment was refunded; reserved for later implementation |

Do not invent alternative names for the same state.

Allowed transitions:

```text
creating -> pending
creating -> failed
pending -> paid
pending -> amount_mismatch
pending -> expired
pending -> cancelled
cancelled -> paid_after_cancel
expired -> paid_after_cancel
paid -> refunded
```

Final payment states must never be overwritten back to `pending`.

Every state change must record time, source, and enough context for audit.

## 3. Amount Trust Rules

All internal money values use integer cents.

Required fields:

```text
expected_amount_cents
paid_amount_cents
amount_difference_cents
currency
```

Do not use floating-point values for authoritative payment calculations.

The expected amount comes from the server-side order record.

The actual paid amount comes from the verified EasyPay callback or verified gateway order query result.

The success page must never use an input value, query parameter, JavaScript state, local storage value, or unsigned developer parameter as the actual paid amount.

Amount decision:

```text
actual == expected -> paid
actual != expected -> amount_mismatch
```

An amount mismatch must not trigger normal fulfillment. It must create an administrator alert and a developer abnormal-payment event when applicable.

## 4. Order Creation

Create the local order before or atomically around the gateway creation process.

Persist gateway QR content, direct payment URL, gateway transaction number, creation time, expiration time, and checkout token information.

Do not return payment information to the browser without also persisting enough data to restore the order after refresh.

Order numbers must be generated with a collision-resistant strategy and protected by a unique database index.

### Idempotency

- Public checkout creation uses a client-generated random idempotency key.
- Developer API creation uses `Idempotency-Key` scoped to the developer application.
- Identical retries return the original order.
- The same key with different important parameters returns an idempotency conflict.
- `(app_id, external_order_no)` is unique.
- The same external order number must not create different amounts.

## 5. Refresh and Network Recovery

After order creation, the checkout stores only a safe resume token, not trusted payment truth.

On load or refresh:

1. Read the checkout token from the URL or local recovery storage.
2. Query the server for the authoritative order.
3. Restore the correct state, QR code, amount, expiration, and polling behavior.
4. If paid, display the server-confirmed actual amount and paid time.
5. If expired, cancelled, failed, or mismatched, render the matching state instead of silently creating a new order.

QR generation must be local or self-hosted. Do not depend on a public QR image API.

If QR rendering fails, provide the direct payment link as a fallback.

## 6. Cancellation and Repayment

- Only `creating` or `pending` orders may be cancelled by normal user or API actions.
- A paid, mismatched, or paid-after-cancel order cannot be cancelled by the payer.
- Cancelling an order never deletes it.
- Creating a replacement order always creates a new order number and idempotency key.

If the gateway has no close-order API, cancellation means local abandonment only. Do not claim that the channel order is closed.

If a cancelled or expired order later receives verified payment, move it to `paid_after_cancel`, preserve the actual amount, and alert the administrator.

Recommended user wording: 放弃当前订单并重新支付.

## 7. EasyPay Callback Rules

The asynchronous `notify_url` callback is the primary payment confirmation source.

The browser `return_url` improves user experience but must not independently determine final success.

Typical callback parameters include:

```text
pid
trade_no
out_trade_no
type
name
money
trade_status
sign
sign_type
```

Process callbacks in this order:

1. Capture a sanitized callback record and request identifier.
2. Validate required parameters.
3. Validate `pid` against server configuration.
4. Verify the EasyPay signature using the exact documented signing rules.
5. Find and lock the local order.
6. Validate the current state and gateway transaction number uniqueness.
7. Parse callback `money` into integer cents without float-based comparison.
8. Compare actual and expected amounts.
9. Update order and callback records in one database transaction.
10. Create notification or webhook jobs inside the same committed business flow.
11. Return the gateway-required success response only after durable persistence.

Never log merchant secrets or expose callback signatures unnecessarily.

### Callback Idempotency

- Use a unique callback fingerprint or a unique gateway trade number constraint.
- Duplicate callbacks must not duplicate accounting, fulfillment, notifications, or webhooks.
- A duplicate valid callback should receive the expected success response.
- Every business side effect must be idempotent independently of callback duplication.

## 8. Synchronous Return

- Verify the return signature when present.
- Redirect using an opaque checkout/result token.
- Do not include a trusted amount in the URL.
- Query the authoritative server-side order on the result page.
- If asynchronous confirmation has not arrived, show confirming status and continue server queries.
- Do not mark an order paid only because the browser returned from the gateway.

## 9. Reconciliation

Use a scheduled job to inspect long-running pending orders and callback gaps.

- Query the gateway order API when supported.
- Feed verified query results through the same amount and state transition rules used by callbacks.
- Record `reconciliation` as the status source.
- Do not implement a second, weaker paid-order path.
- Alert administrators when the local and gateway states disagree.

## 10. Developer API Rules

Developer APIs use versioned routes under `/api/v1`.

Required creation concepts:

- `app_id`;
- external order number;
- amount in cents;
- currency;
- subject or description;
- optional fixed payment type;
- notify URL;
- return URL;
- metadata;
- timestamp;
- nonce;
- HMAC-SHA256 signature;
- idempotency key.

Security requirements:

- Validate signature against the raw canonical request representation.
- Reject expired timestamps and replayed nonces.
- Restrict notify and return URLs to configured application allowlists.
- Optionally enforce IP allowlists and amount limits.
- Rate limit by application and IP.
- Never expose `app_secret` after creation except during an explicit secure rotation flow.

The API checkout amount is immutable and must be loaded from the server.

Developers may query only orders belonging to their own application.

## 11. Developer Webhooks

Webhook events include:

```text
payment.paid
payment.amount_mismatch
payment.paid_after_cancel
payment.expired
payment.cancelled
payment.refunded
```

Every event has a globally unique `event_id` and includes expected amount, actual amount, payment method, gateway transaction number, status, and paid time when available.

Sign developer webhooks with HMAC-SHA256 over the timestamp and exact raw request body.

- Deliver asynchronously after the payment transaction commits.
- Reuse the same event ID for retries.
- Treat HTTP 2xx as success.
- Record attempts, response status, response summary, duration, and error.
- Use bounded exponential retry and allow manual redelivery.
- Prevent webhook SSRF by rejecting loopback, private, link-local, and cloud metadata targets.

User-facing success does not depend on successful developer webhook delivery.

## 12. Administrator Notifications

Verified successful payments notify the administrator through configured channels.

Notifications include order number, external order number, expected amount, actual amount, payment type, gateway trade number, source, creation time, and paid time.

Mismatch, paid-after-cancel, bad signature, duplicate trade number, and repeated webhook failure use explicit abnormal-payment alerts.

Notification delivery is asynchronous, recorded, retried, and manually repeatable. It must not delay the EasyPay callback response.

## 13. Persistence and Transactions

MySQL is the authoritative store for orders, callbacks, status events, webhooks, notifications, applications, and audits.

Do not use JSON files as the production source of truth.

Use database transactions and unique constraints for:

- internal order number;
- gateway trade number;
- application plus external order number;
- application plus idempotency key;
- callback fingerprint;
- webhook event ID.

The database order update and creation of downstream delivery tasks must be committed consistently. Queue execution may be asynchronous, but the task record must not be lost.

## 14. API and JSON Behavior

- All API responses use JSON with a consistent shape.
- Use appropriate HTTP status codes.
- Validation errors use stable machine-readable error codes and human-readable messages.
- Never return HTML error pages from JSON endpoints.
- Do not expose stack traces, filesystem paths, secrets, database errors, or gateway keys.
- Include a request ID for operational tracing when practical.

## 15. Security Boundaries

- HTTPS is mandatory in production.
- Gateway and developer secrets remain server-side.
- Checkout tokens must be high entropy, expiring, and non-enumerable.
- Store token hashes where practical.
- Protect administrator actions with authenticated sessions, CSRF protection, rate limiting, secure cookies, and optional MFA.
- Escape user and developer-controlled text before rendering.
- Minimize sensitive callback and request logging.
- Never weaken signature or amount verification to make a test pass.

## 16. Background Work

The initial system uses Laravel database queues and Laravel Scheduler.

Suitable queued tasks:

- administrator notifications;
- developer webhook delivery;
- webhook retries;
- email or bot delivery;
- reconciliation requests;
- cleanup and expiration work.

Core payment confirmation remains synchronous through a MySQL transaction. Do not defer the authoritative order update to a queue.

Redis may be introduced later without changing domain behavior, but must not become the source of payment truth.

## 17. Acceptance Invariants

Every payment implementation must preserve these invariants:

1. Refreshing does not lose a created order.
2. Retrying creation with the same idempotency key does not create a duplicate order.
3. The browser cannot mark an order paid.
4. The final displayed amount comes from verified server data.
5. Amount mismatch never becomes normal paid fulfillment.
6. Duplicate callbacks do not duplicate side effects.
7. Cancelled orders are retained and late payment is detected.
8. API-controlled amounts cannot be edited by the payer.
9. Notifications and webhooks can fail without losing the payment record.
10. Missed callbacks can be recovered through reconciliation.
