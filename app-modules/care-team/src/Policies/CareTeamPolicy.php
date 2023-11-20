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

namespace Assist\CareTeam\Policies;

use App\Models\User;
use Assist\CareTeam\Models\CareTeam;
use Illuminate\Auth\Access\Response;

class CareTeamPolicy
{
    public function viewAny(User $user): Response
    {
        return $user->canOrElse(
            abilities: 'care_team.view-any',
            denyResponse: 'You do not have permission to view care teams.'
        );
    }

    public function view(User $user, CareTeam $careTeam): Response
    {
        return $user->canOrElse(
            abilities: ['care_team.*.view', "care_team.{$careTeam->id}.view"],
            denyResponse: 'You do not have permission to view this care team.'
        );
    }

    public function create(User $user): Response
    {
        return $user->canOrElse(
            abilities: 'care_team.create',
            denyResponse: 'You do not have permission to create care teams.'
        );
    }

    public function update(User $user, CareTeam $careTeam): Response
    {
        return $user->canOrElse(
            abilities: ['care_team.*.update', "care_team.{$careTeam->id}.update"],
            denyResponse: 'You do not have permission to update this care team.'
        );
    }

    public function delete(User $user, CareTeam $careTeam): Response
    {
        return $user->canOrElse(
            abilities: ['care_team.*.delete', "care_team.{$careTeam->id}.delete"],
            denyResponse: 'You do not have permission to delete this care team.'
        );
    }

    public function restore(User $user, CareTeam $careTeam): Response
    {
        return $user->canOrElse(
            abilities: ['care_team.*.restore', "care_team.{$careTeam->id}.restore"],
            denyResponse: 'You do not have permission to restore this care team.'
        );
    }

    public function forceDelete(User $user, CareTeam $careTeam): Response
    {
        return $user->canOrElse(
            abilities: ['care_team.*.force-delete', "care_team.{$careTeam->id}.force-delete"],
            denyResponse: 'You do not have permission to permanently delete this care team.'
        );
    }
}
