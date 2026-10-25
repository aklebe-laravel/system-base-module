<?php

namespace Modules\SystemBase\app\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Common helpers for models.
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
     * Sets $userClassName when the model boots.
     *
     * @return void
     */
    protected static function bootTraitBaseModel(): void
    {
        static::$userClassName = app('system_base')->getUserClassName();
    }

    /**
     * Builder for items visible in the frontend. Overwrite to add restrictions.
     *
     * @return Builder
     */
    public static function getBuilderFrontendItems(): Builder
    {
        return self::query();
    }

    /**
     * Scope loadByFrontend(): if $fieldValue is numeric, it filters by id.
     * Otherwise, by column $fieldNonNumeric (like a slug).
     *
     * @param  Builder  $builder
     * @param  mixed    $fieldValue
     * @param  string   $fieldNonNumeric
     *
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
     * Names of BelongsToMany relations to copy in replicateWithRelations(). Overwrite this.
     *
     * @return array
     */
    public function getReplicateRelations(): array
    {
        return [];
    }

    /**
     * Saves a copy of this model (created_at = now) and syncs the relations of getReplicateRelations().
     * If the model has a method afterReplicated($original), it's called before saving the copy.
     *
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
