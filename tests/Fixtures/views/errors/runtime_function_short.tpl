{function name="render_user"}
  <p>{$user->getAuthIdentifier()}</p>
{/function}
<h1>Wrapper</h1>
{render_user user=$user}
