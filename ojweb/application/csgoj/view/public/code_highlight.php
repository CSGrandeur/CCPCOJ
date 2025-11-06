{if(config('OJ_ENV.OJ_CDN') == 'local') }
    {js href="__STATIC__/highlight/highlight.min.js" /}
    {css href="__STATIC__/highlight/styles/github.min.css" /}
{else /}
    {js href="//fastly.jsdelivr.net/npm/highlight.js@11.11.1/lib/common.min.js" /}
    {css href="//fastly.jsdelivr.net/npm/highlight.js@11.11.1/styles/github.min.css" /}
{/if}
<script type="text/javascript">hljs.highlightAll();</script>