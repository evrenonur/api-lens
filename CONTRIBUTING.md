# Contributing Guide

Thank you for your interest in contributing to API Lens! 🎉

## How to Contribute

### 1. Fork & Clone

```bash
# Fork the repo on GitHub (click "Fork" button)
# Clone your fork
git clone https://github.com/YOUR_USERNAME/api-lens.git
cd api-lens

# Add upstream remote
git remote add upstream https://github.com/evrenonur/api-lens.git
```

### 2. Create a Branch

```bash
# Create a new branch from up-to-date main
git checkout main
git pull upstream main
git checkout -b feat/my-feature
```

Branch naming conventions:
- `feat/feature-name` → New feature
- `fix/bug-description` → Bug fix
- `docs/documentation` → Documentation
- `refactor/description` → Refactoring

### 3. Development

```bash
# Install PHP dependencies
composer install

# Install UI dependencies
cd ui && npm install && cd ..

# Run tests
composer test

# Run linter
composer lint-test

# Build frontend
cd ui && npm run build
```

### 4. Commit Conventions

This project uses **[Conventional Commits](https://www.conventionalcommits.org/)**.

```
<type>(<scope>): <description>
```

| Type | Description | Version Impact |
|------|-------------|----------------|
| `feat` | New feature | MINOR (1.x.0) |
| `fix` | Bug fix | PATCH (1.0.x) |
| `perf` | Performance improvement | PATCH |
| `refactor` | Code refactoring | PATCH |
| `docs` | Documentation | - |
| `test` | Tests | - |
| `style` | Code style | - |
| `chore` | Maintenance | - |
| `ci` | CI/CD changes | - |

**Examples:**
```bash
git commit -m "feat(ui): add response time histogram"
git commit -m "fix(extractor): handle closure routes correctly"
git commit -m "test: add DocBlockExtractor unit tests"
git commit -m "docs: update installation instructions"
```

**Breaking Change:**
```bash
git commit -m "feat!: redesign configuration structure"
```

### 5. Submit Pull Request

```bash
git push origin feat/my-feature
```

Then open a Pull Request on GitHub against the `main` branch.

## Code Style

- PHP: Laravel Pint with `laravel` preset
- Vue/TypeScript: Standard formatting via Vite

## Testing

All new features and bug fixes should include tests:

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage
```
