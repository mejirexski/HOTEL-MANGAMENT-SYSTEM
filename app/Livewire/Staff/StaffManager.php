<?php

namespace App\Livewire\Staff;

use App\Support\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class StaffManager extends Component
{
    use WithPagination;

    private const STAFF_ROLES = [
        'general-manager', 
        'receptionist', 
        'cashier',
        'bar-manager', 
        'bartender', 
        'storekeeper',
        'gym-manager', 
        'gym-attendant',
        'housekeeping', 
        'maintenance',
    ];

    public string $search = '';
    public string $roleFilter = '';

    public bool $showForm = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $role = '';
    public string $password = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['owner', 'general-manager']), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    private function assignableRoles(): array
    {
        if (Auth::user()->hasRole('owner')) {
            return self::STAFF_ROLES;
        }

        return array_values(array_diff(self::STAFF_ROLES, ['general-manager']));
    }

    private function canManage(User $target): bool
    {
        if ($target->id === Auth::id()) {
            return false;
        }
        if ($target->hasRole('owner')) {
            return false;
        }
        if ($target->hasRole('general-manager') && ! Auth::user()->hasRole('owner')) {
            return false;
        }

        return $target->hasAnyRole(self::STAFF_ROLES);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in($this->assignableRoles())],
            'password' => $this->editingId
                ? ['nullable', 'string', 'min:8']
                : ['required', 'string', 'min:8'],
        ];
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'email', 'phone', 'role', 'password']);
        $this->resetValidation();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);
        abort_unless($this->canManage($user), 403);

        $this->resetValidation();
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone ?? '';
        $this->role = $user->getRoleNames()->first() ?? '';
        $this->password = '';
        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['owner', 'general-manager']), 403);

        $data = $this->validate();

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            abort_unless($this->canManage($user), 403);

            $before = [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->getRoleNames()->first(),
            ];

            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->phone = $data['phone'] ?: null;

            $passwordChanged = ($data['password'] ?? '') !== '';
            if ($passwordChanged) {
                $user->password = $data['password'];
            }

            $user->save();
            $user->syncRoles([$data['role']]);

            $after = [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $data['role'],
            ];
            if ($passwordChanged) {
                $after['password_changed'] = true; // never log the password itself
            }

            AuditLog::record('user.updated', $user, $before, $after);

            if ($before['role'] !== $data['role']) {
                AuditLog::record('user.role_changed', $user, ['role' => $before['role']], ['role' => $data['role']]);
            }

            session()->flash('status', 'Staff member updated.');
        } else {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?: null,
                'password' => $data['password'],
                'is_active' => true,
            ]);
            $user->assignRole($data['role']);

            AuditLog::record('user.created', $user, null, [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $data['role'],
            ]);

            session()->flash('status', 'Staff member created. Give them the temporary password.');
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $user = User::findOrFail($id);
        abort_unless($this->canManage($user), 403);
    
        $before = ['is_active' => (bool) $user->is_active];
    
        $user->update(['is_active' => ! $user->is_active]);
    
        AuditLog::record(
            $user->is_active ? 'user.activated' : 'user.deactivated',
            $user,
            $before,
            ['is_active' => (bool) $user->is_active],
        );
    
        session()->flash('status', $user->is_active ? 'Account activated.' : 'Account deactivated.');
    }

    public function render()
    {
        $staff = User::query()
            ->role(self::STAFF_ROLES)
            ->with('roles')
            ->when($this->search !== '', fn ($q) => $q->where(
                fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
            ))
            ->when(
                in_array($this->roleFilter, self::STAFF_ROLES, true),
                fn ($q) => $q->role($this->roleFilter)
            )
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.staff.staff-manager', [
            'staff' => $staff,
            'roles' => $this->assignableRoles(),
            'me' => Auth::id(),
        ]);
    }
}