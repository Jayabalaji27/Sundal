<?php
/**
 * Regression test for QA report 2026-10-04, CI1/CI2: an expired CSRF token
 * (419) came back as a plain redirect, which Inertia treats as success, so
 * the contact form cleared itself and showed its success toast although
 * nothing was saved.
 */

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

test('an expired CSRF token comes back as a form error, not a silent success', function () {
    // CSRF checks are skipped in tests, so raise the exception the middleware would.
    Route::post('/_test/expired-form', fn () => throw new TokenMismatchException('CSRF token mismatch.'))
        ->middleware('web');

    $this->from('/')
        ->post('/_test/expired-form', [], ['X-Inertia' => 'true'])
        ->assertRedirect('/')
        ->assertSessionHasErrors(['error' => 'The page expired, please try again.']);
});
