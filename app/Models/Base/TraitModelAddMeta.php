<?php

namespace Modules\SystemBase\app\Models\Base;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Used to determine a model have a parent or not.
 */
trait TraitModelAddMeta
{
    /**
     * it's like a parent id
     * use attribute "relatedPivotModelId"
     * @var int|null
     */
    private mixed $_relatedPivotModelId = null;

    /**
     * The boot of this trait ...
     *
     * @return void
     */
    public static function bootTraitModelAddMeta(): void
    {
    }

    /**
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