<?php
/**
 * Regression tests for QA report 2026-10-04, A1: after logging in, users
 * landed on /timer/status (403) because the timer's background fetch()
 * polling after logout was stored as the post-login "intended" URL.
 */

use App\Models\User;

// Headers a browser sends with fetch() vs. a top-level page load.
const BACKGROUND_FETCH = ['Sec-Fetch-Dest' => 'empty', 'Sec-Fetch-Mode' => 'cors'];
const PAGE_LOAD = ['Sec-Fetch-Dest' => 'document', 'Sec-Fetch-Mode' => 'navigate'];

test('a background fetch while logged out does not become the post-login page', function () {
    $user = User::factory()->create();

    $this->get('/timer/status', BACKGROUND_FETCH)->assertRedirect(route('login'));
    expect(session()->has('url.intended'))->toBeFalse();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));
});

test('a JSON request while logged out gets a 401 and is not remembered', function () {
    $this->getJson('/timer/status')->assertUnauthorized();

    expect(session()->has('url.intended'))->toBeFalse();
});

test('a real page load while logged out is still the post-login page', function () {
    $user = User::factory()->create();

    $this->get('/projects', PAGE_LOAD)->assertRedirect(route('login'));

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(url('/projects'));
});

test('an Inertia visit while logged out is still the post-login page', function () {
    $user = User::factory()->create();

    $this->get('/projects', BACKGROUND_FETCH + ['X-Inertia' => 'true'])->assertRedirect(route('login'));

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(url('/projects'));
});
