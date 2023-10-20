<?php

namespace App\Filament\Tables\Filters;

use Closure;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Filters\BaseFilter;
use Filament\Forms\Components\Component;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use App\Filament\Tables\Filters\QueryBuilder\Concerns\HasConstraints;
use App\Filament\Tables\Filters\QueryBuilder\Forms\Components\RuleBuilder;

class QueryBuilder extends BaseFilter
{
    use HasConstraints;

    /**
     * @var array<string, int | string | null> | null
     */
    protected ?array $constraintPickerColumns = [];

    protected string | Closure | null $constraintPickerWidth = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->form(fn (QueryBuilder $filter): array => [
            Fieldset::make($filter->getLabel())
                ->schema([
                    RuleBuilder::make('rules')
                        ->constraints($filter->getConstraints())
                        ->blockPickerColumns($filter->getConstraintPickerColumns())
                        ->blockPickerWidth($filter->getConstraintPickerWidth()),
                    Checkbox::make('not')
                        ->label('Exclude these filters (NOT)'),
                ])
                ->columns(1),
        ]);

        $this->query(function (Builder $query, array $data) {
            $query->{($data['not'] ?? false) ? 'whereNot' : 'where'}(function (Builder $query) use ($data) {
                $this->applyRulesToQuery($query, $data['rules'], $this->getRuleBuilder());
            });
        });

        $this->baseQuery(function (Builder $query, array $data) {
            $this->applyRulesToBaseQuery($query, $data['rules'], $this->getRuleBuilder());
        });

        $this->columnSpanFull();
    }

    public static function getDefaultName(): ?string
    {
        return 'queryBuilder';
    }

    public function applyRulesToQuery(Builder $query, array $rules, RuleBuilder $ruleBuilder): Builder
    {
        foreach ($rules as $ruleIndex => $rule) {
            $ruleBuilderBlockContainer = $ruleBuilder->getChildComponentContainer($ruleIndex);

            if ($rule['type'] === RuleBuilder::OR_BLOCK_NAME) {
                $query->{$rule['data']['not'] ?? false ? 'whereNot' : 'where'}(function (Builder $query) use ($rule, $ruleBuilderBlockContainer) {
                    $isFirst = true;

                    foreach ($rule['data'][RuleBuilder::OR_BLOCK_GROUPS_REPEATER_NAME] as $orGroupIndex => $orGroup) {
                        $query->{match ([$isFirst, ($orGroup['not'] ?? false)]) {
                            [true, false] => 'where',
                            [true, true] => 'whereNot',
                            [false, false] => 'orWhere',
                            [false, true] => 'orWhereNot',
                        }}(function (Builder $query) use ($orGroup, $orGroupIndex, $ruleBuilderBlockContainer) {
                            $this->applyRulesToQuery(
                                $query,
                                $orGroup['rules'],
                                $this->getNestedRuleBuilder($ruleBuilderBlockContainer, $orGroupIndex),
                            );
                        });

                        $isFirst = false;
                    }
                });

                continue;
            }

            $this->tapOperatorFromRule(
                $rule,
                $ruleBuilderBlockContainer,
                fn ($operator) => $operator->applyToBaseQuery($query),
            );
        }

        return $query;
    }

    public function applyRulesToBaseQuery(Builder $query, array $rules, RuleBuilder $ruleBuilder): Builder
    {
        foreach ($rules as $ruleIndex => $rule) {
            $ruleBuilderBlockContainer = $ruleBuilder->getChildComponentContainer($ruleIndex);

            if ($rule['type'] === RuleBuilder::OR_BLOCK_NAME) {
                foreach ($rule['data'][RuleBuilder::OR_BLOCK_GROUPS_REPEATER_NAME] as $orGroupIndex => $orGroup) {
                    $this->applyRulesToBaseQuery(
                        $query,
                        $orGroup['rules'],
                        $this->getNestedRuleBuilder($ruleBuilderBlockContainer, $orGroupIndex),
                    );
                }

                continue;
            }

            $this->tapOperatorFromRule(
                $rule,
                $ruleBuilderBlockContainer,
                fn ($operator) => $operator->applyToBaseFilterQuery($query),
            );
        }

        return $query;
    }

    /**
     * @param  array<string, int | string | null> | int | string | null  $columns
     */
    public function constraintPickerColumns(array | int | string | null $columns = 2): static
    {
        if (! is_array($columns)) {
            $columns = [
                'lg' => $columns,
            ];
        }

        $this->constraintPickerColumns = [
            ...($this->constraintPickerColumns ?? []),
            ...$columns,
        ];

        return $this;
    }

    /**
     * @return array<string, int | string | null> | int | string | null
     */
    public function getConstraintPickerColumns(?string $breakpoint = null): array | int | string | null
    {
        $columns = $this->constraintPickerColumns ?? [
            'default' => 1,
            'sm' => null,
            'md' => null,
            'lg' => null,
            'xl' => null,
            '2xl' => null,
        ];

        if ($breakpoint !== null) {
            return $columns[$breakpoint] ?? null;
        }

        return $columns;
    }

    public function constraintPickerWidth(string | Closure | null $width): static
    {
        $this->constraintPickerWidth = $width;

        return $this;
    }

    public function getConstraintPickerWidth(): ?string
    {
        return $this->evaluate($this->constraintPickerWidth);
    }

    protected function getRuleBuilder(): RuleBuilder
    {
        return $this->getForm()->getComponent(fn (Component $component): bool => $component instanceof RuleBuilder);
    }

    protected function getNestedRuleBuilder(ComponentContainer $ruleBuilderBlockContainer, string $orGroupIndex): RuleBuilder
    {
        return $ruleBuilderBlockContainer
            ->getComponent(fn (Component $component): bool => $component instanceof Repeater)
            ->getChildComponentContainer($orGroupIndex)
            ->getComponent(fn (Component $component): bool => $component instanceof RuleBuilder);
    }

    protected function tapOperatorFromRule(array $rule, ComponentContainer $ruleBuilderBlockContainer, Closure $callback): void
    {
        $constraint = $this->getConstraint($rule['type']);

        if (! $constraint) {
            return;
        }

        $operator = $rule['data'][$constraint::OPERATOR_SELECT_NAME];

        if (blank($operator)) {
            return;
        }

        [$operatorName, $isInverseOperator] = $constraint->parseOperatorString($operator);

        $operator = $constraint->getOperator($operatorName);

        if (! $operator) {
            return;
        }

        try {
            $ruleBuilderBlockContainer->validate();
        } catch (ValidationException) {
            return;
        }

        $constraint
            ->settings($rule['data']['settings'])
            ->inverse($isInverseOperator);

        $operator
            ->constraint($constraint)
            ->settings($rule['data']['settings'])
            ->inverse($isInverseOperator);

        $callback($operator);

        $constraint
            ->settings(null)
            ->inverse(null);

        $operator
            ->constraint(null)
            ->settings(null)
            ->inverse(null);
    }
}