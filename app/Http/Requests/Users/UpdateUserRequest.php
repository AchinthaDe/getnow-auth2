<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Domain\Accounts\Data\UserData;
use App\Domain\Accounts\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Optional;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $actor */
        $actor = $this->user('api') ?? $this->user();
        if ($actor === null) {
            return false;
        }

        /** @var User|string|null $user */
        $user = $this->route('user');
        if (! $user instanceof User) {
            return false;
        }

        if (! $actor->can('update', $user)) {
            return false;
        }

        if ($actor->role !== UserRole::Superadmin && $this->has('role')) {
            return false;
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge([
                'email' => strtolower(trim((string) $this->input('email'))),
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User|string|null $user */
        $user = $this->route('user');

        /** @var User|null $actor */
        $actor = $this->user('api') ?? $this->user();
        $isNonSuperadmin = $actor !== null && $actor->role !== UserRole::Superadmin;

        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user instanceof User ? $user : null),
            ],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'max:72'],
            'role' => ['sometimes', 'required', Rule::enum(UserRole::class)],
        ];

        if ($isNonSuperadmin && ($this->has('password') || $this->has('email'))) {
            $rules['current_password'] = ['required', 'string', 'current_password:api'];
        }

        return $rules;
    }

    public function toDto(): UserData
    {
        $validated = $this->validated();

        $name = array_key_exists('name', $validated)
            ? (string) $validated['name']
            : new Optional;

        $email = array_key_exists('email', $validated)
            ? (string) $validated['email']
            : new Optional;

        $password = array_key_exists('password', $validated)
            ? (string) $validated['password']
            : new Optional;

        $role = array_key_exists('role', $validated)
            ? UserRole::from((string) $validated['role'])
            : new Optional;

        return new UserData(
            name: $name,
            email: $email,
            password: $password,
            role: $role,
        );
    }
}
