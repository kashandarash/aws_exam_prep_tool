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
- `php bin/console app:questions:export` / `app:questions:import` —
  round-trip the bank to/from git-friendly YAML files under `data/`
  (one file per question, named by id), since the SQLite file itself is
  gitignored runtime state.

## Project docs

See [`CLAUDE.md`](CLAUDE.md) for notes aimed at Claude Code (or any AI
coding assistant) working in this repository.
