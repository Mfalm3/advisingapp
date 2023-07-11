<?php

namespace App\Http\Livewire\EngagementInteractionType;

use Livewire\Component;
use App\Models\EngagementInteractionType;

class Create extends Component
{
    public EngagementInteractionType $engagementInteractionType;

    public function mount(EngagementInteractionType $engagementInteractionType)
    {
        $this->engagementInteractionType = $engagementInteractionType;
    }

    public function render()
    {
        return view('livewire.engagement-interaction-type.create');
    }

    public function submit()
    {
        $this->validate();

        $this->engagementInteractionType->save();

        return redirect()->route('admin.engagement-interaction-types.index');
    }

    protected function rules(): array
    {
        return [
            'engagementInteractionType.type' => [
                'string',
                'nullable',
            ],
        ];
    }
}
