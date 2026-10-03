<?php
/**
 * Payments must be confirmed with the provider (or approved by a person) before
 * a plan is activated or an invoice is marked paid. Browser-reported results
 * and unsigned notifications must never be enough.
 */

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentSetting;
use App\Models\Plan;
use App\Models\PlanOrder;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(PlanSeeder::class);
    $this->withoutMiddleware([
        \App\Http\Middleware\CheckInstallation::class,
        \App\Http\Middleware\ShareGlobalSettings::class,
        \App\Http\Middleware\DemoModeMiddleware::class,
        \App\Http\Middleware\CheckPlanAccess::class,
        \App\Http\Middleware\CheckPlanLimits::class,
        \App\Http\Middleware\CheckModuleAccess::class,
        \App\Http\Middleware\EnsureEmailIsVerified::class,
    ]);
    Cache::flush();
    Http::preventStrayRequests();
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function paySuperadmin(array $paymentSettings = []): User
{
    $admin = User::factory()->create(['type' => 'superadmin']);
    $admin->assignRole('superadmin');
    foreach ($paymentSettings as $key => $value) {
        PaymentSetting::create(['user_id' => $admin->id, 'workspace_id' => null, 'key' => $key, 'value' => $value]);
    }
    return $admin;
}

function payCompany(): array
{
    $user = User::factory()->create(['type' => 'company', 'plan_id' => Plan::where('is_default', true)->value('id')]);
    $workspace = Workspace::create(['name' => 'Pay WS', 'slug' => 'pay-' . uniqid(), 'owner_id' => $user->id, 'is_active' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'owner', 'status' => 'active']);
    $user->update(['current_workspace_id' => $workspace->id]);
    $user->assignRole('company');
    return [$user, $workspace];
}

function payPaidPlan(): Plan
{
    return Plan::create(['name' => 'Pro ' . uniqid(), 'price' => 20, 'yearly_price' => 200, 'duration' => 'monthly', 'is_default' => false,
        'max_users_per_workspace' => 5, 'max_clients_per_workspace' => 5, 'max_managers_per_workspace' => 1,
        'max_projects_per_workspace' => 5, 'workspace_limit' => 1, 'storage_limit' => 1, 'is_plan_enable' => 'on']);
}

function payInvoice(User $owner, Workspace $ws, array $attrs = []): Invoice
{
    $project = Project::create(['workspace_id' => $ws->id, 'title' => 'P', 'status' => 'active', 'priority' => 'low', 'progress' => 0, 'created_by' => $owner->id]);
    return Invoice::create(array_merge([
        'project_id' => $project->id, 'workspace_id' => $ws->id, 'created_by' => $owner->id, 'title' => 'Inv',
        'invoice_date' => now()->toDateString(), 'due_date' => now()->addWeek()->toDateString(),
        'subtotal' => 100, 'total_amount' => 100, 'status' => 'sent', 'tax_rate' => [],
    ], $attrs));
}

/** Fake PayPal's token + order endpoints. */
function fakePayPalOrder(string $orderId, string $status, string $amount, string $currency = 'USD'): void
{
    $order = fn (string $s) => [
        'id' => $orderId,
        'status' => $s,
        'purchase_units' => [[
            'payments' => ['captures' => $s === 'COMPLETED'
                ? [['id' => 'CAP-1', 'status' => 'COMPLETED', 'amount' => ['value' => $amount, 'currency_code' => $currency]]]
                : []],
        ]],
    ];

    Http::fake([
        'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'token']),
        "api-m.sandbox.paypal.com/v2/checkout/orders/{$orderId}/capture" => Http::response($order('COMPLETED')),
        "api-m.sandbox.paypal.com/v2/checkout/orders/{$orderId}" => Http::response($order($status)),
    ]);
}

const PAYPAL_SETTINGS = ['is_paypal_enabled' => '1', 'paypal_client_id' => 'cid', 'paypal_secret_key' => 'secret', 'paypal_mode' => 'sandbox'];

// ═══ Invoice payments ═══

