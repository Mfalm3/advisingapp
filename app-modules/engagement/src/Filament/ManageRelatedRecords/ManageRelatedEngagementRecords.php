<?php

/*
<COPYRIGHT>

    Copyright © 2016-2024, Canyon GBS LLC. All rights reserved.

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

namespace AdvisingApp\Engagement\Filament\ManageRelatedRecords;

use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Infolists\Infolist;
use App\Settings\LicenseSettings;
use Illuminate\Support\HtmlString;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Vite;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use AdvisingApp\Ai\Models\AiAssistant;
use Filament\Forms\Components\Actions;
use FilamentTiptapEditor\TiptapEditor;
use AdvisingApp\Ai\Settings\AiSettings;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use AdvisingApp\Timeline\Models\Timeline;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use FilamentTiptapEditor\Enums\TiptapOutput;
use AdvisingApp\Engagement\Models\Engagement;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use AdvisingApp\Authorization\Enums\LicenseType;
use AdvisingApp\Engagement\Models\EmailTemplate;
use Filament\Resources\Pages\ManageRelatedRecords;
use AdvisingApp\Engagement\Models\EngagementResponse;
use Filament\Resources\RelationManagers\RelationManager;
use AdvisingApp\Engagement\Enums\EngagementDeliveryMethod;
use AdvisingApp\Engagement\Enums\EngagementDeliveryStatus;
use Filament\Forms\Components\Actions\Action as FormAction;
use AdvisingApp\Engagement\Models\Contracts\HasDeliveryMethod;
use AdvisingApp\Engagement\Actions\CreateEngagementDeliverable;
use Filament\Infolists\Components\Fieldset as InfolistFieldset;
use AdvisingApp\Engagement\Filament\Resources\EngagementResource\Fields\EngagementSmsBodyField;

class ManageRelatedEngagementRecords extends ManageRelatedRecords
{
    // TODO: Obsolete when there is no table, remove from Filament
    protected static string $relationship = 'timeline';

    protected static ?string $navigationLabel = 'Email and Texts';

    protected static ?string $breadcrumb = 'Email and Texts';

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema(fn (Timeline $record) => match ($record->timelineable::class) {
            Engagement::class => [
                TextEntry::make('user.name')
                    ->label('Created By')
                    ->getStateUsing(fn (Timeline $record): string => $record->timelineable->user->name),
                InfolistFieldset::make('Content')
                    ->schema([
                        TextEntry::make('subject')
                            ->getStateUsing(fn (Timeline $record): ?string => $record->timelineable->subject)
                            ->hidden(fn ($state): bool => blank($state))
                            ->columnSpanFull(),
                        TextEntry::make('body')
                            ->getStateUsing(fn (Timeline $record): HtmlString => $record->timelineable->getBody())
                            ->columnSpanFull(),
                    ]),
                InfolistFieldset::make('deliverable')
                    ->label('Delivery Information')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('deliverable.channel')
                            ->label('Channel')
                            ->getStateUsing(function (Timeline $record): string {
                                /** @var HasDeliveryMethod $timelineable */
                                $timelineable = $record->timelineable;

                                return $timelineable->getDeliveryMethod()->getLabel();
                            }),
                        IconEntry::make('deliverable.delivery_status')
                            ->getStateUsing(fn (Timeline $record): EngagementDeliveryStatus => $record->timelineable->deliverable->delivery_status)
                            ->icon(fn (EngagementDeliveryStatus $state): string => $state->getIconClass())
                            ->color(fn (EngagementDeliveryStatus $state): string => $state->getColor())
                            ->label('Status'),
                        TextEntry::make('deliverable.delivered_at')
                            ->getStateUsing(fn (Timeline $record): string => $record->timelineable->deliverable->delivered_at)
                            ->label('Delivered At')
                            ->hidden(fn (Timeline $record): bool => is_null($record->timelineable->deliverable->delivered_at)),
                        TextEntry::make('deliverable.delivery_response')
                            ->getStateUsing(fn (Timeline $record): string => $record->timelineable->deliverable->delivery_response)
                            ->label('Error Details')
                            ->hidden(fn (Timeline $record): bool => is_null($record->timelineable->deliverable->delivery_response)),
                    ])
                    ->columns(),
            ],
            EngagementResponse::class => [
                TextEntry::make('content')
                    ->translateLabel(),
                TextEntry::make('sent_at')
                    ->dateTime('Y-m-d H:i:s'),
            ],
        });
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('delivery_method')
                ->label('What would you like to send?')
                ->options(EngagementDeliveryMethod::getOptions())
                ->default(EngagementDeliveryMethod::Email->value)
                ->disableOptionWhen(fn (string $value): bool => EngagementDeliveryMethod::tryFrom($value)?->getCaseDisabled())
                ->selectablePlaceholder(false)
                ->live(),
            Fieldset::make('Content')
                ->schema([
                    TextInput::make('subject')
                        ->autofocus()
                        ->required()
                        ->placeholder(__('Subject'))
                        ->hidden(fn (Get $get): bool => $get('delivery_method') === EngagementDeliveryMethod::Sms->value)
                        ->columnSpanFull(),
                    TiptapEditor::make('body')
                        ->disk('s3-public')
                        ->visibility('public')
                        ->directory('editor-images/engagements')
                        ->label('Body')
                        ->mergeTags($mergeTags = [
                            'student first name',
                            'student last name',
                            'student full name',
                            'student email',
                        ])
                        ->showMergeTagsInBlocksPanel(! ($form->getLivewire() instanceof RelationManager))
                        ->profile('email')
                        ->output(TiptapOutput::Json)
                        ->required()
                        ->hintAction(fn (TiptapEditor $component) => Action::make('loadEmailTemplate')
                            ->form([
                                Select::make('emailTemplate')
                                    ->searchable()
                                    ->options(function (Get $get): array {
                                        return EmailTemplate::query()
                                            ->when(
                                                $get('onlyMyTemplates'),
                                                fn (Builder $query) => $query->whereBelongsTo(auth()->user())
                                            )
                                            ->orderBy('name')
                                            ->limit(50)
                                            ->pluck('name', 'id')
                                            ->toArray();
                                    })
                                    ->getSearchResultsUsing(function (Get $get, string $search): array {
                                        return EmailTemplate::query()
                                            ->when(
                                                $get('onlyMyTemplates'),
                                                fn (Builder $query) => $query->whereBelongsTo(auth()->user())
                                            )
                                            ->when(
                                                $get('onlyMyTeamTemplates'),
                                                fn (Builder $query) => $query->whereIn('user_id', auth()->user()->teams->users->pluck('id'))
                                            )
                                            ->where(new Expression('lower(name)'), 'like', "%{$search}%")
                                            ->orderBy('name')
                                            ->limit(50)
                                            ->pluck('name', 'id')
                                            ->toArray();
                                    }),
                                Checkbox::make('onlyMyTemplates')
                                    ->label('Only show my templates')
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('emailTemplate', null)),
                                Checkbox::make('onlyMyTeamTemplates')
                                    ->label("Only show my team's templates")
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('emailTemplate', null)),
                            ])
                            ->action(function (array $data) use ($component) {
                                $template = EmailTemplate::find($data['emailTemplate']);

                                if (! $template) {
                                    return;
                                }

                                $component->state($template->content);
                            }))
                        ->hidden(fn (Get $get): bool => $get('delivery_method') === EngagementDeliveryMethod::Sms->value)
                        ->helperText('You can insert student information by typing {{ and choosing a merge value to insert.')
                        ->columnSpanFull(),
                    EngagementSmsBodyField::make(context: 'create', form: $form),
                    Actions::make([
                        FormAction::make('draftWithAi')
                            ->label('Draft with AI Assistant')
                            ->link()
                            ->icon('heroicon-m-pencil')
                            ->modalContent(fn () => view('engagement::filament.manage-related-records.manage-related-engagement-records.draft-with-ai-modal-content', [
                                'recordTitle' => $this->getRecordTitle(),
                                'avatarUrl' => AiAssistant::query()->where('is_default', true)->first()
                                    ?->getFirstTemporaryUrl(now()->addHour(), 'avatar', 'avatar-height-250px') ?: Vite::asset('resources/images/canyon-ai-headshot.jpg'),
                            ]))
                            ->modalWidth(MaxWidth::ExtraLarge)
                            ->modalSubmitActionLabel('Draft')
                            ->form([
                                Textarea::make('instructions')
                                    ->hiddenLabel()
                                    ->rows(4)
                                    ->placeholder('What do you want to write about?')
                                    ->required(),
                            ])
                            ->action(function (array $data, Get $get, Set $set) use ($mergeTags) {
                                $service = app(AiSettings::class)->default_model->getService();

                                $userName = auth()->user()->name;
                                $userJobTitle = auth()->user()->job_title ?? 'staff member';
                                $clientName = app(LicenseSettings::class)->data->subscription->clientName;
                                $educatableLabel = $this->getOwnerRecord()::getLabel();

                                $mergeTagsList = collect($mergeTags)
                                    ->map(fn (string $tag): string => <<<HTML
                                        <span data-type="mergeTag" data-id="{$tag}" contenteditable="false">{$tag}</span>
                                    HTML)
                                    ->join(', ', ' and ');

                                if ($get('delivery_method') === EngagementDeliveryMethod::Sms->value) {
                                    $content = $service->complete(<<<EOL
                                        The user's name is {$userName} and they are a {$userJobTitle} at {$clientName}.
                                        Please draft a short SMS message for a {$educatableLabel} at their college.
                                        The user will send a message to you containing instructions for the content.

                                        You should only respond with the SMS content, you should never greet them.

                                        You may use merge tags to insert dynamic data about the student in the body of the SMS:
                                        {$mergeTagsList}
                                    EOL, $data['instructions']);

                                    $set('body', Str::markdown($content));

                                    return;
                                }

                                $content = $service->complete(<<<EOL
                                    The user's name is {$userName} and they are a {$userJobTitle} at {$clientName}.
                                    Please draft an email for a {$educatableLabel} at their college.
                                    The user will send a message to you containing instructions for the content.

                                    You should only respond with the email content, you should never greet them.
                                    The first line should contain the raw subject of the email, with no "Subject: " label at the start.
                                    All following lines after the subject are the email body.

                                    When you answer, it is crucial that you format the email body using rich text in Markdown format.
                                    The subject line can not use Markdown formatting, it is plain text.
                                    Do not ever mention in your response that the answer is being formatted/rendered in Markdown.

                                    You may use merge tags to insert dynamic data about the student in the body of the email, but these do not work in the subject line:
                                    {$mergeTagsList}
                                EOL, $data['instructions']);

                                $set('subject', (string) str($content)
                                    ->before("\n")
                                    ->trim());

                                $set('body', (string) str($content)->after("\n")->markdown());
                            })
                            ->visible(
                                auth()->user()->hasLicense(LicenseType::ConversationalAi) &&
                                app(AiSettings::class)->default_model
                            ),
                    ]),
                ]),
            Fieldset::make('Send your email or text')
                ->schema([
                    Toggle::make('send_later')
                        ->reactive()
                        ->helperText('By default, this email or text will send as soon as it is created unless you schedule it to send later.'),
                    DateTimePicker::make('deliver_at')
                        ->required()
                        ->visible(fn (Get $get) => $get('send_later')),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No email or text messages.')
            ->emptyStateDescription('Create an email or text message to get started.')
            ->defaultSort('record_sortable_date', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->whereHasMorph('timelineable', [
                Engagement::class,
                EngagementResponse::class,
            ]))
            ->columns([
                TextColumn::make('direction')
                    ->getStateUsing(fn (Timeline $record) => match ($record->timelineable::class) {
                        Engagement::class => 'Outbound',
                        EngagementResponse::class => 'Inbound',
                    })
                    ->icon(fn (string $state) => match ($state) {
                        'Outbound' => 'heroicon-o-arrow-up-tray',
                        'Inbound' => 'heroicon-o-arrow-down-tray',
                    }),
                TextColumn::make('type')
                    ->getStateUsing(function (Timeline $record) {
                        /** @var HasDeliveryMethod $timelineable */
                        $timelineable = $record->timelineable;

                        return $timelineable->getDeliveryMethod();
                    }),
                TextColumn::make('record_sortable_date')
                    ->label('Date')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Email or Text')
                    ->modalHeading('Create new email or text')
                    ->createAnother(false)
                    ->record(fn (Timeline $record) => $record->timelineable)
                    ->action(function (array $data) {
                        $engagement = new Engagement($data);
                        $engagement->recipient()->associate($this->getRecord());
                        $engagement->save();

                        $createEngagementDeliverable = resolve(CreateEngagementDeliverable::class);

                        $createEngagementDeliverable($engagement, $data['delivery_method']);
                    }),
            ])
            ->actions([
                ViewAction::make()
                    ->modalHeading(function (Timeline $record) {
                        /** @var HasDeliveryMethod $timelineable */
                        $timelineable = $record->timelineable;

                        return "View {$timelineable->getDeliveryMethod()->getLabel()}";
                    }),
            ])
            ->filters([
                SelectFilter::make('direction')
                    ->options([
                        Engagement::class => 'Outbound',
                        EngagementResponse::class => 'Inbound',
                    ])
                    ->modifyQueryUsing(
                        fn (Builder $query, array $data) => $query
                            ->when($data['value'], fn (Builder $query) => $query->whereHasMorph('timelineable', $data['value']))
                    ),
                SelectFilter::make('type')
                    ->options(EngagementDeliveryMethod::class)
                    ->modifyQueryUsing(
                        fn (Builder $query, array $data) => $query
                            ->when(
                                $data['value'] === EngagementDeliveryMethod::Email->value,
                                fn (Builder $query) => $query
                                    ->whereHasMorph(
                                        'timelineable',
                                        [Engagement::class],
                                        fn (Builder $query, string $type) => match ($type) {
                                            Engagement::class => $query->whereRelation('deliverable', 'channel', $data['value']),
                                        }
                                    )
                            )
                            ->when(
                                $data['value'] === EngagementDeliveryMethod::Sms->value,
                                fn (Builder $query) => $query->whereHasMorph(
                                    'timelineable',
                                    [Engagement::class, EngagementResponse::class],
                                    fn (Builder $query, string $type) => match ($type) {
                                        Engagement::class => $query->whereRelation('deliverable', 'channel', $data['value']),
                                        EngagementResponse::class => $query,
                                    }
                                )
                            )
                    ),
            ]);
    }
}