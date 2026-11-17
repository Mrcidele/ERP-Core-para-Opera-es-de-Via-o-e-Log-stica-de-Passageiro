<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\GetAuthenticatedUserAction;
use App\Domain\Identity\Actions\LoginAction;
use App\Domain\Identity\Actions\LogoutAction;
use App\Domain\Identity\DTOs\LoginDTO;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Exceptions\InvalidCredentialsException;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Domain\Shared\Events\LoginFailed;
use Tests\Fakes\FakePasswordHasher;
use Tests\Fakes\FakeTokenService;
use Tests\Fakes\InMemoryUserRepository;
use Tests\Fakes\RecordingEventDispatcher;

beforeEach(function (): void {
    $this->users = new InMemoryUserRepository;
    $this->users->add(new User('user-1', 'Diego Despachante', 'despachante@erp.test', UserRole::Dispatcher, 'hashed:Password@123'));
    $this->hasher = new FakePasswordHasher;
    $this->tokens = new FakeTokenService;
    $this->events = new RecordingEventDispatcher;
    $this->login = new LoginAction($this->users, $this->hasher, $this->tokens, $this->events);
});

it('logs in with valid credentials and issues a token', function (): void {
    $session = $this->login->execute(new LoginDTO('despachante@erp.test', 'Password@123'));

    expect($session->user->id)->toBe('user-1')
        ->and($session->user->role)->toBe(UserRole::Dispatcher)
        ->and($this->tokens->active)->toBe([$session->token => 'user-1'])
        ->and($this->events->events)->toBeEmpty();
});

it('finds the account regardless of the e-mail letter case', function (): void {
    expect($this->login->execute(new LoginDTO('Despachante@ERP.test', 'Password@123'))->user->id)->toBe('user-1');
});

it('revokes the previous token when logging in again', function (): void {
    $first = $this->login->execute(new LoginDTO('despachante@erp.test', 'Password@123'));
    $second = $this->login->execute(new LoginDTO('despachante@erp.test', 'Password@123'));

    expect($this->tokens->active)->toBe([$second->token => 'user-1'])
        ->and($first->token)->not->toBe($second->token);
});

it('rejects a wrong password, issues no token and announces the failure', function (): void {
    expect(fn () => $this->login->execute(new LoginDTO('despachante@erp.test', 'errada')))
        ->toThrow(InvalidCredentialsException::class, 'E-mail ou senha incorretos.');

    expect($this->tokens->active)->toBeEmpty()
        ->and($this->events->events)->toEqual([new LoginFailed('despachante@erp.test', 'user-1')]);
});

it('rejects an unknown e-mail with the same message and still compares a password', function (): void {
    expect(fn () => $this->login->execute(new LoginDTO('ninguem@erp.test', 'Password@123')))
        ->toThrow(InvalidCredentialsException::class, 'E-mail ou senha incorretos.');

    // Compara com um hash de descarte, para a resposta levar o mesmo tempo de uma conta real.
    expect($this->hasher->comparedAgainst)->toHaveCount(1)
        ->and($this->hasher->comparedAgainst[0])->toStartWith('$argon2id$')
        ->and($this->tokens->active)->toBeEmpty()
        ->and($this->events->events)->toEqual([new LoginFailed('ninguem@erp.test', null)]);
});

it('logs out by revoking only the given token', function (): void {
    $this->users->add(new User('user-2', 'Ana', 'admin@erp.test', UserRole::Admin, 'hashed:Password@123'));
    $mine = $this->login->execute(new LoginDTO('despachante@erp.test', 'Password@123'))->token;
    $other = $this->login->execute(new LoginDTO('admin@erp.test', 'Password@123'))->token;

    (new LogoutAction($this->tokens))->execute($mine);

    expect($this->tokens->active)->toBe([$other => 'user-2']);
});

it('returns the authenticated user', function (): void {
    expect((new GetAuthenticatedUserAction($this->users))->execute('user-1')->email)->toBe('despachante@erp.test');
});

it('fails for a user that no longer exists', function (): void {
    expect(fn () => (new GetAuthenticatedUserAction($this->users))->execute('missing'))
        ->toThrow(UserNotFoundException::class);
});

it('rehashes an outdated password hash after a successful login', function (): void {
    $this->users->add(new User('user-2', 'Conta Antiga', 'antiga@erp.test', UserRole::Admin, 'legacy:Senha@Antiga1'));

    $this->login->execute(new LoginDTO('antiga@erp.test', 'Senha@Antiga1'));

    expect($this->users->findById('user-2')?->passwordHash)->toBe('hashed:Senha@Antiga1');
});

it('keeps an up-to-date hash untouched and never rehashes on a failed login', function (): void {
    $this->users->add(new User('user-2', 'Conta Antiga', 'antiga@erp.test', UserRole::Admin, 'legacy:Senha@Antiga1'));

    $this->login->execute(new LoginDTO('despachante@erp.test', 'Password@123'));
    expect(fn () => $this->login->execute(new LoginDTO('antiga@erp.test', 'errada')))->toThrow(InvalidCredentialsException::class);

    expect($this->users->findById('user-1')?->passwordHash)->toBe('hashed:Password@123')
        ->and($this->users->findById('user-2')?->passwordHash)->toBe('legacy:Senha@Antiga1');
});
