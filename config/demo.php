<?php

/*
 * Demo accounts for presenting the product (seeded by DemoShowcaseSeeder).
 *
 * DEMO_LOGIN_PANEL=true lists these accounts and the shared password on the
 * login page, with one-click "Fill in". Keep it off anywhere real customers
 * log in: the password is shown to everyone who opens the page.
 *
 * This is separate from app.is_demo, which also blocks edits and deletes.
 */
return [
    'login_panel' => (bool) env('DEMO_LOGIN_PANEL', false),

    'password' => env('DEMO_PASSWORD', 'Demo@12345'),

    // Shown in this order; an email with no matching user is skipped.
    'accounts' => [
        ['role' => 'Super Admin', 'email' => 'superadmin@test.com'],
        ['role' => 'Owner', 'email' => 'company@test.com'],
        ['role' => 'Manager', 'email' => 'manager@test.com'],
        ['role' => 'Manager', 'email' => 'neha.iyer@test.com'],
        ['role' => 'Member', 'email' => 'member@test.com'],
        ['role' => 'Member', 'email' => 'vikram.singh@test.com'],
        ['role' => 'Client', 'email' => 'client@test.com'],
    ],
];
