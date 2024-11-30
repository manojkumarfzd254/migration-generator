<?php

namespace Manojkumar\MigrationGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateMigrationsCommand extends Command
{
    protected $signature = 'make:migrations-from-db {connection?}';
    protected $description = 'Generate migration files from an existing database';

    public function handle(): void
    {
        $connection = $this->argument('connection') ?? config('database.default');
        $driver = DB::connection($connection)->getDriverName();

        if (!in_array($driver, ['mysql', 'pgsql'])) {
            $this->error('Only MySQL and PostgreSQL are supported.');
            return;
        }

        $tables = $this->getTables($connection, $driver);

        if (empty($tables)) {
            $this->error('No tables found in the database.');
            return;
        }

        foreach ($tables as $tableName) {
            if($tableName == "migrations")
                continue;
            $this->generateMigration($connection, $driver, $tableName);
        }

        $this->info('Migration files generated successfully.');
    }

    protected function getTables(string $connection, string $driver): array
    {
        if ($driver === 'mysql') {
            $result = DB::connection($connection)->select('SHOW TABLES');
            return array_map(fn($table) => reset($table), $result);
        }

        if ($driver === 'pgsql') {
            $result = DB::connection($connection)->select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
            return array_column($result, 'tablename');
        }

        return [];
    }

    protected function generateMigration(string $connection, string $driver, string $tableName): void
    {
        $columns = $this->getColumns($connection, $driver, $tableName);

        $migrationStubPath = __DIR__ . '/../stubs/migration.stub';
        if (!File::exists($migrationStubPath)) {
            $this->error("Migration stub file not found at: $migrationStubPath");
            return;
        }

        $migrationStub = File::get($migrationStubPath);
        $migrationContent = str_replace(
            ['{{ tableName }}', '{{ columns }}', '{{ compositePrimaryKeys }}'],
            [
                $tableName,
                $this->generateColumns($columns, $driver, $tableName, $connection),
                $this->handleCompositePrimaryKeys($connection, $driver, $tableName),
            ],
            $migrationStub
        );

        $fileName = database_path("migrations/" . date('Y_m_d_His') . "_create_{$tableName}_table.php");
        File::put($fileName, $migrationContent);
        $this->info("Generated migration for table: $tableName");
    }

    protected function getColumns($connection, $driver, $tableName)
    {
        if ($driver === 'mysql') {
            $columns = DB::connection($connection)->select("SHOW COLUMNS FROM $tableName");
        } elseif ($driver === 'pgsql') {
            $columns = DB::connection($connection)->select("
            SELECT column_name, data_type, is_nullable, column_default 
            FROM information_schema.columns 
            WHERE table_name = '$tableName'
        ");
        }

        // Debugging
        foreach ($columns as $column) {
            $this->info(json_encode($column));
        }

        return $columns;
    }


    protected function generateColumns(array $columns, string $driver, string $tableName, string $connection): string
    {
        $schema = '';

        foreach ($columns as $column) {
            $name = $driver === 'mysql' ? $column->Field : $column->column_name;
            $type = $driver === 'mysql'
                ? $this->mapMysqlColumnType($column->Type)
                : $this->mapPgsqlColumnType($column->data_type);

            $nullable = ($driver === 'mysql' ? $column->Null : $column->is_nullable) === 'YES' ? '->nullable()' : '';
            $default = $this->getDefaultValue($driver, $column);

            if ($this->isPrimaryKey($connection, $driver, $tableName, $name) && $name === 'id') {
                $schema .= "\$table->id();\n";
            } else {
                $schema .= "            \$table->$type('$name')$nullable$default;\n";
            }
        }

        return $schema;
    }


    protected function mapMysqlColumnType(string $type): string
    {
        if (Str::contains($type, 'int')) return 'integer';
        if (Str::contains($type, 'varchar')) return 'string';
        if (Str::contains($type, 'text')) return 'text';
        if (Str::contains($type, 'date')) return 'date';
        if (Str::contains($type, 'timestamp')) return 'timestamp';
        if (Str::contains($type, ['float', 'double'])) return 'float';
        if (Str::contains($type, 'decimal')) return 'decimal';
        if (Str::contains($type, 'enum')) return 'enum';
        if (Str::contains($type, 'blob')) return 'binary';
        return 'string';
    }

    protected function mapPgsqlColumnType(string $type): string
    {
        return match ($type) {
            'smallint', 'integer', 'bigint' => 'integer',
            'character varying', 'varchar', 'char', 'text' => 'string',
            'boolean' => 'boolean',
            'date' => 'date',
            'timestamp without time zone', 'timestamp with time zone' => 'timestamp',
            'numeric', 'decimal' => 'decimal',
            'real', 'double precision' => 'float',
            'bytea' => 'binary',
            'json', 'jsonb' => 'json',
            default => 'string',
        };
    }

    protected function isPrimaryKey(string $connection, string $driver, string $tableName, string $columnName): bool
    {
        if ($driver === 'mysql') {
            $primaryKeys = DB::connection($connection)->select("SHOW KEYS FROM `$tableName` WHERE Key_name = 'PRIMARY'");
            return collect($primaryKeys)->contains('Column_name', $columnName);
        }

        if ($driver === 'pgsql') {
            $primaryKeys = DB::connection($connection)->select("
                SELECT a.attname
                FROM pg_index i
                JOIN pg_attribute a ON a.attnum = ANY(i.indkey)
                WHERE i.indrelid = '$tableName'::regclass AND i.indisprimary;
            ");
            return collect($primaryKeys)->contains('attname', $columnName);
        }

        return false;
    }

    protected function handleCompositePrimaryKeys(string $connection, string $driver, string $tableName): string
    {
        $keys = [];

        if ($driver === 'mysql') {
            $primaryKeys = DB::connection($connection)->select("SHOW KEYS FROM `$tableName` WHERE Key_name = 'PRIMARY'");
            $keys = array_column($primaryKeys, 'Column_name');
        }

        if ($driver === 'pgsql') {
            $primaryKeys = DB::connection($connection)->select("
                SELECT a.attname
                FROM pg_index i
                JOIN pg_attribute a ON a.attnum = ANY(i.indkey)
                WHERE i.indrelid = '$tableName'::regclass AND i.indisprimary;
            ");
            $keys = array_unique(array_column($primaryKeys, 'attname'));
        }

        return count($keys) > 1 ? "\$table->primary(['" . implode("', '", $keys) . "']);\n" : '';
    }

    protected function getDefaultValue(string $driver, $column): string
    {
        if ($driver === 'mysql') {
            $default = $column->Default ?? null;
        } elseif ($driver === 'pgsql') {
            $default = $column->column_default ?? null;
        } else {
            $default = null;
        }

        return $default !== null ? "->default('$default')" : '';
    }
}
