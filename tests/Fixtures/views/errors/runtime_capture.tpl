<h1>Header</h1>
{capture name="buf"}
  <p>{$user->getAuthIdentifier()}</p>
{/capture}
{$smarty.capture.buf}
