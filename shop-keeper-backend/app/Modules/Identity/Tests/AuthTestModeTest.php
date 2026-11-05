<?php

use App\Modules\Identity\AuthTestMode;

it('is off by default', function () {
    expect(AuthTestMode::isOn())->toBeFalse();
});

it('is on when configured outside production', function () {
    config(['shop_keeper.auth_test_mode' => true]);

    expect(AuthTestMode::isOn())->toBeTrue();
});

it('refuses to turn on in production', function () {
    config(['shop_keeper.auth_test_mode' => true]);
    app()->detectEnvironment(fn () => 'production');

    expect(AuthTestMode::isOn())->toBeFalse();
});
