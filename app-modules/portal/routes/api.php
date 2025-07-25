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

use AdvisingApp\Portal\Http\Controllers\ResourceHub\ResourceHubPortalArticleController;
use AdvisingApp\Portal\Http\Controllers\ResourceHub\ResourceHubPortalAuthenticateController;
use AdvisingApp\Portal\Http\Controllers\ResourceHub\ResourceHubPortalCategoryController;
use AdvisingApp\Portal\Http\Controllers\ResourceHub\ResourceHubPortalController;
use AdvisingApp\Portal\Http\Controllers\ResourceHub\ResourceHubPortalLogoutController;
use AdvisingApp\Portal\Http\Controllers\ResourceHub\ResourceHubPortalRequestAuthenticationController;
use AdvisingApp\Portal\Http\Controllers\ResourceHub\ResourceHubPortalSearchController;
use AdvisingApp\Portal\Http\Middleware\AuthenticateIfRequiredByPortalDefinition;
use AdvisingApp\Portal\Http\Middleware\EnsureResourceHubPortalIsEmbeddableAndAuthorized;
use AdvisingApp\Portal\Http\Middleware\EnsureResourceHubPortalIsEnabled;
use App\Multitenancy\Http\Middleware\NeedsTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::prefix('api')
    ->name('api.')
    ->middleware([
        EnsureFrontendRequestsAreStateful::class,
        'api',
        NeedsTenant::class,
        EnsureResourceHubPortalIsEnabled::class,
        EnsureResourceHubPortalIsEmbeddableAndAuthorized::class,
    ])
    ->group(function () {
        Route::middleware(['auth:sanctum', 'abilities:resource-hub-portal'])
            ->group(function () {
                Route::get('/user', function (Request $request) {
                    $user = $request->user('student') ?? $request->user('prospect');

                    if (! $user || ! $user->tokenCan('resource-hub-portal')) {
                        return response()->json(['message' => 'Unauthenticated.'], 401);
                    }

                    return $user;
                })->name('user.auth-check');
            });

        Route::prefix('portal/resource-hub')
            ->name('portal.resource-hub.')
            ->group(function () {
                Route::get('/', [ResourceHubPortalController::class, 'show'])
                    ->middleware(['signed:relative'])
                    ->name('define');

                Route::middleware([AuthenticateIfRequiredByPortalDefinition::class])
                    ->group(function () {
                        Route::post('/authenticate/logout', ResourceHubPortalLogoutController::class)
                            ->name('logout');

                        Route::post('/search', [ResourceHubPortalSearchController::class, 'get'])
                            ->middleware(['signed:relative'])
                            ->name('search');

                        Route::get('/categories', [ResourceHubPortalCategoryController::class, 'index'])
                            ->name('category.index');

                        Route::get('/categories/{category}', [ResourceHubPortalCategoryController::class, 'show'])
                            ->name('category.show');

                        Route::get('/categories/{category}/articles/{article}', [ResourceHubPortalArticleController::class, 'show'])
                            ->name('article.show');
                    });

                Route::post('/authenticate/request', ResourceHubPortalRequestAuthenticationController::class)
                    ->middleware(['signed:relative'])
                    ->name('request-authentication');

                Route::post('/authenticate/{authentication}', ResourceHubPortalAuthenticateController::class)
                    ->middleware(['signed:relative'])
                    ->name('authenticate.embedded');
            });
    });
