<?php

namespace Modules\SystemBase\app\Services;

use Illuminate\Database\Eloquent\Builder;
use Modules\SystemBase\app\Services\Base\BaseService;

class ModelService extends BaseService
{
    /**
     * modify builders "where" by params like "1,2,3,7-14,81"
     *
     * @param  Builder  $builder
     * @param  string   $param
     *
     * @return void
     */
    public function PrepareBuilderIdsByParam(Builder $builder, string $param): void
    {
        $idList = explode(',', $param);
        foreach ($idList as $id) {
            if (!($id = trim($id))) {
                continue;
            }

            $between = explode('-', $id);

            if (count($between) === 2) { // found "x-y"
                if (!$between[0]) { // "-y"
                    $builder->where('id', '<=', $between[1]);
                } elseif (!$between[1]) { // "x-"
                    $builder->where('id', '>=', $between[0]);
                } else { // "x-y"
                    $builder->whereBetween('id', $between);
                }
            } else { // simple value
                $builder->where('id', $id);
            }
        }
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
        //$this->debug("resolving: ", [$dotParts, $whereValue]);
        if (count($dotParts) > 1) {
            if (count($dotParts) === 2) {
                if ($dotParts[0] === 'pivot') {
                    //$builder->relationXXXX()->wherePivot($dotParts[1], $whereCondition, $whereValue);
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