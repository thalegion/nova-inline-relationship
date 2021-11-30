<?php

namespace KirschbaumDevelopment\NovaInlineRelationship\Contracts;

use Illuminate\Database\Eloquent\Model;
use KirschbaumDevelopment\NovaInlineRelationship\Dto\RelationObservableDto;

interface RelationshipObservable
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
    public function updating(Model $model, $attribute, RelationObservableDto $value);

    /**
     * Handle creating event for the relationship
     *
     * @param Model $model
     * @param $attribute
     * @param $value
     *
     * @return mixed
     */
    public function creating(Model $model, $attribute, RelationObservableDto $value);

    /**
     * Handle created event for the relationship
     *
     * @param Model $model
     * @param $attribute
     * @param $value
     *
     * @return mixed
     */
    public function created(Model $model, $attribute, RelationObservableDto $value);
}
