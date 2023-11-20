<?php

namespace Assist\Form\Models;

use App\Models\BaseModel;
use Assist\Prospect\Models\Prospect;
use Assist\AssistDataModel\Models\Student;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperFormSubmission
 *
 * @property Student|Prospect|null $author
 */
class FormSubmission extends BaseModel
{
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(
            FormField::class,
            'form_field_submission',
            'submission_id',
            'field_id',
        )
            ->withPivot(['id', 'response']);
    }

    public function author(): MorphTo
    {
        return $this->morphTo();
    }
}
