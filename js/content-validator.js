/**
 * @file
 * Content Validator — live editor feedback.
 *
 * Improvements:
 *  - Progress bar showing rules passed/total
 *  - Character counter on body field
 *  - Debounced validation (400ms)
 *  - Animated transitions on state changes
 */

(function (Drupal, once) {
  'use strict';

  const MIN_TITLE = 10;
  const MIN_BODY  = 100;

  // ── Utilities ──────────────────────────────────────────────────────────────

  function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
  }

  function plainText(html) {
    return html.replace(/<[^>]+>/g, '').trim();
  }

  // ── Character counter ──────────────────────────────────────────────────────

  function attachCharCounter(bodyEl, minLength) {
    const wrapper = bodyEl.closest('.form-item') || bodyEl.parentElement;
    let counter = wrapper.querySelector('.cv-char-counter');

    if (!counter) {
      counter = document.createElement('div');
      counter.className = 'cv-char-counter';
      wrapper.appendChild(counter);
    }

    function update() {
      const len = plainText(bodyEl.value).length;
      counter.textContent = `${len} / ${minLength} chars minimum`;
      counter.className = `cv-char-counter ${len < minLength ? 'cv-char-counter--low' : 'cv-char-counter--ok'}`;
    }

    bodyEl.addEventListener('input', update);
    update();
  }

  // ── Validation rules ───────────────────────────────────────────────────────

  function runRules(titleEl, bodyEl) {
    const rules = [];

    if (titleEl) {
      const len = titleEl.value.trim().length;
      rules.push({
        label: `Title length (${len} chars)`,
        pass: len >= MIN_TITLE,
        error: `Title is too short (${len} chars, min ${MIN_TITLE}).`,
      });
    }

    if (bodyEl) {
      const len = plainText(bodyEl.value).length;
      rules.push({
        label: `Body length (${len} chars)`,
        pass: len >= MIN_BODY,
        error: `Body is too short (${len} chars, min ${MIN_BODY}).`,
      });
    }

    return rules;
  }

  // ── Summary renderer ───────────────────────────────────────────────────────

  function renderSummary(summary, rules) {
    const passed  = rules.filter(r => r.pass).length;
    const total   = rules.length;
    const isValid = passed === total;
    const errors  = rules.filter(r => !r.pass).map(r => r.error);

    // Update class (triggers CSS transition)
    summary.className = [
      'content-validator-summary',
      isValid ? 'content-validator-summary--valid' : 'content-validator-summary--invalid',
    ].join(' ');

    // Progress bar (0-100)
    const pct = total > 0 ? Math.round((passed / total) * 100) : 0;

    if (isValid) {
      summary.innerHTML = `
        <div class="content-validator-summary__label">
          ✅ ${Drupal.t('Content passes all @n rules.', { '@n': total })}
        </div>
        <div class="content-validator-block__progress" style="margin-top:.4rem">
          <div class="content-validator-block__progress-bar" style="width:${pct}%;background:#22c55e"></div>
        </div>
      `;
    } else {
      const listItems = errors.map(e => `<li>${e}</li>`).join('');
      summary.innerHTML = `
        <div class="content-validator-summary__label">
          ❌ ${Drupal.t('@passed / @total rules passing', { '@passed': passed, '@total': total })}
        </div>
        <div class="content-validator-block__progress" style="margin-top:.4rem">
          <div class="content-validator-block__progress-bar" style="width:${pct}%;background:#ef4444"></div>
        </div>
        <ul class="content-validator-summary__errors">${listItems}</ul>
      `;
    }
  }

  // ── Behavior ───────────────────────────────────────────────────────────────

  Drupal.behaviors.contentValidator = {
    attach(context) {
      const summaryEls = once('content-validator', '#content-validator-summary', context);
      if (!summaryEls.length) return;

      const summary = summaryEls[0];
      const titleEl = context.querySelector('input[data-drupal-selector="edit-title-0-value"]');
      const bodyEl  = context.querySelector('.field--name-body .form-textarea');

      // Attach character counter to body field
      if (bodyEl) {
        attachCharCounter(bodyEl, MIN_BODY);
      }

      const validate = debounce(() => {
        const rules = runRules(titleEl, bodyEl);
        renderSummary(summary, rules);
      }, 400);

      [titleEl, bodyEl].forEach(el => {
        if (el) el.addEventListener('input', validate);
      });

      // Run on attach
      summary.className = 'content-validator-summary content-validator-summary--pending';
      summary.innerHTML = `<div class="content-validator-summary__label">⏳ ${Drupal.t('Checking content…')}</div>`;
      validate();
    },
  };

})(Drupal, once);
