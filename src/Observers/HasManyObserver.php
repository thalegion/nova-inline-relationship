<?php

namespace KirschbaumDevelopment\NovaInlineRelationship\Observers;

use Illuminate\Support\Arr;
use Illuminate\Database\Eloquent\Model;
use KirschbaumDevelopment\NovaInlineRelationship\Dto\RelationObservableDto;

class HasManyObserver extends BaseObserver
{
    public function updating(Model $model, $attribute, RelationObservableDto $value)
    {
        $model->{$attribute}()
            ->whereNotIn('id', Arr::pluck($value->items, 'id'))
            ->get()
            ->each
            ->delete();

        foreach ($value->items as $item) {
            /** @var Model $childModel */
            $childModel = $model->{$attribute}()->find($item->id);

            $model->{$attribute}()->save($item);
        }

        $this->runCallbacks($value);
    }

    public function created(Model $model, $attribute, RelationObservableDto $value)
    {
        foreach($value->items as $item) {
            $model->{$attribute}()->save($item);
        }

        $this->runCallbacks($value);
    }

    private function runCallbacks(RelationObservableDto $value): void
    {
        if ($value->callbacks) {
            foreach ($value->callbacks as $callback) {
                call_user_func($callback);
            }
        }

        $value->callbacks = [];
    }
}
