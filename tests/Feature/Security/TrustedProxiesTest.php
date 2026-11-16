<?php

declare(strict_types=1);

use App\Infrastructure\Database\Models\AuditLogModel;
use App\Infrastructure\Support\TrustedProxies;

afterEach(fn () => TrustedProxies::apply(null));

function loginIpSeenBehind(string $forwardedFor): ?string
{
    test()->withHeaders(['X-Forwarded-For' => $forwardedFor])
        ->postJson('/api/v1/auth/login', ['email' => 'ninguem@erp.test', 'password' => 'errada'])
        ->assertUnprocessable();

    return AuditLogModel::query()->latest('created_at')->firstOrFail()->ip_address;
}

it('ignores X-Forwarded-For when no proxy is trusted', function (?string $setting): void {
    TrustedProxies::apply($setting);

    expect(loginIpSeenBehind('203.0.113.9'))->toBe('127.0.0.1');
})->with([null, '', ' , ', '10.0.0.0/8']);

it('takes the client IP from a trusted proxy', function (string $setting): void {
    TrustedProxies::apply($setting);

    expect(loginIpSeenBehind('203.0.113.9'))->toBe('203.0.113.9');
})->with(['*', '127.0.0.1', '10.0.0.0/8, 127.0.0.0/8']);
