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

namespace Assist\IntegrationAI\Providers;

use Filament\Panel;
use Illuminate\Support\ServiceProvider;
use Assist\IntegrationAI\Client\AzureOpenAI;
use Assist\IntegrationAI\IntegrationAIPlugin;
use Assist\Authorization\AuthorizationRoleRegistry;
use Assist\IntegrationAI\Client\Contracts\AIChatClient;
use Assist\Authorization\AuthorizationPermissionRegistry;
use Assist\IntegrationAI\Client\Playground\AzureOpenAI as PlaygroundAzureOpenAI;

class IntegrationAIServiceProvider extends ServiceProvider
{
    public function register()
    {
        Panel::configureUsing(fn (Panel $panel) => $panel->plugin(new IntegrationAIPlugin()));

        $this->app->singleton(AIChatClient::class, function () {
            if ($this->app->runningUnitTests() || config('services.azure_open_ai.enable_test_mode') === true) {
                return new PlaygroundAzureOpenAI();
            }

            return new AzureOpenAI();
        });
    }

    public function boot()
    {
        $this->registerRolesAndPermissions();
    }

    protected function registerRolesAndPermissions()
    {
        $permissionRegistry = app(AuthorizationPermissionRegistry::class);

        $permissionRegistry->registerApiPermissions(
            module: 'integration-ai',
            path: 'permissions/api/custom'
        );

        $permissionRegistry->registerWebPermissions(
            module: 'integration-ai',
            path: 'permissions/web/custom'
        );

        $roleRegistry = app(AuthorizationRoleRegistry::class);

        $roleRegistry->registerApiRoles(
            module: 'integration-ai',
            path: 'roles/api'
        );

        $roleRegistry->registerWebRoles(
            module: 'integration-ai',
            path: 'roles/web'
        );
    }
}
