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

use AdvisingApp\Authorization\Enums\LicenseType;
use AdvisingApp\Authorization\Models\Role;
use AdvisingApp\Team\Models\Team;
use App\Filament\Resources\UserResource\Actions\AssignLicensesBulkAction;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Authenticatable;
use App\Models\User;
use Lab404\Impersonate\Services\ImpersonateManager;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertTrue;

use STS\FilamentImpersonate\Tables\Actions\Impersonate;

use function Tests\asSuperAdmin;

it('renders impersonate button for non super admin users when user is super admin', function () {
    asSuperAdmin();

    $user = User::factory()->create();

    $component = livewire(ListUsers::class);

    $component
        ->assertSuccessful()
        ->assertCountTableRecords(2)
        ->assertTableActionVisible(Impersonate::class, $user);
});

it('does not render impersonate button for super admin users when user is not super admin', function () {
    $superAdmin = User::factory()->create();
    asSuperAdmin($superAdmin);

    $user = User::factory()
        ->create()
        ->givePermissionTo('user.view-any', 'user.*.view');
    actingAs($user);

    $component = livewire(ListUsers::class);

    $component
        ->assertSuccessful()
        ->assertCountTableRecords(1)
        ->assertTableActionHidden(Impersonate::class, $superAdmin);
});

it('does not render impersonate button for super admin users at all', function () {
    $superAdmin = User::factory()->create();
    asSuperAdmin($superAdmin);

    $user = User::factory()->create();
    asSuperAdmin($user);

    $component = livewire(ListUsers::class);

    $component
        ->assertSuccessful()
        ->assertCountTableRecords(2)
        ->assertTableActionHidden(Impersonate::class, $superAdmin);
});

it('does not render impersonate button for super admin users even if user is also a Super Admin', function () {
    $superAdmin = User::factory()->create();
    asSuperAdmin($superAdmin);

    $user = User::factory()
        ->create();

    $user->assignRole(Authenticatable::SUPER_ADMIN_ROLE);

    actingAs($user);

    $component = livewire(ListUsers::class);

    $component
        ->assertSuccessful()
        ->assertCountTableRecords(2)
        ->assertTableActionHidden(Impersonate::class, $superAdmin);
});

it('allows super admin user to impersonate', function () {
    $superAdmin = User::factory()->create();
    asSuperAdmin($superAdmin);

    $user = User::factory()->create();

    $component = livewire(ListUsers::class);

    $component
        ->assertSuccessful()
        ->assertCountTableRecords(2)
        ->callTableAction(Impersonate::class, $user);

    expect($user->isImpersonated())->toBeTrue()
        ->and(auth()->id())->toBe($user->id);
});

it('allows a user to leave impersonate', function () {
    $first = User::factory()->create();
    asSuperAdmin($first);

    $second = User::factory()->create();

    app(ImpersonateManager::class)->take($first, $second);

    expect($second->isImpersonated())->toBeTrue()
        ->and(auth()->id())->toBe($second->id);

    $second->leaveImpersonation();

    expect($second->isImpersonated())->toBeFalse()
        ->and(auth()->id())->toBe($first->id);
});

it('does not allow a user without permission to assign licenses in bulk', function () {
    $user = User::factory()->create();
    $user->givePermissionTo([
        'user.view-any',
        'user.create',
        'user.*.update',
        'user.*.view',
        'user.*.delete',
        'user.*.restore',
        'user.*.force-delete',
    ]);
    actingAs($user);

    $records = User::factory(2)->create()->prepend($user);

    livewire(ListUsers::class)
        ->assertSuccessful()
        ->assertCountTableRecords($records->count())
        ->assertTableBulkActionHidden(AssignLicensesBulkAction::class);
});

it('allows a user with permission to assign licenses in bulk', function () {
    $user = User::factory()->create();
    $user->givePermissionTo([
        'user.view-any',
        'user.create',
        'user.*.update',
        'user.*.view',
        'user.*.delete',
        'user.*.restore',
        'user.*.force-delete',
        'license.view-any',
        'license.create',
        'license.*.update',
        'license.*.view',
        'license.*.delete',
        'license.*.restore',
        'license.*.force-delete',
    ]);
    actingAs($user);

    $records = User::factory(2)->create()->prepend($user);

    $licenseTypes = collect(LicenseType::cases());

    $records->each(function (User $record) use ($licenseTypes) {
        $licenseTypes->each(fn ($license) => assertFalse($record->hasLicense($license)));
    });

    livewire(ListUsers::class)
        ->assertSuccessful()
        ->assertCountTableRecords($records->count())
        ->callTableBulkAction(AssignLicensesBulkAction::class, $records, [
            'replace' => true,
            ...$licenseTypes->mapWithKeys(fn (LicenseType $licenseType) => [$licenseType->value => true]),
        ])
        ->assertHasNoTableBulkActionErrors()
        ->assertNotified('Assigned Licenses');

    $records->each(function (User $record) use ($licenseTypes) {
        $record->refresh();
        $licenseTypes->each(fn (LicenseType $licenseType) => assertTrue($record->hasLicense($licenseType)));
    });
});

