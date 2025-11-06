{if(config('OJ_ENV.OJ_CDN') == 'local') /}
    {js href='__STATIC__/identicon.js/identicon.min.js' /}
{else /}
    <script src="https://fastly.jsdelivr.net/npm/identicon.js@2.3.3/identicon.min.js"></script>
{/if}
{js href="__STATIC__/js/tools/hash_img.js" /}