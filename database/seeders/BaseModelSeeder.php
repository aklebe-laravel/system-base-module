<?php

namespace Modules\SystemBase\database\seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Base seeder with a helper to create models by factories while avoiding unique conflicts.
 */
class BaseModelSeeder extends Seeder
{
    /**
     * Max attempts per model in TryCreateFactories().
     *
     * @var int
     */
    public int $tryUniqueCreateCount = 100;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        Model::unguard();
    }

    /**
     * Creates $count models by their factory.
     * If saving fails (like a unique conflict), a new model is made, up to $tryUniqueCreateCount times.
     *
     * @param  string         $modelClass
     * @param  int            $count
     * @param  callable|null  $data  returns attributes for factory()->make()
     *
     * @return array list of ids created
     */
    public function TryCreateFactories(string $modelClass, int $count = 1, ?callable $data = null): array
    {
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $created = false;
            for ($try = 1; $try <= $this->tryUniqueCreateCount; $try++) {
                /** @var Model $subject */
                $subject = app($modelClass)->factory()->make($data ? $data() : []);
                try {
                    $subject->save();
                    $result[] = $subject->getKey();
                    $created = true;
                    break;
                } catch (QueryException) {
                    Log::debug("Failed to seed model for \"{$modelClass}\", try again ({$i}:{$try})", [__METHOD__]);
                }
            }

            if (!$created) {
                Log::warning("Max attempts ({$this->tryUniqueCreateCount}) used to seed model for {$modelClass}, skipped!", [__METHOD__]);
            }
        }

        return $result;
    }

}
