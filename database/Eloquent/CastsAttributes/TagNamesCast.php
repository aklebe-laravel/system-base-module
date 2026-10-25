<?php

declare(strict_types=1);

namespace Modules\SystemBase\database\Eloquent\CastsAttributes;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Modules\SystemBase\app\Traits\TraitHasTags;

/**
 * Virtual attribute 'tag_names' for models using TraitHasTags.
 * get: names of the loaded 'tags2' relation ([] if not loaded, to avoid extra queries).
 * set: syncs the tags (for new models after they are saved).
 *
 * @implements CastsAttributes<array<int, string>, array<int, string>|null>
 */
final class TagNamesCast implements CastsAttributes
{
    /**
     * Get the value for PHP / Livewire usage.
     *
     * @param array<string, mixed> $attributes
     * @return array<int, string>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (! $model->relationLoaded('tags2')) {
            return [];
        }

        return $model->tags2->pluck('name')->all();
    }

    /**
     * Handle setting the value from forms / Livewire.
     *
     * @param array<int, string>|null $value
     * @param array<string, mixed> $attributes
     * @return array<empty>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $tagsArray = $value ?? [];

        if ($model->exists) {
            // Direct execution during livewire sync or update
            /** @var TraitHasTags $model */
            $model->syncTags($tagsArray);
        } else {
            // Defer execution for new models until they are saved and have an ID
            $model->saved(function (Model $savedModel) use ($tagsArray): void {
                /** @var TraitHasTags $savedModel */
                $savedModel->syncTags($tagsArray);
            });
        }

        // Return empty array to keep the local attribute valid for current request lifecycle
        return [];
    }
}
