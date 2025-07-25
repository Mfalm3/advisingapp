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

use AdvisingApp\StudentDataModel\Filament\Resources\StudentResource\Pages\ViewStudent;
use AdvisingApp\StudentDataModel\Filament\Resources\StudentResource\RelationManagers\EnrollmentsRelationManager;
use AdvisingApp\StudentDataModel\Models\Enrollment;
use AdvisingApp\StudentDataModel\Models\Student;
use AdvisingApp\StudentDataModel\Settings\ManageStudentConfigurationSettings;
use App\Models\User;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ImportAction;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

it('renders the Import Enrollments Action based on proper access', function () {
    $user = User::factory()->licensed(Student::getLicenseType())->create();

    $student = Student::factory()
        ->has(Enrollment::factory()->count(1))
        ->create();

    $user->givePermissionTo('student.view-any');
    $user->givePermissionTo('student.*.view');
    $user->givePermissionTo('enrollment.view-any');
    $user->givePermissionTo('enrollment.import');

    actingAs($user);

    livewire(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => ViewStudent::class,
    ])
        ->assertOk()
        ->assertTableActionHidden(ImportAction::class);

    $studentSettings = app(ManageStudentConfigurationSettings::class);
    $studentSettings->is_enabled = true;
    $studentSettings->save();

    $user->revokePermissionTo('enrollment.import');

    livewire(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => ViewStudent::class,
    ])
        ->assertOk()
        ->assertTableActionHidden(ImportAction::class);

    $user->givePermissionTo('enrollment.import');

    livewire(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => ViewStudent::class,
    ])
        ->assertOk()
        ->assertTableActionVisible(ImportAction::class);
});

it('renders the Create Enrollment Action based on proper access', function () {
    $user = User::factory()->licensed(Student::getLicenseType())->create();

    $student = Student::factory()
        ->has(Enrollment::factory()->count(1))
        ->create();

    $user->givePermissionTo('student.view-any');
    $user->givePermissionTo('student.*.view');
    $user->givePermissionTo('enrollment.view-any');
    $user->givePermissionTo('enrollment.create');

    actingAs($user);

    livewire(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => ViewStudent::class,
    ])
        ->assertOk()
        ->assertTableActionHidden(CreateAction::class);

    $studentSettings = app(ManageStudentConfigurationSettings::class);
    $studentSettings->is_enabled = true;
    $studentSettings->save();

    $user->revokePermissionTo('enrollment.create');

    livewire(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => ViewStudent::class,
    ])
        ->assertOk()
        ->assertTableActionHidden(CreateAction::class);

    $user->givePermissionTo('enrollment.create');

    livewire(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => ViewStudent::class,
    ])
        ->assertOk()
        ->assertTableActionVisible(CreateAction::class);
});

it('renders the Edit Enrollment Table Action based on proper access', function () {
    $user = User::factory()->licensed(Student::getLicenseType())->create();

    $student = Student::factory()
        ->has(Enrollment::factory()->count(1))
        ->create();

    $user->givePermissionTo('student.view-any');
    $user->givePermissionTo('student.*.view');
    $user->givePermissionTo('enrollment.view-any');
    $user->givePermissionTo('enrollment.*.update');

    actingAs($user);

    livewire(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => ViewStudent::class,
    ])
        ->assertOk()
        ->assertTableActionHidden(EditAction::class, $student->enrollments->first());

    $studentSettings = app(ManageStudentConfigurationSettings::class);
    $studentSettings->is_enabled = true;
    $studentSettings->save();

    $user->revokePermissionTo('enrollment.*.update');

    livewire(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => ViewStudent::class,
    ])
        ->assertOk()
        ->assertTableActionHidden(EditAction::class, $student->enrollments->first());

    $user->givePermissionTo('enrollment.*.update');

    livewire(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => ViewStudent::class,
    ])
        ->assertOk()
        ->assertTableActionVisible(EditAction::class, $student->enrollments->first());
});

it('renders the Delete Enrollment Table Action based on proper access', function () {
    $user = User::factory()->licensed(Student::getLicenseType())->create();

    $student = Student::factory()
        ->has(Enrollment::factory()->count(1))
        ->create();

    $user->givePermissionTo('student.view-any');
    $user->givePermissionTo('student.*.view');
    $user->givePermissionTo('enrollment.view-any');
    $user->givePermissionTo('enrollment.*.delete');

    actingAs($user);

    livewire(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => ViewStudent::class,
    ])
        ->assertOk()
        ->assertTableActionHidden(DeleteAction::class, $student->enrollments->first());

    $studentSettings = app(ManageStudentConfigurationSettings::class);
    $studentSettings->is_enabled = true;
    $studentSettings->save();

    $user->revokePermissionTo('enrollment.*.delete');

    livewire(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => ViewStudent::class,
    ])
        ->assertOk()
        ->assertTableActionHidden(DeleteAction::class, $student->enrollments->first());

    $user->givePermissionTo('enrollment.*.delete');

    livewire(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => ViewStudent::class,
    ])
        ->assertOk()
        ->assertTableActionVisible(DeleteAction::class, $student->enrollments->first());
});

