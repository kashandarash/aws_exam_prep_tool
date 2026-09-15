# Symfony Hello World

A very minimal [Symfony](https://symfony.com) 7 application. One page, one
route, a local SQLite database, and Bootstrap 5 for styling — no Docker,
no Node/npm build step, no frontend bundler.

## Stack

- **PHP 8.2+** with Symfony 7 (`symfony/skeleton`)
- **Doctrine ORM + DBAL** against a local **SQLite** file (`var/data_dev.db`)
- **Twig** for templates
- **Bootstrap 5** loaded from a CDN (no build step)
- PHP's **built-in web server** for local development

## Requirements

- PHP 8.2 or later, with the `pdo_sqlite` extension
- [Composer](https://getcomposer.org)

## Setup

```bash
composer install
php bin/console doctrine:schema:create
```

This creates `var/data_dev.db` with the single table the app uses.

## Run it

```bash
php -S 127.0.0.1:8000 -t public
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000). Every page load records
a visit in the SQLite database and shows the running count.

## What it does

A single controller (`src/Controller/HelloController.php`) handles `GET /`,
renders `templates/hello/index.html.twig` with "Hello, World!", and persists
a `Visit` entity (`src/Entity/Visit.php`) to prove the SQLite database is
actually wired up and working.

## Project docs

See [`CLAUDE.md`](CLAUDE.md) for notes aimed at Claude Code (or any AI
coding assistant) working in this repository.
