<?php

/*
<COPYRIGHT>

    Copyright © 2016-2025, Canyon GBS LLC. All rights reserved.

    Advising App™ is licensed under the Elastic License 2.0. For more details,
    see https://github.com/canyongbs/advisingapp/blob/main/LICENSE.

    Notice:

    - You may not provide the software to third parties as a hosted or managed
      service, where the service provides users with access to any substantial set of
      the features or functionality of the software.
    - You may not move, change, disable, or circumvent the license key functionality
      in the software, and you may not remove or obscure any functionality in the
      software that is protected by the license key.
    - You may not alter, remove, or obscure any licensing, copyright, or other notices
      of the licensor in the software. Any use of the licensor’s trademarks is subject
      to applicable law.
    - Canyon GBS LLC respects the intellectual property rights of others and expects the
      same in return. Canyon GBS™ and Advising App™ are registered trademarks of
      Canyon GBS LLC, and we are committed to enforcing and protecting our trademarks
      vigorously.
    - The software solution, including services, infrastructure, and code, is offered as a
      Software as a Service (SaaS) by Canyon GBS LLC.
    - Use of this software implies agreement to the license terms and conditions as stated
      in the Elastic License 2.0.

    For more information or inquiries please visit our website at
    https://www.canyongbs.com or contact us via email at legal@canyongbs.com.

</COPYRIGHT>
*/

namespace AdvisingApp\Ai\Actions;

use AdvisingApp\Ai\Models\Prompt;
use AdvisingApp\Ai\Models\PromptType;
use App\Http\Requests\Tenants\SyncTenantRequest;
use Illuminate\Support\Facades\DB;

class SyncTenantSmartPrompts
{
    public function execute(SyncTenantRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $promptIds = [];

            foreach ($request->validated('smartPrompts') ?? [] as $smartPromptCategory) {
                $promptType = PromptType::query()
                    ->firstOrCreate(
                        ['title' => $smartPromptCategory['title']],
                        ['description' => $smartPromptCategory['description'] ?? null],
                    );

                foreach ($smartPromptCategory['smart_prompts'] ?? [] as $smartPrompt) {
                    /** @var Prompt $prompt */
                    $prompt = Prompt::find($smartPrompt['id']) ?? new Prompt();

                    $prompt->id = $smartPrompt['id'];
                    $prompt->title = $smartPrompt['title'];
                    $prompt->description = $smartPrompt['description'] ?? null;
                    $prompt->prompt = $smartPrompt['prompt'];
                    $prompt->type_id = $promptType->getKey();
                    $prompt->is_smart = true;
                    $prompt->save();

                    $promptIds[] = $smartPrompt['id'];
                }
            }

            Prompt::query()
                ->where('is_smart', true)
                ->whereKeyNot($promptIds)
                ->delete();
        });
    }
}
