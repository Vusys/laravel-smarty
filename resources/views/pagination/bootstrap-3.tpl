{if $paginator->hasPages()}
    <nav>
        <ul class="pagination">
            {if $paginator->onFirstPage()}
                <li class="disabled" aria-disabled="true" aria-label="{lang key="pagination.previous"}">
                    <span aria-hidden="true">&lsaquo;</span>
                </li>
            {else}
                <li>
                    <a href="{$paginator->previousPageUrl()}" rel="prev" aria-label="{lang key="pagination.previous"}">&lsaquo;</a>
                </li>
            {/if}

            {foreach $elements as $element}
                {if $element|is_array}
                    {foreach $element as $page => $url}
                        {if $page == $paginator->currentPage()}
                            <li class="active" aria-current="page"><span>{$page}</span></li>
                        {else}
                            <li><a href="{$url}">{$page}</a></li>
                        {/if}
                    {/foreach}
                {else}
                    <li class="disabled" aria-disabled="true"><span>{$element}</span></li>
                {/if}
            {/foreach}

            {if $paginator->hasMorePages()}
                <li>
                    <a href="{$paginator->nextPageUrl()}" rel="next" aria-label="{lang key="pagination.next"}">&rsaquo;</a>
                </li>
            {else}
                <li class="disabled" aria-disabled="true" aria-label="{lang key="pagination.next"}">
                    <span aria-hidden="true">&rsaquo;</span>
                </li>
            {/if}
        </ul>
    </nav>
{/if}
