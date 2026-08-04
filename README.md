# Laravel Auth

Minimal Laravel authentication scaffolding for **Blade** and **Inertia Vue** (JavaScript and
TypeScript). Forked from [laravel/breeze](https://github.com/laravel/breeze).

This is Breeze with the React, Livewire and API stacks removed. Two stacks remain, both
complete: controllers, form requests, routes, middleware, views/components and tests.

## Installation

Add Github repository to composer.json

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/estuardoquan/laravel-auth"
    }
],
```

```bash
composer require estuardoquan/laravel-auth:dev-main --dev
php artisan laravel-auth:install
```

## Stacks

```bash
php artisan laravel-auth:install blade
php artisan laravel-auth:install vue
php artisan laravel-auth:install vue --typescript --https --dark
```

| Stack       | Argument | Published                                                                                                                                                       |
| ----------- | -------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Blade       | `blade`  | Auth controllers, form requests, routes, `app/View/Components`, the full `resources/views` set, Tailwind/Vite config, tests                                     |
| Inertia Vue | `vue`    | Auth controllers, form requests, routes, `HandleInertiaRequests` middleware, `resources/js/{Components,Layouts,Pages}`, entrypoint, Tailwind/Vite config, tests |

### Options

| Option         | Applies to | Effect                                                                    |
| -------------- | ---------- | ------------------------------------------------------------------------- |
| `--dark`       | both       | Keeps Tailwind `dark:` classes in the published views and components      |
| `--typescript` | `vue`      | Publishes the `.ts` entrypoint, `resources/js/types`, and `tsconfig.json` |
| `--ssr`        | `vue`      | Publishes the SSR entrypoint and wires Ziggy and Vite for SSR             |
| `--eslint`     | `vue`      | Publishes `.eslintrc.cjs` and `.prettierrc`, and adds a `lint` script     |
| `--https`      | both       | Adds `vite-plugin-https` and rewrites `vite.config.js` around it          |
| `--pest`       | both       | Publishes Pest tests instead of PHPUnit tests                             |
| `--composer`   | both       | Absolute path to the Composer binary                                      |

Without `--typescript`, the Vue stack publishes `resources/js/app.js` and the JavaScript
components from `stubs/inertia-vue`. With it, `resources/js/app.ts`, the TypeScript
components from `stubs/inertia-vue-ts`, and `resources/js/types`.

`--https` adds `vite-plugin-https` to `devDependencies` as
`github:estuardoquan/vite-plugin-https` — it is not on npm — imports it in
`vite.config.js`, appends `https()` to the plugin list, and wraps the config in
`defineConfig(async () => ({ ... }))`, since the plugin reads the cert/key pair
asynchronously. It runs with the plugin's defaults: `site.crt` and `site.key` under
`/var/local/ssl`.

## Pages published by the Vue stack

```
Pages/Auth/{Login,Register,ForgotPassword,ResetPassword,VerifyEmail,ConfirmPassword}.vue
Pages/Profile/Edit.vue
Pages/Profile/Partials/{UpdateProfileInformationForm,UpdatePasswordForm,DeleteUserForm}.vue
Pages/{Dashboard,Welcome}.vue
```

These names match the `Inertia::render()` calls in the published controllers, so the routes
resolve immediately after `npm run build`.

## Package layout

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

Namespace: `EQ\Laravel\Auth\`.

## Requirements

PHP 8.2+, Laravel 11, 12 or 13.

## Licence

MIT. Copyright Taylor Otwell (original Breeze) and Estuardo Quan (this fork).
