<?php

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

// Email verification is intentionally disabled until SMTP is configured.
// Delete or invert this file when it is re-enabled.

it('lets a freshly registered user reach the dashboard without verifying their email', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'New Cook',
        'email' => 'new.cook@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $user = User::where('email', 'new.cook@example.com')->firstOrFail();
    expect($user->email_verified_at)->toBeNull();

    $this->get(route('dashboard'))->assertOk();

    Notification::assertNothingSent();
});

it('lets an unverified user use pages behind the verified middleware', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $this->actingAs($user)->get(route('appearance.edit'))->assertOk();
});

it('has email verification switched off', function () {
    expect(new User)->not->toBeInstanceOf(MustVerifyEmail::class)
        ->and(Features::enabled(Features::emailVerification()))->toBeFalse()
        ->and(Route::has('verification.notice'))->toBeFalse()
        ->and(Route::has('verification.send'))->toBeFalse();
});
