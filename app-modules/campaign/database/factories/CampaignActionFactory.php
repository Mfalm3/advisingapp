<?php

/*
<COPYRIGHT>

Copyright © 2022-2023, Canyon GBS LLC

All rights reserved.

This file is part of a project developed using Laravel, which is an open-source framework for PHP.
Canyon GBS LLC acknowledges and respects the copyright of Laravel and other open-source
projects used in the development of this solution.

This project is licensed under the Affero General Public License (AGPL) 3.0.
For more details, see https://github.com/canyongbs/assistbycanyongbs/blob/main/LICENSE.

Notice:
- The copyright notice in this file and across all files and applications in this
 repository cannot be removed or altered without violating the terms of the AGPL 3.0 License.
- The software solution, including services, infrastructure, and code, is offered as a
 Software as a Service (SaaS) by Canyon GBS LLC.
- Use of this software implies agreement to the license terms and conditions as stated
 in the AGPL 3.0 License.

For more information or inquiries please visit our website at
https://www.canyongbs.com or contact us via email at legal@canyongbs.com.

</COPYRIGHT>
*/

namespace Assist\Campaign\Database\Factories;

use Carbon\Carbon;
use Assist\Campaign\Models\Campaign;
use Assist\Campaign\Enums\CampaignActionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Assist\Campaign\Models\CampaignAction>
 */
class CampaignActionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'type' => fake()->randomElement([
                CampaignActionType::BulkEngagement,
            ]),
            'data' => [],
            'execute_at' => fake()->dateTimeBetween('+1 week', '+1 year'),
        ];
    }

    public function successfulExecution(?Carbon $at = null): self
    {
        return $this->state([
            'execute_at' => $at ?? now(),
            'last_execution_attempt_at' => $at ?? now(),
            'successfully_executed_at' => $at ?? now(),
        ]);
    }

    public function failedExecution(?Carbon $at = null): self
    {
        return $this->state([
            'execute_at' => $at ?? now(),
            'last_execution_attempt_at' => $at ?? now(),
            'last_execution_attempt_error' => fake()->sentence(),
        ]);
    }

    public function campaignDisabled(): self
    {
        return $this->state([
            'campaign_id' => Campaign::factory()->disabled(),
        ]);
    }
}
