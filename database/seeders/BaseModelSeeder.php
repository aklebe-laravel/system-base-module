<?php

namespace Modules\SystemBase\database\seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class BaseModelSeeder extends Seeder
{
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
     * @param  string         $modelClass
     * @param  int            $count
     * @param  callable|null  $data
     *
     * @return array list of ids created
     */
    public function TryCreateFactories(string $modelClass, int $count = 1, callable $data = null): array
    {
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $tries = $this->tryUniqueCreateCount;
            while ($tries-- > 0) {
                /** @var Model $subject */
                $subject = app($modelClass)->factory()->make($data ? $data() : []);
                try {

                    //// @todo: without events to avoid user notifications?
                    //if (app('system_base')->hasInstanceClassOrTrait($subject, TraitAttributeAssignment::class)) {
                    //    /** @var TraitAttributeAssignment $subject */
                    //    $subject->saveWithoutEvents();
                    //    Log::info("saved ".get_class($subject)." with saveWithoutEvents()");
                    //} else {
                    //    $subject->save();
                    //}

                    $subject->save();
                    $result[] = $subject->getKey();
                    break;
                } catch (\Illuminate\Database\QueryException $e) {
                    Log::debug("Failed to seed model for \"{$modelClass}\", try again ({$i}:{$tries}", [__METHOD__]);
                }
            }

            if ($tries <= 0) {
                Log::warning("Max attempts ({$this->tryUniqueCreateCount}) used to seed model for {$modelClass}, skipped!", [__METHOD__]);
            }
        }

        return $result;
    }

}
