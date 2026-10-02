<?php
use App\Models\User;

$updated = User::where('type', 'staff')->update([
    'is_enable_login' => 1,
    'status'          => 'active',
]);

echo "Updated {$updated} staff users" . PHP_EOL;
