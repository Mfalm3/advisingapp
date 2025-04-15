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

use AdvisingApp\CareTeam\Filament\Resources\StudentCareTeamRoleResource;
use AdvisingApp\CareTeam\Filament\Resources\StudentCareTeamRoleResource\Pages\CreateStudentCareTeamRole;
use AdvisingApp\CareTeam\Models\CareTeamRole;
use AdvisingApp\CareTeam\Tests\Tenant\RequestFactories\CreateCareTeamRoleRequestFactory;
use AdvisingApp\StudentDataModel\Models\Student;
use App\Enums\CareTeamRoleType;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;
use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertFalse;
use function Tests\asSuperAdmin;



test('CreateStudentCareTeamRole is gated with proper access control', function () {
    $user = User::factory()->licensed(Student::getLicenseType())->create();

    actingAs($user)
        ->get(
            StudentCareTeamRoleResource::getUrl('create')
        )->assertForbidden();

    livewire(CreateStudentCareTeamRole::class)
         ->assertForbidden();

    $user->givePermissionTo('product_admin.view-any');
    $user->givePermissionTo('product_admin.create');

    actingAs($user)
        ->get(
            StudentCareTeamRoleResource::getUrl('create')
        )->assertSuccessful();

    livewire(CreateStudentCareTeamRole::class)
        ->assertSuccessful();
});

test('A successful action on the CreateStudentCareTeamRole page', function () {
    asSuperAdmin()
        ->get(
            StudentCareTeamRoleResource::getUrl('create')
        )
        ->assertSuccessful();

    $createRequest = CreateCareTeamRoleRequestFactory::new()->state(['type' => CareTeamRoleType::Student])->create();

    livewire(CreateStudentCareTeamRole::class)
          ->set('data', $createRequest)
          ->call('create')
          ->assertHasNoFormErrors();

    assertCount(1, CareTeamRole::all());

    assertDatabaseHas(CareTeamRole::class, $createRequest);
});

test('CreateStudentCareTeamRole requires valid data', function ($data, $errors) {
    asSuperAdmin();

    $createRequest = CreateCareTeamRoleRequestFactory::new($data)->create();

    livewire(CreateStudentCareTeamRole::class)
        ->set('data', $createRequest)
        ->call('create')
        ->assertHasFormErrors($errors);

        assertCount(0, CareTeamRole::all());
})->with(
    [
        'name missing' => [CreateCareTeamRoleRequestFactory::new()->state(['type' => CareTeamRoleType::Student])->without('name'), ['name' => 'required']],
        'name not a string' => [CreateCareTeamRoleRequestFactory::new()->state(['name' => 1, 'type' => CareTeamRoleType::Student]), ['name' => 'string']],
        'is_default not a boolean' => [CreateCareTeamRoleRequestFactory::new()->state(['is_default' => 'a', 'type' => CareTeamRoleType::Student]), ['is_default' => 'boolean']],
    ]
);

test('Creating a default care team role will make all other care team roles not the default', function() {
    asSuperAdmin();

    CareTeamRole::factory()->create(['is_default' => true, 'type' => CareTeamRoleType::Student]);
    CareTeamRole::factory()->count(3)->create(['type' => CareTeamRoleType::Student]);

    $careTeamRoles = CareTeamRole::all();

    $createRequest = CreateCareTeamRoleRequestFactory::new()->state(['is_default' => true, 'type' => CareTeamRoleType::Student])->create();

    livewire(CreateStudentCareTeamRole::class)
          ->set('data', $createRequest)
          ->call('create')
          ->assertHasNoFormErrors();
    
    $careTeamRoles->each(fn($careTeamRole) => assertFalse($careTeamRole->fresh()->is_default === true));
});

