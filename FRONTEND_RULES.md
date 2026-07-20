# Frontend Architecture Rules

## Mandatory Instruction

IMPORTANT:

Before creating or restructuring any user-facing page, read:

1. `.agents/rules/01-design-system.md`;
2. `.agents/rules/02-payment-business-rules.md` when payment or order state is involved;
3. `.agents/rules/03-code-convention.md`;
4. this frontend architecture document;
5. the relevant section of `docs/payment-system-prd.md`.

Do not generate isolated pages first and attempt to unify them later.

Always establish the information architecture, module boundary, shared components, design tokens, server contract, and responsive behavior before implementing a page.

The goal is not maximum generation speed. The goal is a maintainable, reliable payment SaaS interface that remains consistent as the product grows.

## 1. Product Surfaces

The product has three user-facing surfaces:

1. **Public checkout** for custom-amount payments and developer-created fixed-amount payments.
2. **Local operations console** for local business orders, exceptions, callbacks, administrator notifications, developer applications, webhook deliveries, and audits.
3. **Developer platform** for API credentials, API documentation, integration examples, order lookup, and webhook configuration.

The local operations console is not a replacement for EasyPay's full merchant backend.

Do not rebuild EasyPay's complete upstream order center, merchant balance, settlement ledger, payment-channel management, or refund engine. Reuse EasyPay V2 capabilities through the gateway adapter and retain only the local product data required by the PRD.

## 2. Experience Goals

Every surface must communicate:

- trust;
- stability;
- professional restraint;
- clear payment amounts;
- explicit order status;
- efficient operations;
- minimal visual noise.

Use Apple Pay clarity, Stripe Dashboard trust, Linear precision, and Vercel information hierarchy as references without copying their branding or inventing decorative effects.

Avoid entertainment-oriented visuals, excessive gradients, glassmorphism, oversized cards, card stacking, unnecessary animation, and inconsistent page styles.

## 3. Approved Frontend Stack

Use the existing server-rendered modular monolith:

- Laravel;
- Blade layouts and Blade components;
- Alpine.js for small local interactions;
- Vite for asset compilation;
- project CSS variables and shared component styles;
- Laravel localization files for user-facing copy that needs reuse or translation.

Do not replace this architecture with Vue, React, a separate SPA, Pinia, Axios-only client state, Tailwind, or shadcn unless the user explicitly approves a documented architecture change.

Server-rendered HTML is the default. Use JSON endpoints only for asynchronous actions such as creating an order, restoring checkout state, polling order status, cancelling an order, and operational filters that genuinely benefit from partial updates.

## 4. Frontend Module Boundaries

Organize frontend code by business responsibility rather than by a single global component folder.

Recommended responsibilities:

```text
resources/views/
├── checkout/
├── operations/
├── developer/
├── auth/
├── layouts/
└── components/

resources/js/
├── checkout/
├── operations/
├── developer/
└── shared/

resources/css/
├── tokens.css
├── base.css
├── components/
└── modules/
```

Module-specific UI, Alpine behavior, and styles stay inside their business module when they are not shared.

Shared components contain only genuinely reusable primitives or cross-module patterns. Do not place every business component in a global directory.

## 5. Component Layers

Build components in three layers.

### 5.1 Design Primitives

Examples:

- button;
- input;
- select;
- badge;
- card;
- modal;
- table;
- toast;
- loading indicator;
- empty state.

These components own visual tokens and common accessibility behavior.

### 5.2 Payment Components

Examples:

- amount input;
- amount display;
- payment method selector;
- QR code panel;
- payment status display;
- order summary;
- payment success result;
- payment exception notice.

These components display server-authoritative payment data and must never invent order status or actual paid amount in the browser.

### 5.3 Operations Components

Examples:

- local order table;
- order status badge;
- order detail drawer;
- callback event list;
- webhook delivery log;
- notification delivery log;
- exception summary.

Do not create generic abstractions before two or more real use cases demonstrate reuse.

## 6. Page Responsibilities

Each page must have one primary user objective.

- Checkout: complete or resume one payment safely.
- Payment result: show the server-confirmed result and actual paid amount.
- Operations dashboard: identify recent payments, delivery failures, and exceptional orders.
- Local order list: find product-created orders and inspect status.
- Order detail: review order mapping, callback facts, amount comparison, and delivery history.
- Developer application: manage credentials and callback configuration.
- API documentation: explain integration with navigable documentation and copyable examples.

Do not turn a page into a collection of all available upstream data. Information density must serve the page objective.

## 7. State Ownership

The server is authoritative for:

- order existence;
- expected amount;
- actual paid amount;
- payment status;
- expiration;
- cancellation;
- callback verification;
- authorization;
- EasyPay order mapping.

Alpine.js may manage only local presentation state such as modal visibility, selected tabs, temporary validation messages, loading state, and the current checkout panel.

Browser storage may keep only an opaque checkout recovery token and non-authoritative UI preferences. It must not be treated as proof of payment or as the source of the final amount.

