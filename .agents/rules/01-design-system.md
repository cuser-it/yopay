# Payment SaaS Design System Rules

## Mandatory Instruction

IMPORTANT:

Before writing any UI code, always read and follow this design system.

Do not create arbitrary colors, spacing, radius, shadows, typography, or component styles.

Every new component must reuse existing design tokens and shared components.

The final UI should feel like an Apple-designed SaaS payment product, combining Apple Pay clarity, Stripe Dashboard trust, and Linear precision.

Payment correctness and usability always take priority over decoration.

## 1. Product Positioning

This project is a lightweight payment checkout, merchant order dashboard, and developer API platform.

The interface must communicate high trust, stability, minimalism, professionalism, explicit status, and mature SaaS quality.

Do not use decorative visuals, large gradient areas, excessive rounding, cartoon styling, heavy glassmorphism, distracting animation, noisy cards, or oversized content that creates avoidable scrolling.

## 2. Design Language

All pages follow Apple Human Interface Guidelines, Stripe Dashboard, and Linear SaaS UI principles.

Required principles:

- controlled whitespace;
- clear hierarchy;
- content before decoration;
- explicit payment status;
- low visual noise;
- one obvious primary action per state;
- efficient use of the available viewport.

The checkout should normally fit in one 1440x900 desktop viewport when content permits. Do not make cards, headings, QR codes, or spacing unnecessarily large.

## 3. Color Tokens

All colors must be CSS variables. Component CSS must not contain raw HEX, RGB, HSL, or named color values.

```css
:root {
  --primary: #1677ff;
  --primary-hover: #4096ff;
  --primary-light: #eff6ff;
  --text-primary: #111827;
  --text-secondary: #4b5563;
  --text-tertiary: #9ca3af;
  --border: #e5e7eb;
  --bg-page: #f8fafc;
  --bg-card: #ffffff;
  --success: #22c55e;
  --warning: #f59e0b;
  --error: #ef4444;
  --info: #1677ff;
  --shadow-card: 0 8px 32px rgba(0, 0, 0, 0.08);
}
```

Use success for paid/completed, warning for mismatch/manual review, error for failed/destructive, and info for pending/active states.

Never use color as the only status indicator. Pair it with text, an icon, or a badge label.

## 4. Typography

```css
font-family: Inter, 'SF Pro Display', 'PingFang SC', 'Microsoft YaHei', sans-serif;
```

Code uses `'SF Mono', 'Cascadia Code', Consolas, monospace`.

| Use | Size | Weight |
| --- | --- | --- |
| Page title | 32px | 600-700 |
| Section title | 20px | 600 |
| Body | 14px | 400 |
| Caption | 12px | 400-500 |
| Button | 16px | 500 |
| Large payment amount | 48px | 700 |

Avoid unnecessary all-caps text, excessive bold text, and more than three type sizes in one card.

## 5. Spacing System

Use the 8px grid and only these values:

```text
4, 8, 12, 16, 24, 32, 40, 48, 64
```

Define reusable spacing variables. Do not introduce arbitrary values such as 13px, 17px, or 25px.

## 6. Radius and Cards

```css
--radius-small: 8px;
--radius-card: 12px;
--radius-modal: 16px;
--radius-pill: 999px;
```

Do not exceed 16px except for pill badges, tags, or switches.

Cards use:

```css
background: var(--bg-card);
border: 0;
border-radius: var(--radius-card);
box-shadow: var(--shadow-card);
```

Do not use thick borders, strong shadows, colored card backgrounds, or competing shadow styles.

## 7. Buttons and Inputs

Primary buttons are used for payment, creation, confirmation, and saving:

```css
height: 48px;
border-radius: var(--radius-small);
background: var(--primary);
font-size: 16px;
font-weight: 500;
```

Secondary buttons use white, `--border`, and `--text-primary`. Danger buttons use `--error` only for destructive or cancellation actions.

Buttons require hover, focus-visible, disabled, and loading states. Prevent duplicate submission while loading.

Inputs require accessible labels, nearby validation messages, visible focus rings, and mobile touch targets of at least 44px. Placeholder text is not a label.

## 8. Payment UI

The payment page is the product core.

```text
Desktop card width: 440px
Mobile card width: min(100%, available viewport width)
```

Never create horizontal page overflow. Use `width: 100%`, `max-width`, `min-width: 0`, `box-sizing: border-box`, and controlled container padding.

Payment structure:

1. Merchant information;
2. Amount;
3. Payment method;
4. QR code or payment action;
5. Payment status;
6. Secondary actions.

Payment amount must use `¥ 100.00`. Do not display `100`, `￥100`, unformatted cents, or browser-provided values as the final paid amount.

Before order creation on desktop, show only the centered payment card. After creation, reveal the detail card using a subtle slide/fade while the original card yields space.

On mobile, replace the input state with the payment detail state instead of using two side-by-side cards.

## 9. Payment Status

Every status uses a badge with text and a visible indicator:

```text
● 已支付
● 待支付
● 已过期
● 金额异常
● 支付失败
```

- paid: `--success`;
- pending/creating: `--info`;
- expired/cancelled: `--text-tertiary`;
- mismatch/paid after cancellation: `--warning`;
- failed: `--error`.

## 10. Dashboard and Tables

The dashboard uses a restrained Stripe-style layout.

```text
Desktop sidebar: 240px
Main content: remaining width
```

The sidebar is white. Active items use `--primary-light` and `--primary`.

Metrics prioritize actual paid amount, successful order count, pending orders, mismatches, late payments, and notification failures.

Order tables show order number, expected/actual amount, payment method, status, time, and action when relevant.

Tables are clean, use row hover, and avoid zebra striping. On small screens, hide secondary columns or use a card layout instead of horizontal page overflow.

## 11. API Documentation

Developer documentation follows Stripe API Docs:

```text
Left: navigation
Center: documentation
Right: code example
```

Code examples use the project monospace font, syntax highlighting, copy actions, and clear request/response separation.

## 12. Animation

Normal interface transitions must not exceed 200ms.

Allowed: fade, short slide, subtle scale, loading opacity, and small progress transitions.

Forbidden: bounce, decorative rotation, large parallax, long sequences, or animation that delays payment actions.

Respect `prefers-reduced-motion`.

## 13. Responsive Rules

Target widths:

```text
Desktop: 1440px
Tablet: 768px
Mobile: 375px
```

- Never solve mobile overflow by changing desktop dimensions globally.
- Scope mobile overrides inside explicit media queries.
- Do not use `100vw` for cards inside padded containers; use `width: 100%` and controlled padding.
- Test the checkout at 375px without horizontal scrolling.
- Keep the primary payment action visible without excessive scrolling.

## 14. Component Priority

Reuse shared components before creating new markup or styles:

- Button;
- Card;
- Modal;
- Input;
- Badge;
- Table;
- Dropdown;
- Toast;
- Loading;
- Empty state;
- Payment method selector;
- Payment status panel.

Do not create duplicate component styles under page-specific class names.

## 15. UI Code Rules

- Split components by responsibility.
- Use design tokens for every visual primitive.
- Do not use inline styles.
- Do not duplicate CSS.
- Keep components single-purpose.
- Preserve semantic HTML, accessible names, keyboard navigation, and focus states.
- UI text must be concise, calm, trustworthy, and explicit.

## 16. Page Development Order

For every page:

1. Define information architecture.
2. Define layout and responsive behavior.
3. Identify reusable components.
4. Implement functional states.
5. Apply visual styling.
6. Verify desktop and mobile behavior.
7. Verify loading, empty, success, error, and permission states.
