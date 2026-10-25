<?php

namespace Modules\SystemBase\app\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Adds a runtime attribute "relatedPivotModelId" (not stored in the database).
 * Used to know whether a model was loaded for a parent (like a parent id).
 */
trait TraitModelAddMeta
{
    /**
     * It's like a parent id. Use the attribute "relatedPivotModelId".
     *
     * @var mixed
     */
    private mixed $_relatedPivotModelId = null;

    /**
     * The boot of this trait.
     *
     * @return void
     */
    public static function bootTraitModelAddMeta(): void
    {
    }

    /**
     * Accessor/mutator for the runtime attribute.
     *
     * @return Attribute
     */
    public function relatedPivotModelId(): Attribute
    {
        return Attribute::make(get: function ($v) {
            return $this->_relatedPivotModelId;
        }, set: function ($v) {
            $this->_relatedPivotModelId = $v;
            return $v;
        },);
    }


}