describe('Invoice payments', function () {
    test('new payments are pending unless marked completed', function () {
        [$owner, $ws] = payCompany();
        $invoice = payInvoice($owner, $ws);

        $payment = Payment::create(['invoice_id' => $invoice->id, 'amount' => 100, 'payment_method' => 'x', 'payment_date' => now(), 'created_by' => $owner->id]);

        expect($payment->fresh()->status)->toBe('pending')
            ->and($invoice->fresh()->status)->toBe('sent');
    });

    test('a pending bank transfer does not mark the invoice paid until approved', function () {
        [$owner, $ws] = payCompany();
        $invoice = payInvoice($owner, $ws);

        $this->post(route('invoices.payment.process', $invoice->payment_token), ['payment_method' => 'bank', 'amount' => 100])
            ->assertSessionHasNoErrors();

        $payment = $invoice->payments()->first();
        expect($payment->status)->toBe('pending')->and($invoice->fresh()->status)->toBe('sent');

        $this->actingAs($owner)->post(route('invoices.payments.approve', [$invoice, $payment]));
        expect($invoice->fresh()->status)->toBe('paid');
    });

    test('a fully paid invoice cannot be paid again', function () {
        [$owner, $ws] = payCompany();
        $invoice = payInvoice($owner, $ws);
        $invoice->createPaymentRecord(100, 'stripe', 'pi_1', verified: true);
        expect($invoice->fresh()->status)->toBe('paid');

        $this->post(route('invoices.payment.process', $invoice->payment_token), ['payment_method' => 'bank', 'amount' => 100])
            ->assertSessionHasErrors('amount');
    });

    test('unverified gateway results are pending (CinetPay)', function () {
        [$owner, $ws] = payCompany();
        $invoice = payInvoice($owner, $ws);

        app(\App\Http\Controllers\CinetPayPaymentController::class)->processInvoicePayment(
            \Illuminate\Http\Request::create('/', 'POST', ['amount' => 100, 'cpm_trans_id' => 'fake', 'cpm_result' => '00']),
            $invoice
        );

        expect($invoice->payments()->first()->status)->toBe('pending')->and($invoice->fresh()->status)->toBe('sent');
    });

    test('PayPal invoice payment is recorded only after PayPal confirms it', function () {
        [$owner, $ws] = payCompany();
        foreach (PAYPAL_SETTINGS as $key => $value) {
            PaymentSetting::create(['user_id' => $owner->id, 'workspace_id' => $ws->id, 'key' => $key, 'value' => $value]);
        }
        $invoice = payInvoice($owner, $ws);

        fakePayPalOrder('ORDER-BAD', 'COMPLETED', '1.00');
        $this->actingAs($owner)->post(route('paypal.invoice.payment'), ['invoice_id' => $invoice->id, 'amount' => 100, 'order_id' => 'ORDER-BAD'])
            ->assertSessionHasErrors('error');
        expect($invoice->payments()->count())->toBe(0);

        fakePayPalOrder('ORDER-OK', 'APPROVED', '100.00'); // approved only: server captures it
        $this->actingAs($owner)->post(route('paypal.invoice.payment'), ['invoice_id' => $invoice->id, 'amount' => 100, 'order_id' => 'ORDER-OK'])
            ->assertSessionHasNoErrors();

        expect($invoice->payments()->first()->status)->toBe('completed')
            ->and($invoice->fresh()->status)->toBe('paid');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v2/checkout/orders/ORDER-OK/capture'));
    });
});

// ═══ Plan payments ═══

