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

use AdvisingApp\Ai\Settings\AiResearchAssistantSettings;
use AdvisingApp\Research\Models\ResearchRequest;
use AdvisingApp\Research\Models\ResearchRequestQuestion;
use App\Models\User;
use Exception;

class GenerateResearchQuestion
{
    public function execute(ResearchRequest $researchRequest): string
    {
        $settings = app(AiResearchAssistantSettings::class);

        throw_if(
            ! $settings->discovery_model,
            new Exception('Discovery model is not set in the settings.')
        );

        return $settings->discovery_model
            ->getService()
            ->complete(
                prompt: $this->getPrompt($researchRequest),
                content: $this->getContent($researchRequest),
            );
    }

    protected function getContent(ResearchRequest $researchRequest): string
    {
        $questions = $researchRequest->questions
            ->map(fn (ResearchRequestQuestion $question, int $index) => '**Question ' . ($index + 1) . ":** {$question->content}" . PHP_EOL . '**Answer ' . ($index + 1) . ":** {$question->response}")
            ->implode(PHP_EOL . PHP_EOL);

        if (filled($questions)) {
            $questions = <<<EOD

            
                **Clarification Q & A:**

                {$questions}
                
                ---
                EOD;
        }

        return <<<EOD
            **Research topic:**

            {$researchRequest->topic}

            ---{$questions}

            **Instructions:**
            
            Before the research is conducted, you need to help improve the chances that it will be relevant and useful to me. Using my research topic, respond with one relevant question that will help to clarify it. As you form your next question, to gain clarification on how to research this topic effectively, ensure that the question asked is materially different from any previously asked clarification questions. You will be able to ask two questions in total, and you will know the answer to the previous question/s before you ask the next one. Do not respond with any greetings or salutations, and do not include any additional information or context. Just respond with your question:
            EOD;
    }

    protected function getPrompt(ResearchRequest $researchRequest): string
    {
        /** @var User $user */
        $user = auth()->user();

        $userName = $user->name;
        $userJobTitle = filled($user->job_title) ? "with the job title **{$user->job_title}**" : '';

        $institutionalContext = app(AiResearchAssistantSettings::class)->context;

        return <<<EOD
            ## Requestor Information
            
            This request is submitted by **{$userName}** who is an institutional staff member {$userJobTitle}.

            ---

            ## Institutional Context

            {$institutionalContext}
            EOD;
    }
}
