{if $paginator->hasPages()}
    <nav>
        <ul class="pagination">
            {if $paginator->onFirstPage()}
                <li class="page-item disabled" aria-disabled="true" aria-label="{lang key="pagination.previous"}">
                    <span class="page-link" aria-hidden="true">&lsaquo;</span>
                </li>
            {else}
                <li class="page-item">
                    <a class="page-link" href="{$paginator->previousPageUrl()}" rel="prev" aria-label="{lang key="pagination.previous"}">&lsaquo;</a>
                </li>
            {/if}

            {foreach $elements as $element}
                {if is_string($element)}
                    <li class="page-item disabled" aria-disabled="true"><span class="page-link">{$element}</span></li>
                {/if}

                {if is_array($element)}
                    {foreach $element as $page => $url}
                        {if $page == $paginator->currentPage()}
                            <li class="page-item active" aria-current="page"><span class="page-link">{$page}</span></li>
                        {else}
                            <li class="page-item"><a class="page-link" href="{$url}">{$page}</a></li>
                        {/if}
                    {/foreach}
                {/if}
            {/foreach}

            {if $paginator->hasMorePages()}
                <li class="page-item">
                    <a class="page-link" href="{$paginator->nextPageUrl()}" rel="next" aria-label="{lang key="pagination.next"}">&rsaquo;</a>
                </li>
            {else}
                <li class="page-item disabled" aria-disabled="true" aria-label="{lang key="pagination.next"}">
                    <span class="page-link" aria-hidden="true">&rsaquo;</span>
                </li>
            {/if}
        </ul>
    </nav>
{/if}
