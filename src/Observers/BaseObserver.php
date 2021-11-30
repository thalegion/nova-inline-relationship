<?php

namespace KirschbaumDevelopment\NovaInlineRelationship\Observers;

use Illuminate\Database\Eloquent\Model;
use KirschbaumDevelopment\NovaInlineRelationship\Contracts\RelationshipObservable;
use KirschbaumDevelopment\NovaInlineRelationship\Dto\RelationObservableDto;

abstract class BaseObserver implements RelationshipObservable
{
    /**
     * Handle updating event for the relationship
     *
     * @param Model $model
     * @param $attribute
     * @param $value
     *
     * @return mixed
     */
    public function updating(Model $model, $attribute, RelationObservableDto $value)
    {
    }

    /**
     * Handle creating event for the relationship
     *
     * @param Model $model
     * @param $attribute
     * @param $value
     *
     * @return mixed
     */
    public function creating(Model $model, $attribute, RelationObservableDto $value)
    {
    }

    /**
     * Handle created event for the relationship
     *
     * @param Model $model
     * @param $attribute
     * @param $value
     *
     * @return mixed
     */
    public function created(Model $model, $attribute, RelationObservableDto $value)
    {
    }
}
