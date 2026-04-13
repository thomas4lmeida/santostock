<?php

namespace App\Http\Requests\EventItems;

use App\Enums\ItemCondition;
use App\Http\Requests\CoordinatorRequest;
use App\Models\EventItem;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class SaveEventItemRequest extends CoordinatorRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'item_category_id' => ['required', 'integer', 'exists:item_categories,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'rental_cost_cents' => ['required', 'integer', 'min:0'],
            'condition' => ['nullable', Rule::enum(ItemCondition::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $next = $this->input('condition');
            if (! $next) {
                return;
            }

            $item = $this->route('item');
            if (! $item instanceof EventItem) {
                return;
            }

            $nextEnum = ItemCondition::tryFrom($next);
            if ($nextEnum && ! $item->condition->canTransitionTo($nextEnum)) {
                $validator->errors()->add(
                    'condition',
                    "Não é possível voltar de {$item->condition->label()} para {$nextEnum->label()}."
                );
            }
        });
    }
}
