<?php
/**
 * Regression test for QA report 2026-10-04, L2: a branding logo stored as a
 * full URL was prefixed with APP_URL again ("https://hosthttps://host/...").
 */

test('getFile leaves full URLs alone and prefixes relative paths', function () {
    config(['app.url' => 'https://app.example.test']);

    expect(getFile('https://cdn.example.test/images/logo.png'))->toBe('https://cdn.example.test/images/logo.png')
        ->and(getFile('/images/logos/logo-dark.png'))->toBe('https://app.example.test/images/logos/logo-dark.png');
});
