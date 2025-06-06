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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('campaigns', 'caseload_id')) {
                $table->dropColumn('caseload_id');
            }

            $table->foreignUuid('segment_id')->nullable(false)->change();
        });

        DB::table('permission_groups')
            ->whereIn('name', ['Caseload', 'Caseload Subject'])
            ->delete();

        Schema::dropIfExists('caseload_subjects');
        Schema::dropIfExists('caseloads');
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignUuid('caseload_id')->nullable()->constrained('caseloads');

            $table->dropForeign('segment_id');
            $table->foreignUuid('segment_id')->nullable()->change()->constrained('segments');
        });

        Schema::create('caseloads', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');
            $table->text('description')->nullable();
            $table->json('filters')->nullable();
            $table->string('model');
            $table->string('type');

            $table->foreignUuid('user_id')->constrained('users');

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('caseload_subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('subject_id');
            $table->string('subject_type');

            $table->foreignUuid('caseload_id')->constrained('caseloads')->cascadeOnDelete();

            $table->index(['subject_type', 'subject_id']);

            $table->timestamps();
            $table->softDeletes();
        });
    }
};