## 8. Request Boundaries

Do not make payment requests directly from arbitrary Blade components.

Route browser requests through explicit checkout endpoints and reusable JavaScript request helpers. All JSON endpoints must return a stable JSON envelope and must never leak HTML error pages.

Centralize:

- CSRF handling;
- request IDs when available;
- JSON parsing;
- timeout handling;
- network error mapping;
- retry decisions;
- user-safe error messages.

Creating an order is protected by idempotency. Status polling and restoration are safe to repeat. Client retries must not create duplicate payable orders.

## 9. Checkout Responsive Contract

The checkout must remain a compact single-page experience.

### Desktop

- Initial state shows one centered payment card.
- The payment detail panel is not rendered as a large empty area before an order exists.
- After successful order creation, the detail panel enters with a restrained fade-and-slide transition, visually emerging from behind the form card.
- The form card may reduce width or shift to make room, but the complete experience should fit within the viewport at common desktop sizes.

### Mobile

- Initial state shows amount input, supported payment methods, and the payment action.
- After order creation, replace the form content with the QR code and payment detail state rather than forcing a two-column layout.
- Every container uses the available parent width and must not overflow the viewport.
- Use `width: 100%`, `max-width`, container padding, and `box-sizing: border-box`; do not use `100vw` inside padded containers.

Only Alipay and WeChat Pay are supported. Do not add QQ Wallet.

## 10. Design Tokens and Styling

All colors, spacing, typography, radius, shadows, sizes, and transitions must come from the design system and CSS variables.

Do not hardcode new visual values inside Blade templates, Alpine expressions, or module scripts.

Prefer semantic component classes over large page-specific selector chains. Reuse established primitives before adding new CSS.

Inline styles are prohibited except for unavoidable runtime values that cannot be represented safely by a class or CSS custom property.

## 11. Localization and Copy

Keep payment language concise, factual, and reassuring.

Reusable titles, buttons, statuses, validation messages, and error messages should use Laravel language files rather than being duplicated across templates.

Chinese is the initial product language. The architecture must allow later English support without requiring page rewrites.

Do not translate raw gateway errors directly to users. Map them to stable product messages and keep technical context in logs.

## 12. Page Development Workflow

For every page or major page refactor:

1. Read the PRD and identify the page's single primary responsibility.
2. Confirm whether EasyPay or an existing local module already provides the required capability.
3. Define server-authoritative data, endpoints, states, and error cases.
4. Sketch the information hierarchy and desktop/mobile behavior.
5. Inventory existing primitives and business components.
6. Add only the missing reusable components.
7. Implement the page using layouts and module boundaries.
8. Verify loading, empty, validation, pending, success, failure, expired, cancelled, mismatch, and restored states where relevant.
9. Verify accessibility, keyboard behavior, responsive containment, and visual tokens.
10. Remove duplicated markup, styles, requests, and state logic.

Do not start with decorative polishing before payment state and information architecture are correct.

## 13. Refactor Sequence

Refactor the current project incrementally.

### Phase 0: Legacy Audit and Cleanup

- the former `index.html`, `success.html`, `assets/`, `payapi/`, and deprecated `api/` implementations have been removed;
- preserve previously configured EasyPay V1 callback and return URLs only through thin Laravel route aliases and public bootstrap shims;
- do not restore duplicate frontend, endpoint, file-order, or payment-state implementations;
- treat Laravel routes, database orders, gateway adapters, and Blade views as the only active application path.

### Phase 1: Foundation

- initialize the Laravel modular monolith;
- establish routes, layouts, authentication boundary, configuration, and environment validation;
- implement design tokens, shared primitives, localization, request helpers, and module directories;
- create the gateway interface with EasyPay V2 as the default adapter and V1 as the compatibility adapter.

### Phase 2: Reliable Checkout

- implement public custom-amount checkout;
- implement developer fixed-amount checkout;
- implement idempotent order creation, QR persistence, refresh restoration, polling, cancellation, and payment result pages;
- implement verified callback processing and amount reconciliation before visual polish.

### Phase 3: Local Operations

- implement the focused dashboard, local order list, order detail, callback records, exception handling, administrator notifications, and delivery logs;
- query EasyPay for upstream facts instead of building a second complete ledger.

### Phase 4: Developer Platform

- implement developer applications, API credentials, signed OpenAPI endpoints, API documentation, webhook configuration, delivery history, retries, and audit records.

Do not generate all modules in one pass. Complete and verify each phase before expanding the next one.

## 14. Frontend Definition of Done

A frontend change is complete only when:

- it follows the PRD and all four project rule files;
- the server remains authoritative for payment facts;
- it reuses tokens and shared components;
- it has a clear desktop and mobile layout contract;
- it does not overflow common viewport sizes;
- all relevant loading and terminal states are represented;
- duplicate requests cannot create duplicate orders;
- errors remain JSON at API boundaries and user-safe in the interface;
- it does not duplicate EasyPay's upstream management capabilities;
- it improves maintainability rather than creating an isolated page implementation.
