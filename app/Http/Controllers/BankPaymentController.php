<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Models\Setting;
use App\Models\PlanOrder;
use App\Models\PaymentSetting;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;

class BankPaymentController extends Controller
{
    public function processPayment(Request $request)
    {
        $validated = validatePaymentRequest($request, [
            'amount' => 'required|numeric|min:0',
        ]);

        try {
            $plan = Plan::findOrFail($validated['plan_id']);

            $receiptPath = null;
            if (isDemo() === false && $request->hasFile('receipt')) {
                $file = $request->file('receipt');
                $fileName = 'receipt_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $upload = upload_file($request, 'receipt', $fileName, 'payment-receipts');
                if ($upload['status']) {
                    $receiptPath = $upload['url'];
                }
            }

            $planOrder = createPlanOrder([
                'user_id' => auth()->id(),
                'plan_id' => $plan->id,
                'billing_cycle' => $validated['billing_cycle'],
                'payment_method' => 'bank',
                'coupon_code' => $validated['coupon_code'] ?? null,
                'payment_id' => 'BANK_' . strtoupper(uniqid()),
                'status' => 'pending',
            ]);

            if ($receiptPath) {
                $planOrder->update(['receipt_path' => $receiptPath]);
            }

            return back()->with('success', __('Payment request submitted. Your plan will be activated after payment verification.'));

        } catch (\Exception $e) {
            return handlePaymentError($e, 'bank');
        }
    }

    public function processInvoicePayment(Request $request)
    {
        try {
            $request->validate([
                'invoice_token' => 'required|string',
                'amount' => 'required|numeric|min:0.01',
                'receipt' => 'nullable|file',
            ]);
            
            $invoice = Invoice::where('payment_token', $request->invoice_token)->firstOrFail();

            $remainingAmount = $invoice->remaining_amount;
            $pendingTotal = $invoice->payments()->where('status', 'pending')->sum('amount');
            $availableAmount = $remainingAmount - $pendingTotal;

            if ($availableAmount <= 0) {
                return back()->withErrors(['amount' => __('A pending payment already covers the full due amount. Please wait for approval.')]);
            }

            if ($request->amount > $availableAmount) {
                return back()->withErrors(['amount' => __('Amount exceeds available balance of ') . number_format($availableAmount, 2)]);
            }

            $receiptPath = null;
            if (isDemo() === false && $request->hasFile('receipt')) {
                $file = $request->file('receipt');
                $fileName = 'receipt_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $upload = upload_file($request, 'receipt', $fileName, 'payment-receipts');
                if ($upload['status']) {
                    $receiptPath = $upload['url'];
                }
            }

            Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => $request->amount,
                'payment_method' => 'bank',
                'transaction_id' => 'BANK_' . strtoupper(uniqid()),
                'payment_date' => now(),
                'created_by' => auth()->id() ?? $invoice->created_by,
                'workspace_id' => $invoice->workspace_id,
                'status' => 'pending',
                'receipt_path' => $receiptPath,
            ]);
            
            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', __('Bank transfer payment submitted. It will be confirmed after approval.'));
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return back()->withErrors(['error' => __('Invoice not found. Please check the link and try again.')]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => __('Payment request failed. Please try again or contact support.')]);
        }
    }

    public function processInvoicePaymentFromLink(Request $request, $token)
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'receipt' => 'nullable|file',
            ]);
            
            $invoice = Invoice::where('payment_token', $token)->firstOrFail();

            $remainingAmount = $invoice->remaining_amount;
            $pendingTotal = $invoice->payments()->where('status', 'pending')->sum('amount');
            $availableAmount = $remainingAmount - $pendingTotal;

            if ($availableAmount <= 0) {
                return back()->withErrors(['amount' => __('A pending payment already covers the full due amount. Please wait for approval.')]);
            }

            if ($request->amount > $availableAmount) {
                return back()->withErrors(['amount' => __('Amount exceeds available balance of ') . number_format($availableAmount, 2)]);
            }

            $receiptPath = null;
            if (isDemo() === false && $request->hasFile('receipt')) {
                $file = $request->file('receipt');
                $fileName = 'receipt_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $upload = upload_file($request, 'receipt', $fileName, 'payment-receipts');
                if ($upload['status']) {
                    $receiptPath = $upload['url'];
                }
            }

            Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => $request->amount,
                'payment_method' => 'bank',
                'transaction_id' => 'BANK_' . strtoupper(uniqid()),
                'payment_date' => now(),
                'created_by' => $invoice->created_by,
                'workspace_id' => $invoice->workspace_id,
                'status' => 'pending',
                'receipt_path' => $receiptPath,
            ]);
            
            return redirect()->route('invoices.payment', $invoice->payment_token)
                ->with('success', __('Bank transfer payment submitted. It will be confirmed after approval.'));
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return back()->withErrors(['error' => __('Invoice not found. Please check the link and try again.')]);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => __('Payment request failed. Please try again or contact support.')]);
        }
    }
}