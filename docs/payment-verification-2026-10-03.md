# Payment Verification Fixes — 2026-10-03

**Branch:** `fix/payment-verification` (created from `main`).

**Tests:** 13 new tests in `tests/Feature/PaymentVerificationTest.php`, all passing. The full suite has 122 passing tests. The other 35 tests were already failing before this work and fail the same way now.

**Type check:** `tsc` error count unchanged (937). No new errors.

## Rule this branch puts in place

A plan is only activated, and an invoice is only marked paid, when one of these is true:

1. The payment was **confirmed with the payment provider on the server**. This can be a signed notification, or a fresh lookup through the provider's API that checks status, amount and currency.
2. **A person approved it**. The superadmin approves plan orders under **Plan Orders**. The company owner approves invoice payments on the invoice page.

What the payer's browser reports ("status=success", an order ID, a return URL) is never enough on its own.

> **Testing note:** These changes were tested with simulated gateway responses. They have **not** been run against real gateway accounts. Test each gateway in its sandbox before taking live payments.

---

## 1. Critical problems found and fixed

| # | Problem | Impact | Fix |
|---|---|---|---|
| 1 | **Easebuzz return URL logged in any user.** `success()` and `invoiceSuccess()` took a user ID from the transaction ID and called `auth()->login($user)` whenever `status=success`. | **Account takeover.** Anyone could log in as any account, including the superadmin, with one forged request. | Login removed. Results are only accepted with a valid Easebuzz reverse hash (SHA-512 with the merchant salt, compared with `hash_equals`), the right merchant key and a matching amount. |
| 2 | **Benefit return URL activated any plan for any user** named in the query string. It was a public route with no payment check. | Free plans for anyone, even without logging in. | No longer activates anything. |
| 3 | **Benefit integration is a placeholder.** Its session, payment lookup and webhook check were stubs that always reported success. | Any company could activate any plan by posting two made-up IDs. | Fails closed: verification always returns false. A real Benefit integration is still needed before it can be used. |
| 4 | **YooKassa return URL** gave the logged-in user whatever `plan_id` was in the URL. | Free plans for any company user. | Looks up our own pending order and confirms the payment with YooKassa's API. |
| 5 | **PayPal** activated plans and recorded invoice payments from an order ID sent by the browser, without asking PayPal. The invoice PayPal button only *approved* the order and never captured it. | Free plans and fake "paid" invoices. Real invoice payments were never collected. | New `App\Services\PayPalService` fetches the order, captures it if it is only approved, and checks status, amount and currency. Each PayPal order can only pay for one thing. |
| 6 | **Mercado Pago return URL** activated the plan from query parameters. It didn't even check `status`. | Free plans. | Records a pending plan order instead. |
| 7 | **SSPay sent the merchant secret key to the browser.** It also activated plans from browser data, and `success()` checked nothing at all. | Leaked credentials and free plans. | The bill is created on the server and the browser is sent to SSPay's bill page. Payments are confirmed with SSPay's `getBillTransactions` API. |

## 2. Gateway-by-gateway changes

### Plan purchases (company pays superadmin)

| Gateway | Before | Now |
|---|---|---|
| PayPal | Trusted browser order ID | Confirmed with PayPal API (amount + currency), one use per order |
| PayHere | Trusted browser `status_code`; webhook unsigned and unreachable | Pending order at checkout; activated only by PayHere notification with valid `md5sig` + matching LKR amount |
| Easebuzz | Trusted `status=success`, auto-login | Pending order at checkout; activated only with valid reverse hash + matching amount |
| YooKassa | Trusted URL / webhook body | Pending order at checkout; activated only after `getPaymentInfo()` reports `succeeded`, `paid`, RUB, matching amount |
| SSPay | Secret in browser, trusted browser | Server-side bill; activated only after `getBillTransactions` reports paid with matching amount |
| Benefit | Placeholder, always "success" | Disabled (fails closed) |
| Aamarpay, CinetPay, Midtrans, Nepalste, Ozow, Paiement, ToyyibPay (callback), Xendit (success), Mercado Pago (return URL) | Activated plan from browser / unsigned data | **Pending plan order** for superadmin approval (`recordUnverifiedPlanPayment()`) |
| Stripe, Razorpay (plan), Authorize.Net, Cashfree, FedaPay, Flutterwave, Iyzipay, Khalti, Paystack, PayTR, Tap, PaymentWall, Mercado Pago (direct) | Already confirmed with the provider | Unchanged |

