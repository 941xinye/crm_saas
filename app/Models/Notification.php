<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

/**
 * 为 Laravel DatabaseNotification 的 data 字段加 array cast，
 * 使 where('data->key', $value) 可以做精确 JSON 查询，
 * 替代 LIKE '% "key":1 %' 的前缀匹配（activity_id=1 误匹配 10, 100...）。
 */
class Notification extends DatabaseNotification
{
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }
}
