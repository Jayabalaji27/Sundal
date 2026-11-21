<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Test Email</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 20px;
        }
        .header {
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #777;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            {{-- Same embedded logo as emails/layout.blade.php, so this test also shows it works. --}}
            @php($logoFile = \App\Support\BrandLogo::file())
            <img alt="{{ config('app.name') }}" src="{{ isset($message) && $logoFile ? $message->embed($logoFile) : \App\Support\BrandLogo::url() }}" width="160" style="border:none;display:block;outline:none;text-decoration:none;width:160px;max-width:100%;height:auto;margin:4px 0 8px 0;"/>
            <h2>Test Email</h2>
        </div>
        
        <p>Hello,</p>
        
        <p>This is a test email from <strong>{{ config('app.name') }}</strong>.</p>
        
        <p>If you received this email, your email configuration is working correctly.</p>
        
        <p>Thank you!</p>
        
        <div class="footer">
            <p>This is an automated test email. Please do not reply.</p>
        </div>
    </div>
</body>
</html>