describe('Plan payments', function () {
    test('PayPal activates a plan only for a confirmed order with the right amount and currency, once', function () {
        paySuperadmin(PAYPAL_SETTINGS);
        [$user] = payCompany();
        $plan = payPaidPlan();
        $defaultPlanId = $user->plan_id;

        fakePayPalOrder('ORDER-CHEAP', 'COMPLETED', '0.01');
        $this->actingAs($user)->post(route('paypal.payment'), ['plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'order_id' => 'ORDER-CHEAP'])
            ->assertSessionHasErrors('error');
        expect($user->fresh()->plan_id)->toBe($defaultPlanId);

        fakePayPalOrder('ORDER-JPY', 'COMPLETED', '20.00', 'JPY');
        $this->actingAs($user)->post(route('paypal.payment'), ['plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'order_id' => 'ORDER-JPY'])
            ->assertSessionHasErrors('error');

        fakePayPalOrder('ORDER-GOOD', 'COMPLETED', '20.00');
        $this->actingAs($user)->post(route('paypal.payment'), ['plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'order_id' => 'ORDER-GOOD'])
            ->assertSessionHasNoErrors();
        expect($user->fresh()->plan_id)->toBe($plan->id);

        // Replaying the same PayPal order for another purchase is refused.
        [$other] = payCompany();
        $this->actingAs($other)->post(route('paypal.payment'), ['plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'order_id' => 'ORDER-GOOD'])
            ->assertSessionHasErrors('error');
        expect($other->fresh()->plan_id)->not->toBe($plan->id);
    });

    test('PayHere: browser success codes and unsigned notifications never activate a plan', function () {
        paySuperadmin(['payhere_merchant_id' => 'M1', 'payhere_merchant_secret' => 'S3CRET']);
        [$user] = payCompany();
        $plan = payPaidPlan();
        $order = createPendingPlanOrder(['user_id' => $user->id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
            'payment_method' => 'payhere', 'payment_id' => 'plan_x_1']);

        $this->actingAs($user)->post(route('payhere.payment'), ['plan_id' => $plan->id, 'billing_cycle' => 'monthly',
            'payment_id' => 'plan_x_1', 'status_code' => '2']);
        expect($order->fresh()->status)->toBe('pending');

        $this->app['auth']->forgetGuards();
        $notify = ['merchant_id' => 'M1', 'order_id' => 'plan_x_1', 'payhere_amount' => '20.00', 'payhere_currency' => 'LKR', 'status_code' => '2'];
        $this->post('/payments/payhere/callback', $notify + ['md5sig' => 'FORGED'])->assertStatus(400);
        expect($order->fresh()->status)->toBe('pending');

        $sig = strtoupper(md5('M1' . 'plan_x_1' . '20.00' . 'LKR' . '2' . strtoupper(md5('S3CRET'))));
        $this->post('/payments/payhere/callback', $notify + ['md5sig' => $sig])->assertOk();
        expect($order->fresh()->status)->toBe('approved')->and($user->fresh()->plan_id)->toBe($plan->id);
    });

    test('PayHere: a correctly signed notification for too little money is rejected', function () {
        paySuperadmin(['payhere_merchant_id' => 'M1', 'payhere_merchant_secret' => 'S3CRET']);
        [$user] = payCompany();
        $plan = payPaidPlan();
        $order = createPendingPlanOrder(['user_id' => $user->id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
            'payment_method' => 'payhere', 'payment_id' => 'plan_y_1']);

        $notify = ['merchant_id' => 'M1', 'order_id' => 'plan_y_1', 'payhere_amount' => '1.00', 'payhere_currency' => 'LKR', 'status_code' => '2'];
        $sig = strtoupper(md5('M1' . 'plan_y_1' . '1.00' . 'LKR' . '2' . strtoupper(md5('S3CRET'))));

        $this->post('/payments/payhere/callback', $notify + ['md5sig' => $sig])->assertStatus(400);
        expect($order->fresh()->status)->toBe('pending');
    });

    test('Easebuzz: a forged success neither activates a plan nor logs anyone in', function () {
        paySuperadmin(['easebuzz_merchant_key' => 'KEY', 'easebuzz_salt_key' => 'SALT']);
        [$victim] = payCompany();
        $plan = payPaidPlan();
        $order = createPendingPlanOrder(['user_id' => $victim->id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
            'payment_method' => 'easebuzz', 'payment_id' => "plan_{$plan->id}_{$victim->id}_1"]);

        $forged = ['status' => 'success', 'txnid' => "plan_{$plan->id}_{$victim->id}_1", 'amount' => '20.00', 'key' => 'KEY', 'hash' => 'nope'];
        $this->post('/payments/easebuzz/success', $forged);

        $this->assertGuest();
        expect($order->fresh()->status)->toBe('pending');

        $valid = ['status' => 'success', 'txnid' => "plan_{$plan->id}_{$victim->id}_1", 'amount' => '20.00', 'key' => 'KEY',
            'email' => 'a@b.c', 'firstname' => 'A', 'productinfo' => 'Pro', 'udf1' => 'monthly'];
        $fields = ['udf10', 'udf9', 'udf8', 'udf7', 'udf6', 'udf5', 'udf4', 'udf3', 'udf2', 'udf1', 'email', 'firstname', 'productinfo', 'amount', 'txnid', 'key'];
        $sequence = 'SALT|success';
        foreach ($fields as $f) {
            $sequence .= '|' . ($valid[$f] ?? '');
        }
        $this->post('/payments/easebuzz/callback', $valid + ['hash' => hash('sha512', $sequence)])->assertOk();

        expect($order->fresh()->status)->toBe('approved');
        $this->assertGuest();
    });

    test('Benefit and YooKassa return URLs no longer hand out plans', function () {
        paySuperadmin();
        [$user] = payCompany();
        $plan = payPaidPlan();
        $before = $user->plan_id;

        $this->get(route('benefit.success', ['plan_id' => $plan->id, 'user_id' => $user->id]));
        $this->actingAs($user)->get(route('yookassa.success', ['plan_id' => $plan->id, 'order_id' => 'X', 'billing_cycle' => 'yearly']));
        $this->post('/payments/yookassa/callback', ['object' => ['id' => 'fake', 'status' => 'succeeded',
            'metadata' => ['plan_id' => $plan->id, 'user_id' => $user->id, 'billing_cycle' => 'yearly']]])->assertOk();

        expect($user->fresh()->plan_id)->toBe($before)
            ->and(PlanOrder::where('status', 'approved')->count())->toBe(0);
    });

    test('SSPay confirms the bill with SSPay before activating', function () {
        paySuperadmin(['sspay_secret_key' => 'sk', 'sspay_category_code' => 'cat']);
        [$user] = payCompany();
        $plan = payPaidPlan();
        $order = createPendingPlanOrder(['user_id' => $user->id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
            'payment_method' => 'sspay', 'payment_id' => 'BILL1']);

        $billStatus = '3'; // SSPay "pending"
        Http::fake(['sspay.my/index.php/api/getBillTransactions' => function () use (&$billStatus) {
            return Http::response([['billpaymentStatus' => $billStatus, 'billpaymentAmount' => '20.00']]);
        }]);

        // The notification claims success, but SSPay's API says the bill isn't paid.
        $this->post('/payments/sspay/callback', ['billcode' => 'BILL1', 'status_id' => '1'])->assertOk();
        expect($order->fresh()->status)->toBe('pending');

        $billStatus = '1';
        $this->post('/payments/sspay/callback', ['billcode' => 'BILL1'])->assertOk();
        expect($order->fresh()->status)->toBe('approved')->and($user->fresh()->plan_id)->toBe($plan->id);
    });

    test('gateways without server-side verification create a pending order (Ozow)', function () {
        paySuperadmin(['ozow_site_key' => 'site']);
        [$user] = payCompany();
        $plan = payPaidPlan();
        $before = $user->plan_id;

        $this->actingAs($user)->post(route('ozow.payment'), ['plan_id' => $plan->id, 'billing_cycle' => 'monthly',
            'transaction_id' => 'T1', 'status' => 'Complete']);

        expect($user->fresh()->plan_id)->toBe($before)
            ->and(PlanOrder::where('payment_method', 'ozow')->first()?->status)->toBe('pending');
    });

    test('webhook routes are reachable without a session or CSRF token', function () {
        $this->app['auth']->forgetGuards();
        foreach (['payhere', 'sspay', 'yookassa', 'easebuzz'] as $gateway) {
            $status = $this->post("/payments/{$gateway}/callback", [])->status();
            expect($status)->not->toBeIn([302, 419], "{$gateway} callback returned {$status}");
        }
    });
});
