routeIs={if $request->routeIs('feed.*')}yes{else}no{/if}
input={$request->input('q', 'fallback')|escape}
path={$request->path()}
