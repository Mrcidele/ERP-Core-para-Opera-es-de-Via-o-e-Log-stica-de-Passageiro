<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\DeactivateUserAction;
use App\Domain\Identity\Actions\ListUsersAction;
use App\Domain\Identity\Actions\ReactivateUserAction;
use App\Domain\Identity\Actions\UpdateUserAction;
use App\Domain\Identity\DTOs\UpdateUserDTO;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Exceptions\CannotDeactivateSelfException;
use App\Domain\Identity\Exceptions\EmailAlreadyTakenException;
use App\Domain\Identity\Exceptions\LastAdministratorException;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Domain\Shared\Events\UserDeactivated;
use App\Domain\Shared\Events\UserReactivated;
use App\Domain\Shared\Events\UserUpdated;
use App\Domain\Shared\Pagination\PageQuery;
use Tests\Fakes\FakePasswordHasher;
use Tests\Fakes\FakeTokenService;
use Tests\Fakes\FixedClock;
use Tests\Fakes\ImmediateTransactionRunner;
use Tests\Fakes\InMemoryUserRepository;
use Tests\Fakes\RecordingEventDispatcher;

beforeEach(function (): void {
    $this->users = new InMemoryUserRepository;
    $this->tokens = new FakeTokenService;
    $this->events = new RecordingEventDispatcher;
    $this->clock = new FixedClock('2026-11-18 12:00:00');

    $this->users->add(new User('admin-1', 'Caio', 'caio@erp.test', UserRole::Admin, 'hashed:Senha-Antiga-2026'));
    $this->users->add(new User('disp-1', 'Bruna', 'bruna@erp.test', UserRole::Dispatcher, 'hashed:Senha-Antiga-2026'));

    $this->update = new UpdateUserAction($this->users, new FakePasswordHasher, $this->tokens, new ImmediateTransactionRunner, $this->events);
    $this->deactivate = new DeactivateUserAction($this->users, $this->tokens, new ImmediateTransactionRunner, $this->events, $this->clock);
    $this->reactivate = new ReactivateUserAction($this->users, new ImmediateTransactionRunner, $this->events);
});

it('lists users by name, filtering by status and searching name or e-mail', function (): void {
    $this->users->add(new User('mech-1', 'Ana', 'oficina@erp.test', UserRole::MechanicSupervisor, 'x', new DateTimeImmutable('2026-11-01')));
    $list = new ListUsersAction($this->users);

    expect(array_map(fn (User $user): string => $user->name, $list->execute()->items))->toBe(['Ana', 'Bruna', 'Caio'])
        ->and(array_map(fn (User $user): string => $user->id, $list->execute(new PageQuery(status: 'inactive'))->items))->toBe(['mech-1'])
        ->and(array_map(fn (User $user): string => $user->id, $list->execute(new PageQuery(search: 'OFICINA'))->items))->toBe(['mech-1']);
});

it('updates name, e-mail and role and audits only what changed, without the password', function (): void {
    $user = $this->update->execute(new UpdateUserDTO('disp-1', ' Bruna Lima ', 'Bruna@ERP.test', UserRole::FleetManager));

    expect([$user->name, $user->email, $user->role])->toBe(['Bruna Lima', 'bruna@erp.test', UserRole::FleetManager])
        ->and($this->events->events)->toEqual([new UserUpdated('disp-1', ['name' => 'Bruna', 'role' => 'dispatcher'], ['name' => 'Bruna Lima', 'role' => 'fleet_manager'], false)]);
});

it('resets the password with a new hash, ends the open sessions and records only that it was reset', function (): void {
    $token = $this->tokens->issueFor('disp-1');

    $this->update->execute(new UpdateUserDTO('disp-1', 'Bruna', 'bruna@erp.test', UserRole::Dispatcher, 'Senha-Nova-2026x'));

    expect($this->users->findById('disp-1')?->passwordHash)->toBe('hashed:Senha-Nova-2026x')
        ->and($this->tokens->active)->not->toHaveKey($token)
        ->and($this->events->events)->toEqual([new UserUpdated('disp-1', [], [], true)])
        ->and(serialize($this->events->events))->not->toContain('Senha-Nova-2026x');
});