### Invoice payments (client pays company)

- **New default:** a payment is recorded as **pending** unless the code marks it completed (`Payment` model `creating` hook; `Invoice::createPaymentRecord(..., verified: true)`).
- **Invoice totals** (`paid_amount`, status, `remaining_amount`) now count **completed payments only**.

Paths that mark payments **completed**:

| Gateway | How it is confirmed |
|---|---|
| Stripe | PaymentIntent `succeeded` |
| Authorize.Net | Direct charge |
| PayPal | Confirmed with the PayPal API |
| Razorpay | Payment fetched from the API; must be `captured` for the exact amount |
| Easebuzz | Valid response hash |
| PayHere | Signed notification |
| YooKassa | Webhook re-fetched from the API |

Everything else, including bank transfers, waits for the owner's approval. CinetPay and PayHere used to force `completed` and update the invoice status by hand; they no longer do.

## 3. Other fixes

- **Unapproved bank transfers marked invoices as paid.** Invoice totals summed pending payments.
- **Paid invoices could be paid again.** `remaining_amount ?: total_amount` fell back to the full total when the balance was 0.
- **Bank-transfer receipts were silently dropped.** `receipt_path` wasn't fillable on `Payment`.
- **The "Pending payments → Approve / Reject" panel never showed** on the invoice page. `InvoiceController::show()` didn't pass `pendingPayments`. It does now, for owners and managers.
- **Webhooks could not be reached.**
  - `payments/payhere/callback` and `payments/sspay/callback` were inside the `auth` group. They are now public.
  - These four were not CSRF-exempt, so the gateways' servers were rejected. All four are now exempt and verified in their controllers:
    - `payments/payhere/callback`
    - `payments/sspay/callback`
    - `payments/yookassa/callback`
    - `payments/easebuzz/callback`

## 4. New shared code

- `app/Services/PayPalService.php`: confirms (and if needed captures) PayPal orders.
- In `app/Helpers/helper.php`:

| Helper | Purpose |
|---|---|
| `platformPaymentSettings()` | The superadmin's gateway credentials. Plan checkouts use these. |
| `createPendingPlanOrder()` | Records a plan purchase that is not yet confirmed. |
| `completePlanOrderPayment()` | Approves a pending order once the provider has confirmed the amount. Safe to call twice. |
| `recordUnverifiedPlanPayment()` | For gateways without server-side verification: creates a pending order for manual approval. |
| `planPaymentAlreadyUsed()` | Blocks reuse of a provider payment ID. |

## 5. What changes for users

- **Superadmin:** review **Plan Orders** for pending orders from these gateways, and approve them once the money arrives:
  - Aamarpay
  - CinetPay
  - Midtrans
  - Nepalste
  - Ozow
  - Paiement
  - ToyyibPay callbacks
  - Xendit success page
  - Mercado Pago return page
- **Company owners:** approve pending invoice payments, including bank transfers, on the invoice page.
- **Payers:** some gateways now show *"Payment received. Your plan will be activated once the payment is confirmed."* instead of activating straight away.

## 6. Still open

1. **Online invoice payment isn't connected in the UI.** The public invoice page only submits bank transfer, and the per-gateway invoice modals post to routes that don't exist (`*.invoice.payment.link`).
2. **Benefit** needs a real integration with the Benefit API.
3. **Gateways now in manual approval** (see section 5) could get automatic provider checks later. Prioritise the ones you actually use.
4. **Gateways left unchanged** call their provider, but their amount checks were not re-audited:
   - Cashfree
   - FedaPay
   - Flutterwave
   - Iyzipay
   - Khalti
   - Paystack
   - PayTR
   - Tap
   - PaymentWall
5. **Gateway assumptions to confirm in sandbox:**
   - SSPay's `getBillTransactions` response format (assumed to match ToyyibPay's).
   - The currencies the checkout buttons use:
     - PayPal plans: `defaultCurrency`, falling back to USD.
     - PayPal invoices: USD.
     - PayHere: LKR.
     - YooKassa: RUB.

## 7. Deployment

- No migrations and no seeders.
- Deploy the code, then run `npm run build`, because `sspay-payment-form.tsx` changed.
- Then run `php artisan optimize:clear`.
- In each gateway's dashboard, make sure the notification / webhook URL points to the routes above.