it('can filter users by multiple teams', function () {
    asSuperAdmin();

    $adminTeam = Team::factory()->create();

    $adminTeamGroup = User::factory()
        ->count(3)
        ->create();

    $adminTeamGroup->each(function ($user) use ($adminTeam) {
        $user->team()->associate($adminTeam)->save();
    });

    $modTeam = Team::factory()->create();

    $modsTeamGroup = User::factory()
        ->count(3)
        ->create();

    $modsTeamGroup->each(function ($user) use ($modTeam) {
        $user->team()->associate($modTeam)->save();
    });

    $supportTeam = Team::factory()->create();

    $supportTeamGroup = User::factory()
        ->count(3)
        ->create();

    $supportTeamGroup->each(function ($user) use ($supportTeam) {
        $user->team()->associate($supportTeam)->save();
    });

    livewire(ListUsers::class)
        ->set('tableRecordsPerPage', 10)
        ->assertCanSeeTableRecords($adminTeamGroup->merge($modsTeamGroup)->merge($supportTeamGroup))
        ->filterTable('team', [$adminTeam->id, $modTeam->id])
        ->assertCanSeeTableRecords(
            $adminTeamGroup
        )
        ->assertCanNotSeeTableRecords($supportTeamGroup);
});

it('it filters users based on team', function () {
    asSuperAdmin();

    $teamA = Team::factory()->create(['name' => 'Team A']);
    $teamB = Team::factory()->create(['name' => 'Team B']);

    $userInTeamA = User::factory()
        ->count(3)
        ->create();

    $userInTeamA->each(function ($user) use ($teamA) {
        $user->team()->associate($teamA)->save();
    });

    $userInTeamB = User::factory()
        ->count(3)
        ->create();

    $userInTeamB->each(function ($user) use ($teamB) {
        $user->team()->associate($teamB)->save();
    });

    $unassignedUser = User::factory()->count(2)->create();

    livewire(ListUsers::class)
        ->set('tableRecordsPerPage', 10)
        ->assertCanSeeTableRecords($unassignedUser->merge($userInTeamA)->merge($userInTeamB))
        ->filterTable('team', [$teamA->getKey()])
        ->assertCanSeeTableRecords(
            $userInTeamA
        )
        ->assertCanNotSeeTableRecords(
            $unassignedUser->merge($userInTeamB)
        )
        ->filterTable('team', [$teamB->getKey()])
        ->assertCanSeeTableRecords(
            $userInTeamB
        )
        ->assertCanNotSeeTableRecords(
            $unassignedUser->merge($userInTeamA)
        )
        ->filterTable('team', ['unassigned'])
        ->assertCanSeeTableRecords(
            $unassignedUser
        )
        ->assertCanNotSeeTableRecords(
            $userInTeamA->merge($userInTeamB)
        );
});

