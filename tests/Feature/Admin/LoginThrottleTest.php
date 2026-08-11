<?php // tests/Feature/Admin/LoginThrottleTest.php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('admin-login|127.0.0.1');
});

it('rate limits repeated login attempts', function () {
    User::factory()->create(['email' => 'owner@example.com', 'is_operator' => true]);

    foreach (range(1, 5) as $attempt) {
        $this->post('/admin/login', [
            'email' => 'owner@example.com',
            'password' => 'wrong-password',
        ]);
    }

    $this->post('/admin/login', [
        'email' => 'owner@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(429);
});
