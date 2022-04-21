<?php

namespace KirschbaumDevelopment\NovaInlineRelationship;

use App\Containers\ContentSection\StoryContent\Models\StoryContent;
use Ebess\AdvancedNovaMediaLibrary\Fields\Media;
use Illuminate\Validation\ValidationException;
use KirschbaumDevelopment\NovaInlineRelationship\Dto\RelationObservableDto;
use stdClass;
use Carbon\Carbon;
use App\Nova\Resource;
use Laravel\Nova\Nova;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\Field;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Laravel\Nova\ResourceToolElement;
use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Contracts\ListableField;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Fields\ResolvesReverseRelation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use KirschbaumDevelopment\NovaInlineRelationship\Rules\RelationshipRule;
use Illuminate\Database\Eloquent\Relations\Concerns\SupportsDefaultModels;
use KirschbaumDevelopment\NovaInlineRelationship\Traits\RequireRelationship;
use KirschbaumDevelopment\NovaInlineRelationship\Observers\NovaInlineRelationshipObserver;

class NovaInlineRelationship extends Field
{
    use RequireRelationship;

    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'nova-inline-relationship';

    /**
     * All the Models observed by the field
     *
     * @var array
     */
    public static $observedModels = [];

    /**
     * Name of resource class to be used
     *
     * @var string
     */
    private $resourceClass;

    /**
     * Name of field used to sort the models.
     *
     * @var string
     */
    private $sortUsing = '';

    /**
     * Pass resourceClass to NovaInlineRelationship.
     *
     * @param string $class
     *
     * @return NovaInlineRelationship
     */
    public function resourceClass(string $class): self
    {
        $this->resourceClass = $class;

        return $this;
    }

    /**
     * Fluent setter for sortUsing field.
     *
     * @param string $sortUsing
     *
     * @return NovaInlineRelationship
     */
    public function sortUsing(string $sortUsing): self
    {
        $this->sortUsing = $sortUsing;

        return $this;
    }

    /**
     * Resolve the field's value for display.
     *
     * @param mixed $resource
     * @param string|null $attribute
     *
     * @return void
     */
    public function resolveForDisplay($resource, $attribute = null)
    {
        parent::resolveForDisplay($resource, $attribute);

        $attribute = $attribute ?? $this->attribute;

        $properties = $this->getPropertiesWithMetaForDisplay($resource, $attribute);

        $this->resolveResourceFields($resource, $attribute, $properties);
    }

    /**
     * Resolve the field's value.
     *
     * @param mixed $resource
     * @param string|null $attribute
     *
     * @return void
     */
    public function resolve($resource, $attribute = null)
    {
        parent::resolve($resource, $attribute);

        $request = app(NovaRequest::class);

        if ($request->isCreateOrAttachRequest() || $request->isUpdateOrUpdateAttachedRequest()) {
            $attribute = $attribute ?? $this->attribute;

            $properties = $this->getPropertiesWithMetaForForms($resource, $attribute);

            $this->resolveResourceFields($resource, $attribute, $properties);
        }
    }

    /**
     * Fetch default values from collection
     *
     * @param Collection $properties
     *
     * @return Collection
     */
    public function getDefaultsFromProperties(Collection $properties): Collection
    {
        return $properties->map(function ($value) {
            return $value['default'] ?? '';
        });
    }

    /**
     * Get Properties From Resource with Meta Information
     *
     * @param mixed $resource
     * @param string $attribute
     *
     * @return Collection
     */
    public function getPropertiesWithMetaForDisplay($resource, $attribute): Collection
    {
        $fields = $this->getFieldsFromResource($resource, $attribute)
            ->filter->authorize(app(NovaRequest::class))
            ->filter(function ($field) {
                return $field->showOnDetail;
            });

        return $this->getPropertiesFromFields($fields)
            ->keyBy('attribute')
            ->map(function ($value, $key) {
                return $this->setMetaFromClass($value, $key);
            });
    }

