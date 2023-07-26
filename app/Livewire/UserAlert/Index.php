<?php

namespace App\Livewire\UserAlert;

use Livewire\Component;
use App\Models\UserAlert;
use Livewire\WithPagination;
use App\Livewire\WithSorting;
use Illuminate\Http\Response;
use App\Livewire\WithConfirmation;
use Illuminate\Support\Facades\Gate;

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
        $this->orderable = (new UserAlert())->orderable;
    }

    public function render()
    {
        $query = UserAlert::with(['users'])->advancedFilter([
            's' => $this->search ?: null,
            'order_column' => $this->sortBy,
            'order_direction' => $this->sortDirection,
        ]);

        $userAlerts = $query->paginate($this->perPage);

        return view('livewire.user-alert.index', compact('query', 'userAlerts'));
    }

    public function deleteSelected()
    {
        abort_if(Gate::denies('user_alert_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        UserAlert::whereIn('id', $this->selected)->delete();

        $this->resetSelected();
    }

    public function delete(UserAlert $userAlert)
    {
        abort_if(Gate::denies('user_alert_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $userAlert->delete();
    }
}
