@php
/** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp
# Testing

- Test what is genuinely useful, not everything. Write a test when it would catch a real bug: business logic, data-shaping, command/import behaviour, edge cases, and regressions. Skip tests for trivial changes with no logic.
- Do NOT write a test for visual/CSS/Tailwind tweaks, DOM-order or copy changes, config, or simple pass-through code. Verify those by eye (a screenshot if it's worth it), not with a brittle assertion.
- Favour a few high-value assertions over many granular ones. Assert real behaviour/output, never framework internals or the values you just set up in the test.
- In browser tests, assert the rendered DOM, not text that also lives in the Inertia props JSON (that passes even when the UI is broken).
- If you're unsure whether a change warrants a test, ask rather than defaulting to writing one.
- When you do test, run the minimum needed: `{{ $assist->artisanCommand('test --compact') }}` with a specific filename or filter.
