<?php

namespace Modules\SystemBase\app\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\SystemBase\app\Services\Base\BaseService;

class ModelService extends BaseService
{
    /**
     * modify builders "where" by params like "1,2,3,7-14,81"
     *
     * @param  Builder  $builder
     * @param  string  $param
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
     * @param  string  $timestamp
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
     * @param  string  $timestamp
     *
     * @return void
     */
    public function PrepareBuilderSinceUpdated(Builder $builder, string $timestamp): void
    {
        $builder->where('updated_at', '>=', $timestamp);
    }
}