<?php

/*
<COPYRIGHT>

    Copyright © 2022-2023, Canyon GBS LLC. All rights reserved.

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

namespace Assist\Prospect\Rest\Resources;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Relations\HasMany;
use App\Rest\Resource as RestResource;
use Assist\Prospect\Models\ProspectStatus;
use Lomkit\Rest\Http\Requests\RestRequest;
use Assist\Prospect\Enums\ProspectStatusColorOptions;
use Assist\Prospect\Enums\SystemProspectClassification;

class ProspectStatusResource extends RestResource
{
    public static $model = ProspectStatus::class;

    public function fields(RestRequest $request): array
    {
        return [
            'id',
            'classification',
            'name',
            'color',
            'created_at',
            'updated_at',
        ];
    }

    public function createRules(RestRequest $request): array
    {
        return [
            'id' => ['missing'],
            'classification' => ['required', Rule::enum(SystemProspectClassification::class)],
            'name' => ['required', 'string', 'unique:prospect_statuses,name', 'max:255'],
            'color' => ['required', Rule::enum(ProspectStatusColorOptions::class)],
            'created_at' => ['missing'],
            'updated_at' => ['missing'],
        ];
    }

    public function updateRules(RestRequest $request): array
    {
        return [
            'id' => ['missing'],
            'classification' => [Rule::enum(SystemProspectClassification::class)],
            'name' => ['string', 'unique:prospect_statuses,name', 'max:255'],
            'color' => [Rule::enum(ProspectStatusColorOptions::class)],
            'created_at' => ['missing'],
            'updated_at' => ['missing'],
        ];
    }

    public function relations(RestRequest $request): array
    {
        return [
            HasMany::make('prospects', ProspectResource::class),
        ];
    }
}
