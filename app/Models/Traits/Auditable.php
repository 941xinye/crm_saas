<?php

namespace App\Models\Traits;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 自动审计 Trait：挂到 Model 事件，自动记录 CRUD 操作。
 *
 * 用法：在业务模型中 use Auditable;
 * 已知限制：Model::query()->update() / delete() 批量操作不触发单模型事件。
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            AuditService::record($model, 'created', null, static::snapshot($model));
        });

        // updated 事件时 Eloquent 的 getOriginal() 仍返回修改前的值（original 在 save() 开始时快照），
        // 无需在 updating 里用静态变量传递——直接调用 snapshotOriginal() 即可。
        static::updated(function (Model $model) {
            // SoftDeletes::restore() 会同时触发 restored + updated，
            // 此时 deleted_at 从非 null 变成 null，只改了这一个字段——跳过避免重复审计。
            $originalDeleted = $model->getOriginal('deleted_at');
            $currentDeleted = $model->deleted_at;
            $isRestoreSideEffect = $originalDeleted !== null && $currentDeleted === null;
            if ($isRestoreSideEffect) {
                return;
            }

            AuditService::record(
                $model,
                'updated',
                static::snapshotOriginal($model),
                static::snapshot($model),
            );
        });

        static::deleted(function (Model $model) {
            AuditService::record($model, 'deleted', static::snapshot($model), null);
        });

        // restored 事件注册依赖 SoftDeletes 提供的静态方法；
        // 未使用 SoftDeletes 的模型（如 Attachment）不存在该静态方法，
        // static::restored() 会落入 __callStatic -> new static，在 boot 期间触发递归 LogicException（Laravel 12 递归保护）。
        if (in_array(SoftDeletes::class, class_uses_recursive(static::class))) {
            static::restored(function (Model $model) {
                AuditService::record($model, 'restored', null, static::snapshot($model));
            });
        }
    }

    /**
     * 取模型当前 fillable 字段快照。
     */
    protected static function snapshot(Model $model): array
    {
        $fillable = $model->getFillable();
        $data = [];
        foreach ($fillable as $field) {
            if ($field === 'tenant_id') {
                continue;
            }
            $value = $model->$field;
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }
            $data[$field] = $value;
        }
        return $data;
    }

    /**
     * 取 original 值快照（updated/deleted 事件里调，此时 original 还是修改前的值）。
     */
    protected static function snapshotOriginal(Model $model): array
    {
        $fillable = $model->getFillable();
        $data = [];
        foreach ($fillable as $field) {
            if ($field === 'tenant_id') {
                continue;
            }
            $value = $model->getOriginal($field);
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }
            $data[$field] = $value;
        }
        return $data;
    }
}
