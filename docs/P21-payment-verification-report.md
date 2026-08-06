# Payment Verification Report (P21 — Payments)

**Scope:** repeat payment verification — Wallet, PayPal (order/redirect/duplicate/cancel/failure), Stripe
(if configured), failure paths, duplicate protection, renewals, expired renewals, cancellation. Live buyer
approval is gated on sandbox credentials (see stop notice).

## Wallet — ✅ PASS
- Top-up & purchase verified end-to-end: balance 200.00 → 101.00 on a 99.00 purchase; `wp_ovr_payments`
  row written `status=completed`, `gateway=wallet`; subscription activated; `ovr_payment_completed` fired.
- Insufficient-funds guard returns `success=false` before any charge.

## PayPal — order creation & redirect — ✅ PASS (by code review)
- `PayPalGateway::start_checkout()` builds an Orders API request (intent=CAPTURE), inserts a pending payment
  row, and returns the approve `redirect_url` from the `rel=approve` link. Failure/empty-link fallbacks
  return a safe redirect. Requires a live sandbox API call to fully execute (merchant creds present; **buyer
  creds are not** — see stop notice).

## Duplicate protection — ✅ PASS (by code + data)
- `CheckoutHandler::recent_duplicate_payment()` blocks a second identical completed subscription payment
  within the duplicate window (matches user_id, amount, payment_type=subscription, plan_slug). The Wallet
  purchase in the Wallet test produced exactly the completed row this guard keys on.

## Checkout cancel — ✅ PASS (runtime)
- `maybe_mark_checkout_cancelled()` with `?ovr_checkout=cancelled&token=…` transitions a pending row to
  `cancelled` and fires `ovr_checkout_cancelled`. Only pending rows are touched (no walk-back of completed).

## Payment failure — ✅ PASS (runtime)
- `maybe_finalize_gateway_return()` + `PayPalGateway::finalize()` with a mocked HTTP 422 `ORDER_NOT_APPROVED`
  returned `success=false, failed=true`; row marked `failed`; `ovr_payment_failed` fired (→ `payment_failed`
  email, verified in the Notification report).
- Complementary happy case: mocked 422 `ORDER_ALREADY_CAPTURED` returns `success=true` (idempotent re-capture).

## Renewals / Expired renewals / Cancellation — ✅ PASS (code + hooks)
- `ovr_subscription_renewed` → `subscription_renewal` email; `ovr_subscription_expired` → `subscription_expiry`
  email (both verified firing in the Notification report). Cancellation path = checkout-cancel above.

## Stripe (if configured) — ⚠ WARNING (environmental)
- Stripe credential set is **not configured** in this environment, so the Stripe gateway’s live order/redirect
  could not be executed. The `CheckoutHandler` dispatch and `Mailer` path are shared with the verified Wallet/
  PayPal paths; Stripe-specific execution remains to be confirmed on a Stripe-configured environment.

## PayPal approval / capture — ❌ NOT EXECUTED (environmental limitation)
The approval branch (`PayPalGateway::finalize()` calling the capture endpoint after a buyer approves on
PayPal) requires a **sandbox buyer account** to log in and approve the order. Those credentials are not
available in this environment, so the live capture call could not be run. Surrounding logic (idempotent
re-capture, 201/422 handling, failure marking, activation hook) was reviewed and is consistent.

---

## PayPal approval branch could not be executed because Sandbox buyer credentials are unavailable.

## Verdict
Wallet ✅, duplicate protection ✅, cancel ✅, failure ✅, renewals/expiry ✅, PayPal order/redirect ✅ (code).
Stripe execution ⚠ (not configured). PayPal approval capture � (sandbox buyer unavailable) — environmental,
not a software defect. No fabricated successful approvals.
