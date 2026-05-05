{function name="render_user"}
  <p>name: {$user->getAuthIdentifier()}</p>
{/function}

<h1>Wrapper</h1>
{call name="render_user" user=$user}
