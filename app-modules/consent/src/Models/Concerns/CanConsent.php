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

namespace AdvisingApp\Consent\Models\Concerns;

use AdvisingApp\Audit\Overrides\BelongsToMany;
use AdvisingApp\Consent\Models\ConsentAgreement;
use AdvisingApp\Consent\Models\UserConsentAgreement;

trait CanConsent
{
    /**
     * @return BelongsToMany<ConsentAgreement, $this>
     */
    public function consentAgreements(): BelongsToMany
    {
        /** @phpstan-ignore argument.templateType (We are using some Laravel magic here to use Tenant as a Pivot without actually being a pivot) */
        return $this->belongsToMany(ConsentAgreement::class, 'user_consent_agreements')
            ->using(UserConsentAgreement::class) // @phpstan-ignore argument.type (Same as above)
            ->withPivot('ip_address', 'deleted_at')
            ->withTimestamps();
    }

    public function hasConsentedTo(ConsentAgreement $agreement): bool
    {
        return $this->consentAgreements()
            ->where('consent_agreements.id', $agreement->id)
            ->whereNull('user_consent_agreements.deleted_at')
            ->exists();
    }

    public function hasNotConsentedTo(ConsentAgreement $agreement): bool
    {
        return ! $this->hasConsentedTo($agreement);
    }

    public function consentTo(ConsentAgreement $agreement): void
    {
        if ($this->hasConsentedTo($agreement)) {
            return;
        }

        $this->consentAgreements()->attach($agreement->id, [
            'ip_address' => request()->ip(),
        ]);
    }
}
