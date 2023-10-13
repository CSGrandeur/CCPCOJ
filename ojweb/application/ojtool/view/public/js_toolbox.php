{if(config('OJ_ENV.OJ_CDN') == 'local') /}
    {js href='__STATIC__/seedrandom/seedrandom.min.js' /}
    {js href='__STATIC__/muuri/muuri.min.js' /}
    {js href='__STATIC__/identicon.js/identicon.min.js' /}
{else /}
    <script src="https://fastly.jsdelivr.net/npm/seedrandom@3.0.5/seedrandom.min.js"></script>
    <script src="https://fastly.jsdelivr.net/npm/muuri@0.9.5/dist/muuri.min.js"></script>
    <script src="https://fastly.jsdelivr.net/npm/identicon.js@2.3.3/identicon.min.js"></script>
{/if}