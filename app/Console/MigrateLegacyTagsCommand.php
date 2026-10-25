<?php

declare(strict_types=1);

namespace Modules\SystemBase\app\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Modules\SystemBase\app\Traits\TraitHasTags;


final class MigrateLegacyTagsCommand extends Command
{
    /**
     * The name and signature of the console command.
     * examples:
     *      sail artisan migration:legacy-tags Modules\\CentralUi\\app\\Models\\Saying tags
     *      sail artisan migration:legacy-tags Modules\\CentralUi\\app\\Models\\ProxtopPropertyTemplate tags
     *
     * @var string
     */
    protected $signature = 'migration:legacy-tags 
                            {model : The fully qualified class name of the model (e.g. "App\Models\Post")} 
                            {column : The name of the legacy text column containing comma-separated tags}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dynamically migrate legacy comma-separated tags from any model and column to polymorphic tags';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var string $modelClass */
        $modelClass = $this->argument('model');
        /** @var string $column */
        $column = $this->argument('column');

        // Validate model existence
        if (! class_exists($modelClass)) {
            $this->error(sprintf('Model class [%s] does not exist.', $modelClass));
            return Command::FAILURE;
        }

        // Validate that the model uses the HasTags trait
        $traits = class_uses_recursive($modelClass);
        if (! in_array(TraitHasTags::class, $traits, true)) {
            $this->error(sprintf('The model [%s] must use the [HasTags] trait.', $modelClass));
            return Command::FAILURE;
        }

        /** @var Model $instance */
        $instance = new $modelClass();
        $tableName = $instance->getTable();

        // Check if the legacy column exists in the table
        if (! Schema::hasColumn($tableName, $column)) {
            $this->error(sprintf('The column [%s] does not exist on table [%s].', $column, $tableName));
            return Command::FAILURE;
        }

        $this->info(sprintf('Starting migration for [%s] using column [%s]...', $modelClass, $column));

        // Process data in chunks to handle high-volume production datasets safely
        $processedCount = 0;

        $modelClass::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->chunkById(100, function ($records) use ($column, &$processedCount): void {
                foreach ($records as $record) {
                    /** @var TraitHasTags|Model $record */
                    $record->syncTags($this->toTagList($record->getRawOriginal($column)));
                    $processedCount++;
                }
            });

        $this->info(sprintf('Migration completed. Successfully processed %d records.', $processedCount));

        return Command::SUCCESS;
    }

    /**
     * Converts the legacy column value to a list of tag names.
     * Accepts comma-separated text or a JSON array (for columns which were cast to 'array').
     *
     * @param  mixed  $value
     *
     * @return array<int, string>
     */
    private function toTagList(mixed $value): array
    {
        if (is_array($value)) {
            return array_map('strval', $value);
        }

        $value = trim((string) $value);
        if (str_starts_with($value, '[')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_map('strval', $decoded);
            }
        }

        return explode(',', $value);
    }
}
