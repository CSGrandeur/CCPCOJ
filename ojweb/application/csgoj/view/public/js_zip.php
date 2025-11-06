{if(config('OJ_ENV.OJ_CDN') == 'local') /}
    {js href='__STATIC__/zip.js/zip.min.js' /}
{else /}
    <script src="https://fastly.jsdelivr.net/npm/@zip.js/zip.js@2.8.8/dist/zip.min.js"></script>
{/if}
