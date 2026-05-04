{extends file="errors/error_layout.tpl"}

{block name="content"}
  <h1>From child</h1>
  <p>{$user->getAuthIdentifier()}</p>
{/block}
