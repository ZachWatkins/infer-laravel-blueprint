---
name: infer-laravel-blueprint
description: "Use when: generating a Laravel Shift Blueprint YAML file from a data source in JSON, CSV, XLSX, or XML format, turning a plain-language request into a valid `infer-laravel-blueprint` CLI tool command, and asking for missing CLI options before running it for the current project workspace."
---

# Laravel Shift Blueprint Generator

ONLY EXECUTE the `infer-laravel-blueprint` CLI tool located at `./infer-laravel-blueprint`. DO NOT use python, composer, or any other CLI tool as a feature of this skill.
ONLY READ files created by the `infer-laravel-blueprint` CLI tool.
DO NOT WRITE OR MODIFY any files yourself when using this skill.
DO NOT search for files unless they are parameters for the `infer-laravel-blueprint` CLI tool.
The project workspace is the VS Code workspace folder where the user is running the commands.

## Context and Scope

You are operating as a global skill. You must ground all of your responses, logic, and generation in the context of the user's active, open VS Code workspace directory. You will invoke the `infer-laravel-blueprint` CLI tool as specified in the command template.

## CLI Usage

```shell
Infer a Laravel Shift Blueprint file from various data sources. By Zach Watkins.
Usage: infer-laravel-blueprint [--controller-methods=index,create,store,edit,update,show,destroy,api.index,api.store,api.store,api.update,api.show,api.destroy,<custom>] [--model=<name>] [--resource=web,api,index,create,store,edit,update,show,destroy,api.index,api.store,api.store,api.update,api.show,api.destroy] [--seeders]  [--view=blade|inertia] [--cwd=<current-working-directory>] [--data-selector=<selector>] [--dry-run] [--http-header=<name>:<value>] [--http-timeout=<seconds>] [--save] [--help] <path-or-url>

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

## Pre-Execution Steps

Before performing the primary task, you must:

1. Get the root directory of the open project workspace to use as the `--cwd` parameter.
2. Ensure the `infer-laravel-blueprint` CLI parameters only read files from and save files to the open VS Code workspace folder.

## Purpose

Use the `infer-laravel-blueprint` CLI tool in this repository to transform a JSON, CSV, XLSX, or XML dataset into a Laravel Shift Blueprint YAML file. The skill converts a natural-language request into the correct command, asks for any missing CLI options, and verifies the generated output.

## File Access Boundaries

- Do not read any data source provided to the script.
- If the task requires behavior not described in `infer-laravel-blueprint --help`, inform the user of this limitation and any error message thrown.
- Treat `infer-laravel-blueprint` as the single source of truth for supported flags, arguments, and validation rules.
- Only read and write files within the open VS Code workspace folder.

## Required Inputs

The user may provide any of the following in their prompt:

- a local data file path or URL to infer a Laravel Shift Blueprint file for.
- HTTP headers for the URL request.
- The project workspace using this skill as the current working directory parameter.
- The desired model name for the Laravel Shift Blueprint file.
- The desired view type for the Laravel Shift Blueprint file (blade or inertia).
- The desired resource type for the Laravel Shift Blueprint file (web, api, index, create, store, edit, update, show, destroy, api.index, api.store, api.update, api.show, api.destroy).
- The desired controller methods for the Laravel Shift Blueprint file (index, create, store, edit, update, show, destroy, api.index, api.store, api.update, api.show, api.destroy). Other valid custom methods can also be specified using alphabetical characters.
- Whether to generate seeders for the model in the Laravel Shift Blueprint file.

If the user does not provide a value for an option, ask a short follow-up question before executing the command.

## Workflow

1. Identify the dataset source.
   - If the user provides a file path local to the project workspace, use it as a fully qualified path in the positional argument.
   - If the user provides a URL, use it as-is in the positional argument.
   - If neither is present, ask the user for the JSON, CSV, XLSX, or XML file path.

2. Identify the current working directory.
   - The user should not need to provide the --cwd option explicitly; use the project workspace as the default value.

3. Build the command.
   - Use the CLI entry point: `infer-laravel-blueprint`
   - Add only the flags that the user provided or that you confirmed.
   - Preserve quoting for file paths and URLs.

4. Run the command and inspect the result.
   - First run the command with the `--dry-run` flag to check for errors or warnings without generating the file.
   - If the command fails, explain the validation error and ask which option should change.
   - If the dry-run succeeds without errors, proceed to run the command to generate the file.
   - If the user wants to preview the file and not save it, then run the command without the `--save` flag to capture the results from stdout and provide this to the user.
   - If the generation succeeds, summarize where the YAML was written or what was previewed.

## Decision Points

- If the user wants to use the result to run Laravel Shift Blueprint to scaffold the Laravel code, then run `php artisan blueprint:build <model-blueprint.yaml>` in their project directory, replacing `<model-blueprint.yaml>` with the actual file name of the generated Blueprint YAML file.
- If the user only wants to preview the generated Blueprint YAML without saving it, run the command without the `--save` flag and do not execute `php artisan blueprint:build`.
- If the user wants to inspect a data file to determine a database schema, run the command with the `--db=<database_name>` and `--format=sql` flags.

## Quality Bar

- Ask for any missing required option before running the command.
- Prefer a short confirmation step when the request is ambiguous.
- Do not invent undocumented flags.

## Example Prompts

- “Generate a Blueprint file from data.json for a User model and save it.”
- “Use the CSV at ./data.csv to create a blueprint web resource and preview the YAML.”
- “Fetch https://example.com/users.json and save it as a User model blueprint with web resources.”
- ”Generate a Blueprint file from https://example.com/users.json as a User model with Inertia views and then scaffold the Laravel code.”
- “Create the Blueprint file for my dataset, but ask me any missing options before running it.”
- “Show me the database schema compatible with data.xlsx.”

## Follow-Up Questions

Ask the user only for the values that are missing. Good examples:

- “Which file or URL should I use as the source data?”
- “What name should I use for the model?”
- “What resource type should I generate: api, web, a subset of them, or none?”
- “What controller methods should be included in the generated Blueprint file?”
- “Do you want to seed the database with example models?”
- “Should users be able to modify your app's models through the generated interface?”
- “Should users be able to delete your app's models through the generated interface?”
- “Do you want to use Inertia views in the generated Blueprint file?”
- “Do you want to preview the generated Blueprint YAML in the terminal or save it to a file?”
- “Do you want to run the Laravel Shift Blueprint scaffolding after generating the Blueprint file?”

## Completion Check

The task is complete only when:

- the source dataset is identified,
- all required options are either supplied or confirmed,
- the command reflects the user’s intent,
- the generated Blueprint YAML file or preview is returned to the user,
- the user has confirmed whether they want to run the Laravel Shift Blueprint scaffolding command `php artisan blueprint:build <model-blueprint.yaml>`.
- and any errors are explained clearly if the generation fails.