    /**
     * Get Properties From Resource with Meta Information
     *
     * @param mixed $resource
     * @param string $attribute
     *
     * @return Collection
     */
    public function getPropertiesWithMetaForForms($resource, $attribute): Collection
    {
        $fields = $this->getFieldsFromResource($resource, $attribute)
            ->filter->authorize(app(NovaRequest::class))
            ->filter(function ($field) {
                $request = app(NovaRequest::class);

                return ($request->isCreateOrAttachRequest() && $field->showOnCreation)
                    || ($request->isUpdateOrUpdateAttachedRequest() && $field->showOnUpdate);
            });

        return $this->getPropertiesFromFields($fields)
            ->keyBy('attribute')
            ->map(function ($value, $key) {
                return $this->setMetaFromClass($value, $key);
            });
    }

    /**
     * Checks if a relationship is singular
     *
     * @param Model $model
     * @param $relation
     *
     * @return bool
     */
    public function isSingularRelationship(Model $model, $relation): bool
    {
        return collect(class_uses($model->{$relation}()))->contains(SupportsDefaultModels::class);
    }

    /**
     * Checks if a relationship is deletable
     *
     * @param Model $model
     * @param $relation
     *
     * @return bool
     */
    public function isRelationshipDeletable(Model $model, $relation): bool
    {
        return !($model->{$relation}() instanceof BelongsTo);
    }

    /**
     * Create a duplicate request object using the base request
     *
     * @param NovaRequest $request
     * @param array $item
     *
     * @return NovaInlineRelationshipRequest
     */
    public function getDuplicateRequest(NovaRequest $request, array $item): NovaInlineRelationshipRequest
    {
        $files = collect($item)->filter(function ($itemData) {
            return $itemData instanceof UploadedFile;
        });

        // Create a New Request
        $newRequest = NovaInlineRelationshipRequest::createFrom($request)
            ->duplicate($item, array_merge($request->all(), $item));

        // Update List of converted Files
        $newRequest->updateConvertedFiles($files);

        return $newRequest;
    }

    /**
     * Get Value for the Child attribute from field
     *
     * @param Field $field
     * @param NovaInlineRelationshipRequest $request
     * @param string $attribute
     *
     * @return mixed|null
     */
    public function getValueFromField(Field $field, NovaInlineRelationshipRequest $request, string $attribute)
    {
        $temp = new stdClass();

        // Fill Attributes in Field
        $field->fillAttribute($request, $attribute, $temp, $attribute);

        return $temp->{$attribute} ?? null;
    }

    /**
     * Resolve the fields for the resource.
     *
     * @param $resource
     * @param $attribute
     * @param $properties
     */
    protected function resolveResourceFields($resource, $attribute, $properties)
    {
        if (!empty($this->sortUsing) && $this->value instanceof Collection && $this->value->isNotEmpty()) {
            $this->value = $this->value->sortBy($this->sortUsing)->values();
        }

        $this->rules = array_merge($this->rules, [$this->getRelationshipRule($attribute, $properties)]);
        $modelKey = optional($this->value)->first() ?? $resource->{$attribute}()->getRelated()->newInstance();

        $this->withMeta([
            'defaults' => $this->getDefaultsFromProperties($properties)->all(),
            'settings' => $properties->all(),
            'models' => $this->modelIds(),
            'modelKey' => Str::plural(Str::kebab(class_basename($modelKey))),
            'singularLabel' => Str::title(Str::singular($this->name)),
            'pluralLabel' => Str::title(Str::plural($this->name)),
            'singular' => $this->isSingularRelationship($resource, $attribute),
            'deletable' => $this->isRelationshipDeletable($resource, $attribute),
            'addChildAtStart' => $this->requireChild,
            'sortable' => !empty($this->sortUsing),
        ]);

        $this->updateFieldValue($resource, $attribute, $properties);
    }

