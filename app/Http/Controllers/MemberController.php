<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        // whereLike() sem case sensitivity: vira LIKE no MariaDB e ILIKE no Postgres (onde LIKE diferencia maiúsculas)
        $members = User::visibleTo($request->user())
            ->with('unit:id,code')
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($w) => $w->whereLike('name', "%{$term}%")->orWhereLike('registration', "%{$term}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Members/Index', [
            'members' => $members->through(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'registration' => $u->registration,
                'role' => $u->role->value, 'role_label' => $u->role->label(), 'active' => $u->active, 'unit' => $u->unit?->code,
            ]),
            'filters' => ['q' => $request->query('q')],
            'roles' => collect(Role::cases())->reject(fn ($r) => $r === Role::Admin && ! $request->user()->isAdmin())
                ->map(fn ($r) => ['value' => $r->value, 'label' => $r->label()])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'registration' => ['required', 'string', 'max:20', 'unique:users,registration'],
            'role' => ['required', Rule::in($this->assignableRoles($request->user()))],
        ]);

        User::create($data + [
            'unit_id' => $request->user()->unit_id,
            'password' => Str::password(16),
        ]);

        return back()->with('success', 'Membro cadastrado.');
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        Gate::authorize('update', $member);

        $data = $request->validate([
            'role' => ['sometimes', Rule::in($this->assignableRoles($request->user()))],
            'active' => ['sometimes', 'boolean'],
        ]);

        $member->update($data);

        return back()->with('success', 'Membro atualizado.');
    }

    private function assignableRoles(User $user): array
    {
        return $user->isAdmin()
            ? array_column(Role::cases(), 'value')
            : [Role::Gestor->value, Role::Membro->value];
    }
}
