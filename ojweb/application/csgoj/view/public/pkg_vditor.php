{if(config('OJ_ENV.OJ_CDN') == 'local') /}
    {css href='__STATIC__/vditor/dist/index.css' /}
    {css href='__STATIC__/vditor/dist/css/content-theme/light.css' /}
    {js href='__STATIC__/vditor/dist/index.min.js' /}
{else /}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/vditor@3.10.6/dist/index.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/vditor@3.10.6/dist/css/content-theme/light.css">
    <script src="https://fastly.jsdelivr.net/npm/vditor@3.10.6/dist/index.min.js"></script>
{/if}