    /**
     * Pluck id's from the model or collection.
     *
     * @return array
     */
    protected function modelIds()
    {
        if ($this->value instanceof Model) {
            $models = [$this->value->{$this->value->getKeyName()}];
        } elseif ($this->value instanceof Collection && $this->value->isNotEmpty()) {
            $key = $this->value->first()->getKeyName();
            $models = $this->value->pluck($key)->all();
        }

        return $models ?? [];
    }

    /**
     * Set Meta Values using Field
     *
     * @param array $item
     * @param $attrib
     * @param null $value
     * @param null|mixed $resource
     *
     * @return array
     */
    protected function setMetaFromClass(array $item, $attrib, $value = null, $resource = null)
    {
        $request = app(NovaRequest::class);
        $attrs = ['name' => $attrib, 'attribute' => $attrib];

        /** @var Field $class */
        $class = $item['field'];

        if ($resource && ($request->isResourceDetailRequest() || $request->isResourceIndexRequest())) {
            $class->resolveForDisplay($resource, $attrib);
        } else {
            if ($resource) {
                $class->resolve($resource, $attrib);
            } else {
                if (is_callable($class->resolveCallback)) {
                    $resolvedValue = call_user_func($class->resolveCallback, $value, $resource, $attrib);

                    if ($resolvedValue) {
                        $value = $resolvedValue;
                    }
                }

                $class->value = $value !== null ? $value : '';
            }
        }

        if (!empty($item['options']) && is_array($item['options'])) {
            $class->withMeta($item['options']);
        }

        if (!empty($item['placeholder'])) {
            $class->withMeta([
                'extraAttributes' => [
                    'placeholder' => $item['placeholder'],
                ]
            ]);
        }

        $item['meta'] = $class->jsonSerialize();
        $item['meta']['singularLabel'] = $item['label'] ?? $attrib;
        $item['meta']['placeholder'] = 'Add ' . $item['meta']['singularLabel'];

        return $item;
    }

    /**
     * Hydrate the given attribute on the model based on the incoming request.
     *
     * @param NovaRequest $request
     * @param string $requestAttribute
     * @param object $model
     * @param string $attribute
     *
     * @return mixed
     */
    protected function fillAttributeFromRequest(NovaRequest $request, $requestAttribute, $model, $attribute)
    {
        if ($request->exists($requestAttribute)) {
            $response = is_array($request[$requestAttribute])
                ? $request[$requestAttribute]
                : json_decode($request[$requestAttribute], true);

            $observableData = new RelationObservableDto([], []);
            $baseRequest = NovaRequest::createFrom($request);
            $instance = new $this->resourceClass::$model();
            $resource = Nova::newResourceFromModel($instance);
            $fields = $resource->availableFields($request);
            $callbacks = [];

            if ($response) {
                foreach ($response as $order => $item) {
                    if (!empty($this->sortUsing)) {
                        $item['values'][$this->sortUsing] = $order;
                    }

                    if ($item['modelId']) {
                        $relatedModel = $instance::find($item['modelId']);
                        $relatedModel->fill($item['values']);
                    } else {
                        $relatedModel = $instance->newInstance($item['values']);
                    }

                    $observableData->items[] = $relatedModel;

                    $baseRequest->replace($item['values']);

                    $fields
                        ->each(function ($field) use ($request, $requestAttribute, $baseRequest, $relatedModel, $observableData, $order) {
                            if ($field instanceof Media) {
                                $itemMedias = $request['__media__.' . $requestAttribute . '.' . $order . '.' . $field->attribute] ?? [];

                                $baseRequest->merge(['__media__' => [$field->attribute => $itemMedias]]);

                                try {
                                    $observableData->callbacks[] = $field->fill($baseRequest, $relatedModel);
                                } catch (ValidationException $exception) {
                                    $newMessages = [];
                                    $messageBag = $exception->validator->getMessageBag();

                                    foreach (
                                        $exception->validator->getMessageBag()->getMessages() as $messageKey => $message
                                    ) {
                                        $messageBag->add(
                                            $requestAttribute,
                                            json_encode([$requestAttribute . '.' . $order . '.' . $messageKey => $message]
                                            )
                                        );
                                    }

                                    throw $exception;
                                }
                            } else {
                                $result = $field->fill($baseRequest, $relatedModel);

                                if (is_callable($result)) {
                                    $observableData->callbacks[] = $result;
                                }
                            }
                        });
                }
            }

            if ($model instanceof Model) {
                $this->setModelAttributeValue($model, $attribute, $observableData);
            }
        }
    }

