<?php

use AdvisingApp\Ai\Events\AiMessageFileDeleted;
use AdvisingApp\Ai\Models\AiMessageFile;
use Illuminate\Support\Facades\Event;

it('dispatches the AiMessageFileDeleted event when an AiMessageFile is deleted', function () {
    $aiMessageFile = AiMessageFile::factory()->create();

    Event::fake();

    $aiMessageFile->delete();

    Event::assertDispatched(AiMessageFileDeleted::class, function ($event) use ($aiMessageFile) {
        return $event->aiMessageFile->is($aiMessageFile);
    });
});