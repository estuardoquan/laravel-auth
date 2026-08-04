# Working Rules

Operating rules for Claude in this repository. These override default response habits.
When a rule conflicts with the instinct to be thorough, the rule wins.

---

## Rule 1 — Flag and stop

If something is wrong, say that it is wrong, say what is wrong, and **stop writing**.
Then wait.

Do not, unless asked:

- propose solutions, fixes, or alternatives
- list multiple options
- diverge into adjacent subjects, side notes, or "while you're at it" advice
- append a recommendation section

The follow-up instruction comes from the user, and will be one of:

- give me the best answer
- give me the worst answer
- explain what is wrong with what I am proposing

Until that instruction arrives, the turn is over.

### Example

> **User:** I'll proxy the OAuth host under a per-app alias. Does this work?

**Wrong** — flags the problem, then keeps going: adds the failure modes, the nginx header
config, a "what I'd actually do" recommendation, and two unrelated side notes on TLD
choice and token revocation.

**Right:**

> Machine-to-machine token requests will work. Browser-facing flows will not — sessions
> are scoped per hostname, so a central OAuth server behind per-app aliases cannot do SSO.

Then stop.

---

## Rule 2 — `(wait)` blocks

A message ending in `(wait)` is **not answered in chat**.

Protocol:

1. Save the user's prompt plus brief notes to the running list (memory or an `.md` file —
   either is fine).
2. Reply with a single ellipsis — `...` — and nothing else. No acknowledgment, no standby
   line, no analysis.
3. If the next message also ends in `(wait)`, repeat — reasoning about it in light of
   everything already collected, still under Rule 1.
4. When a message arrives **without** `(wait)`, the sequence is closed. Work through the
   collected list **point by point**.

Rule 1 applies inside the `(wait)` list too: the notes flag what is wrong, they do not
solve it.

---

## Quick reference

| Situation                                      | Action                               |
| ---------------------------------------------- | ------------------------------------ |
| Something is wrong                             | Name it. Stop. Wait for instruction. |
| User asks "does this work or not"              | Answer that question only.           |
| Message ends in `(wait)`                       | Save to list. Reply `...` only.      |
| Message without `(wait)` after `(wait)` blocks | Close the list, go point by point.   |
| Tempted to add a helpful aside                 | Don't.                               |

---

# Repository context

`estuardoquan/laravel-auth` — a fork of [laravel/breeze](https://github.com/laravel/breeze)
at `727b746`, reduced to two stacks.

## Identifiers

|           |                                                |
| --------- | ---------------------------------------------- |
| Package   | `estuardoquan/laravel-auth`                    |
| Namespace | `EQ\Laravel\Auth\`, `EQ\Laravel\Auth\Console\` |
| Provider  | `EQ\Laravel\Auth\AuthServiceProvider`          |
| Command   | `laravel-auth:install`                         |

`laravel-auth:` rather than `auth:` because Laravel core owns the `auth:` namespace via
`auth:clear-resets`.

`AuthServiceProvider` collides by short name with Laravel's own
`App\Providers\AuthServiceProvider`. Different namespace, so nothing breaks, but any file
importing both needs an alias.

## What was removed from upstream Breeze

Stacks: React, Livewire (both APIs), and API-only. That means `stubs/{api,livewire,
livewire-common,livewire-functional,inertia-react,inertia-react-ts}`,
`src/Console/InstallsApiStack.php`, `src/Console/InstallsLivewireStack.php`, and the
React methods inside `InstallsInertiaStacks`.

Also dropped: `art/`, `.github/`, `CHANGELOG.md`, `UPGRADE.md` — Breeze release history
and CI pinned to `laravel/.github` reusable workflows.

## Layout

```
src/
  AuthServiceProvider.php
  Console/InstallCommand.php
  Console/InstallsBladeStack.php
  Console/InstallsInertiaStacks.php
stubs/
  default/          Blade stack, and the shared form requests and tests
  inertia-common/   Controllers, middleware, routes and providers shared by both Vue variants
  inertia-vue/      JavaScript components and entrypoint
  inertia-vue-ts/   TypeScript components, entrypoint and type declarations
```

`stubs/inertia-vue-ts` is byte-identical to upstream Breeze. `stubs/inertia-vue` is too
except for `vite.config.js`, which carries the Tailwind v4 plugin (upstream is still on
v3) and an array-form `input:` for the Laravel plugin (upstream passes a bare string).
Any other change to either is a deliberate divergence, not a sync gap.

## Invariants to preserve

Breaking any of these breaks `laravel-auth:install` silently — the installer succeeds and
the app fails later.

- Every `Inertia::render('X')` call in `stubs/inertia-common` must have a matching
  `stubs/inertia-vue*/resources/js/Pages/X.vue`. Currently 9 names, all covered.
  Path case matters on Linux: `Auth/Login`, not `auth/login`.
- Every `__DIR__ . '/../../stubs/...'` path in `src/` must resolve. Currently 40.
- `resources/js/{Components,Layouts,Pages}` and, under `--typescript`, `types` are
  published by `installInertiaVueStack()`. `app.js` resolves pages via
  `import.meta.glob('./Pages/**/*.vue')`, and `app.blade.php` references
  `resources/js/Pages/{$page['component']}.vue` in its `@vite` directive — so removing
  those directories leaves an app that cannot build.
- `configureZiggyForSsr()` patches `resources/js/types/index.d.ts` under `--typescript`.
  That file must keep shipping or `--ssr --typescript` fatals on a missing path.
- `removeDarkClasses()` runs a `Finder` over `resource_path('js')` for `*.vue`. It is only
  safe because the stub components are copied there first. If they ever stop being copied,
  this strips `dark:` classes out of the consumer's own components.

## Style

`InstallCommand.php` and `InstallsInertiaStacks.php` have been through a formatter that
differs from upstream Breeze: spaces around `.` concatenation and `fn(` with no space.
Match the file being edited, not upstream.

## Verification

There is no test suite for the package itself; upstream Breeze has none either. What the
`--pest` / PHPUnit stubs test is the _installed application_, not this code.

Before claiming a change is safe, check the invariants above and run `php -l` on anything
touched in `src/`.

## Consuming app

Not on Packagist. The app requires it through a VCS repository:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/estuardoquan/laravel-auth"
    }
]
```

```bash
composer require estuardoquan/laravel-auth:dev-main --dev
```

`dev-main` because the repo has no tags. Composer caches VCS metadata by commit, so after
a package rename or force-push, `composer clearcache` is required or it resolves to the
old name.
