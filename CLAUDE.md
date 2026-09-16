# CLAUDE.md

Guidance for Claude Code (or another AI coding assistant) working in this
repository.

## What this is

An AWS Certified Developer Associate (DVA-C02) exam-prep tool: import
exam questions, browse/edit the bank, and take randomized practice
tests that retire questions once you've gotten them right enough
times. It started as a minimal Symfony "Hello World" skeleton and has
grown a real feature set (see below), but the original constraint
still holds — keep it runnable with just PHP and Composer, no Docker,
no Node/npm build step, and don't add abstractions or infrastructure
beyond what a feature actually needs.

## Stack

- Symfony 7 (`symfony/skeleton`), plus `twig`, `doctrine/doctrine-bundle`,
  `doctrine/dbal`, `doctrine/orm`, `symfony/yaml`, and `aws/aws-sdk-php`
  (for the Bedrock Runtime client)
- SQLite via Doctrine, configured through `DATABASE_URL` in `.env`
  (`var/data_dev.db` in the `dev` environment) — gitignored, and the
  runtime store only, not the source of truth (see Data below)
- Twig templates in `templates/`, extending `templates/base.html.twig`
  which loads Bootstrap 5 from jsdelivr's CDN — no Node/npm, no asset
  bundler
- No Docker, no `compose.yaml` — run everything with `php` and `composer`
  directly
- Amazon Bedrock Runtime (`Aws\BedrockRuntime\BedrockRuntimeClient`,
  wired in `config/services.yaml`), authenticated via an API key
  (`AWS_BEARER_TOKEN_BEDROCK` / `AWS_REGION` / `AWS_BEDROCK_MODEL` in
  `.env`) — used only by the HTML-paste question import

## What it does

- `/` (`HomeController`) — landing page with bank stats and a summary of
  the workflow below.
- `/questions/add` (`QuestionController::add`) — paste the HTML of an AWS
  Certified Developer Associate (DVA-C02) exam-question page; it's sent
  to Bedrock via the Converse API with a forced tool call to extract
  questions/options/correct answer(s) verbatim (see
  `extractionSystemPrompt()`), deduplicated against the existing bank
  before saving.
- `/questions` (`QuestionController::list`) — search (question text
  only), filter by mastery status, and paginate (20/page) the bank; each
  question links to its edit page.
- `/questions/{id}/edit` (`QuestionController::edit`) — manual form to
  fix a question's text/options/correct answer(s) by hand.
- `/test/take` (`TestController::take`) — pulls up to
  `TestController::QUESTIONS_PER_TEST` random questions that haven't yet
  reached `Question::MASTERY_THRESHOLD` correct answers, scores the
  submission, and increments each correctly-answered question's streak
  (mastered questions stop appearing in new tests).
- `app:questions:export` / `app:questions:import` console commands
  round-trip the bank to/from `data/{id}.yml` (text + options only) —
  see Data below.

## Data

- `var/data_dev.db` is the live runtime store — gitignored, and treated
  as real user data (see Database persistence below), never a disposable
  fixture.
- `data/*.yml` is the git-friendly mirror of the bank: one YAML file per
  question, named by id, holding only `text` and `options`. Regenerate it
  with `app:questions:export`; repopulate a fresh database with
  `app:questions:import`. Both commands dedupe by normalized question
  text (`Question::normalizeText()`), so re-running either is always
  safe — it only ever adds missing questions, never touches existing
  rows.
- `data/input_example.html` and any sample-exam PDFs are one-off
  reference/source material for manual or assisted import, not something
  the app reads at runtime.

## Common commands

```bash
composer install                       # install PHP dependencies
php bin/console doctrine:schema:create # ONLY when var/data_dev.db has no schema yet
php bin/console doctrine:schema:update --force  # after entity changes, see below
php bin/console app:questions:export   # write the bank to data/{id}.yml (git-friendly)
php bin/console app:questions:import   # (re)populate the bank from data/*.yml
php -S 127.0.0.1:8000 -t public        # run the app locally
```

There is no test suite yet; if you add one, prefer PHPUnit via
`symfony/test-pack` and keep it equally minimal.

## Database persistence

`var/data_dev.db` holds real working data (questions added through the
app or imported) and must survive across dev-server restarts and across
Claude's own manual testing sessions. It is not a disposable fixture.

- Never run `doctrine:database:drop`, `doctrine:schema:drop`, or
  `doctrine:fixtures:load` against it, and never re-run
  `doctrine:schema:create` on a database that already has the schema
  (it errors, but don't work around that by dropping first).
- After changing an entity, run `doctrine:schema:update` **without**
  `--force` first and read the generated SQL diff. Only re-run with
  `--force` if the diff is purely additive (new table/column) or you've
  confirmed with the user that altering/losing existing rows is fine —
  a `--force` update can `DROP`/rewrite columns depending on the change.
- Restarting `php -S 127.0.0.1:8000 -t public` does not touch the SQLite
  file, so no extra steps are needed to preserve data across restarts.
- For throwaway data while manually testing a feature, add it through
  the app's own forms (or against a copied-aside database file / a
  `DATABASE_URL` override), not by dropping/recreating the live schema
  or editing rows you didn't add.

## Conventions

- Routes are defined with PHP attributes on controllers in
  `src/Controller/`, auto-loaded via `config/routes/attributes.yaml`.
- Console commands live in `src/Command/`, auto-registered via
  `#[AsCommand]` (autoconfigure is on in `config/services.yaml`).
- Entities live in `src/Entity/`, repositories in `src/Repository/`,
  mapped with attributes (no XML/YAML mapping).
- Dedup and options-validation logic for a question lives once, on the
  entity (`Question::normalizeText()`, `Question::buildOptions()`) —
  every place that creates or edits questions (the add form, the edit
  form, the Bedrock import, the YAML import) reuses it rather than
  re-deriving its own copy.
- `var/` and `vendor/` are gitignored build/runtime artifacts — never
  commit the SQLite database file or installed dependencies.
- Don't introduce Webpack Encore, AssetMapper, a Node toolchain, or Docker
  unless the user asks for it — the whole point of this project is to stay
  runnable with just PHP and Composer.
