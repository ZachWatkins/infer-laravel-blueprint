# Agent Instructions for Infer Data Schema

This repository is a PHP schema-inference utility for turning raw tabular data into either:

- a SQL schema (`src/SQL/...`), or
- a Laravel Shift Blueprint definition (`src/Blueprint/...`).

The library reads source data from CSV, JSON, XML, Excel, and HTTP endpoints using Flow PHP ETL and then infers column names, data types, and modifiers by scanning the values in each column. The project is intentionally opinionated: it tries to produce the safest general-purpose schema for a target database engine or Blueprint output, while keeping parser implementations thin and consistent.

See [README.md](README.md) for a higher-level overview, usage examples, and dependency references.

## What this project does

The main goal is not generic ETL. It is schema inference from sample data.

- `src/SQL/Parsers/*Parser.php` load data from a source and return an instance of `SQLColumnCollection`.
- `src/SQL/Inferrers/ColumnTypeInferrer.php` decides the SQL column type and modifiers by analyzing row values.
- `src/SQL/Enums/*` define database-specific type families (`SQLite`, `MySQL`, `SQL Server`).
- `src/Blueprint/Parsers/*Parser.php` do the same thing for Laravel Blueprint output rather than raw SQL.
- `src/Blueprint/Inferrers/BlueprintColumnTypeInferrer.php` chooses Laravel column types and modifiers such as `nullable`, `unique`, `unsigned`, or `increments`.

In other words, most code changes should be framed around one question: "How does this data source become a consistent schema object?"

## Source tree and real responsibilities

### CLI and entry points

- `src/Console.php` is the command-line surface for the app. It accepts flags such as `--db`, `--format=sql|blueprint`, and Blueprint-specific options, then dispatches to the correct parser.
- `index.php` is the application bootstrap that invokes the console.
- `scripts/` contains packaging and helper scripts such as building the standalone binary.

### SQL layer

- `src/SQL/Parsers/` contains the source-specific parsers: CSV, JSON, XML, Excel, and HTTP.
- `src/SQL/Models/SQLColumn.php` and `SQLColumnCollection.php` hold the inferred schema output.
- `src/SQL/Interfaces/` defines parser and collection contracts.
- `src/SQL/Inferrers/ColumnTypeInferrer.php` is the central inference logic used by all SQL parsers.
- `src/Support/ColumnStats.php` tracks aggregate observations for each column: nullability, numeric state, date/time detection, string lengths, uniqueness, sequence detection, and value ranges.
- `src/SQL/Enums/` defines supported database types and their column-type mappings.

### Blueprint layer

- `src/Blueprint/Parsers/` mirrors the SQL parser structure for Blueprint output.
- `src/Blueprint/Models/` contains Blueprint schema models.
- `src/Blueprint/Inferrers/BlueprintColumnTypeInferrer.php` infers Laravel field types and Blueprint attributes from the same column stats.
- `src/Blueprint/Enums/` defines Laravel type names and Blueprint annotations used in generated files.
- `src/Blueprint/Lexers/` and related classes help convert inferred schema data into Blueprint syntax or definitions.

### Data source contract

All parsers follow the same pattern:

1. Read rows from a source with Flow PHP ETL.
2. Feed the rows into the relevant inferrer.
3. Return a typed collection of inferred columns.

This is the key architectural rule: parser code should stay thin and source-specific; type detection should live in the inferrer layer.

## Important project conventions

- Use PHP 8.3+ syntax and `declare(strict_types=1);` in all PHP files.
- Follow PSR-12 style and use explicit type declarations on properties, parameters, and return values.
- Prefer enums for supported types and modifiers instead of raw strings.
- Keep interfaces and contracts in the matching namespace (`src/SQL/Interfaces` or `src/Blueprint/Interfaces`).
- Do not create a new parser or inferrer without updating the corresponding contract and tests.
- Prefer reusing `ColumnStats` for cross-column analysis instead of duplicating inference rules in individual parser classes.
- Keep `src/SQL/...` and `src/Blueprint/...` behavior symmetric where possible:
  - same source data
  - same column discovery logic
  - different output representation only

## Working on this codebase

Before adding new functionality, confirm which layer owns the behavior:

- If the change is about converting a data source into rows, it belongs in a parser under `src/SQL/Parsers/` or `src/Blueprint/Parsers/`.
- If the change is about interpreting values into a column type, it belongs in an inferrer.
- If the change is about the emitted schema model, it belongs in the models or enums.
- If the change affects CLI behavior or supported flags, it belongs in `src/Console.php`.

## Testing expectations

The project uses Pest. Add or update tests in the relevant area:

- `tests/SQL/Feature/` for SQL inference and parser behavior
- `tests/Blueprint/Feature/` for Blueprint inference and parser behavior
- fixture files under `tests/SQL/fixtures/` and `tests/Blueprint/fixtures/` when the output depends on sample data

Each test should validate real inference results from actual data, not mock-only behavior.

## Build and verification commands

- Install dependencies: `composer install`
- Run the test suite: `composer run test`
- Check code style: `composer run lint`
- For every new or modified PHP file, run PHPStan on that file individually with concise output: `composer exec -- phpstan analyse <path-to-file.php> --no-progress --error-format=table` (for example, `composer exec -- phpstan analyse src/Console.php --no-progress --error-format=table`).
- When fixing PHPStan issues in a specific file, start with that file's analysis rather than the full `composer analyse` run; use each diagnostic's file, line, and identifier to guide a type-safe fix, then rerun the same focused analysis. Run broader analysis only when the fix affects shared behavior or targeted results indicate it is needed.
- Build the packaged CLI: `composer run build`

## Coding-agent guidance

When making code changes, aim for:

- small, focused edits
- consistent interfaces between source parsers and inferrers
- explicit, descriptive naming
- docblocks on public APIs and non-trivial methods
- regression coverage when changing inference logic

The real purpose of this repo is not to be a generic framework. It is a compact, data-source-driven schema generator that infers usable SQL or Laravel Blueprint definitions from sample records. That purpose should guide all implementation choices.

When making changes to `README.md`, instead make those changes to `scripts/build-readme.php` and run `composer build:readme`.

When making changes to the signature of `src/Console.php`, also run `composer build:readme` and update any related tests to reflect the new interface.
