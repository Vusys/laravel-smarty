{if feature_active('on-flag')}[positive]{else}[negative]{/if}
{if feature_active('off-flag')}[positive-off]{else}[negative-off]{/if}
{if feature_active('beta-export', $user)}[for-positive]{else}[for-negative]{/if}
