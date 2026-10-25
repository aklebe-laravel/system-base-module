<?php

namespace Modules\SystemBase\app\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\SystemBase\app\Traits\TraitBaseModel;
use Modules\SystemBase\app\Traits\TraitHasTags;
use Modules\SystemBase\database\Eloquent\CastsAttributes\TagNamesCast;

/**
 * Base model with tags: virtual attribute 'tag_names' (array of names) backed by the polymorphic tags relation.
 * Load the relation 'tags2' to get the names, assigning 'tag_names' syncs the tags.
 */
class ModelWithTags extends Model
{
    use TraitBaseModel;
    use TraitHasTags;

    /**
     * Ensure Livewire appends the field during dehydration to the frontend layout.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'tag_names',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tag_names' => TagNamesCast::class,
        ];
    }
}