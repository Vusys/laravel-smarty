{if $paginator->hasPages()}
    <div class="ui pagination menu" role="navigation">
        {if $paginator->onFirstPage()}
            <a class="icon item disabled" aria-disabled="true" aria-label="{lang key="pagination.previous"}"> <i class="left chevron icon"></i> </a>
        {else}
            <a class="icon item" href="{$paginator->previousPageUrl()}" rel="prev" aria-label="{lang key="pagination.previous"}"> <i class="left chevron icon"></i> </a>
        {/if}

        {foreach $elements as $element}
            {if $element|is_array}
                {foreach $element as $page => $url}
                    {if $page == $paginator->currentPage()}
                        <a class="item active" href="{$url}" aria-current="page">{$page}</a>
                    {else}
                        <a class="item" href="{$url}">{$page}</a>
                    {/if}
                {/foreach}
            {else}
                <a class="icon item disabled" aria-disabled="true">{$element}</a>
            {/if}
        {/foreach}

        {if $paginator->hasMorePages()}
            <a class="icon item" href="{$paginator->nextPageUrl()}" rel="next" aria-label="{lang key="pagination.next"}"> <i class="right chevron icon"></i> </a>
        {else}
            <a class="icon item disabled" aria-disabled="true" aria-label="{lang key="pagination.next"}"> <i class="right chevron icon"></i> </a>
        {/if}
    </div>
{/if}
