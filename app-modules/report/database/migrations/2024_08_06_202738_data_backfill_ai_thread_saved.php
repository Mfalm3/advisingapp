<?php

/*
<COPYRIGHT>

    Copyright © 2016-2024, Canyon GBS LLC. All rights reserved.

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

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        DB::table('tracked_event_counts')
            ->insertOrIgnore([
                'id' => (string) Str::orderedUuid(),
                'type' => 'ai-thread-saved',
                'count' => 0,
                'created_at' => now(),
            ]);

        DB::table('ai_threads')
            ->whereNotNull('saved_at')
            ->eachById(function ($thread) {
                DB::table('tracked_events')
                    ->insert([
                        'id' => (string) Str::orderedUuid(),
                        'type' => 'ai-thread-saved',
                        'occurred_at' => $thread->saved_at,
                    ]);

                DB::table('tracked_event_counts')
                    ->where('type', 'ai-thread-saved')
                    ->update([
                        'count' => DB::raw('count + 1'),
                        'last_occurred_at' => DB::raw("GREATEST(last_occurred_at, '{$thread->saved_at}')"),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        DB::table('tracked_event_counts')
            ->where('type', 'ai-thread-saved')
            ->delete();

        DB::table('tracked_events')
            ->where('type', 'ai-thread-saved')
            ->delete();
    }
};
