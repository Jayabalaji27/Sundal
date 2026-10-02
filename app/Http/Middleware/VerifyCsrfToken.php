<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'payments/aamarpay/success',
        'payments/aamarpay/callback',
        'aamarpay/invoice/success',
        'aamarpay/invoice/callback',
        '/aamarpay/invoice/success',
        '/aamarpay/invoice/callback',
        'aamarpay/invoice/success/*',
        '/aamarpay/invoice/success/*',
        'midtrans/invoice/success/*',
        '/midtrans/invoice/success/*',
        'yookassa/invoice/success/*',
        '/yookassa/invoice/success/*',
        'paiement/invoice/success/*',
        '/paiement/invoice/success/*',
        'paiement/invoice/callback-link',
        '/paiement/invoice/callback-link',
        'cinetpay/invoice/success/*',
        '/cinetpay/invoice/success/*',
        'cinetpay/invoice/callback-link',
        '/cinetpay/invoice/callback-link',
        'payhere/invoice/success/*',
        '/payhere/invoice/success/*',
        'payhere/invoice/callback-link',
        '/payhere/invoice/callback-link',
        'aamarpay/create-invoice-payment',
        '/aamarpay/create-invoice-payment',
        'payments/tap/success',
        'payments/tap/callback',
        'payments/benefit/success',
        'payments/benefit/callback',
        'payments/easebuzz/success',
        'payments/easebuzz/callback',
        'payments/easebuzz/invoice-success',
        'payments/ozow/invoice-success',
        'payments/paytabs/callback',
        'payments/iyzipay/callback',
        'payments/iyzipay/success',
        'iyzipay/invoice/success',
        'iyzipay/invoice/callback',
        'iyzipay/invoice/success/*'
    ];
}