<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class InvoicePreviewController extends Controller
{
    public function preview(Request $request)
    {
        $template = $request->query('template', 'default');
        $showQr = $request->query('qr') === 'true';
        $color = $request->query('color', '#3b82f6');
        
        return view('invoice-preview', [
            'template' => $template,
            'showQr' => $showQr,
            'color' => $color,
        ]);
    }
}
