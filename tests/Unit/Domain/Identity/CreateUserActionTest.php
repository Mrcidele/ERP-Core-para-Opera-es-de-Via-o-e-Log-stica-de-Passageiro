<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\CreateUserAction;
use App\Domain\Identity\DTOs\CreateUserDTO;
use App\Domain\Identity\Entities\User;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Exceptions\EmailAlreadyTakenException;
use App\Domain\Shared\Events\UserCreated;
use Tests\Fakes\FakePasswordHasher;
use Tests\Fakes\InMemoryUserRepository;
use Tests\Fakes\RecordingEventDispatcher;

beforeEach(function (): void {
    $this->users = new InMemoryUserRepository;
    $this->events = new RecordingEventDispatcher;
    $this->action = new CreateUserAction($this->users, new FakePasswordHasher, $this->events);
});

it('creates a user with the password hashed and the e-mail in lower case', function (): void {
    $user = $this->action->execute(new CreateUserDTO('  Caio Marcidele ', ' Caio@Transportadora.COM ', UserRole::Admin, 'Senha-Forte-2026'), 'console');

    expect($user)->toEqual(new User('user-1', 'Caio Marcidele', 'caio@transportadora.com', UserRole::Admin, 'hashed:Senha-Forte-2026'))
        ->and($this->users->findById('user-1'))->toEqual($user);
});

it('publishes the creation without the password', function (): void {
    $user = $this->action->execute(new CreateUserDTO('Ana', 'ana@transportadora.com', UserRole::FleetManager, 'Senha-Forte-2026'), 'console');

    expect($this->events->events)->toEqual([new UserCreated($user->id, 'Ana', 'ana@transportadora.com', 'fleet_manager', 'console')])
        ->and(serialize($this->events->events))->not->toContain('Senha-Forte-2026');
});

it('refuses an e-mail that is already in use, whatever the case', function (): void {
    $this->users->add(new User('existing', 'Ana', 'ana@transportadora.com', UserRole::Dispatcher, 'hashed:x'));

    expect(fn () => $this->action->execute(new CreateUserDTO('Outra Ana', 'ANA@transportadora.com', UserRole::Admin, 'Senha-Forte-2026'), 'console'))
        ->toThrow(EmailAlreadyTakenException::class, 'Já existe um usuário com este e-mail.')
        ->and($this->users->users)->toHaveCount(1)
        ->and($this->events->events)->toBe([]);
});

it('names every role in Portuguese', function (): void {
    expect(array_map(fn (UserRole $role): string => $role->label(), UserRole::cases()))
        ->toBe(['Administração', 'Despacho', 'Gestão de frota', 'Supervisão de oficina']);
});
