{if(config('OJ_ENV.OJ_CDN') == 'local') /}
    {js href='__STATIC__/zip.js/zip-full.min.js' /}
{else /}
    <script src="https://fastly.jsdelivr.net/npm/@zip.js/zip.js@2.7.52/dist/zip-fs-full.min.js"></script>
{/if}
