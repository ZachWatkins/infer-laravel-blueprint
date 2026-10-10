# Infer Data Schema

**This is not intended to be used in production environments. It is only intended for development and testing purposes.**

[![Test](https://github.com/ZachWatkins/infer-laravel-blueprint/actions/workflows/test.yml/badge.svg)](https://github.com/ZachWatkins/infer-laravel-blueprint/actions/workflows/test.yml) [![Audit](https://github.com/ZachWatkins/infer-laravel-blueprint/actions/workflows/audit.yml/badge.svg)](https://github.com/ZachWatkins/infer-laravel-blueprint/actions/workflows/audit.yml)

This PHP library performs limited inference of the SQL column schema for a given data source and can either provide those details or a [Laravel Shift Blueprint](https://blueprint.laravelshift.com/) YAML file, which can then be used to scaffold the relevant [Laravel](https://laravel.com/) PHP framework files which implement that data model into an existing application.

## Example CLI usage

You can use the `index.php` file from the command line to create a Laravel Shift Blueprint file.

Given the following example CSV file:

```csv
id,name,locale,birthdate,accept_terms
1,"John Doe",en-US,1990-01-01,true
2,"Jane Smith",en-GB,1992-02-02,false
3,"Monica Lee",fr-FR,1994-05-05,true
4,"Bob Mitchell",en-CA,1985-03-03,true
5,"Alice Johnson",en-AU,1993-04-04,false
6,"Carlos Gomez",es-MX,1988-06-06,true
7,"Akira Tanaka",ja-JP,1991-07-07,false
8,"Hans Schmidt",de-DE,1983-08-08,true
9,"Rahul Sharma",en-IN,1995-09-09,false
```

Running `php index.php --model=User path/to/users.csv` will produce the following result:

```yaml
models:
  User:
    id: tinyIncrements unique unsigned auto_increment
    name: tinyText unique
    locale: char:5 unique
    birthdate: date unique
    accept_terms: boolean

controllers:
  User:
    index:
      query: all:users
      inertia: User/Index with:users
    create:
      inertia: User/Create
    store:
      validate: id, name, locale, birthdate, accept_terms
      save: user
      flash: user.id
      redirect: users.index
    show:
      inertia: User/Show with:user
    edit:
      inertia: User/Edit with:user
    update:
      validate: id, name, locale, birthdate, accept_terms
      update: user
      flash: user.id
      redirect: users.index
    destroy:
      delete: user
      redirect: users.index

seeders: User

```

Blueprint YAML is printed to the console by default. Add `--save` to write it to `user-blueprint.yaml` instead:

```sh
php index.php --model=User --save path/to/users.csv
```

To use this file with Laravel Shift Blueprint in an existing Laravel application to scaffold the relevant framework files, run:

```sh
php artisan blueprint:build user-blueprint.yaml
```

The CLI supports CSV, JSON, XML, and Excel files (`.xlsx`, `.xls`, and `.ods`). With `--format=blueprint`, it also accepts an HTTP or HTTPS URL. The URL path extension selects the matching parser and default `Accept` header (`text/csv`, `application/json`, `application/xml`, or the matching spreadsheet media type); query strings do not affect format detection. URLs without an extension default to JSON. HTTP sources use a single GET request. Add or override request headers with repeatable `--http-header=<name>:<value>` options (note: the CLI rejects attempts to use credential headers for security purposes). Set `--http-timeout=<seconds>` to control the timeout for each HTTP request; it defaults to 30 seconds and accepts 1-3600 seconds. Before downloading, the CLI sends a HEAD request and rejects responses whose declared length exceeds one quarter of remaining PHP memory, capped at 64 MiB. The same ceiling is enforced on the download:

```sh
php index.php --model=Post --http-header="User-Agent: MyApp" https://jsonplaceholder.typicode.com/posts
```

Blueprint YAML is printed to the console by default. With `--save`, HTTP output is written to the current working directory, or the directory specified by `--cwd`.

To see all available options, run `php index.php --help`:

```sh
$ php index.php --help
Infer a Laravel Shift Blueprint file from various data sources. By Zach Watkins.
Usage: index.php [--controller-methods=index,create,store,edit,update,show,destroy,api.index,api.store,api.store,api.update,api.show,api.destroy,<custom>] [--model=<name>] [--resource=web,api,index,create,store,edit,update,show,destroy,api.index,api.store,api.store,api.update,api.show,api.destroy] [--seeders]  [--view=blade|inertia] [--cwd=<current-working-directory>] [--data-selector=<selector>] [--dry-run] [--http-header=<name>:<value>] [--http-timeout=<seconds>] [--save] [--help] <path-or-url>

Options:
  [--controller-methods=] Specify the Blueprint controller methods.
                          Accepts: index, create, store, edit, update, show,
                          destroy, api.index, api.store, api.store, api.update,
                          api.show, api.destroy, <custom>. Default: none.
  [--model=]              Specify the Blueprint model name.
  [--resource=]           Define the Blueprint model controller resources.
                          Accepts: web, api, index, create, store, edit, update,
                          show, destroy, api.index, api.store, api.store,
                          api.update, api.show, api.destroy. Default: none.
  [--seeders]             Include seeders in Blueprint output.
  [--view=]               Set the Blueprint view type. Accepts: blade, inertia.
                          Default: blade.
  [--cwd=]                Set the current working directory.
  [--data-selector=]      Specify a data selector (e.g., JSONPath, XPath) for
                          extracting relevant data from the source.
  [--dry-run]             Perform a trial run without making any changes.
  [--http-header=]        Add a request header for an HTTP Blueprint source.
                          May be specified more than once. Rejects credential
                          headers: Authorization, Proxy-Authorization, Cookie.
  [--http-timeout=]       Set the timeout in seconds for each HTTP request.
                          Default: 30 seconds. Accepts: 1-3600.
  [--save]                Save the output to a file.
  [--help]                Display this help message.

```

## Install as a coding agent skill

To install the standalone executable CLI application as a system-wide GitHub Copilot agent skill at ~/.copilot/skills/infer-laravel-blueprint, clone this repository and then run the following command:

```sh
composer install:skill
```

If you use a different coding agent, you can run these commands replacing `path` with the folder you want to install the skill to:

```sh
composer build
php scripts/install-skill.php --destination=path
```

## Library usage

You can use the library to customize how the values from data sources are parsed:

```php
use ZachWatkins\InferLaravelBlueprint\Blueprint\Lexers\BlueprintFileLexer;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Models\BlueprintConfig;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Models\BlueprintModel;
use ZachWatkins\InferLaravelBlueprint\Blueprint\Parsers\CsvParser;

$parser = new CsvParser();
$blueprintColumnCollection = $parser->parse('path/to/your/file.csv');
$model = new BlueprintModel('Model', $blueprintColumnCollection);
$config = new BlueprintConfig(
    models: [$model],
    resources: ['web'],
    seeders: true,
    view: 'inertia',
);
$lexer = new BlueprintFileLexer;
$result = $lexer->toString($config);

file_put_contents('user-blueprint.yaml', $result);

// models:
//   User:
//     id: tinyIncrements unique unsigned auto_increment
//     name: tinyText unique
//     locale: char:5 unique
//     birthdate: date unique
//     accept_terms: boolean
//
// controllers:
//   User:
//     index:
//       query: all:users
//       inertia: User/Index with:users
//     create:
//       inertia: User/Create
//     store:
//       validate: id, name, locale, birthdate, accept_terms
//       save: user
//       flash: user.id
//       redirect: users.index
//     show:
//       inertia: User/Show with:user
//     edit:
//       inertia: User/Edit with:user
//     update:
//       validate: id, name, locale, birthdate, accept_terms
//       update: user
//       flash: user.id
//       redirect: users.index
//     destroy:
//       delete: user
//       redirect: users.index
//
// seeders: User
//
```

## Development

To enable the pre-commit hook in this checkout, run:

```sh
git config core.hooksPath .githooks
```

The hook runs Pint with `--repair --format=txt` on staged PHP files. If Pint changes a file, it exits with a nonzero status; review and stage the formatting changes before retrying the commit.

To build this readme file, run the following command:

```sh
composer build:readme
```

To build the test fixtures after making changes to the library, run the following command:

```sh
composer test:build
```

To build the library as a standalone executable CLI application, run the following command:

```sh
composer build
```

To install the standalone executable CLI application as a system-wide GitHub Copilot agent skill, run the following command:

```sh
composer install:skill
```

If you use a different coding agent, you can run these commands replacing `path` with the folder you want to install the skill to:

```sh
composer build
php scripts/install-skill.php --destination=path
```

## Additional Resources

- [PHP](https://www.php.net/)
- [Flow PHP ETL](https://flow-php.com/documentation/quick-start/)
- [Flow PHP ETL CSV Adapter](https://flow-php.com/documentation/components/adapters/csv/)
- [Flow PHP ETL JSON Adapter](https://flow-php.com/documentation/components/adapters/json/)
- [Flow PHP ETL XML Adapter](https://flow-php.com/documentation/components/adapters/xml/)
- [Flow PHP ETL Excel Adapter](https://flow-php.com/documentation/components/adapters/excel/)
- [Flow PHP ETL HTTP Adapter](https://flow-php.com/documentation/components/adapters/http/)
- [Pest PHP](https://pestphp.com/docs/introduction)
- [SQL Server Data Types](https://learn.microsoft.com/en-us/sql/t-sql/data-types/data-types-transact-sql)