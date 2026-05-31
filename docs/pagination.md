# Pagination

Laravel's paginator integrates without extra wiring. The package ships Smarty ports of every `pagination::*` template Laravel includes:

```php
public function index(Request $request)
{
    return view('posts', [
        'posts' => Post::query()->paginate(15),
    ]);
}
```

```smarty
{foreach $posts as $post}
  <article>{$post->title|escape}</article>
{/foreach}

{$posts->links()}                            {* default tailwind *}
{$posts->links('pagination::bootstrap-5')}   {* pick another preset *}
```

Bundled presets: `pagination::tailwind` (default), `pagination::simple-tailwind`, `pagination::bootstrap-5`, `pagination::simple-bootstrap-5`, `pagination::bootstrap-4`, `pagination::simple-bootstrap-4`, `pagination::bootstrap-3`, `pagination::simple-bootstrap-3`, `pagination::semantic-ui`.

The package's `.tpl` versions take priority over Laravel's framework Blade pagination views, so `$paginator->links('pagination::bootstrap-5')` resolves to a Smarty template — not the framework's `bootstrap-5.blade.php`. To customise, publish them and edit in place:

```bash
php artisan vendor:publish --tag=smarty-pagination-views
```

Anything you publish under `resources/views/vendor/pagination/` wins over both the package's bundled `.tpl` and Laravel's bundled `.blade.php` for the matching preset name.
