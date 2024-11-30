# Migrations Generator for Laravel

This package provides a command for generating Laravel migration files from an existing MySQL or PostgreSQL database. It scans your database tables and generates migration files based on the schema, allowing you to version-control your database changes.

## Features

- **Supports MySQL and PostgreSQL databases**
- **Generates migration files automatically from existing database tables**
- **Handles primary keys (including composite keys)**
- **Supports nullable, default, and column types**

## Installation

You can install this package via Composer. In your Laravel project directory, run the following command:

```bash
composer require manojkumar/migration-generator
```


Laravel Auto-discovery (optional)
Laravel will automatically register the service provider if you are using Laravel 5.5 or later. If you are using an older version of Laravel, you will need to manually register the service provider.

To manually register the service provider, add it to the providers array in config/app.php

```bash
Manojkumar\MigrationGenerator\MigrationGeneratorServiceProvider::class,
```

Usage
Once installed, you can use the Artisan command to generate migration files from your database.

Generate Migrations
To generate migration files for all tables in your database, run the following command:

```bash
php artisan make:migrations-from-db
```

By default, the package will use the database connection defined in your .env file. If you want to specify a different database connection, you can pass the connection name as an argument:

```bash
php artisan make:migrations-from-db mysql
```
or

```bash
php artisan make:migrations-from-db pgsql
```

This command will generate migration files for all tables in the specified database connection.

Migration Files
The generated migration files will be placed in the database/migrations directory. Each migration file will contain the necessary schema definitions for the corresponding database table.

Example Output
If you run the command for a table called users, the generated migration file will include code like this:

```bash
public function up()
{
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamps();
    });
}
```

Customization
You can customize the generated migration files by modifying the stub file used by the package. To do so, first, publish the stub file to your project:

```bash
php artisan vendor:publish --provider="Manojkumar\MigrationGenerator\MigrationGeneratorServiceProvider"
```

This will publish the migration stub file to resources/stubs/migration.stub. You can edit this file to adjust the generated migration content as needed.

