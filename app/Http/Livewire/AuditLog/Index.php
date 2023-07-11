<?php

namespace App\Http\Livewire\AuditLog;

use Livewire\Component;
use App\Models\AuditLog;
use Livewire\WithPagination;
use Illuminate\Http\Response;
use App\Http\Livewire\WithSorting;
use Illuminate\Support\Facades\Gate;
use App\Http\Livewire\WithConfirmation;

class Index extends Component
{
    use WithPagination;
    use WithSorting;
    use WithConfirmation;

    public int $perPage;

    public array $orderable;

    public string $search = '';

    public array $selected = [];

    public array $paginationOptions;

    protected $queryString = [
        'search' => [
            'except' => '',
        ],
        'sortBy' => [
            'except' => 'id',
        ],
        'sortDirection' => [
            'except' => 'desc',
        ],
    ];

    public function getSelectedCountProperty()
    {
        return count($this->selected);
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function resetSelected()
    {
        $this->selected = [];
    }

    public function mount()
    {
        $this->sortBy = 'id';
        $this->sortDirection = 'desc';
        $this->perPage = 100;
        $this->paginationOptions = config('project.pagination.options');
        $this->orderable = (new AuditLog())->orderable;
    }

    public function render()
    {
        $query = AuditLog::advancedFilter([
            's' => $this->search ?: null,
            'order_column' => $this->sortBy,
            'order_direction' => $this->sortDirection,
        ]);

        $auditLogs = $query->paginate($this->perPage);

        return view('livewire.audit-log.index', compact('auditLogs', 'query'));
    }

    public function deleteSelected()
    {
        abort_if(Gate::denies('audit_log_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        AuditLog::whereIn('id', $this->selected)->delete();

        $this->resetSelected();
    }

    public function delete(AuditLog $auditLog)
    {
        abort_if(Gate::denies('audit_log_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $auditLog->delete();
    }
}
