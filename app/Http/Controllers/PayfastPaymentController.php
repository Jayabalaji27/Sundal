<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanOrder;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentSetting;
use Illuminate\Http\Request;

class PayfastPaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'coupon_code' => 'nullable|string',
            'customer_details' => 'required|array',
            'customer_details.firstName' => 'required|string',
            'customer_details.lastName' => 'required|string',
            'customer_details.email' => 'required|email',
        ]);

        try {
            $settings = getPaymentMethodConfig('payfast');
            $isLive = ($settings['mode'] ?? 'sandbox') === 'live';
            
            if (!$settings['merchant_id'] || !$settings['merchant_key']) {
                return response()->json(['success' => false, 'error' => __('PayFast not configured')]);
            }
            
            $plan = Plan::findOrFail($validated['plan_id']);
            $pricing = calculatePlanPricing($plan, $validated['coupon_code'] ?? null, $validated['billing_cycle'] ?? 'monthly');
            
            if ($pricing['final_price'] < 5.00) {
                return response()->json(['success' => false, 'error' => __('Minimum amount is R5.00')]);
            }
            
            $paymentId = 'pf_' . $plan->id . '_' . time() . '_' . uniqid();
            
            createPlanOrder([
                'user_id' => auth()->id(),
                'plan_id' => $validated['plan_id'],
                'billing_cycle' => $validated['billing_cycle'],
                'payment_method' => 'payfast',
                'coupon_code' => $validated['coupon_code'] ?? null,
                'payment_id' => $paymentId,
                'status' => 'pending'
            ]);
            
            $data = [
                'merchant_id' => $settings['merchant_id'],
                'merchant_key' => $settings['merchant_key'],
                'return_url' => route('payfast.success'),
                'cancel_url' => route('plans.index'),
                'notify_url' => route('payfast.callback'),
                'name_first' => $validated['customer_details']['firstName'],
                'name_last' => $validated['customer_details']['lastName'],
                'email_address' => $validated['customer_details']['email'],
                'm_payment_id' => $paymentId,
                'amount' => number_format($pricing['final_price'], 2, '.', ''),
                'item_name' => $plan->name,
            ];
            
            $passphrase = $settings['passphrase'] ?? '';
            $signature = $this->generateSignature($data, $passphrase);
            $data['signature'] = $signature;
            
            $htmlForm = '';
            foreach ($data as $name => $value) {
                $htmlForm .= '<input name="' . $name . '" type="hidden" value="' . $value . '" />';
            }
            
            $endpoint = $isLive 
                ? 'https://www.payfast.co.za/eng/process' 
                : 'https://sandbox.payfast.co.za/eng/process';
            
            return response()->json([
                'success' => true,
                'inputs' => $htmlForm,
                'action' => $endpoint
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => __('Payment failed')]);
        }
    }
    
    public function generateSignature($data, $passPhrase = null)
    {
        $pfOutput = '';
        foreach ($data as $key => $val) {
            if ($val !== '') {
                $pfOutput .= $key . '=' . urlencode(trim($val)) . '&';
            }
        }
        
        $getString = substr($pfOutput, 0, -1);
        if ($passPhrase !== null) {
            $getString .= '&passphrase=' . urlencode(trim($passPhrase));
        }
        return md5($getString);
    }
        
    public function callback(Request $request)
    {
        try {
            // Validate IP address (only for live mode)
            $settings = getPaymentMethodConfig('payfast');
                        
            // Get callback data
            $pfData = $request->all();
            $paymentId = $pfData['m_payment_id'] ?? null;
            $paymentStatus = $pfData['payment_status'] ?? null;
            
            if (!$paymentId) {
                return response(__('Missing payment ID'), 400);
            }
            
            // Find the plan order
            $planOrder = PlanOrder::where('payment_id', $paymentId)->first();
            
            if (!$planOrder) {
                return response(__('Order not found'), 404);
            }
            
            // Verify signature
            if (!$this->verifyPayfastSignature($pfData, $settings['passphrase'] ?? '')) {
                return response(__('Invalid signature'), 400);
            }
            
            // Verify amount
            if (!$this->verifyAmount($pfData, $planOrder)) {
                return response(__('Amount mismatch'), 400);
            }
            
            // Process payment based on status
            if ($paymentStatus === 'COMPLETE') {
                if ($planOrder->status === 'pending') {
                    // Update order status
                    $planOrder->update([
                        'status' => 'approved',
                        'processed_at' => now()
                    ]);
                    
                    // Assign plan to user
                    $user = $planOrder->user;
                    $plan = $planOrder->plan;
                    $expiresAt = $planOrder->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth();
                    
                    $user->update([
                        'plan_id' => $plan->id,
                        'plan_expires_at' => $expiresAt,
                    ]);
                }
            } else {                
                if (in_array($paymentStatus, ['CANCELLED', 'FAILED'])) {
                    $planOrder->update(['status' => 'rejected']);
                }
            }
            
            return response('OK', 200);
        } catch (\Exception $e) {
            return response('ERROR', 500);
        }
    }
    
    
    private function verifyPayfastSignature($pfData, $passphrase = '')
    {
        $signature = $pfData['signature'] ?? '';
        unset($pfData['signature']);
        
        $expectedSignature = $this->generateSignature($pfData, $passphrase);
        
        return hash_equals($expectedSignature, $signature);
    }
    
    private function verifyAmount($pfData, $planOrder)
    {
        $receivedAmount = floatval($pfData['amount_gross'] ?? 0);
        $expectedAmount = floatval($planOrder->final_price);
        
        // Allow small floating point differences
        return abs($receivedAmount - $expectedAmount) < 0.01;
    }

    public function success(Request $request)
    {
        // Try different parameter names PayFast might use
        $paymentId = $request->get('m_payment_id') ?? $request->get('pf_payment_id') ?? $request->get('payment_id');
        
        if (!$paymentId && auth()->check()) {
            // If no payment ID, find the most recent pending order for this user
            $planOrder = PlanOrder::where('user_id', auth()->id())
                ->where('payment_method', 'payfast')
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->first();
        } else {
            $planOrder = PlanOrder::where('payment_id', $paymentId)->first();
        }
        
        if ($planOrder) {
            // Always process the payment on success return
            $planOrder->update([
                'status' => 'approved',
                'processed_at' => now()
            ]);
            
            // Assign plan to user
            $user = $planOrder->user;
            $plan = $planOrder->plan;
            $expiresAt = $planOrder->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth();
            
            $user->update([
                'plan_id' => $plan->id,
                'plan_expires_at' => $expiresAt,
            ]);
            
            return redirect()->route('plans.index')->with('success', __('Payment completed and plan activated!'));
        }
        
        return redirect()->route('plans.index')->with('error', __('Payment verification failed'));
    }

    public function processInvoicePayment(Request $request)
    {
        $validated = $request->validate([
            'invoice_token' => 'required|string',
            'amount' => 'required|numeric|min:0.01'
        ]);

        try {
            $invoice = Invoice::where('payment_token', $validated['invoice_token'])->firstOrFail();
            
            // Get payment settings for the invoice creator
            $settings = PaymentSetting::where('user_id', $invoice->created_by)
                ->whereIn('key', ['payfast_merchant_id', 'payfast_merchant_key', 'payfast_passphrase', 'payfast_mode', 'is_payfast_enabled'])
                ->pluck('value', 'key')
                ->toArray();

            if (($settings['is_payfast_enabled'] ?? '0') !== '1') {
                throw new \Exception('PayFast payment method is not enabled');
            }

            if (empty($settings['payfast_merchant_id']) || empty($settings['payfast_merchant_key'])) {
                throw new \Exception('PayFast credentials not configured');
            }
            
            $paymentId = 'inv_' . $invoice->id . '_' . time() . '_' . uniqid();
            $isLive = ($settings['payfast_mode'] ?? 'sandbox') === 'live';

            $data = [
                'merchant_id' => $settings['payfast_merchant_id'],
                'merchant_key' => $settings['payfast_merchant_key'],
                'return_url' => route('payfast.invoice.success') . '?token=' . $validated['invoice_token'] . '&amount=' . $validated['amount'] . '&m_payment_id=' . $paymentId,
                'cancel_url' => route('invoices.payment', $validated['invoice_token']),
                'notify_url' => route('payfast.invoice.callback'),
                'name_first' => $invoice->client->first_name ?? 'Customer',
                'name_last' => $invoice->client->last_name ?? '',
                'email_address' => $invoice->client->email ?? 'customer@example.com',
                'm_payment_id' => $paymentId,
                'amount' => number_format($validated['amount'], 2, '.', ''),
                'item_name' => 'Invoice #' . $invoice->invoice_number,
            ];

            $passphrase = $settings['payfast_passphrase'] ?? '';
            $signature = $this->generateSignature($data, $passphrase);
            $data['signature'] = $signature;

            $htmlForm = '';
            foreach ($data as $name => $value) {
                $htmlForm .= '<input name="' . $name . '" type="hidden" value="' . $value . '" />';
            }

            $endpoint = $isLive
                ? 'https://www.payfast.co.za/eng/process'
                : 'https://sandbox.payfast.co.za/eng/process';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'inputs' => $htmlForm,
                    'action' => $endpoint,
                    'payment_id' => $paymentId
                ]);
            }

            // For non-AJAX requests, create and submit form
            $form = '<form id="payfast-form" method="POST" action="' . $endpoint . '">';
            $form .= $htmlForm;
            $form .= '</form><script>document.getElementById("payfast-form").submit();</script>';
            
            return response($form);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'errors' => $e->errors()], 422);
            }
            return back()->withErrors($e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => __('Invoice not found. Please check the link and try again.')], 404);
            }
            return back()->withErrors(['error' => __('Invoice not found. Please check the link and try again.')]);
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => __('Payment processing failed. Please try again or contact support.')], 500);
            }
            return back()->withErrors(['error' => __('Payment processing failed. Please try again or contact support.')]);
        }
    }

    public function invoiceSuccess(Request $request)
    {
        try {
            $token = $request->get('token');
            $paymentId = $request->get('m_payment_id');
            $paymentAmount = $request->get('amount');

            if (!$token || !$paymentId || !$paymentAmount) {
                return redirect()->route('home')->with('error', __('Invalid payment response'));
            }

            $invoice = Invoice::where('payment_token', $token)->first();
            
            if (!$invoice) {
                return redirect()->route('home')->with('error', __('Invoice not found'));
            }
            
            // Check if payment already exists to prevent duplicates
            $existingPayment = Payment::where('invoice_id', $invoice->id)
                ->where('payment_method', 'payfast')
                ->where('transaction_id', $paymentId)
                ->first();
                
            if (!$existingPayment) {
                $invoice->createPaymentRecord($paymentAmount, 'payfast', $paymentId);
            }
            
            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', __('Payment completed successfully!'));
                
        } catch (\Exception $e) {
            return redirect()->route('home')->with('error', __('Payment processing failed'));
        }
    }
    
    public function invoiceCallback(Request $request)
    {
        try {
            $pfData = $request->all();
            $paymentId = $pfData['m_payment_id'] ?? null;
            $paymentStatus = $pfData['payment_status'] ?? null;

            if (!$paymentId || !str_starts_with($paymentId, 'inv_')) {
                return response('Invalid payment ID', 400);
            }

            // Extract invoice ID from payment ID
            $parts = explode('_', $paymentId);
            if (count($parts) < 3) {
                return response('Invalid payment ID format', 400);
            }

            $invoiceId = $parts[1];
            $invoice = Invoice::find($invoiceId);

            if (!$invoice) {
                return response('Invoice not found', 404);
            }

            // Get payment settings for verification
            $paymentSettings = PaymentSetting::where('user_id', $invoice->created_by)
                ->whereIn('key', ['payfast_passphrase'])
                ->pluck('value', 'key')
                ->toArray();

            // Verify signature
            if (!$this->verifyPayfastSignature($pfData, $paymentSettings['payfast_passphrase'] ?? '')) {
                return response('Invalid signature', 400);
            }

            // Process payment based on status
            if ($paymentStatus === 'COMPLETE') {
                $amount = floatval($pfData['amount_gross'] ?? 0);
                $transactionId = $pfData['pf_payment_id'] ?? $paymentId;

                if ($amount > 0) {
                    // Check for duplicate payments
                    $existingPayment = Payment::where('invoice_id', $invoice->id)
                        ->where('payment_method', 'payfast')
                        ->where('transaction_id', $transactionId)
                        ->first();
                        
                    if (!$existingPayment) {
                        $invoice->createPaymentRecord($amount, 'payfast', $transactionId);
                    }
                }
            }

            return response('OK', 200);
        } catch (\Exception $e) {
            return response('ERROR', 500);
        }
    }
    public function createInvoicePaymentFromLink(Request $request, $token)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01'
            ]);

            $invoice = Invoice::where('payment_token', $token)->firstOrFail();

            $paymentSettings = PaymentSetting::where('user_id', $invoice->created_by)
                ->whereIn('key', ['payfast_merchant_id', 'payfast_merchant_key', 'payfast_passphrase', 'payfast_mode', 'is_payfast_enabled'])
                ->pluck('value', 'key')
                ->toArray();

            if (($paymentSettings['is_payfast_enabled'] ?? '0') !== '1') {
                throw new \Exception('PayFast payment method is not enabled');
            }

            if (empty($paymentSettings['payfast_merchant_id']) || empty($paymentSettings['payfast_merchant_key'])) {
                throw new \Exception('PayFast credentials not configured');
            }

            $paymentId = 'inv_' . $invoice->id . '_link_' . time() . '_' . uniqid();
            $isLive = ($paymentSettings['payfast_mode'] ?? 'sandbox') === 'live';

            $data = [
                'merchant_id' => $paymentSettings['payfast_merchant_id'],
                'merchant_key' => $paymentSettings['payfast_merchant_key'],
                'return_url' => route('payfast.invoice.success.from-link', $token),
                'cancel_url' => route('invoices.payment', $token),
                'notify_url' => route('payfast.invoice.callback'),
                'name_first' => 'Customer',
                'name_last' => '',
                'email_address' => 'customer@example.com',
                'm_payment_id' => $paymentId,
                'amount' => number_format($request->amount, 2, '.', ''),
                'item_name' => 'Invoice #' . $invoice->invoice_number,
            ];

            $passphrase = $paymentSettings['payfast_passphrase'] ?? '';
            $signature = $this->generateSignature($data, $passphrase);
            $data['signature'] = $signature;

            session(['payfast_invoice_' . $token => [
                'payment_id' => $paymentId,
                'amount' => $request->amount
            ]]);

            $htmlForm = '';
            foreach ($data as $name => $value) {
                $htmlForm .= '<input name="' . $name . '" type="hidden" value="' . $value . '" />';
            }

            $endpoint = $isLive
                ? 'https://www.payfast.co.za/eng/process'
                : 'https://sandbox.payfast.co.za/eng/process';

            return response()->json([
                'success' => true,
                'inputs' => $htmlForm,
                'action' => $endpoint,
                'payment_id' => $paymentId
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => __('Invoice not found')], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => __('Payment creation failed')], 500);
        }
    }

    public function invoiceSuccessFromLink(Request $request, $token)
    {
        try {
            $sessionKey = 'payfast_invoice_' . $token;
            $paymentData = session($sessionKey);

            if (!$paymentData) {
                return redirect()->route('invoices.payment', $token)
                    ->with('error', __('Invalid payment response'));
            }

            $invoice = Invoice::where('payment_token', $token)->first();

            if (!$invoice) {
                return redirect()->route('invoices.payment', $token)
                    ->with('error', __('Invoice not found'));
            }

            $existingPayment = Payment::where('invoice_id', $invoice->id)
                ->where('payment_method', 'payfast')
                ->where('transaction_id', $paymentData['payment_id'])
                ->first();

            if (!$existingPayment) {
                $invoice->createPaymentRecord($paymentData['amount'], 'payfast', $paymentData['payment_id']);
            }

            session()->forget($sessionKey);

            return redirect()->route('invoices.payment', $token)
                ->with('success', 'Payment processed successfully.');

        } catch (\Exception $e) {
            return redirect()->route('invoices.payment', $token)
                ->with('error', __('Payment processing failed'));
        }
    }
}