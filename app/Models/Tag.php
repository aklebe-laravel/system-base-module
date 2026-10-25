<?php
declare(strict_types=1);

namespace Modules\SystemBase\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Tag extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * Generates the slug from the name when a tag is created.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Tag $tag): void {
            $tag->slug = Str::slug($tag->name);
        });
    }

    /**
     * Note: morphToMany() needs the related model class, so this relation only resolves other Tag models.
     * Use the morphedByMany() relation of a concrete model class to get tagged models.
     */
    public function taggables(): MorphToMany
    {
        return $this->morphToMany(self::class, 'taggable');
    }
}
