{if $paginator->hasPages()}
    <nav>
        <ul class="pagination">
            {if $paginator->onFirstPage()}
                <li class="disabled" aria-disabled="true"><span>{lang key="pagination.previous"}</span></li>
            {else}
                <li><a href="{$paginator->previousPageUrl()}" rel="prev">{lang key="pagination.previous"}</a></li>
            {/if}

            {if $paginator->hasMorePages()}
                <li><a href="{$paginator->nextPageUrl()}" rel="next">{lang key="pagination.next"}</a></li>
            {else}
                <li class="disabled" aria-disabled="true"><span>{lang key="pagination.next"}</span></li>
            {/if}
        </ul>
    </nav>
{/if}
