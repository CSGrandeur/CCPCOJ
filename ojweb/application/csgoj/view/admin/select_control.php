{if(config('OJ_ENV.OJ_CDN') == 'local') }
    {css href="__STATIC__/bootstrap-select-1.13.18/css/bootstrap-select.min.css" /}
    {js href="__STATIC__/bootstrap-select-1.13.18/js/bootstrap-select.min.js" /}
{else /}
    {css href="//fastly.jsdelivr.net/npm/bootstrap-select@1.13.18/dist/css/bootstrap-select.min.css" /}
    {js href="//fastly.jsdelivr.net/npm/bootstrap-select@1.13.18/dist/js/bootstrap-select.min.js" /}
{/if}