it('ends the sessions when the role changes, but not on a name change', function (): void {
    $token = $this->tokens->issueFor('disp-1');

    $this->update->execute(new UpdateUserDTO('disp-1', 'Bruna L.', 'bruna@erp.test', UserRole::Dispatcher));
    expect($this->tokens->active)->toHaveKey($token);

    $this->update->execute(new UpdateUserDTO('disp-1', 'Bruna L.', 'bruna@erp.test', UserRole::Admin));
    expect($this->tokens->active)->not->toHaveKey($token);
});

it('publishes nothing when nothing changed', function (): void {
    $this->update->execute(new UpdateUserDTO('disp-1', 'Bruna', 'bruna@erp.test', UserRole::Dispatcher));

    expect($this->events->events)->toBe([]);
});

it('refuses an e-mail that belongs to another user', function (): void {
    expect(fn () => $this->update->execute(new UpdateUserDTO('disp-1', 'Bruna', 'CAIO@erp.test', UserRole::Dispatcher)))
        ->toThrow(EmailAlreadyTakenException::class);
});

it('does not let the last active administrator lose the role, not even by their own hand', function (): void {
    expect(fn () => $this->update->execute(new UpdateUserDTO('admin-1', 'Caio', 'caio@erp.test', UserRole::Dispatcher)))
        ->toThrow(LastAdministratorException::class)
        ->and($this->users->findById('admin-1')?->role)->toBe(UserRole::Admin);
});

it('lets an administrator step down when another administrator is active', function (): void {
    $this->users->add(new User('admin-2', 'Dora', 'dora@erp.test', UserRole::Admin, 'x'));

    expect($this->update->execute(new UpdateUserDTO('admin-1', 'Caio', 'caio@erp.test', UserRole::Dispatcher))->role)->toBe(UserRole::Dispatcher);
});

it('deactivates a user, ends their sessions and audits it', function (): void {
    $token = $this->tokens->issueFor('disp-1');

    $user = $this->deactivate->execute('disp-1', 'admin-1');

    expect($user->isActive())->toBeFalse()
        ->and($user->deactivatedAt)->toEqual(new DateTimeImmutable('2026-11-18 12:00:00'))
        ->and($this->tokens->active)->not->toHaveKey($token)
        ->and($this->events->events)->toEqual([new UserDeactivated('disp-1', 'bruna@erp.test')]);
});

it('does not let anyone deactivate their own access', function (): void {
    expect(fn () => $this->deactivate->execute('admin-1', 'admin-1'))->toThrow(CannotDeactivateSelfException::class)
        ->and($this->users->findById('admin-1')?->isActive())->toBeTrue();
});

it('does not deactivate the last active administrator', function (): void {
    $this->users->add(new User('admin-2', 'Dora', 'dora@erp.test', UserRole::Admin, 'x', new DateTimeImmutable('2026-11-01')));

    expect(fn () => $this->deactivate->execute('admin-1', 'admin-2'))->toThrow(LastAdministratorException::class);
});

it('treats deactivating an inactive user and reactivating an active one as no-ops', function (): void {
    $this->deactivate->execute('disp-1', 'admin-1');
    $this->deactivate->execute('disp-1', 'admin-1');
    $this->reactivate->execute('admin-1');

    expect($this->events->events)->toEqual([new UserDeactivated('disp-1', 'bruna@erp.test')]);
});

it('reactivates a user and audits it', function (): void {
    $this->deactivate->execute('disp-1', 'admin-1');

    expect($this->reactivate->execute('disp-1')->isActive())->toBeTrue()
        ->and($this->events->events[1])->toEqual(new UserReactivated('disp-1', 'bruna@erp.test'));
});

it('answers not found for an unknown user', function (string $method): void {
    $call = match ($method) {
        'update' => fn () => $this->update->execute(new UpdateUserDTO('nope', 'X', 'x@erp.test', UserRole::Admin)),
        'deactivate' => fn () => $this->deactivate->execute('nope', 'admin-1'),
        'reactivate' => fn () => $this->reactivate->execute('nope'),
    };

    expect($call)->toThrow(UserNotFoundException::class);
})->with(['update', 'deactivate', 'reactivate']);
