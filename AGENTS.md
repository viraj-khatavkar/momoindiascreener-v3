# Project instructions

Before planning or editing, read the [rule index](.ai/rules/index.md) and the rules for each affected path. Search `.ai/rules` by topic for decisions that apply across paths. Use the project skills that match the task.

- Use Laravel Boost `search-docs` before changing code. Check the installed package version before using its API.
- Use Boost `database-query` for read-only queries and `database-schema` before writing models or migrations. Use `get-absolute-url` before sharing a project URL.
- Get approval before changing dependencies, adding a top-level directory, deleting a test, or creating database records in Tinker. Create documentation only on request.
- After a PHP change, run `vendor/bin/pint --dirty --format agent`.
- Run independent test commands in sequence when they use the [shared MySQL test database](.ai/rules/tests.md). Parallel test workers need separate databases.
- Use Boost `record-rule` for lasting decisions that the code, tests, and documentation do not explain. Keep one current entry per decision, scoped to the affected paths.
