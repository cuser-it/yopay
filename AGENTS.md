# Codex Project Instructions

This repository is a lightweight, reliable, and extensible payment checkout system composed of:

- a public custom-amount payment checkout;
- a fixed-amount developer API checkout;
- a focused local operations console for product-specific orders, exceptions, callbacks, notifications, and developer integrations;
- payment callback, notification, reconciliation, and webhook capabilities.

## Mandatory Rule Loading

Before analyzing, planning, writing, reviewing, or modifying code, read and follow these files:

1. `.agents/rules/01-design-system.md`
2. `.agents/rules/02-payment-business-rules.md`
3. `.agents/rules/03-code-convention.md`
4. `FRONTEND_RULES.md`

Do not treat these files as optional references. They are project requirements.

For UI work, always read the design system, code convention, and frontend architecture rules first.

For order, payment, callback, API, webhook, amount, or database work, always read the payment business rules and code convention first.

For full-stack work, read all four rule files before making changes.

## Rule Priority

When rules conflict, use this priority:

1. Payment correctness and security.
2. Data integrity and callback idempotency.
3. Project code conventions and architecture.
4. Design system and visual consistency.

Never weaken payment correctness, amount verification, callback validation, authentication, or order-state integrity for visual or implementation convenience.

## Product Reference

The complete product requirements are documented in `docs/payment-system-prd.md`.

EasyPay V1/V2 endpoint ownership and the reuse-versus-build boundary are documented in `docs/easypay-capability-review.md`. Read it before changing gateway integration, reconciliation, merchant operations, refunds, or order-list behavior.

Use that PRD when a task requires product context beyond the three rule files.

## General Working Principles

- Keep the system a modular monolith unless the user explicitly approves another architecture.
- Prefer the smallest reliable implementation over unnecessary infrastructure.
- Reuse EasyPay's documented payment creation, order query, order list, merchant information, refund, refund query, and close-order capabilities instead of rebuilding a duplicate gateway management platform.
- Prefer EasyPay V2 with RSA signatures and timestamps for new work. Keep V1 only behind the gateway adapter when migration compatibility requires it.
- Store the minimum local data required for idempotency, checkout recovery, developer-order mapping, callback audit, amount verification, exceptional states, notifications, and webhooks. Do not copy EasyPay's complete ledger or settlement system locally without a proven requirement.
- Do not add Redis, microservices, WebSockets, Kafka, RabbitMQ, Kubernetes, or a separate frontend SPA unless the requirement clearly justifies them.
- Do not trust browser-provided payment state or amount.
- Do not duplicate payment, signature, status-transition, or component styling logic.
- Preserve backward compatibility only when it does not compromise correctness or security.
- Fix root causes instead of adding temporary workarounds.

## Legacy Cleanup Status

The legacy root `index.html`, `success.html`, `assets/`, `api/`, and `payapi/` implementations were removed after the Laravel replacement was established.

Do not recreate payment behavior in those locations. The only permitted legacy compatibility is a thin Laravel route alias plus a public bootstrap shim for previously configured EasyPay V1 callback and return URLs. This cleanup status supersedes the temporary migration wording in `.agents/rules/03-code-convention.md`.
