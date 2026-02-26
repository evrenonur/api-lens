# Changelog

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
