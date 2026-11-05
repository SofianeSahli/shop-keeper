<?php

use App\Modules\Identity\Actions\IssueDeviceSession;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Str;

it('keeps one token per account and device', function () {
    $user = User::factory()->create();
    $deviceId = (string) Str::uuid();

    $first = app(IssueDeviceSession::class)->handle($user, $deviceId);
    $second = app(IssueDeviceSession::class)->handle($user, $deviceId);

    expect($first)->not->toBe($second);
    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens()->first()->name)->toBe($deviceId);
});

it('leaves the tokens of other devices and other accounts alone', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $deviceId = (string) Str::uuid();
    $otherDeviceId = (string) Str::uuid();

    app(IssueDeviceSession::class)->handle($otherUser, $deviceId);
    app(IssueDeviceSession::class)->handle($user, $otherDeviceId);
    app(IssueDeviceSession::class)->handle($user, $deviceId);
    app(IssueDeviceSession::class)->handle($user, $deviceId);

    expect($user->tokens()->count())->toBe(2);
    expect($otherUser->tokens()->count())->toBe(1);
});

it('treats a device id in capitals as the same device', function () {
    $user = User::factory()->create();
    $deviceId = (string) Str::uuid();

    app(IssueDeviceSession::class)->handle($user, $deviceId);
    app(IssueDeviceSession::class)->handle($user, strtoupper($deviceId));

    expect($user->tokens()->count())->toBe(1);
});

it('never gives a token an expiry', function () {
    $user = User::factory()->create();

    app(IssueDeviceSession::class)->handle($user, (string) Str::uuid());

    expect($user->tokens()->first()->expires_at)->toBeNull();
});
