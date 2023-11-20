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

namespace App\Providers;

use Closure;
use Illuminate\Support\Arr;
use PHPUnit\Framework\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Testing\Concerns\TestDatabases;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * @class MultiConnectionParallelTestingServiceProvider
 *
 * Based on https://sarahjting.com/blog/laravel-paratest-multiple-db-connections
 */
class MultiConnectionParallelTestingServiceProvider extends ServiceProvider
{
    use TestDatabases;

    protected array $parallelConnections = ['sis'];

    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('testing')) {
            ParallelTesting::setUpProcess(function (int $token) {
                $this->whenNotUsingInMemoryDatabase(function ($database) {
                    if (ParallelTesting::option('recreate_databases')) {
                        foreach ($this->parallelConnections as $connection) {
                            Schema::connection($connection)
                                ->dropDatabaseIfExists($this->databaseOnConnection($connection));
                        }
                    }
                });
            });

            ParallelTesting::setUpTestCase(function (int $token, TestCase $testCase) {
                $uses = array_flip(class_uses_recursive(get_class($testCase)));

                $databaseTraits = [
                    DatabaseMigrations::class,
                    DatabaseTransactions::class,
                    DatabaseTruncation::class,
                    RefreshDatabase::class,
                ];

                if (Arr::hasAny($uses, $databaseTraits) && ! ParallelTesting::option('without_databases')) {
                    $this->whenNotUsingInMemoryDatabase(function ($database) use ($uses, $token) {
                        $allCreated = [];

                        foreach ($this->parallelConnections as $connection) {
                            $this->usingConnection($connection, function ($connection) use (&$allCreated) {
                                [$testDatabase, $created] = $this->ensureTestDatabaseExists($this->databaseOnConnection($connection));

                                $this->switchToDatabase($testDatabase);

                                if ($created) {
                                    $allCreated[] = [$connection, $testDatabase];
                                }
                            });
                        }

                        if (isset($uses[DatabaseTransactions::class])) {
                            $this->ensureSchemaIsUpToDate();
                        }

                        foreach ($allCreated as [$connection, $testDatabase]) {
                            $this->usingConnection($connection, function () use ($testDatabase) {
                                ParallelTesting::callSetUpTestDatabaseCallbacks($testDatabase);
                            });
                        }

                        Config::set('database.fdw.external_database', "testing_test_{$token}");
                    });
                }
            });

            ParallelTesting::tearDownProcess(function () {
                $this->whenNotUsingInMemoryDatabase(function ($database) {
                    if (ParallelTesting::option('drop_databases')) {
                        foreach ($this->parallelConnections as $connection) {
                            Schema::connection($connection)
                                ->dropDatabaseIfExists($this->databaseOnConnection($connection));
                        }
                    }
                });
            });
        }
    }

    protected function usingConnection(string $connection, Closure $callable): void
    {
        $originalConnection = config('database.default');

        try {
            config()->set('database.default', $connection);
            $callable($connection);
        } finally {
            config()->set('database.default', $originalConnection);
        }
    }

    protected function databaseOnConnection(string $connection): string
    {
        return config("database.connections.{$connection}.database");
    }
}
