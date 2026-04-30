{if $paginator->hasPages()}
    <nav>
        <ul class="pagination">
            {if $paginator->onFirstPage()}
                <li class="page-item disabled" aria-disabled="true">
                    <span class="page-link">{lang key="pagination.previous"}</span>
                </li>
            {else}
                <li class="page-item">
                    <a class="page-link" href="{$paginator->previousPageUrl()}" rel="prev">{lang key="pagination.previous"}</a>
                </li>
            {/if}

            {if $paginator->hasMorePages()}
                <li class="page-item">
                    <a class="page-link" href="{$paginator->nextPageUrl()}" rel="next">{lang key="pagination.next"}</a>
                </li>
            {else}
                <li class="page-item disabled" aria-disabled="true">
                    <span class="page-link">{lang key="pagination.next"}</span>
                </li>
            {/if}
        </ul>
    </nav>
{/if}
