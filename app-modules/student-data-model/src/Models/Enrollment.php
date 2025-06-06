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

namespace AdvisingApp\StudentDataModel\Models;

use AdvisingApp\StudentDataModel\Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * @mixin IdeHelperEnrollment
 */
class Enrollment extends Model
{
    use SoftDeletes;

    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    use UsesTenantConnection;

    protected $table = 'enrollments';

    /**
     * This Model has a primary key that is auto generated as a v4 UUID by Postgres.
     * We do so so that we can do things like view, edit, and delete a specific record in the UI / API.
     * This ID should NEVER be used for relationships as these records do not belong to our system, our reset during syncs, and are not truly unique.
     */
    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'sisid',
        'division',
        'class_nbr',
        'crse_grade_off',
        'unt_taken',
        'unt_earned',
        'last_upd_dt_stmp',
        'section',
        'name',
        'department',
        'faculty_name',
        'faculty_email',
        'semester_code',
        'semester_name',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'last_upd_dt_stmp' => 'datetime',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'sisid', 'sisid');
    }
}
