# Inferring Decimal Values for Blueprint

## Status

Accepted (Last updated: 2026-08-29 21:25:00 CST)

## Context

Floating-point arithmetic can introduce rounding errors, while decimal values can preserve exact precision. The inferrer cannot determine how a value will be used, so it applies consistent inference rules based on the observed values.

## Decision

The library favors the Laravel Blueprint `decimal` type for numeric values with decimal points. It uses floating-point types only when the observed values exceed the supported decimal precision.

## Consequences

The inferred Blueprint declaration may favor precision over calculation speed. Users can adjust generated Blueprint definitions to match their application's requirements.