it('renders the Delete Bulk Enrollments Table Action based on proper access', function () {
    $user = User::factory()->licensed(Student::getLicenseType())->create();

    $student = Student::factory()
        ->has(Enrollment::factory()->count(1))
        ->create();

    $user->givePermissionTo('student.view-any');
    $user->givePermissionTo('student.*.view');
    $user->givePermissionTo('enrollment.view-any');
    $user->givePermissionTo('enrollment.*.delete');

    actingAs($user);

    livewire(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => ViewStudent::class,
    ])
        ->assertOk()
        ->assertTableBulkActionHidden(DeleteBulkAction::class, $student->enrollments->first());

    $studentSettings = app(ManageStudentConfigurationSettings::class);
    $studentSettings->is_enabled = true;
    $studentSettings->save();

    $user->revokePermissionTo('enrollment.*.delete');

    livewire(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => ViewStudent::class,
    ])
        ->assertOk()
        ->assertTableBulkActionHidden(DeleteBulkAction::class, $student->enrollments->first());

    $user->givePermissionTo('enrollment.*.delete');

    livewire(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => ViewStudent::class,
    ])
        ->assertOk()
        ->assertTableBulkActionVisible(DeleteBulkAction::class, $student->enrollments->first());
});

it('Can filter enrollments by semester', function () {
    $user = User::factory()->licensed(Student::getLicenseType())->create();

    $enrollmentOne = Enrollment::factory()->state([
        'semester_name' => 'Fall 2023',
    ]);
    $enrollmentTwo = Enrollment::factory()->state([
        'semester_name' => 'Spring 2024',
    ]);
    $enrollmentThree = Enrollment::factory()->state([
        'semester_name' => 'Fall 2024',
    ]);

    $student = Student::factory()
        ->has($enrollmentOne, 'enrollments')
        ->has($enrollmentTwo, 'enrollments')
        ->has($enrollmentThree, 'enrollments')
        ->create();

    $user->givePermissionTo('student.view-any');
    $user->givePermissionTo('student.*.view');
    $user->givePermissionTo('enrollment.view-any');

    actingAs($user);

    livewire(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => ViewStudent::class,
    ])
        ->assertCanSeeTableRecords($student->enrollments)
        ->filterTable(
            'semester_name',
            ['Fall 2023']
        )
        ->assertCanSeeTableRecords($student->enrollments->where('semester_name', 'Fall 2023'))
        ->assertCanNotSeeTableRecords($student->enrollments->whereIn('semester_name', ['Spring 2024', 'Fall 2024']))
        ->filterTable(
            'semester_name',
            ['Spring 2024']
        )
        ->assertCanSeeTableRecords($student->enrollments->where('semester_name', 'Spring 2024'))
        ->assertCanNotSeeTableRecords($student->enrollments->whereIn('semester_name', ['Fall 2023', 'Fall 2024']))
        ->filterTable(
            'semester_name',
            ['Fall 2024']
        )
        ->assertCanSeeTableRecords($student->enrollments->where('semester_name', 'Fall 2024'))
        ->assertCanNotSeeTableRecords($student->enrollments->whereIn('semester_name', ['Fall 2023', 'Spring 2024']))
        ->filterTable(
            'semester_name',
            ['Fall 2023', 'Spring 2024']
        )
        ->assertCanSeeTableRecords($student->enrollments->whereIn('semester_name', ['Fall 2023', 'Spring 2024']))
        ->assertCanNotSeeTableRecords($student->enrollments->where('semester_name', 'Fall 2024'))
        ->filterTable(
            'semester_name',
            ['Fall 2023', 'Fall 2024']
        )->assertCanSeeTableRecords($student->enrollments->whereIn('semester_name', ['Fall 2023', 'Fall 2024']))
        ->assertCanNotSeeTableRecords($student->enrollments->where('semester_name', 'Spring 2024'))
        ->filterTable(
            'semester_name',
            ['Spring 2024', 'Fall 2024']
        )->assertCanSeeTableRecords($student->enrollments->whereIn('semester_name', ['Spring 2024', 'Fall 2024']))
        ->assertCanNotSeeTableRecords($student->enrollments->where('semester_name', 'Fall 2023'))
        ->filterTable(
            'semester_name',
            ['Fall 2023', 'Spring 2024', 'Fall 2024']
        )->assertCanSeeTableRecords($student->enrollments)
        ->filterTable('semester_name', [])->assertCanSeeTableRecords($student->enrollments);
});
