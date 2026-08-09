<?php // tests/Feature/Admin/PanelAccessTest.php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('redirects an anonymous visitor to the login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets an operator in', function () {
    $this->actingAs(User::factory()->create(['is_operator' => true]))
        ->get('/admin')
        ->assertSuccessful();
});

it('refuses a user who is not an operator', function () {
    $this->actingAs(User::factory()->create(['is_operator' => false]))
        ->get('/admin')
        ->assertForbidden();
});

it('has no public registration route', function () {
    $this->post('/admin/register')->assertNotFound();
});

it('creates an operator from the console command', function () {
    $this->artisan('shop:make-admin', [
        '--name' => 'Matin',
        '--email' => 'owner@example.com',
        '--password' => 'correct-horse-battery',
    ])->assertSuccessful();

    $user = User::where('email', 'owner@example.com')->sole();

    expect($user->is_operator)->toBeTrue()
        ->and(Hash::check('correct-horse-battery', $user->password))->toBeTrue();
});

it('refuses to create a second operator with the same email', function () {
    User::factory()->create(['email' => 'owner@example.com']);

    $this->artisan('shop:make-admin', [
        '--name' => 'Matin',
        '--email' => 'owner@example.com',
        '--password' => 'correct-horse-battery',
    ])->assertFailed();
});
