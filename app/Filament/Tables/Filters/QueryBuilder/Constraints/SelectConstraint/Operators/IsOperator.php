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

namespace App\Filament\Tables\Filters\QueryBuilder\Constraints\SelectConstraint\Operators;

use Exception;
use Illuminate\Support\Arr;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Component;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Tables\Filters\QueryBuilder\Constraints\SelectConstraint;
use App\Filament\Tables\Filters\QueryBuilder\Constraints\Operators\Operator;

class IsOperator extends Operator
{
    public function getName(): string
    {
        return 'is';
    }

    public function getLabel(): string
    {
        return __(
            $this->isInverse() ?
                'filament-tables::filters/query-builder.operators.select.is.label.inverse' :
                'filament-tables::filters/query-builder.operators.select.is.label.direct',
        );
    }

    public function getSummary(): string
    {
        $constraint = $this->getConstraint();

        $values = Arr::wrap($this->getSettings()[$constraint->isMultiple() ? 'values' : 'value']);

        $values = Arr::join($values, glue: __('filament-tables::filters/query-builder.operators.select.is.summary.values_glue.0'), finalGlue: __('filament-tables::filters/query-builder.operators.select.is.summary.values_glue.final'));

        return __(
            $this->isInverse() ?
                'filament-tables::filters/query-builder.operators.select.is.summary.inverse' :
                'filament-tables::filters/query-builder.operators.select.is.summary.direct',
            [
                'attribute' => $constraint->getAttributeLabel(),
                'values' => $values,
            ],
        );
    }

    /**
     * @return array<Component>
     */
    public function getFormSchema(): array
    {
        $constraint = $this->getConstraint();

        $field = Select::make($constraint->isMultiple() ? 'values' : 'value')
            ->label(__($constraint->isMultiple() ? 'filament-tables::filters/query-builder.operators.select.is.form.values.label' : 'filament-tables::filters/query-builder.operators.select.is.form.value.label'))
            ->options($constraint->getOptions())
            ->multiple($constraint->isMultiple())
            ->searchable($constraint->isSearchable())
            ->native($constraint->isNative())
            ->optionsLimit($constraint->getOptionsLimit())
            ->required();

        if ($getOptionLabelUsing = invade($constraint)->getOptionLabelUsing) {
            $field->getOptionLabelUsing($getOptionLabelUsing);
        }

        if ($getOptionLabelsUsing = invade($constraint)->getOptionLabelsUsing) {
            $field->getOptionLabelsUsing($getOptionLabelsUsing);
        }

        if ($getOptionLabelFromRecordUsing = $constraint->getOptionLabelFromRecordUsingCallback()) {
            $field->getOptionLabelFromRecordUsing($getOptionLabelFromRecordUsing);
        }

        if ($getSearchResultsUsing = invade($constraint)->getSearchResultsUsing) {
            $field->getSearchResultsUsing($getSearchResultsUsing);
        }

        return [$field];
    }

    public function apply(Builder $query, string $qualifiedColumn): Builder
    {
        $value = $this->getSettings()[$this->getConstraint()->isMultiple() ? 'values' : 'value'];

        if (is_array($value)) {
            return $query->{$this->isInverse() ? 'whereNotIn' : 'whereIn'}($qualifiedColumn, $value);
        }

        return $query->{$this->isInverse() ? 'whereNot' : 'where'}($qualifiedColumn, $value);
    }

    public function getConstraint(): ?SelectConstraint
    {
        $constraint = parent::getConstraint();

        if (! ($constraint instanceof SelectConstraint)) {
            throw new Exception('Is operator can only be used with select constraints.');
        }

        return $constraint;
    }
}