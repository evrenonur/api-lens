# Conventional Commits Rules
#
# Commit message format:
#   <type>(<scope>): <description>
#
# Types:
#   feat     → New feature (MINOR version bump)
#   fix      → Bug fix (PATCH version bump)
#   perf     → Performance improvement (PATCH version bump)
#   refactor → Code refactoring (PATCH version bump)
#   docs     → Documentation change (no release)
#   style    → Code style change (no release)
#   test     → Test addition/modification (no release)
#   chore    → Maintenance (no release)
#   ci       → CI/CD changes (no release)
#   build    → Build system changes (no release)
#
# Breaking Change (MAJOR version bump):
#   feat!: breaking change description
#   or in commit body: BREAKING CHANGE: description
#
# Examples:
#   feat: add OpenAPI 3.1 export support
#   feat(ui): add response time histogram chart
#   fix(extractor): handle closure routes correctly
#   fix: empty body parsing for GET requests
#   perf(ui): lazy-load code snippet highlighter
#   refactor(extractor): extract common rule parsing logic
#   docs: update README installation section
#   test: add DocBlockExtractor unit tests
#   chore: update dependencies
#   feat!: redesign configuration structure
#
# Scope examples:
#   ui, extractor, controller, model, generator,
#   config, middleware, openapi, test, docs
