<?php

namespace Modules\SystemBase\app\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\SystemBase\app\Services\Base\BaseService;

/**
 * Query builder helpers for models.
 */
class ModelService extends BaseService
{
    /**
     * Adds an id filter by a list like "1,2,3,7-14,81", "-5" (up to 5) or "100-" (from 100).
     * All parts are combined by OR inside one where group, so "1,2" finds id 1 and id 2.
     *
     * @param  Builder  $builder
     * @param  string   $param
     *
     * @return void
     */
    public function PrepareBuilderIdsByParam(Builder $builder, string $param): void
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $param)), fn($part) => $part !== ''));
        if (!$parts) {
            return;
        }

        $builder->where(function (Builder $idBuilder) use ($parts) {
            foreach ($parts as $part) {
                $between = explode('-', $part);

                if (count($between) === 2) { // found "x-y"
                    if ($between[0] === '') { // "-y"
                        $idBuilder->orWhere('id', '<=', $between[1]);
                    } elseif ($between[1] === '') { // "x-"
                        $idBuilder->orWhere('id', '>=', $between[0]);
                    } else { // "x-y"
                        $idBuilder->orWhereBetween('id', $between);
                    }
                } else { // simple value
                    $idBuilder->orWhere('id', $part);
                }
            }
        });
    }

    /**
     * modify builders "where" by created_at
     *
     * @param  Builder  $builder
     * @param  string   $timestamp
     *
     * @return void
     */
    public function PrepareBuilderSinceCreated(Builder $builder, string $timestamp): void
    {
        $builder->where('created_at', '>=', $timestamp);
    }

    /**
     * modify builders "where" by updated_at
     *
     * @param  Builder  $builder
     * @param  string   $timestamp
     *
     * @return void
     */
    public function PrepareBuilderSinceUpdated(Builder $builder, string $timestamp): void
    {
        $builder->where('updated_at', '>=', $timestamp);
    }

    /**
     * Adds an OR condition for a column path:
     * - "column": orWhere(column)
     * - "relation.column": orWhereHas(relation, where column)
     * - "relation.pivot.column": wherePivot() on the relation, if the builder has that method
     * - "pivot.column" and deeper paths are not supported yet (ignored)
     *
     * @param  Builder  $builder
     * @param  string   $columnPath
     * @param  string   $whereValue
     * @param  string   $whereCondition
     *
     * @return void
     */
    public function resolveDotsForWhere(Builder $builder, string $columnPath, string $whereValue, string $whereCondition = '='): void
    {
        $dotParts = explode('.', $columnPath);
        if (count($dotParts) > 1) {
            if (count($dotParts) === 2) {
                if ($dotParts[0] === 'pivot') {
                    // not supported: the relation is unknown here
                } else {
                    $builder->orWhereHas($dotParts[0],
                        function (Builder $b2) use ($dotParts, $whereValue, $whereCondition) {
                            $b2->where($dotParts[1], $whereCondition, $whereValue);
                        });
                }
            } elseif (count($dotParts) === 3) {
                // checking for "relationXXX.pivot.yyy"
                if ($dotParts[1] === 'pivot') {
                    $relation = $dotParts[0];
                    if (method_exists($builder, $relation)) {
                        $builder->$relation()->wherePivot($dotParts[2], $whereCondition, $whereValue);
                    }
                }
            } // @todo: else more complex, resolve it later ...
        } else {
            $builder->orWhere($columnPath, $whereCondition, $whereValue);
        }

    }
}