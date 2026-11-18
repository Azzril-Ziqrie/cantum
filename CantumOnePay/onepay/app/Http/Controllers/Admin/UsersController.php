<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\User;
use App\Services\OrderRejected;
use App\Services\UserAdminService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UsersController extends Controller
{
    public function __construct(private readonly UserAdminService $users)
    {
    }

    public function index(Request $request): View
    {
        return view('admin.users', [
            'brand' => Brand::current(),
            'users' => $this->users->list($request->user()),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'roleLabels' => UserAdminService::ROLE_LABELS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules() + [
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(10)->letters()->numbers(), 'max:100'],
        ]);

        return $this->run(fn () => $this->users->create($request->user(), $data), 'Pengguna ditambahkan.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate($this->rules() + [
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', Password::min(10)->letters()->numbers(), 'max:100'],
        ]);

        return $this->run(fn () => $this->users->update($request->user(), $user, $data), 'Pengguna disimpan.');
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        $activate = !$user->is_active;

        return $this->run(
            fn () => $this->users->setActive($request->user(), $user, $activate),
            $activate ? 'Pengguna diaktifkan.' : 'Pengguna dinonaktifkan.'
        );
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', Rule::enum(Role::class)],
            'branch_ids' => ['array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
        ];
    }

    private function run(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (OrderRejected $rejection) {
            return back()->withErrors(['form' => $rejection->getMessage()])->withInput();
        }
        return back()->with('status', $success);
    }
}
