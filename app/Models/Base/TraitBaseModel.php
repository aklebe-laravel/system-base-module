<?php

namespace Modules\SystemBase\app\Models\Base;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 *
 */
trait TraitBaseModel
{
    /**
     * Used to detect the current inherited user model.
     * Can be used like $this->belongsTo($this::$userClassName);
     *
     * @var string
     */
    protected static string $userClassName = '';

    /**
     * @return void
     */
    protected static function bootTraitBaseModel(): void
    {
        static::$userClassName = app('system_base')->getUserClassName();
    }

    /**
     * @return Builder
     */
    public static function getBuilderFrontendItems(): Builder
    {
        return self::query();
    }

    /**
     * If $fieldValue is numeric, the builder collect by column $fieldNumeric.
     * Otherwise, using column $fieldNonNumeric.
     *
     * @param  Builder  $builder
     * @param  mixed  $fieldValue
     * @param  string  $fieldNonNumeric
     * @return void
     */
    public function scopeLoadByFrontend(Builder $builder, mixed $fieldValue, string $fieldNonNumeric): void
    {
        if (is_numeric($fieldValue)) {
            $builder->whereId($fieldValue);
        } else {
            $builder->where($fieldNonNumeric, $fieldValue);
        }
    }

    /**
     * Returns relations to replicate.
     *
     * @return array
     */
    public function getReplicateRelations(): array
    {
        return [];
    }

    /**
     * @return Model
     */
    public function replicateWithRelations(): Model
    {
        /** @var Model $newItem */
        $newItem = $this->replicate();
        $newItem->created_at = Carbon::now();

        // call afterReplicated()
        $methodAfterReplicate = 'afterReplicated';
        if (method_exists($newItem, $methodAfterReplicate)) {
            $newItem->$methodAfterReplicate($this);
        }

        // save it
        $newItem->save();

        // loop through all relation should also be replicated
        foreach ($this->getReplicateRelations() as $relationName) {
            $newItem->$relationName()->sync($this->$relationName);
        }

        return $newItem;
    }

}
