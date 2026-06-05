# Built-in plugins

The package registers a curated set of Smarty plugins on every render that mirror Blade directives and Laravel helpers, so the common cases work out of the box.

## Auth & authorisation blocks

Block tags that wrap `auth()`, `Gate::allows()`, and friends. Their bodies short-circuit when the predicate is false, matching Blade's `@auth` / `@can` semantics — a `{$user->name}` inside `{auth}` won't blow up on a guest request.

```smarty
{auth}
  Welcome back, {$user->name|escape}.
{/auth}

{guest}
  Please <a href="{route name='login'}">sign in</a>.
{/guest}

{can ability="update" model=$post}
  <a href="...">Edit</a>
{/can}

{cannot ability="delete" model=$post}
  (read-only)
{/cannot}

{canany abilities=['update', 'delete'] model=$post}
  <a href="...">Manage</a>
{/canany}

{canall abilities=['publish', 'feature'] model=$post}
  <button>Publish &amp; feature</button>
{/canall}

{auth guard="api"}
  API user.
{/auth}
```

`{auth}` and `{guest}` accept an optional `guard=` parameter and otherwise default to the application's primary guard. Inside `{auth}` the authenticated user is bound as `$user` for the duration of the block (any outer `$user` is restored on exit), so you can write `{$user->name|escape}` without passing the user via view data. `{can}` / `{cannot}` accept `ability=` and an optional `model=` (passed as the gate's argument). `{canany}` / `{canall}` accept `abilities=[...]` and an optional `model=` — `{canany}` matches Blade's `@canany` (renders if any ability passes); `{canall}` is the equivalent of calling `Gate::check([...], $model)` (renders only when every ability passes).

Both multi-ability blocks accept `inverse=true` for the negative arm — `{canany inverse=true}` renders when *none* of the abilities pass, `{canall inverse=true}` renders when *any* of them are missing. Empty `abilities=[]` fails closed in both arms (an accidental empty list never authorizes). For an `{else}`-style layout in a single decision, drop into `{if}` with the wrapper methods on `$auth`:

```smarty
{if $auth?->canAny(['update', 'delete'], $post)}
  <a href="...">Manage</a>
{else}
  (no permissions)
{/if}
```

`$auth->canAny(array $abilities, mixed $arguments = [])` and `$auth->canAll(array $abilities, mixed $arguments = [])` mirror the blocks (and apply the same fail-closed posture for an empty list). Use `?->` to keep guest renders safe — `$auth` is null for unauthenticated requests.

`inverse=true` is **only** supported on `{canany}` / `{canall}` (and `{feature}`, see below) — there's no `{auth inverse=true}` because `{guest}` is the inverse of `{auth}`, and `{cannot}` is the inverse of `{can}`. The flag exists where a paired tag doesn't.

### Block-state safety under exceptions

`{auth}` (and `{error}` further down) bind a temporary variable on the open tag — `$user` for `{auth}`, `$message` for `{error}` — and restore the prior value on close. If the block body throws, the close phase never runs, which on a naive `static`-stack implementation would leak the binding into the next render. The package guards against this: `SmartyEngine::get()` calls `Vusys\LaravelSmarty\Plugins\BlockState::reset()` in a `finally` around every render, so leftover frames can't carry over. Matters especially under Octane / Swoole / RoadRunner, where the worker process (and any plugin-closure `static` state) survives across requests.

## Pennant feature flags

Block tag for `Laravel\Pennant\Feature` — body short-circuits when the flag is off, matching Blade's `@feature`. Requires the optional `laravel/pennant` package; the tag silently no-ops when Pennant isn't installed.

```smarty
{feature name="new-dashboard"}
  <a href="{route name='dashboard.v2'}">Try the new dashboard</a>
{/feature}

{feature name="beta-export" for=$auth->user}
  <button>Export</button>
{/feature}
```

`name=` is the flag identifier. `for=` (optional) scopes the check to a given subject — typically `$auth->user`, but anything Pennant accepts as a scope (a model, a string, a `Scope` instance) works. Without `for=`, Pennant uses its default scope.

Pass `inverse=true` to render the body when the flag is *inactive* — useful for the "show legacy variant when flag is off" half of an A/B layout:

```smarty
{feature name="compact-composer" for=$auth->user}…compact composer…{/feature}
{feature name="compact-composer" for=$auth->user inverse=true}…wide composer…{/feature}
```

