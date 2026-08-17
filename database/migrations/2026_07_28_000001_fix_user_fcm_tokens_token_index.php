<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'user_fcm_tokens';

    private const TOKEN_HASH_UNIQUE = 'user_fcm_tokens_token_hash_unique';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('token', 512);
                $table->char('token_hash', 64);
                $table->string('device_id')->nullable()->index();
                $table->string('platform', 20)->nullable();
                $table->string('app_version', 50)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->unique('token_hash', self::TOKEN_HASH_UNIQUE);
                $table->index(['user_id', 'is_active']);
                $table->index(['device_id', 'is_active']);
            });

            return;
        }

        if (! Schema::hasColumn(self::TABLE, 'token_hash')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->char('token_hash', 64)->nullable()->after('token');
            });
        }

        if (Schema::hasColumn(self::TABLE, 'id')
            && Schema::hasColumn(self::TABLE, 'token')
            && Schema::hasColumn(self::TABLE, 'token_hash')) {
            DB::table(self::TABLE)
                ->select(['id', 'token'])
                ->where(function ($query) {
                    $query->whereNull('token_hash')
                        ->orWhere('token_hash', '');
                })
                ->chunkById(500, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table(self::TABLE)
                            ->where('id', $row->id)
                            ->update([
                                'token_hash' => hash('sha256', (string) $row->token),
                            ]);
                    }
                });
        }

        if (Schema::hasColumn(self::TABLE, 'token_hash')
            && $this->columnIsNullable('token_hash')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->char('token_hash', 64)->nullable(false)->change();
            });
        }

        if (Schema::hasColumn(self::TABLE, 'token_hash')
            && ! Schema::hasIndex(self::TABLE, ['token_hash'], 'unique')) {
            if (Schema::hasIndex(self::TABLE, self::TOKEN_HASH_UNIQUE)) {
                Schema::table(self::TABLE, function (Blueprint $table) {
                    $table->dropIndex(self::TOKEN_HASH_UNIQUE);
                });
            }

            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unique('token_hash', self::TOKEN_HASH_UNIQUE);
            });
        }

        if (Schema::hasColumn(self::TABLE, 'token')) {
            foreach ($this->uniqueIndexesFor(['token']) as $indexName) {
                Schema::table(self::TABLE, function (Blueprint $table) use ($indexName) {
                    $table->dropUnique($indexName);
                });
            }

            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->string('token', 512)->change();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (Schema::hasColumn(self::TABLE, 'token_hash')) {
            foreach ($this->uniqueIndexesFor(['token_hash']) as $indexName) {
                Schema::table(self::TABLE, function (Blueprint $table) use ($indexName) {
                    $table->dropUnique($indexName);
                });
            }

            if (Schema::hasColumn(self::TABLE, 'token_hash')) {
                Schema::table(self::TABLE, function (Blueprint $table) {
                    $table->dropColumn('token_hash');
                });
            }
        }

        if (Schema::hasColumn(self::TABLE, 'token')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                // Do not recreate the invalid 4096-character unique index.
                $table->string('token', 4096)->change();
            });
        }
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function uniqueIndexesFor(array $columns): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $index): string => $index['name'],
            array_filter(
                Schema::getIndexes(self::TABLE),
                static fn (array $index): bool => $index['unique']
                    && $index['columns'] === $columns,
            ),
        ));
    }

    private function columnIsNullable(string $columnName): bool
    {
        if (! Schema::hasTable(self::TABLE)
            || ! Schema::hasColumn(self::TABLE, $columnName)) {
            return false;
        }

        foreach (Schema::getColumns(self::TABLE) as $column) {
            if ($column['name'] === $columnName) {
                return (bool) $column['nullable'];
            }
        }

        return false;
    }
};
