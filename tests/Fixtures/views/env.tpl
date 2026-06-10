{env names="testing,local"}[env-match]{/env}
{env names=['testing', 'local']}[env-array-match]{/env}
{env names="production"}[env-miss]{/env}
{env names="production" inverse=true}[env-inverse-match]{/env}
{env names="testing" inverse=true}[env-inverse-miss]{/env}
{env}[env-empty]{/env}
{env inverse=true}[env-empty-inverse]{/env}
