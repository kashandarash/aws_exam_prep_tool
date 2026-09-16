# AWS Exam Prep Tool

A [Symfony](https://symfony.com) 7 app for building a personal AWS
certification question bank and practicing against it — no Docker, no
Node/npm build step.

## Stack

- **PHP 8.2+** with Symfony 7 (`symfony/skeleton`)
- **Doctrine ORM + DBAL** against a local **SQLite** file (`var/data_dev.db`)
- **Twig** for templates, **Bootstrap 5** loaded from a CDN (no build step)
- **AWS SDK for PHP**, calling **Amazon Bedrock** (Converse API) to turn a
  pasted exam-question web page into structured questions
- PHP's **built-in web server** for local development

## Requirements

- PHP 8.2 or later, with the `pdo_sqlite` extension
- [Composer](https://getcomposer.org)
- To use the HTML-paste import: an AWS account with Bedrock access and a
  Bedrock API key (see `.env.example`)

## Setup

```bash
composer install
cp .env.example .env   # fill in AWS_BEARER_TOKEN_BEDROCK / AWS_REGION / AWS_BEDROCK_MODEL if you want question import
php bin/console doctrine:schema:create
```

To start from an existing question bank instead of an empty one, run
`php bin/console app:questions:import` after this to load everything
under `data/*.yml`.

## Run it

```bash
php -S 127.0.0.1:8000 -t public
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000).

## What it does

- **Home** (`/`) — bank stats and a summary of the workflow below.
- **Add Questions** (`/questions/add`) — paste the HTML of an exam-question
  page; Bedrock extracts every question, its options, and the correct
  answer(s), skipping anything already in the bank.
- **Questions** (`/questions`) — search, filter by mastery status, and
  page through the bank; edit any question by hand.
- **Take Test** (`/test/take`) — up to 20 random questions that haven't
  been mastered yet, scored against the correct answer(s). Answer a
  question correctly enough times (3, by default) and it's retired from
  future tests.
- `php bin/console app:questions:export` / `app:questions:import` — see
  below.

## Exporting and importing the question bank

`var/data_dev.db` is gitignored runtime state, not something you commit —
so the git-friendly source of truth for the bank is `data/*.yml`, one
YAML file per question named after its id (e.g. `data/42.yml`), holding
just that question's `text` and `options`:

```bash
php bin/console app:questions:export   # write every question in the DB to data/{id}.yml
php bin/console app:questions:import   # load every data/*.yml into the DB
```

- **Export** after adding or editing questions (through the UI, the
  Bedrock import, or by hand) to snapshot the current bank into `data/`
  so it can be committed and shared.
- **Import** to (re)populate a database from what's in `data/` — for
  example after a fresh `composer install` + `doctrine:schema:create`,
  or to pull in questions someone else added to `data/` and committed.

Both commands match questions by their normalized text, so re-running
either is always safe: import only ever adds questions that are missing
from the database, and export just rewrites each question's file with
its current content — neither one touches or duplicates anything.

## Project docs

See [`CLAUDE.md`](CLAUDE.md) for notes aimed at Claude Code (or any AI
coding assistant) working in this repository.
