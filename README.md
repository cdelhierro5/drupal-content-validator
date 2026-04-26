# Drupal Content Validator

> Configurable business rule validation for Drupal 10/11. Validates nodes against custom rules before publishing and shows real-time feedback directly in the node editor.  
> Built by [Carlos Del Hierro](https://www.carlosdelhierro.com) — Drupal Senior Developer.

[![Drupal](https://img.shields.io/badge/Drupal-10%2F11-0678BE?style=flat-square&logo=drupal)](https://www.drupal.org)
[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=flat-square&logo=php)](https://www.php.net)
[![License](https://img.shields.io/badge/License-GPL--2.0-blue?style=flat-square)](LICENSE)

---

## The problem this solves

In large Drupal editorial teams, content often gets published without meeting quality standards: bodies that are too short, missing featured images, titles with forbidden words, or required fields left empty. By the time an editor notices, it's already live.

This module intercepts the publication process and validates content against a set of configurable rules before it can go live. Rules are configured per content type from the Drupal admin UI — no code changes needed. Editors get real-time feedback while they type, so they can fix issues before even trying to publish.

---

## Table of contents

- [Features](#features)
- [How it works](#how-it-works)
- [Built-in rules](#built-in-rules)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [The validation block](#the-validation-block)
- [Inline editor feedback](#inline-editor-feedback)
- [Architecture](#architecture)
- [Adding custom rules](#adding-custom-rules)
- [Warnings vs errors](#warnings-vs-errors)
- [Config management](#config-management)
- [FAQ](#faq)

---

## Features

- **Per content type rules** — configure different rules for articles, pages, products, etc.
- **Blocking mode** — prevents publication when rules fail
- **Non-blocking warnings** — flags issues without stopping publication
- **Real-time editor feedback** — progress bar and character counter update as the editor types
- **Configurable block** — shows validation status on any node page
- **Animated progress bar** — shows X/Y rules passing at a glance
- **Character counter** — live count with colour feedback (red when below threshold)
- **Logging** — every validation run logged via Drupal's logger with timestamps
- **Exportable config** — all rules stored in Drupal's config system, compatible with `drush config:export`
- **Extensible** — add new rules by extending `ContentValidatorService`

---

## How it works

### On save / publish

When a node transitions to published state, `hook_node_presave` fires the validator. The `ContentValidatorService` runs all enabled rules for that content type. Each rule adds errors or warnings to a `ValidationResult` value object. If there are errors and blocking mode is on, the node is forced back to unpublished and the editor sees a message listing the issues.

### In the editor

When a node form loads for an enabled content type, the module:

1. Attaches a JS library to the form
2. Injects a summary banner above the form fields
3. Runs client-side checks (title length, body length) on every keystroke (debounced 400ms)
4. Updates the banner with a progress bar and list of failing rules

Client-side checks give instant feedback. Server-side rules are always authoritative on save — they can't be bypassed.

---

## Built-in rules

| Rule | What it checks | Config params |
|------|---------------|---------------|
| **Minimum body length** | Body plain text is at least N characters | `min_length` (integer) |
| **Forbidden words** | Title does not contain any of the listed words | `words` (comma-separated string) |
| **Required fields** | Listed fields are not empty | `fields` (comma-separated machine names) |
| **Has image** | Image field is not empty | `field` (machine name, default: `field_image`) |

Each rule can be enabled or disabled independently per content type. The forbidden words check is case-insensitive and matches substrings.

---

## Requirements

- Drupal 10 or 11
- PHP 8.1+
- The `node` and `block` core modules (both enabled by default)

No third-party module dependencies.

---

## Installation

### Via Composer (recommended)

```bash
# From your Drupal project root
composer require cdelhierro/drupal-content-validator
drush en drupal_content_validator -y
drush cr
```

### Manual

```bash
# Copy module into your Drupal installation
cp -r drupal-content-validator web/modules/custom/drupal_content_validator

# Enable
drush en drupal_content_validator -y
drush cr
```

After enabling, visit `/admin/config/content/content-validator` to configure your rules.

---

## Configuration

Go to **Admin → Configuration → Content → Content Validator**.

The form shows one collapsible section per content type. For each type you can:

**1. Enable validation** — toggles the whole module on/off for that content type.

**2. Blocking mode** — when enabled, failed rules prevent publication. When disabled, rules run but failures only show warnings; the node can still be published.

**3. Individual rules** — each rule has its own enable checkbox and configuration fields.

Example configuration for an `article` content type:

```
[✓] Enable validation for 'article'
[✓] Block publication on validation failure

Rules:
  [✓] Minimum body length
        Min. characters: 200

  [✓] Forbidden words in title
        Words: test, draft, TODO, lorem

  [✓] Required fields
        Fields: field_category, field_summary

  [ ] Must contain image
```

---

## The validation block

The module provides a block plugin called **Content Validation Status** that you can place in any region via Block Layout.

**To add it:**

1. Go to **Admin → Structure → Block layout**
2. Click **Place block** in your desired region
3. Search for **Content Validation Status**
4. Configure the block options

**Block configuration options:**

| Option | Description |
|--------|-------------|
| Show warnings | Display non-blocking warnings in the block |
| Show block when valid | Hide the block entirely when content passes all rules |
| Collapse when valid | Collapse the block (hide details) when content is valid |

**What the block shows:**

- A header with a pass/fail icon and a "X/Y rules" badge
- An animated progress bar (green when passing, red when failing)
- A list of specific error messages
- A separate warnings section if warnings exist

The block cache is invalidated on the node's cache tags, so it always reflects the current state of the content.

---

## Inline editor feedback

When editing a node on an enabled content type, a summary banner appears above the form fields. It updates in real time as you type (debounced 400ms to avoid excessive recalculation).

**States:**

- ⏳ **Pending** (amber) — checking in progress on page load
- ✅ **Valid** (green) — all rules passing, with a full progress bar
- ❌ **Invalid** (red) — list of failing rules with a partial progress bar

**Character counter:**

A character counter appears below the body field showing `current / minimum chars`. It turns red when below the threshold and green when above.

The inline checks are client-side only and cover title length and body length. All other rules run server-side on save.

---

## Architecture

```
drupal_content_validator/
│
├── drupal_content_validator.info.yml       # Module metadata
├── drupal_content_validator.module         # hook_node_presave, hook_form_alter, hook_theme
├── drupal_content_validator.services.yml   # Service definitions
├── drupal_content_validator.routing.yml    # Admin page route
├── drupal_content_validator.links.menu.yml # Admin menu link
├── drupal_content_validator.permissions.yml
├── drupal_content_validator.libraries.yml  # CSS + JS assets
│
├── src/
│   ├── Service/
│   │   ├── ContentValidatorService.php     # Core: runs rules, reads config
│   │   └── ValidationResult.php            # Value object: accumulates errors/warnings
│   ├── Plugin/Block/
│   │   └── ContentValidatorBlock.php       # Block plugin with its own config form
│   ├── Form/
│   │   └── ContentValidatorSettingsForm.php # Per-bundle rule configuration UI
│   └── EventSubscriber/
│       └── NodeValidationSubscriber.php    # Extension point for async reactions
│
├── templates/
│   └── content-validator-block.html.twig   # Block template with progress bar
│
├── css/content-validator.css               # Block + editor banner styles
├── js/content-validator.js                 # Live editor feedback + char counter
│
└── config/
    ├── install/
    │   └── drupal_content_validator.settings.yml  # Default config (empty)
    └── schema/
        └── drupal_content_validator.schema.yml    # Config schema for export
```

### Key design decisions

**`ValidationResult` is a value object.** Rules never throw exceptions — they call `addError()` or `addWarning()` and execution continues. This guarantees all rules run and the summary is always complete.

**Rules are methods, not plugins.** Each rule is a protected method on `ContentValidatorService` following the naming convention `validate{RuleName}()`. This is intentionally simpler than a full Plugin API implementation — adding a rule is adding a method. If you need runtime-pluggable rules from other modules, the service is easy to extend or decorate.

**Config is stored in Drupal's config system.** All rule settings are keyed under `bundles.{bundle_name}.rules.{rule_id}`. This means they are fully exportable with `drush config:export` and importable across environments — staging config matches production config.

**Block cache uses node cache tags.** The block calls `getCacheMaxAge(): 0` to ensure it always reflects the live validation state of the currently viewed node, rather than serving a stale cached version.

**JS validation is advisory only.** The client-side checks exist purely for editor comfort. Server-side rules in `hook_node_presave` are always authoritative and cannot be bypassed by disabling JavaScript.

---

## Adding custom rules

Adding a rule requires changes in three places.

### 1. Add the validation method to `ContentValidatorService`

Follow the naming convention `validate{RuleName}` where `{RuleName}` is the camelCase version of your rule ID:

```php
// Rule ID: min_word_count → method: validateMinWordCount
protected function validateMinWordCount(
  NodeInterface $node,
  ValidationResult $result,
  array $config
): void {
  $min = (int) ($config['min_count'] ?? 50);

  if (!$node->hasField('body')) {
    return;
  }

  $text  = strip_tags($node->get('body')->value ?? '');
  $words = str_word_count($text);

  if ($words < $min) {
    $result->addError(sprintf(
      'Body has %d words. Minimum required: %d.',
      $words,
      $min
    ));
  }
}
```

### 2. Add the rule UI to `ContentValidatorSettingsForm`

In `buildForm()`, inside the `$form['bundles'][$bundle]['rules']` fieldset, add:

```php
$form['bundles'][$bundle]['rules']['min_word_count'] = [
  '#type'  => 'fieldset',
  '#title' => $this->t('Minimum word count'),
];
$form['bundles'][$bundle]['rules']['min_word_count']['enabled'] = [
  '#type'          => 'checkbox',
  '#title'         => $this->t('Enable'),
  '#default_value' => $bundle_config['rules']['min_word_count']['enabled'] ?? FALSE,
];
$form['bundles'][$bundle]['rules']['min_word_count']['min_count'] = [
  '#type'          => 'number',
  '#title'         => $this->t('Minimum words'),
  '#default_value' => $bundle_config['rules']['min_word_count']['min_count'] ?? 50,
  '#min'           => 1,
];
```

### 3. Add the rule to the config schema

In `config/schema/drupal_content_validator.schema.yml`, under the `rules` mapping:

```yaml
min_word_count:
  type: mapping
  mapping:
    enabled:
      type: boolean
    min_count:
      type: integer
```

---

## Warnings vs errors

The module distinguishes between **errors** and **warnings**:

- **Errors** block publication (when blocking mode is on). They represent hard requirements.
- **Warnings** are informational. They appear in the block and editor banner but never block publication.

In your custom rules, use `$result->addWarning()` instead of `$result->addError()` for advisory checks:

```php
// Warn if no featured image, but don't block
if ($node->get('field_image')->isEmpty()) {
  $result->addWarning('No featured image — recommended for social sharing and SEO.');
}
```

---

## Config management

All rule configuration is stored in Drupal's config system under `drupal_content_validator.settings`. You can manage it like any other config:

```bash
# Export after configuring rules in the UI
drush config:export

# Apply rules on a new environment
drush config:import

# View current settings
drush config:get drupal_content_validator.settings

# Check a specific bundle
drush config:get drupal_content_validator.settings bundles.article
```

---

## FAQ

**Q: Can I add validation rules from another module?**
The cleanest approach is to extend `ContentValidatorService` in a subclass and override the `__construct` if you need different dependencies, or to decorate the service in your module's `services.yml`.

**Q: The block doesn't appear after placing it. Why?**
The block only renders on node view pages (`/node/{nid}`) where the content type has validation enabled. On other pages it returns an empty render array and is not displayed.

**Q: Does this work with Drupal's content moderation workflows?**
Yes. The `hook_node_presave` fires on all save operations regardless of workflow state. If you only want to validate on the final publish transition, check `$entity->moderation_state->value === 'published'` at the start of the hook implementation.

**Q: Can editors bypass validation?**
No editor-facing bypass exists. The server-side check in `hook_node_presave` runs regardless of browser JavaScript or the editor's permissions. An admin could temporarily disable validation for a content type from the settings form.

---

## Author

**Carlos Del Hierro** — Drupal Senior Developer
🌐 [carlosdelhierro.com](https://www.carlosdelhierro.com)
💼 [LinkedIn](https://www.linkedin.com/in/carlosdelhierrodev)
🐙 [GitHub](https://github.com/cdelhierro5)

---

## License

GPL-2.0-or-later
