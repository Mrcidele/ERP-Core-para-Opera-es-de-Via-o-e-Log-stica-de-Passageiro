<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

/** @return array<string, mixed> */
function englishLines(string $file): array
{
    return Arr::dot(require base_path("vendor/laravel/framework/src/Illuminate/Translation/lang/en/{$file}.php"));
}

/** @return array<string, mixed> */
function portugueseLines(string $file): array
{
    return Arr::dot(require lang_path("pt_BR/{$file}.php"));
}

/**
 * Campos validados por cada FormRequest da API, com os nomes que o próprio request já traduz.
 *
 * @return array<class-string<FormRequest>, array{fields: list<string>, attributes: array<string, string>}>
 */
function formRequestFields(): array
{
    $requests = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Presentation/Http/Requests'))) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $class = 'App\\Presentation\\Http\\Requests\\'.str_replace(['/', '.php'], ['\\', ''], substr($file->getPathname(), strlen(app_path('Presentation/Http/Requests')) + 1));
        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract() || ! $reflection->isSubclassOf(FormRequest::class)) {
            continue;
        }

        /** @var FormRequest $request */
        $request = $class::create('/', 'GET');
        $request->setContainer(app());

        $requests[$class] = [
            'fields' => array_keys(app()->call([$request, 'rules'])),
            'attributes' => $request->attributes(),
        ];
    }

    return $requests;
}

it('runs in Brazilian Portuguese', function (): void {
    expect(app()->getLocale())->toBe('pt_BR');
});

it('translates every validation, auth and password message of the framework', function (string $file): void {
    // `custom` e `attributes` são do projeto, não mensagens do framework.
    $framework = array_filter(array_keys(englishLines($file)), fn (string $key): bool => ! str_starts_with($key, 'custom') && $key !== 'attributes');
    $missing = array_values(array_diff($framework, array_keys(portugueseLines($file))));

    expect($missing)->toBe([]);
})->with(['validation', 'auth', 'passwords', 'pagination']);

it('keeps every translated message free of English and with the same placeholders', function (): void {
    $english = englishLines('validation');

    foreach (portugueseLines('validation') as $key => $line) {
        if (! is_string($line) || str_starts_with($key, 'attributes.')) {
            continue;
        }

        preg_match_all('/:[a-z_]+/', (string) ($english[$key] ?? ''), $expected);
        preg_match_all('/:[a-z_]+/', $line, $actual);

        expect($line)->not->toMatch('/\b(The|field|must|invalid)\b/')
            ->and(array_unique($actual[0]))->toEqualCanonicalizing(array_unique($expected[0]));
    }
});

it('names every field of every API form in Portuguese', function (): void {
    $global = (array) trans('validation.attributes');
    $untranslated = [];

    foreach (formRequestFields() as $class => ['fields' => $fields, 'attributes' => $own]) {
        foreach ($fields as $field) {
            if (! isset($own[$field]) && ! isset($global[$field])) {
                $untranslated[] = class_basename($class).'.'.$field;
            }
        }
    }

    expect($untranslated)->toBe([]);
});

it('answers validation errors in Portuguese, with field names in Portuguese', function (): void {
    actingAsRole(UserRole::Admin);

    $this->postJson('/api/v1/fleet/vehicles', ['capacity' => 'muitos', 'current_mileage' => -1])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'O campo placa é obrigatório. (e mais 4 erros)')
        ->assertJsonValidationErrors([
            'plate' => 'O campo placa é obrigatório.',
            'chassis' => 'O campo chassi é obrigatório.',
            'capacity' => 'O campo capacidade deve ser um número inteiro.',
            'class_type' => 'O campo classe é obrigatório.',
        ]);

    $this->postJson('/api/v1/identity/users', ['name' => 'Ana', 'email' => 'ana', 'role' => 'admin', 'password' => 'curta', 'password_confirmation' => 'outra'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'email' => 'O campo e-mail deve ser um e-mail válido.',
            'password' => 'A confirmação do campo senha não confere.',
        ]);

    $this->getJson('/api/v1/operations/schedule-timeline?date=amanha')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date' => 'Informe a data no formato AAAA-MM-DD.']);

    $this->getJson('/api/v1/fleet/vehicles?per_page=500')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page' => 'O campo itens por página não pode ser maior que 100.']);
});

it('explains a weak password in Portuguese', function (): void {
    actingAsRole(UserRole::Admin);

    $this->postJson('/api/v1/identity/users', ['name' => 'Ana', 'email' => 'ana@erp.test', 'role' => 'admin', 'password' => 'senhafraca', 'password_confirmation' => 'senhafraca'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password' => [
            'O campo senha deve ter pelo menos 12 caracteres.',
            'O campo senha deve conter pelo menos uma letra maiúscula e uma minúscula.',
            'O campo senha deve conter pelo menos um número.',
        ]]);
});

it('refuses a login with a malformed e-mail in Portuguese', function (): void {
    $this->postJson('/api/v1/auth/login', ['email' => 'nao-e-email', 'password' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => 'O campo e-mail deve ser um e-mail válido.']);
});
