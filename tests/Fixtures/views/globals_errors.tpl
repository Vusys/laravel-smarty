{if $errors->any()}
<ul>
{foreach $errors->all() as $message}<li>{$message}</li>
{/foreach}</ul>
{else}
<p>no-errors</p>
{/if}
first-email={$errors->first('email')}
login-bag-any={if $errors->getBag('login')->any()}yes{else}no{/if}
