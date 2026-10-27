<?php

declare(strict_types=1);

namespace App\Domain\Accounts\Services;

use App\Domain\Accounts\Contracts\TokenIssuer;
use App\Domain\Accounts\Contracts\UserRepositoryInterface;
use App\Domain\Accounts\Data\AuthResultData;
use App\Domain\Accounts\Data\LoginData;
use App\Domain\Accounts\Data\UserData;
use App\Domain\Accounts\Data\UserResponseData;
use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Accounts\Exceptions\InvalidCredentialsException;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\DB;

final class AuthService
{
    /**
     * Constant dummy bcrypt hash matching production BCRYPT_ROUNDS (cost 12 per .env.example).
     * The cost must track production rounds so timing equalization prevents an email-existence oracle.
     */
    private const string DUMMY_HASH = '$2y$12$paGwAYoFIuju5tDSdHrPCOqL7KBasBpmD8nF4EN5nhyUQD0J24r6m';

    public function __construct(
        private readonly UserService $userService,
        private readonly UserRepositoryInterface $userRepository,
        private readonly TokenIssuer $tokenIssuer,
        private readonly Hasher $hasher,
    ) {}

    public function register(UserData $data): AuthResultData
    {
        return DB::transaction(function () use ($data): AuthResultData {
            $customerData = new UserData(
                name: $data->name,
                email: $data->email,
                password: $data->password,
                role: UserRole::Customer,
            );

            $user = $this->userService->store($customerData);
            $tokens = $this->tokenIssuer->issue($user);

            return new AuthResultData(UserResponseData::fromModel($user), $tokens);
        });
    }

    public function login(LoginData $data): AuthResultData
    {
        $email = strtolower(trim($data->email));
        $user = $this->userRepository->findByEmail($email);

        if ($user === null) {
            $this->hasher->check($data->password, self::DUMMY_HASH);

            throw new InvalidCredentialsException;
        }

        if (! $this->hasher->check($data->password, $user->password)) {
            throw new InvalidCredentialsException;
        }

        $tokens = $this->tokenIssuer->issue($user);

        return new AuthResultData(UserResponseData::fromModel($user), $tokens);
    }
}
