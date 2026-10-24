# Inferring Dates for Blueprint

## Status

Accepted (Last updated: 2026-09-07 10:05:00 CST)

## Context

Dates have many accepted formats. To keep inference predictable across data sources, this library supports the common ISO-style date format:

- `yyyy-MM-dd`

## Decision

The inferrer recognizes `yyyy-MM-dd` as a date. Other date formats are inferred as strings unless supported by another explicit inference rule.

## Consequences

- The library will infer dates in the `yyyy-MM-dd` format as Laravel Blueprint `date` columns.
- Dates in other formats will be treated as literal strings, which may require additional handling by the user.