it('filters users based on roles', function () {
    asSuperAdmin();

    $roleA = Role::factory()->create(['name' => 'Role A']);
    $roleB = Role::factory()->create(['name' => 'Role B']);
    $roleC = Role::factory()->create(['name' => 'Role C']);

    $usersInRoleA = User::factory()
        ->count(3)
        ->create()
        ->each(function ($user) use ($roleA) {
            $user->assignRole($roleA);
        });

    $usersInRoleB = User::factory()
        ->count(3)
        ->create()
        ->each(function ($user) use ($roleB) {
            $user->assignRole($roleB);
        });

    $usersInRoleC = User::factory()
        ->count(3)
        ->create()
        ->each(function ($user) use ($roleC) {
            $user->assignRole($roleC);
        });

    $noRolesUsers = User::factory()->count(2)->create();

    livewire(ListUsers::class)
        ->set('tableRecordsPerPage', 10)
        ->filterTable('roles', [$roleA->getKey()])
        ->assertCanSeeTableRecords(
            $usersInRoleA
        )
        ->assertCanNotSeeTableRecords(
            $noRolesUsers->merge($usersInRoleB)->merge($usersInRoleC)
        )
        ->filterTable('roles', [$roleB->getKey()])
        ->assertCanSeeTableRecords(
            $usersInRoleB
        )
        ->assertCanNotSeeTableRecords(
            $noRolesUsers->merge($usersInRoleA)->merge($usersInRoleC)
        )
        ->filterTable('roles', [$roleB->getKey(), $roleC->getKey()])
        ->assertCanSeeTableRecords(
            $usersInRoleB->merge($usersInRoleC)
        )
        ->assertCanNotSeeTableRecords(
            $noRolesUsers->merge($usersInRoleA)
        )
        ->filterTable('roles', ['none'])
        ->assertCanSeeTableRecords(
            $noRolesUsers
        )
        ->assertCanNotSeeTableRecords(
            $usersInRoleA->merge($usersInRoleB)->merge($usersInRoleC)
        );
});

it('Filter users based on licenses', function () {
    asSuperAdmin();

    $usersWithRetentionCrmLicense = User::factory()
        ->count(3)
        ->create()
        ->each(function ($user) {
            $user->grantLicense(LicenseType::RetentionCrm);
        });

    $usersWithRecruitmentCrmLicense = User::factory()
        ->count(3)
        ->create()
        ->each(function ($user) {
            $user->grantLicense(LicenseType::RecruitmentCrm);
        });

    $usersWithConversationalAiLicense = User::factory()
        ->count(3)
        ->create()
        ->each(function ($user) {
            $user->grantLicense(LicenseType::ConversationalAi);
        });
    $usersWithoutLicense = User::factory()
        ->count(3)
        ->create();

    livewire(ListUsers::class)
        ->filterTable('licenses', [LicenseType::RetentionCrm->value])
        ->assertCanSeeTableRecords($usersWithRetentionCrmLicense)
        ->assertCanNotSeeTableRecords($usersWithRecruitmentCrmLicense->merge($usersWithConversationalAiLicense)->merge($usersWithoutLicense))
        ->filterTable('licenses', [LicenseType::RecruitmentCrm->value])
        ->assertCanSeeTableRecords($usersWithRecruitmentCrmLicense)
        ->assertCanNotSeeTableRecords($usersWithRetentionCrmLicense->merge($usersWithConversationalAiLicense)->merge($usersWithoutLicense))
        ->filterTable('licenses', [LicenseType::ConversationalAi->value])
        ->assertCanSeeTableRecords($usersWithConversationalAiLicense)
        ->assertCanNotSeeTableRecords($usersWithRetentionCrmLicense->merge($usersWithRecruitmentCrmLicense)->merge($usersWithoutLicense))
        ->filterTable('licenses', ['no_assigned_license'])
        ->assertCanSeeTableRecords($usersWithoutLicense)
        ->assertCanNotSeeTableRecords($usersWithRetentionCrmLicense->merge($usersWithRecruitmentCrmLicense)->merge($usersWithConversationalAiLicense));
});

it('Filter users based on Created After', function () {
    asSuperAdmin();

    $createdAfterUsers = User::factory()
        ->count(5)
        ->sequence(
            ['created_at' => '2024-11-20 23:00:00'],
            ['created_at' => '2024-11-22 03:00:00'],
            ['created_at' => '2024-11-23 04:00:00'],
            ['created_at' => '2024-11-24 05:30:00'],
            ['created_at' => '2024-11-25 06:00:00'],
        )
        ->create();

    $createdBeforeUsers = User::factory()
        ->count(4)
        ->sequence(
            ['created_at' => '2024-11-20 22:00:00'],
            ['created_at' => '2024-11-18 03:00:00'],
            ['created_at' => '2024-11-17 04:00:00'],
            ['created_at' => '2024-11-16 05:30:00'],
        )
        ->create();

    livewire(ListUsers::class)
        ->set('tableRecordsPerPage', 10)
        ->assertCanSeeTableRecords($createdAfterUsers->merge($createdBeforeUsers))
        ->filterTable('created_after', ['created_at' => '11/20/2024 23:00:00'])
        ->assertCanSeeTableRecords($createdAfterUsers)
        ->assertCanNotSeeTableRecords($createdBeforeUsers);
});
