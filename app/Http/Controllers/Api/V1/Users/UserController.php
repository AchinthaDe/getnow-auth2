<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Users;

use App\Domain\Accounts\Data\UserFilterData;
use App\Domain\Accounts\Data\UserResponseData;
use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Accounts\Services\UserService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class UserController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly UserService $userService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'include_trashed' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $search = isset($validated['search']) && is_string($validated['search']) ? $validated['search'] : null;
        $role = isset($validated['role']) && is_string($validated['role']) ? UserRole::from((string) $validated['role']) : null;
        $includeTrashed = $request->boolean('include_trashed');
        $perPage = isset($validated['per_page']) ? (int) $validated['per_page'] : 15;

        $filter = new UserFilterData(
            search: $search,
            role: $role,
            includeTrashed: $includeTrashed,
            perPage: $perPage,
        );

        $paginator = $this->userService->index($filter);
        $paginator->through(fn (User $user) => UserResponseData::fromModel($user));

        return response()->json($paginator);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = $this->userService->store($request->toDto());

        return response()->json([
            'data' => UserResponseData::fromModel($user),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $found = $this->userService->show($user->public_id);

        return response()->json([
            'data' => UserResponseData::fromModel($found),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        /** @var User|null $actor */
        $actor = $request->user('api');
        if ($actor !== null && $actor->role !== UserRole::Superadmin && $request->has('role')) {
            abort(403, 'Non-superadmins cannot modify roles.');
        }

        $updated = $this->userService->update($user, $request->toDto());

        return response()->json([
            'data' => UserResponseData::fromModel($updated),
        ]);
    }

    public function destroy(User $user): Response
    {
        $this->authorize('delete', $user);

        $this->userService->destroy($user);

        return response()->noContent();
    }
}