    /**
     * Resolve Field class from child Resource attribute
     *
     * @param array $props
     * @param string $key
     *
     * @return Field
     */
    protected function getResourceField($props, $key): Field
    {
        $attrs = ['name' => $key, 'attribute' => $key];

        return resolve($props['component'], $attrs);
    }

    /**
     * Return Relationship rule from properties
     *
     * @param string $attribute
     * @param Collection $properties
     *
     * @return RelationshipRule
     */
    protected function getRelationshipRule($attribute, Collection $properties): RelationshipRule
    {
        $ruleArray = [];
        $messageArray = [];
        $attribArray = [];

        $request = app(NovaRequest::class);
        $resource = new $this->resourceClass(new $this->resourceClass::$model);
        $resourceRules = [];

        if ($request->isCreateOrAttachRequest()) {
            $resourceRules = $resource::rulesForCreation($request);
        } elseif ($request->isUpdateOrUpdateAttachedRequest()) {
            $resourceRules = $resource::rulesForUpdate($request);
        }

        foreach ($resourceRules as $field => $rules) {
            $name = "{$attribute}.*.values.{$field}";
            $ruleArray[$name] = $rules;
            $attribArray[$name] = '';
        }

        return new RelationshipRule($ruleArray, $messageArray, $attribArray);
    }

    /**
     * Get all fields from resource.
     *
     * @param $model
     * @param $attribute
     *
     * @return Collection
     */
    protected function getFieldsFromResource($model, $attribute): Collection
    {
        $resource = !empty($this->resourceClass)
            ? new $this->resourceClass($model)
            : Nova::newResourceFromModel($model->{$attribute}()->getRelated());

        return collect($resource->availableFields(app(NovaRequest::class)))
            ->reject(function ($field) use ($resource) {
                return $field instanceof ListableField ||
                    $field instanceof ResourceToolElement ||
                    $field->attribute === 'ComputedField' ||
                    ($field instanceof ID && $field->attribute === $resource->resource->getKeyName()) ||
                    $field instanceof self;
            });
    }

    /**
     * Get properties for each field.
     *
     * @param Collection $fields
     *
     * @return Collection
     */
    protected function getPropertiesFromFields(Collection $fields): Collection
    {
        return $fields->map(function ($value) {
            return [
                'field' => $value,
                'component' => get_class($value),
                'label' => $value->name,
                'options' => $value->meta,
                'attribute' => $value->attribute,
            ];
        });
    }

    /**
     * Update value for the field
     *
     * @param $resource
     * @param $attribute
     * @param Collection $properties
     */
    protected function updateFieldValue($resource, $attribute, Collection $properties): void
    {
        if ($this->isSingularRelationship($resource, $attribute)) {
            $this->value = collect(Arr::wrap($this->value));
        }

        $this->value = $this->value->map(function ($items) use ($properties) {
            return collect($properties)
                ->map(function ($value, $key) use ($properties, $items) {
                    return $this->setMetaFromClass($value, $key, $items->{$key}, $items);
                })
                ->filter();
        });
    }

    /**
     * save model attribute to a static array
     *
     * @param Model $model
     * @param $attribute
     * @param array $value
     */
    protected function setModelAttributeValue(Model $model, $attribute, RelationObservableDto $value): void
    {
        $modelClass = get_class($model);

        if (!array_key_exists($modelClass, static::$observedModels)) {
            $model::observe(NovaInlineRelationshipObserver::class);
            $model->updated_at = Carbon::now();
        }

        static::$observedModels[$modelClass][$attribute] = $value;
    }
}
