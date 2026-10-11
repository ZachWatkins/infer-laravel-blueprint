---
name: fix-phpstan-file
description: "Find and fix PHPStan issues in one PHP file with a targeted analysis run. Use when asked to fix, resolve, or clean up PHPStan errors for a single file."
---

# Fix PHPStan Issues in One File

Resolve reported PHPStan errors for the requested PHP file with the fewest targeted steps.

1. Run the project's configured PHPStan command for only that file. In this repository, use `composer run analyse -- <path> --no-progress --error-format=json`.
2. For each reported error, make the smallest correct change in the target file. Follow any identifier-specific guidance printed by PHPStan; fix the underlying issue rather than suppressing it.
3. Update the PHPStan baseline if necessary, using `composer exec -- phpstan analyse --generate-baseline`.
4. Rerun the same targeted analysis command and confirm it passes. Do not run the full suite unless the change needs broader validation.
5. Report the change and the verification result. Do not modify unrelated files or pre-existing worktree changes.

Read surrounding code only when needed to understand an error or preserve the file's existing conventions. Do not add ignores, baseline entries, casts, or assertions solely to silence PHPStan.
