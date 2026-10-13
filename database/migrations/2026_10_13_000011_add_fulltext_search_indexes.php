<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 全局搜索 FULLTEXT 索引（P1）：
 * 为 MySQL 生产环境添加 ngram 分词全文索引（支持中文），
 * sqlite 等其他驱动跳过（GlobalSearch 自动回退 LIKE）。
 *
 * 索引列与列顺序必须与 GlobalSearch::SEARCH_FIELDS 一致，
 * 否则 MATCH(...) AGAINST(...) 无法命中索引。
 */
return new class extends Migration
{
    private const INDEXES = [
        'companies' => ['name', 'industry', 'website', 'phone'],
        'contacts' => ['first_name', 'last_name', 'email', 'phone', 'position'],
        'leads' => ['name', 'company_name', 'email', 'phone'],
        'opportunities' => ['title'],
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::INDEXES as $table => $columns) {
            $indexName = "{$table}_fulltext";

            if (Schema::hasIndex($table, $indexName)) {
                continue;
            }

            DB::statement(sprintf(
                'ALTER TABLE `%s` ADD FULLTEXT INDEX `%s` (%s) WITH PARSER ngram',
                $table,
                $indexName,
                implode(', ', array_map(fn (string $column) => "`{$column}`", $columns)),
            ));
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (array_keys(self::INDEXES) as $table) {
            Schema::table($table, function (Blueprint $schema) use ($table) {
                $schema->dropIndex("{$table}_fulltext");
            });
        }
    }
};
