<?php // database/migrations/2026_08_14_000100_make_catalog_content_translatable.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** table => [columns that become translatable] */
    private const TARGETS = [
        'products' => ['name', 'description', 'story'],
        'variants' => ['description'],
        'product_images' => ['alt_text'],
        'variant_options' => ['name'],
        'option_values' => ['value'],
        'personalization_options' => ['label'],
    ];

    /** Columns declared as string(255); two locales will not fit. */
    private const WIDEN = [
        'products' => ['name'],
        'variants' => ['description'],
        'product_images' => ['alt_text'],
        'variant_options' => ['name'],
        'option_values' => ['value'],
        'personalization_options' => ['label'],
    ];

    public function up(): void
    {
        foreach (self::WIDEN as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $column) {
                    $t->text($column)->nullable()->change();
                }
            });
        }

        foreach (self::TARGETS as $table => $columns) {
            foreach ($columns as $column) {
                $this->wrap($table, $column);
            }
        }
    }

    public function down(): void
    {
        foreach (self::TARGETS as $table => $columns) {
            foreach ($columns as $column) {
                $this->unwrap($table, $column);
            }
        }
    }

    /** 'Bifold wallet' becomes {"en":"Bifold wallet"}. */
    private function wrap(string $table, string $column): void
    {
        DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $column) {
            foreach ($rows as $row) {
                $value = $row->{$column};

                if ($value === null || $value === '') {
                    continue;
                }

                // Idempotent: a value that is already a JSON object is left alone.
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    continue;
                }

                DB::table($table)->where('id', $row->id)->update([
                    $column => json_encode(
                        [config('app.fallback_locale') => $value],
                        JSON_UNESCAPED_UNICODE
                    ),
                ]);
            }
        });
    }

    /** {"en":"Bifold wallet"} becomes 'Bifold wallet'. */
    private function unwrap(string $table, string $column): void
    {
        DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $column) {
            foreach ($rows as $row) {
                $decoded = json_decode((string) $row->{$column}, true);

                if (! is_array($decoded)) {
                    continue;
                }

                DB::table($table)->where('id', $row->id)->update([
                    $column => $decoded[config('app.fallback_locale')] ?? reset($decoded) ?: '',
                ]);
            }
        });
    }
};
