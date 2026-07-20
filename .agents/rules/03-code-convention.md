# Code Convention

## Mandatory Instruction

The target architecture is a lightweight modular monolith.

Primary stack:

```text
PHP 8.4 or 8.5
Laravel
Blade
Alpine.js
Custom CSS with design tokens
MySQL
Laravel database queue
Laravel Scheduler
OpenResty plus PHP-FPM
Filament for the administration dashboard when needed
```

Do not introduce a separate Vue, React, Node.js, Redis, microservice, WebSocket, Kafka, RabbitMQ, or Kubernetes layer unless the user explicitly approves it for a demonstrated requirement.

## 1. Architecture

Organize the Laravel application by business responsibility, not by large controllers or duplicated helper files.

Recommended shape:

```text
app/
  Domain/
    Payment/
    Gateway/
    Webhook/
    Notification/
  Http/
    Controllers/
    Requests/
    Middleware/
    Resources/
  Jobs/
  Console/
  Models/
resources/
  views/
    components/
    checkout/
    admin/
  css/
  js/
routes/
  web.php
  api.php
tests/
  Feature/
  Unit/
```

Keep one deployable application and one authoritative database.

Domain rules must not depend on Blade, Alpine.js, HTTP request objects, or a specific payment gateway response shape.

## 2. Laravel Conventions

- Use framework routing, validation, database transactions, migrations, queues, scheduling, logging, encryption, and rate limiting.
- Keep controllers thin: validate, authorize, call an application/domain service, return a response.
- Use Form Request classes for non-trivial validation and authorization.
- Use API Resources or explicit response transformers for public JSON contracts.
- Use service classes for payment creation, callback processing, status transitions, reconciliation, and webhook delivery.
- Use backed enums for canonical payment states when supported by the chosen PHP version.
- Use dependency injection instead of service location or global mutable state.
- Do not copy signature or state-transition logic across controllers.
- Do not read environment variables directly outside configuration files.
- Do not put secrets in source code, Blade templates, JavaScript, logs, or committed `.env` files.

## 3. Payment Gateway Boundary

Define a gateway contract such as:

```text
createPayment
queryPayment
closePayment when supported
verifyCallback
normalizeCallback
```

EasyPay-specific parameter names, MD5 rules, URLs, and status mapping belong inside the EasyPay adapter.

The payment domain consumes normalized gateway results and must not be tightly coupled to `mapi.php` response keys.

Do not allow gateway SDK or HTTP response arrays to leak directly into views or database writes.

## 4. Controllers and Services

Controllers must not:

- calculate authoritative money using floats;
- directly mark an order paid;
- contain full signature algorithms;
- perform multi-step callback transactions inline;
- send notifications before the payment transaction commits;
- build large HTML responses manually.

Use dedicated services for:

- order creation and idempotency;
- checkout restoration;
- cancellation;
- verified callback handling;
- amount comparison;
- state transitions;
- reconciliation;
- administrator notification;
- developer webhook creation and delivery.

## 5. Blade

- Use layouts and Blade components instead of repeating page markup.
- Keep business logic out of templates.
- Templates receive presentation-ready values or view models.
- Escape all user and developer-controlled output by default.
- Use named routes instead of hard-coded internal URLs.
- Use semantic HTML and accessible labels.
- Shared components include button, card, input, badge, modal, toast, loading, empty state, payment method selector, and payment status panel.
- Do not create a separate template for every payment status when one state-aware component is clearer.

Recommended view structure:

```text
resources/views/
  layouts/app.blade.php
  checkout/show.blade.php
  components/ui/button.blade.php
  components/ui/card.blade.php
  components/ui/input.blade.php
  components/ui/badge.blade.php
  components/payment/method-selector.blade.php
  components/payment/status-panel.blade.php
  components/payment/qr-panel.blade.php
```

## 6. Alpine.js

Use Alpine.js only for local interface state and browser interaction:

- amount form state;
- selected payment method;
- loading and validation display;
- QR panel transition;
- polling lifecycle;
- online/offline recovery;
- modal and toast behavior.

Alpine.js must not be the source of truth for payment success, actual paid amount, expiration, or authorization.

Server responses must be revalidated after refresh. Local storage contains only safe recovery information such as checkout tokens and idempotency keys.

Keep Alpine components small. Extract repeated logic into reusable functions or modules under `resources/js`.

## 7. CSS

- Follow `.agents/rules/01-design-system.md` before changing UI styles.
- Define tokens once in a central stylesheet.
- Do not use inline `style` attributes.
- Do not place raw colors inside component styles.
- Avoid duplicated selectors and page-specific copies of shared components.
- Use predictable component class naming.
- Keep layout, component, utility, and state concerns clearly separated.
- Scope responsive changes to explicit media queries.
- Use `box-sizing: border-box` globally.
- Use `min-width: 0` for flexible grid and flex children.
- Do not use `100vw` for cards inside padded page containers.
- Respect `prefers-reduced-motion`.

