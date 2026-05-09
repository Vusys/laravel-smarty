status={$session->status}
has-error={if $session->has('error')}yes{else}no{/if}
default={$session->get('absent', 'd')}
