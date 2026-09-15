# CLAUDE.md

Guidance for Claude Code (or another AI coding assistant) working in this
repository.

## What this is

A deliberately minimal Symfony "Hello World" app. It exists to demonstrate
the smallest reasonable Symfony + SQLite + built-in-server setup — not as a
starting point for a larger product. Keep it minimal unless explicitly asked
to grow it.

## Stack

- Symfony 7 skeleton (`symfony/skeleton`), plus only `twig`,
  `doctrine/doctrine-bundle`, `doctrine/dbal`, `doctrine/orm`
- SQLite via Doctrine, configured through `DATABASE_URL` in `.env`
  (`var/data_dev.db` in the `dev` environment)
- Twig templates in `templates/`, extending `templates/base.html.twig`
  which loads Bootstrap 5 from jsdelivr's CDN — no Node/npm, no
  asset bundler
- No Docker, no `compose.yaml` — run everything with `php` and `composer`
  directly

## Common commands

```bash
composer install                       # install PHP dependencies
php bin/console doctrine:schema:create # (re)create the SQLite schema
php bin/console doctrine:schema:update --force  # after entity changes
php -S 127.0.0.1:8000 -t public        # run the app locally
```

There is no test suite yet; if you add one, prefer PHPUnit via
`symfony/test-pack` and keep it equally minimal.

## Conventions

- Routes are defined with PHP attributes on controllers in
  `src/Controller/`, auto-loaded via `config/routes/attributes.yaml`.
- Entities live in `src/Entity/`, repositories in `src/Repository/`,
  mapped with attributes (no XML/YAML mapping).
- `var/` and `vendor/` are gitignored build/runtime artifacts — never
  commit the SQLite database file or installed dependencies.
- Don't introduce Webpack Encore, AssetMapper, a Node toolchain, or Docker
  unless the user asks for it — the whole point of this project is to stay
  runnable with just PHP and Composer.
