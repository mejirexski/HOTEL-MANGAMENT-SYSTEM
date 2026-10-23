<?php

namespace App\Livewire\Audit;

use App\Support\Bookings\BookingException;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;


class AuditLogViewer extends Component
{
    use WithPagination;

    public string $action = '';
    public string $userSearch = '';
    public string $from = '';
    public string $to = '';
    public ?int $openId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasAnyRole(['owner', 'general-manager']), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['action', 'userSearch', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function toggle(int $id): void
    {
        $this->openId = $this->openId === $id ? null : $id;
    }

    public function render()
    {
        $logs = ActivityLog::query()
            ->with('user:id,name')
            ->when($this->action !== '', fn ($q) => $q->where('action', $this->action))
            ->when($this->userSearch !== '', fn ($q) => $q->whereHas(
                'user',
                fn ($u) => $u->where(
                    fn ($w) => $w->where('name', 'like', "%{$this->userSearch}%")
                        ->orWhere('email', 'like', "%{$this->userSearch}%")
                )
            ))
            ->when($this->from !== '', fn ($q) => $q->where('created_at', '>=', $this->from . ' 00:00:00'))
            ->when($this->to !== '', fn ($q) => $q->where('created_at', '<=', $this->to . ' 23:59:59'))
            ->latest('id')
            ->paginate(20);

        return view('livewire.audit.audit-log-viewer', [
            'logs' => $logs,
            'actions' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}