Recommended CSS structure:

```text
resources/css/
  app.css
  tokens.css
  base.css
  components/
    button.css
    card.css
    input.css
    badge.css
    payment.css
  pages/
    checkout.css
    dashboard.css
```

Do not add a CSS framework merely to recreate a small existing design system.

## 8. Money and Value Objects

- Store authoritative amounts as integer cents.
- Parse external decimal strings explicitly and reject invalid precision.
- Format cents only at presentation boundaries.
- Never compare payment amounts using floats.
- Prefer a small money value object or centralized amount utility.
- Currency defaults to CNY but remains explicit in domain and API models.

## 9. Database

- Use migrations for every schema change.
- Use InnoDB and database transactions for payment updates.
- Add unique indexes for order number, gateway trade number, application external order number, idempotency keys, callback fingerprints, and webhook event IDs.
- Add indexes matching administrator filters and reconciliation queries.
- Do not hide important order data inside unqueryable JSON fields.
- JSON metadata is allowed only for non-authoritative developer extension data.
- Use explicit timestamps for creation, expiration, cancellation, payment, and update events.
- Never delete paid or abnormal-payment orders through normal application flows.

## 10. API Conventions

- Version developer APIs under `/api/v1`.
- Keep browser checkout APIs separate from developer APIs.
- Use JSON consistently for API endpoints.
- Return stable machine-readable codes plus Chinese human-readable messages where appropriate.
- Use correct HTTP status codes for validation, authentication, conflict, not found, rate limiting, gateway failure, and server failure.
- Do not return HTML error bodies from JSON routes.
- Use request IDs for tracing when practical.
- Sign developer API requests with HMAC-SHA256.
- Keep EasyPay signing rules isolated from developer API signing rules.

## 11. Jobs, Queue, and Scheduler

Use Laravel database queue initially.

Queue only work that can happen after authoritative payment persistence:

- administrator notifications;
- developer webhook delivery;
- webhook retries;
- email and bot delivery;
- reconciliation and cleanup tasks.

Do not queue the critical callback verification and order update itself.

Jobs must be idempotent, retry-safe, bounded, observable, and linked to an order or event ID.

Use Laravel Scheduler for expiration, reconciliation, cleanup, and stuck-delivery checks.

## 12. Logging and Errors

- Log structured context including request ID, order number, event ID, application ID, and gateway trade number when available.
- Do not log secrets, full credentials, or unnecessary signatures.
- Separate user-safe error messages from internal operational context.
- Catch errors only when adding context, converting boundaries, or implementing a defined recovery path.
- Do not swallow payment or notification failures silently.
- Do not expose stack traces, SQL, file paths, or gateway configuration to users.

## 13. Testing

Use focused unit and feature tests.

Required payment tests include:

- valid and invalid EasyPay signatures;
- expected and actual amount match;
- amount mismatch;
- duplicate callbacks;
- cancelled then paid;
- expired then paid;
- idempotent public order creation;
- idempotent developer API creation;
- immutable API checkout amount;
- refresh and checkout restoration;
- webhook signing and retry;
- webhook SSRF rejection;
- authorization between developer applications;
- JSON endpoints never returning HTML errors.

Mock external gateway and notification HTTP calls. Do not require real payments in automated tests.

## 14. Naming and Style

- Use descriptive English identifiers for code and database fields.
- Use consistent domain terminology: order, expected amount, paid amount, gateway trade number, checkout token, callback, webhook, notification.
- Avoid one-letter names except conventional short loop indexes.
- Use strict types in new PHP files where compatible with the project.
- Add scalar, return, property, and constructor types.
- Prefer small methods with one responsibility.
- Comments explain non-obvious business decisions, not obvious syntax.
- Avoid premature abstraction, but never duplicate security-critical logic.

## 15. Legacy Migration

The existing `api/` directory is deprecated. Do not add new payment behavior there.

Until Laravel migration is complete, `payapi/` is the canonical legacy payment path.

When replacing legacy behavior:

1. Preserve required external callback URLs or add explicit redirects/proxies.
2. Migrate order data safely.
3. Verify callback, return, and status behavior.
4. Remove duplicate implementations only after the new path is proven.

Do not maintain two independent payment state machines.

## 16. Definition of Done

A change is complete only when:

- payment and design rules were followed;
- authoritative amounts remain server-controlled;
- state transitions remain valid;
- duplicate requests and callbacks are safe;
- loading, success, failure, empty, expired, and mobile states are handled where relevant;
- database migrations and indexes are included when needed;
- tests cover important payment behavior;
- no secret or sensitive value is exposed;
- no duplicate styling or business logic was introduced.
