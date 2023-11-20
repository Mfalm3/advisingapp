@php
    use Carbon\Carbon;
    use App\Models\User;
@endphp

<x-filament::fieldset>
    <x-slot name="label">
        Care Team
    </x-slot>

    <dl class="max-w-md divide-y divide-gray-200 text-gray-900 dark:divide-gray-700 dark:text-white">
        <div class="flex flex-col pb-3">
            <dt class="mb-1 text-sm text-gray-500 dark:text-gray-400">Users to be assigned to the care team</dt>
            <dd class="text-sm font-semibold">
                {{ collect($action['user_ids'])->map(fn(string $userId): User => User::findOrFail($userId))->implode('name', ', ') }}
            </dd>
        </div>
        <div class="flex flex-col pb-3">
            <dt class="mb-1 text-sm text-gray-500 dark:text-gray-400">Remove all prior care team assignments</dt>
            <dd class="text-sm font-semibold">{{ $action['remove_prior'] ? 'True' : 'False' }}</dd>
        </div>
        <div class="flex flex-col pt-3">
            <dt class="mb-1 text-sm text-gray-500 dark:text-gray-400">Execute At</dt>
            <dd class="text-sm font-semibold">{{ Carbon::parse($action['execute_at'])->format('m/d/Y H:i:s') }}</dd>
        </div>
    </dl>

</x-filament::fieldset>
