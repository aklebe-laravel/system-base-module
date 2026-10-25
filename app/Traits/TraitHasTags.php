<?php

declare(strict_types = 1);

namespace Modules\SystemBase\app\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;
use Modules\SystemBase\app\Models\Tag;

/**
 * Polymorphic tags (tables 'tags' and 'taggables'), see Tag and TagNamesCast.
 *
 * @mixin Model
 */
trait TraitHasTags
{
    /**
     * Detaches the tags when the model is deleted and keeps 'tag_names' out of the table columns.
     */
    public static function bootTraitHasTags(): void
    {
        // Triggers only when models are deleted individually or via Collections
        static::deleting(function (Model $model): void {
            /** @var TraitHasTags $model */
            $model->tags2()->detach();
        });

        // 'tag_names' is a virtual attribute (TagNamesCast), there is no such column
        static::saving(function (Model $model): void {
            if (array_key_exists('tag_names', $model->attributes)) {
                unset($model->attributes['tag_names']);
            }
        });
    }

    /**
     * Get all tags for the model.
     */
    public function tags2(): MorphToMany
    {
        return $this->morphToMany(
            Tag::class,
            'taggable',
            'taggables',
            'taggable_id',
            'tag_id',
            $this->getKeyName(),
            'id'
        );
    }

    /**
     * Sync tags from an array of names. Missing tags are created (slug from the name).
     * Empty names are ignored, an empty array detaches all tags.
     *
     * @param  array<int, string>  $tagsArray
     */
    public function syncTags(array $tagsArray): void
    {
        $tagIds = [];

        foreach ($tagsArray as $tagName) {
            $cleanedName = trim((string) $tagName);
            if ($cleanedName === '') {
                continue;
            }

            $tag = Tag::firstOrCreate(['slug' => Str::slug($cleanedName)], ['name' => $cleanedName]);
            $tagIds[] = $tag->id;
        }

        $this->tags2()->sync($tagIds);
        $this->unsetRelation('tags2');
    }

    /**
     * All tags ordered by name, for select components.
     *
     * @return array<int, array{id: int, name: string, slug: string}>
     */
    public static function getAllTagOptions(): array
    {
        return Tag::query()
            ->select(['id', 'name', 'slug'])
            ->orderBy('name')
            ->toBase()
            ->get()
            ->map(fn(\stdClass $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])
            ->toArray();
    }
}
