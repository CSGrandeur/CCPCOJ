
{if(config('OJ_ENV.OJ_CDN') == 'local') }
    {js file="__STATIC__/multiple-select/multiple-select.min.js" /}
    {css file="__STATIC__/multiple-select/multiple-select.min.css" /}
{else /}
    {js href="//fastly.jsdelivr.net/npm/multiple-select@2.2.0/dist/multiple-select.min.js" /}
    {css href="//fastly.jsdelivr.net/npm/multiple-select@2.2.0/dist/multiple-select.min.css" /}
{/if}

{css file="__STATIC__/csg_select/csg_select.css" /}
{js file="__STATIC__/csg_select/csg_select.js" /}
