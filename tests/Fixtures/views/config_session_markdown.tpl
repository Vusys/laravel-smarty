app_name={config key="app.name"}
missing={config key="app.does_not_exist" default="fallback"}
flash={session key="status"}
flash_default={session key="missing" default="nope"}
{session key="status" assign="status"}
{if $status}assigned={$status}{/if}
{if $session->has('status')}shared={$session->status}{/if}
markdown={"**bold**"|markdown nofilter}
