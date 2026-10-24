# Agent Instructions for Infer Laravel Blueprint

This repository is a PHP utility that reads tabular data from CSV, JSON, XML, Excel, and HTTP sources and infers Laravel Shift Blueprint definitions.

See [README.md](README.md) for usage examples and dependency references.

## What this project does

The main goal is schema inference for Laravel Blueprint output:

- `src/Blueprint/Parsers/*Parser.php` load source data and return a `BlueprintColumnCollection`.
- `src/Blueprint/Inferrers/BlueprintColumnTypeInferrer.php` chooses Laravel column types and modifiers by analyzing values in each column.
- `src/Blueprint/Models/` contains the output schema models.
- `src/Blueprint/Lexers/` converts schema models into Blueprint syntax.
- `src/Support/ColumnStats.php` tracks observations used for inference, including nullability, numeric state, date/time detection, string lengths, uniqueness, sequences, and value ranges.

Parser implementations should remain thin and source-specific. Keep type detection in the inferrer and reuse `ColumnStats` for cross-source inference.

## Source tree and responsibilities

- `src/Console.php` accepts CLI options and dispatches to Blueprint parsers.
- `index.php` bootstraps the command-line application.
- `scripts/` contains packaging, documentation, and fixture-building helpers.
- `src/Blueprint/Enums/` defines supported Laravel column types, modifiers, and configuration values.
- `src/Blueprint/Interfaces/` defines parser, inferrer, and model contracts.
- `src/Blueprint/Models/` contains the inferred schema and output configuration.

All parsers follow the same pattern:

1. Read rows from a source with Flow PHP ETL.
2. Feed the rows into the Blueprint inferrer.
3. Return a typed collection of inferred columns.

## Important conventions

- Use PHP 8.3+ syntax and `declare(strict_types=1);` in PHP files.
- Follow PSR-12 style and use explicit type declarations.
- Prefer enums for supported types and modifiers instead of raw strings.
- Keep interfaces and contracts under `src/Blueprint/Interfaces`.
- Update the relevant tests when adding a parser or changing inference behavior.

## Testing expectations

The project uses Pest:

- `tests/Blueprint/Feature/` for inference, parser, and CLI behavior.
- `tests/Blueprint/fixtures/` for source data and expected Blueprint output.

Tests should validate real inference results from data files rather than mock-only behavior.

## Build and verification

- Install dependencies: `composer install`
- Run tests: `composer run test`
- Check code style: `composer run lint`
- Build fixtures: `composer test:build`
- Build the standalone CLI: `composer run build`
- For each modified PHP file, run focused PHPStan analysis:
  `composer exec -- phpstan analyse <path-to-file.php> --no-progress --error-format=json`

When editing `README.md`, update `scripts/build-readme.php` and run `composer build:readme`. When changing the signature of `src/Console.php`, also regenerate the README and update related tests.
