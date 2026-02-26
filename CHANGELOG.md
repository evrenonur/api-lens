# Changelog

## [1.2.1](https://github.com/evrenonur/api-lens/compare/v1.2.0...v1.2.1) (2026-02-26)

### ♻️ Refactoring

* rename package from api-lens/api-lens to evrenonur/api-lens ([4a41347](https://github.com/evrenonur/api-lens/commit/4a41347a254bd0098b3a1e63fb81bae407c6b649))

## [1.2.0](https://github.com/evrenonur/api-lens/compare/v1.1.2...v1.2.0) (2026-02-26)

### 🚀 New Features

* **ui:** show version badge and update notification in TopNav ([91cbc1d](https://github.com/evrenonur/api-lens/commit/91cbc1d07b3376f21c5dd77e0d41254af6a31fa8))

## [1.1.2](https://github.com/evrenonur/api-lens/compare/v1.1.1...v1.1.2) (2026-02-26)

### 🐛 Bug Fixes

* use __DIR__ based asset path for portable installation ([b77bd49](https://github.com/evrenonur/api-lens/commit/b77bd492987f459a953896734df461d20cc9938c))

## [1.1.1](https://github.com/evrenonur/api-lens/compare/v1.1.0...v1.1.1) (2026-02-26)

### 🐛 Bug Fixes

* serve assets via route instead of public path for zero-config install ([dcfcd0d](https://github.com/evrenonur/api-lens/commit/dcfcd0d84d66e5f1df57dd0f22822998e68ea597))

## [1.1.0](https://github.com/evrenonur/api-lens/compare/v1.0.0...v1.1.0) (2026-02-26)

### 🚀 New Features

* **ui:** add export dropdown buttons for OpenAPI and Postman ([8aa6480](https://github.com/evrenonur/api-lens/commit/8aa64801a4b95e52ae6a33c7ed5ac0f8eeef70d8))

### 🐛 Bug Fixes

* **test:** add placeholder Feature test to prevent PHPUnit directory error ([569815d](https://github.com/evrenonur/api-lens/commit/569815d533ef6b96a9d7e481886cae60f71f2cd3))

All notable changes to this project will be documented in this file.

This project follows [Conventional Commits](https://www.conventionalcommits.org/)
and uses [Semantic Versioning](https://semver.org/).

## [v1.0.0] - 2026-02-26

### 🚀 First Stable Release

#### Core Features
- **Auto-Discovery**: Automatically extracts all API routes, controllers, validation rules
- **Interactive UI**: Modern Vue 3 + Tailwind CSS interface with dark/light theme
- **Real-Time Testing**: Send requests directly from the documentation UI
- **Code Snippets**: Auto-generated cURL, JavaScript (fetch), PHP (Guzzle), Python (requests)
- **OpenAPI Export**: Full OpenAPI 3.0 spec generation
- **PHPDoc Annotations**: Custom `@api-lens-*` annotations for enhanced documentation

#### Annotations Support
- `@api-lens-group` — Group endpoints by controller or custom name
- `@api-lens-response {code} {json}` — Define expected response codes with example JSON
- `@api-lens-auth` — Specify authentication type (bearer, basic, api-key)
- `@api-lens-tag` — Add custom tags to endpoints
- `@api-lens-deprecated` — Mark endpoints as deprecated with migration info

#### UI Features
- Keyboard shortcuts (Ctrl+K search, arrow navigation, Esc)
- Request body JSON editor with syntax highlighting
- File upload support with drag & drop
- Response schema visualization
- SQL query & performance metrics panel
- Path parameters support
- Custom headers per request
- LocalStorage persistence for request data

#### Framework Compatibility
- PHP 8.1+ support
- Laravel 10, 11, and 12 compatibility
- Zero configuration required — works out of the box
- MIT License

[v1.0.0]: https://github.com/evrenonur/api-lens/releases/tag/v1.0.0
