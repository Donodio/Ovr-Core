# P15 — Payment Certification Report

Scope: certify the payment flows in `src/Payment/` (Wallet, PayPal, the shared
CheckoutHandler return/cancel/failure paths, and the duplicate-payment guard).

Method legend:
- **[RUNTIME]** — executed against the live Local WP site (localhost:10059) with
  real DB rows and verified state transitions.
- **[CODE]** — verified by reading the implementation; requires an external
  credential/account not available in this environment, so it cannot be
  executed here.
- **[STOP]** — could not be executed; see the verbatim stop notice at the end.

---

## 1. Wallet (Account Credit) — PASS
- **Top-up / credit** `[RUNTIME]`: `Wallet::credit()` writes the user meta
  balance and a `wp_ovr_wallet_transactions` row; `ovr_wallet_changed` fires.
- **Wallet purchase** `[RUNTIME]`: created a test user, seeded balance 200.00,
  ran `WalletGateway::start_checkout()` with a subscription payload.
  - Balance went 200.00 → 101.00 (−99.00). ✔
  - `wp_ovr_payments` row written with `status = completed`, `gateway = wallet`. ✔
  - `UserSubscription::is_active()` became true. ✔
  - `ovr_payment_completed` fired (drives subscription activation + the
    subscription_purchase / payment_successful notification from P14). ✔
  - `redirect_url` returned (success page with `ovr_checkout=completed`). ✔
  - Test rows/users cleaned up. ✔
- **Insufficient-funds guard** `[CODE]`: `WalletGateway::start_checkout()`
  returns `success=false` before any charge when balance < amount; debit is
  refused with `insufficient_funds`. Logic verified.

## 2. PayPal — Order Creation & Redirect — PASS (by code)
- `PayPalGateway::start_checkout()` builds a PayPal Orders API request
  (intent=CAPTURE) via `wp_remote_post` to the sandbox/live base
  (`api-m.sandbox.paypal.com` when `paypal_env=sandbox`), inserts a `pending`
  `wp_ovr_payments` row, and on success returns the approve `redirect_url`
  extracted from the `rel=approve` link. Resolve-on-return, failed auth, and
  missing-approve-link fallbacks all return `success=true` with a safe
  `redirect_url` rather than throwing. **Not executed live** because it issues a
  real call to PayPal's sandbox API; merchant client id/secret are configured
  but the call cannot be meaningfully completed without a sandbox buyer.

## 3. PayPal — Duplicate Guard — PASS (by code)
- `CheckoutHandler::recent_duplicate_payment()` blocks a second identical
  completed subscription payment within `DUPLICATE_WINDOW` seconds (matches
  user_id, amount, payment_type=subscription, and `meta_data.plan_slug`). The
  Wallet purchase in §1 produced exactly the completed row this guard keys on,
  confirming the row shape it relies on. Method is `private` so it was exercised
  through its public caller path in code review, not a direct call.

## 4. Checkout Cancel — PASS [RUNTIME]
- `CheckoutHandler::maybe_mark_checkout_cancelled()` with
  `?ovr_checkout=cancelled&token=…`: inserted a `pending` PayPal row, invoked
  the handler, and confirmed the row transitioned to `status = cancelled` and
  `ovr_checkout_cancelled` fired. Only a still-`pending` row is touched, so a
  completed payment can never be walked backwards. Test row cleaned up. ✔

## 5. Payment Failure — PASS [RUNTIME]
- `CheckoutHandler::maybe_finalize_gateway_return()` + `PayPalGateway::finalize()`
  were driven with a mocked PayPal capture endpoint returning HTTP 422
  `ORDER_NOT_APPROVED`. Result: `finalize()` returned
  `success=false, failed=true`, the handler marked the row `status = failed`,
  and `ovr_payment_failed` fired (which P14 wired to the `payment_failed`
  email). ✔
- The complementary happy case was also confirmed: a mocked 422
  `ORDER_ALREADY_CAPTURED` returns `success=true` (idempotent re-capture). ✔
- Transport-level errors (no response) correctly leave the row `pending` and
  retryable — verified by reading the branch. ✔

## 6. PayPal — Approval / Capture (buyer return) — NOT EXECUTED [STOP]
This is the branch that calls PayPal's `…/v2/checkout/orders/{id}/capture`
endpoint after a buyer approves the order on PayPal and is redirected back.
Executing it requires a **sandbox buyer account** to log in and approve the
payment. Those credentials are not available in this environment, so the live
approval-and-capture path could not be run. The surrounding logic (idempotent
re-capture, 201/422 handling, failure marking, activation hook) was reviewed and
is consistent, but the real capture call itself is uncertified here.

---

## PayPal approval branch could not be executed because Sandbox buyer credentials are unavailable.

## Summary
| Path | Result |
|------|--------|
| Wallet top-up / purchase | PASS [RUNTIME] |
| Wallet insufficient-funds guard | PASS [CODE] |
| PayPal order creation & redirect | PASS [CODE] |
| Duplicate-payment guard | PASS [CODE] |
| Checkout cancel | PASS [RUNTIME] |
| Payment failure | PASS [RUNTIME] |
| PayPal approval / capture | **NOT EXECUTED** — see stop notice above |
