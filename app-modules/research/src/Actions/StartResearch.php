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

namespace AdvisingApp\Research\Actions;

use AdvisingApp\Research\Jobs\AfterResearchRequestSearchQueriesParsed;
use AdvisingApp\Research\Jobs\AwaitResearchRequestReady;
use AdvisingApp\Research\Jobs\FetchResearchRequestLinkParsingResults;
use AdvisingApp\Research\Jobs\GenerateResearchRequestOutline;
use AdvisingApp\Research\Jobs\GenerateResearchRequestSearchQueries;
use AdvisingApp\Research\Jobs\UploadResearchRequestFileForParsing;
use AdvisingApp\Research\Models\ResearchRequest;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class StartResearch
{
    public function execute(ResearchRequest $researchRequest): void
    {
        DB::transaction(function () use ($researchRequest) {
            $researchRequest->touch('started_at');

            if ($researchRequest->getMedia('files')->isEmpty() && empty($researchRequest->links)) {
                Bus::chain([
                    new AwaitResearchRequestReady($researchRequest),
                    Bus::batch([
                        new GenerateResearchRequestSearchQueries($researchRequest),
                    ])
                        ->then(function () use ($researchRequest) {
                            Bus::chain([
                                new AfterResearchRequestSearchQueriesParsed($researchRequest),
                                new AwaitResearchRequestReady($researchRequest),
                                Bus::batch([
                                    new GenerateResearchRequestOutline($researchRequest),
                                ]),
                            ])->dispatch();
                        }),
                ])->dispatch();

                return;
            }

            Bus::batch([
                ...$researchRequest->getMedia('files')->map(function (Media $media): UploadResearchRequestFileForParsing {
                    return new UploadResearchRequestFileForParsing($media);
                })->all(),
                ...array_map(
                    function (string $link) use ($researchRequest): FetchResearchRequestLinkParsingResults {
                        return new FetchResearchRequestLinkParsingResults($researchRequest, $link);
                    },
                    $researchRequest->links,
                ),
            ])
                ->then(function () use ($researchRequest) {
                    Bus::chain([
                        new AwaitResearchRequestReady($researchRequest),
                        Bus::batch([
                            new GenerateResearchRequestSearchQueries($researchRequest),
                        ])
                            ->then(function () use ($researchRequest) {
                                Bus::chain([
                                    new AfterResearchRequestSearchQueriesParsed($researchRequest),
                                    new AwaitResearchRequestReady($researchRequest),
                                    Bus::batch([
                                        new GenerateResearchRequestOutline($researchRequest),
                                    ]),
                                ])->dispatch();
                            }),
                    ])->dispatch();
                })
                ->dispatch();
        });
    }
}
