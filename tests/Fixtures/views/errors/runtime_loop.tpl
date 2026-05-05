<ul>
{foreach $items as $item}
  <li>{$item->missingMethod()}</li>
{/foreach}
</ul>
