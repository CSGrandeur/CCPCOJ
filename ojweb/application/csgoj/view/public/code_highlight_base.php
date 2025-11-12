{if(config('OJ_ENV.OJ_CDN') == 'local') }
    {js href="__STATIC__/highlight/highlight.min.js" /}
    {css href="__STATIC__/highlight/styles/github.min.css" /}
    {js href="__STATIC__/highlight/languages/c.min.js" /}
    {js href="__STATIC__/highlight/languages/cpp.min.js" /}
    {js href="__STATIC__/highlight/languages/java.min.js" /}
    {js href="__STATIC__/highlight/languages/python.min.js" /}
    {js href="__STATIC__/highlight/languages/go.min.js" /}
    {js href="__STATIC__/highlight/languages/javascript.min.js" /}
    {js href="__STATIC__/highlight/languages/plaintext.min.js" /}
{else /}
    {js href="//fastly.jsdelivr.net/gh/highlightjs/cdn-release@11.11.1/build/highlight.min.js" /}
    {css href="//fastly.jsdelivr.net/gh/highlightjs/cdn-release@11.11.1/build/styles/github.min.css" /}
    {js href="//fastly.jsdelivr.net/gh/highlightjs/cdn-release@11.11.1/build/languages/c.min.js" /}
    {js href="//fastly.jsdelivr.net/gh/highlightjs/cdn-release@11.11.1/build/languages/cpp.min.js" /}
    {js href="//fastly.jsdelivr.net/gh/highlightjs/cdn-release@11.11.1/build/languages/java.min.js" /}
    {js href="//fastly.jsdelivr.net/gh/highlightjs/cdn-release@11.11.1/build/languages/python.min.js" /}
    {js href="//fastly.jsdelivr.net/gh/highlightjs/cdn-release@11.11.1/build/languages/go.min.js" /}
    {js href="//fastly.jsdelivr.net/gh/highlightjs/cdn-release@11.11.1/build/languages/javascript.min.js" /}
    {js href="//fastly.jsdelivr.net/gh/highlightjs/cdn-release@11.11.1/build/languages/plaintext.min.js" /}
{/if}