For an `{else}`-style layout in a single decision, use the `feature_active(...)` modifier inside `{if}`:

```smarty
{if feature_active('compact-composer', $auth->user)}
  …compact composer…
{else}
  …wide composer…
{/if}
```

`feature_active($name, $for = null)` returns a bool. The optional second argument is the scope subject (same semantics as the block's `for=`).

Scoped checks (`for=` or the modifier's second argument) need an explicit `{if $auth}` guard in templates that may render for guests, because Pennant's `for(null)` is undefined.

## Form helpers

```smarty
<meta name="csrf-token" content="{csrf_token}">

<form method="post" action="{route name='posts.update' post=$post->id}">
  {csrf_field}
  {method_field method="PUT"}

  <input name="title" value="{old field='title' default=$post->title|default:''}">

  {error field="title"}
    <p class="error">{$message|escape}</p>
  {/error}
</form>
```

Inside `{error}` the validation message is bound as `$message` for the duration of the block, restored on exit.

| Tag | Equivalent |
|-----|------------|
| `{csrf_field}` | `csrf_field()` — full hidden input |
| `{csrf_token}` | `csrf_token()` — raw token, e.g. for `<meta>` tags or AJAX headers |
| `{method_field method="PUT"}` | `method_field('PUT')` |
| `{old field="title" default=...}` | `old('title', $default)` |
| `{error field="..."}...{/error}` | `@error('...')` — body renders only when there is a validation error; `$message` is bound inside |

## URLs & assets

| Tag | Equivalent |
|-----|------------|
| `{route name="posts.show" post=$post}` | `route('posts.show', ['post' => $post])` — every named param other than `name=` becomes a route parameter |
| `{url path="/login"}` | `url('/login')` |
| `{asset path="img/logo.svg"}` | `asset('img/logo.svg')` |
| `{signed_route name="unsubscribe" user=$user->id}` | `URL::signedRoute('unsubscribe', ['user' => $user->id])` — same param convention as `{route}` |
| `{temporary_signed_route name="download" expiration=3600 file=$file->id}` | `URL::temporarySignedRoute('download', 3600, ['file' => $file->id])` — `expiration=` accepts `int` seconds or any `DateTimeInterface` |

Both signed-URL helpers are non-cacheable: a baked signature would either ship a stale URL on warm renders or, for the temporary variant, an already-expired link.

## Translation

```smarty
<h1>{lang key="welcome" name=$user->name}</h1>
<p>{"errors.required"|trans}</p>

<p>{lang_choice key="messages.apples" count=$count}</p>
<p>{"messages.apples"|trans_choice:$count}</p>
```

| Tag/modifier | Equivalent |
|--------------|------------|
| `{lang key="..." foo=... bar=...}` | `__('...', ['foo' => ..., 'bar' => ...])` — every named param other than `key=` becomes a replacement |
| `\|trans` modifier | `__($key, $replace = [])` |
| `{lang_choice key="..." count=$n foo=...}` | `trans_choice('...', $n, ['foo' => ...])` — every named param other than `key=` and `count=` becomes a replacement |
| `\|trans_choice` modifier | `trans_choice($key, $count, $replace = [])` |

## Vite

```smarty
<head>
  {vite_react_refresh}
  {vite entrypoints=['resources/js/app.js']}
  <script nonce="{csp_nonce}">window.__APP_CONFIG = {$config|json};</script>
</head>

{* Versioned URL for an asset that isn't part of an entrypoint *}
<img src="{vite_asset path='resources/img/logo.svg'}" alt="">

{* Inline SVG sprite (output is raw — function plugins aren't auto-escaped) *}
{vite_content path="resources/img/sprite.svg"}
```

| Tag | Equivalent |
|-----|------------|
| `{vite entrypoints=[...] build_directory=...}` | Blade's `@vite([...], $buildDirectory)` — `build_directory` is optional |
| `{vite_react_refresh}` | Blade's `@viteReactRefresh` |
| `{csp_nonce}` | `Vite::cspNonce()` — the per-request CSP nonce, empty string when none has been set |
| `{vite_asset path="..." build_directory="..."}` | `Vite::asset($path, $buildDirectory)` — single versioned URL for an asset not declared as an entrypoint |
| `{vite_content path="..." build_directory="..."}` | `Vite::content($path, $buildDirectory)` — file contents (e.g. for inline SVG sprites under hashed builds) |

`{csp_nonce}`, `{vite_asset}`, and `{vite_content}` are all non-cacheable — the nonce changes per request, and asset URLs / contents change between hot mode and a built deployment.

## Conditional attributes

```smarty
<button class="{class array=['btn' => true, 'btn-primary' => $isPrimary, 'btn-disabled' => !$isActive]}">
<div style="{style array=['color: red' => $hasError, 'font-weight: bold' => $emphasised]}">
```

| Tag | Equivalent |
|-----|------------|
| `{class array=[...]}` | Blade's `@class([...])` — delegates to `Illuminate\Support\Arr::toCssClasses()`, the same helper Blade uses |
| `{style array=[...]}` | Blade's `@style([...])` — delegates to `Illuminate\Support\Arr::toCssStyles()` |

## Number formatting

Wraps `Illuminate\Support\Number` (Laravel 11+) so locale-aware currency, byte sizes, percentages, and abbreviated counts work as Smarty modifiers. On Laravel 10 these modifiers don't register; Smarty's native `number_format` continues to work.

```smarty
{$total|currency:'GBP'}            {* £1,234.56 *}
{$bytes|file_size}                 {* 1.46 KB    *}
{$bytes|file_size:1}               {* 1.5 KB     *}
{$share|percentage:1}              {* 12.3%      *}
{$views|abbreviate}                {* 1K         *}
{$count|number_for_humans:1}       {* 1.5 thousand *}
```

| Modifier | Equivalent |
|----------|------------|
| `\|currency:$in:$locale:$precision` | `Number::currency($value, $in, $locale, $precision)` |
| `\|file_size:$precision:$maxPrecision` | `Number::fileSize($bytes, $precision, $maxPrecision)` |
| `\|percentage:$precision:$maxPrecision:$locale` | `Number::percentage($value, $precision, $maxPrecision, $locale)` |
| `\|abbreviate:$precision:$maxPrecision` | `Number::abbreviate($value, $precision, $maxPrecision)` |
| `\|number_for_humans:$precision:$maxPrecision:$abbreviate` | `Number::forHumans($value, $precision, $maxPrecision, $abbreviate)` |

## Misc helpers

```smarty
{* Config values with a fallback *}
<title>{config key="app.name" default="My App"}</title>

{* Flash messages straight off the wrapper *}
{if $session->has('status')}
  <div class="alert">{$session->status}</div>
{/if}

{* Or pull the value once via the tag and reuse — handy when the same value
   feeds multiple places in the template *}
{session key="status" assign="status"}
{if isset($status)}
  <div class="alert">{$status|escape}</div>
{/if}

{* Resolve a service out of the container and bind it for the rest of the
   template. Useful for view-only pricing tables, formatters, etc. *}
{service name="App\\Services\\PricingTable" assign="pricing"}
<p>From {$pricing->headlinePrice()|currency:'GBP'}/month.</p>

{* Render trusted Markdown to HTML — `nofilter` opts out of auto-escape *}
<article>{$post->body|markdown nofilter}</article>

{* JSON-encode for safe embedding inside a <script> *}
<script>window.__APP_CONFIG = {$config|json};</script>
```

`$session` is one of four auto-shared wrapper objects — see [Auto-shared wrapper objects](wrapper-objects.md) for the full surface (`$auth`, `$request`, `$session`, `$route`).

| Tag/modifier | Equivalent |
|--------------|------------|
| `{config key="app.name" default=...}` | `config('app.name', $default)` |
| `{session key="status" default=...}` | `session('status', $default)` |
| `{session key="status" assign="status"}` | `$status = session('status')` (assigns instead of printing) |
| `$session->status` (auto-shared, see [Auto-shared wrapper objects](wrapper-objects.md)) | `session('status')` |
| `\|markdown` modifier | `Illuminate\Support\Str::markdown($value)` — pair with `nofilter` to keep the rendered HTML, the same way you'd reach for Blade's `{!! !!}` |
| `\|json` modifier | `Js::from($value)` — JSON-encodes for safe JS embedding |
| `{service name="App\\Services\\Foo" assign="foo"}` | `resolve('App\\Services\\Foo')` and assign as `$foo` for the rest of the template |
| `{dump x=$x y=$y}` | `dump($x, $y)` — every named param is dumped. Gated to `local`/`testing`; silent no-op elsewhere |
| `{dd x=$x}` | `dd($x, ...)` — every named param is dumped, then halts. Gated to `local`/`testing`; silent no-op elsewhere |
