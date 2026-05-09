{if $auth}authed:{$auth->id}:{$auth->user->getAuthIdentifier()}{else}guest{/if}
