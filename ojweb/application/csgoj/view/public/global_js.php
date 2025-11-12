{if(config('OJ_ENV.OJ_CDN') == 'local') }
    {js href="__JS__/jquery-3.7.1.min.js" /}
    {js href="__JS__/jquery.cookie.min.js" /}
    {js href="__STATIC__/bootstrap-5.3.8/js/bootstrap.bundle.min.js" /}
    {js href="__STATIC__/bootstrap-table/bootstrap-table.min.js" /}
    {js href='__STATIC__/bootstrap-table/extensions/toolbar/bootstrap-table-toolbar.min.js' /}
    {js href='__STATIC__/tableExport.jquery.plugin/tableExport.min.js' /}
    {js href='__STATIC__/tableExport.jquery.plugin/libs/html2canvas//html2canvas.min.js' /}
    {js href='__STATIC__/bootstrap-table/extensions/export/bootstrap-table-export.min.js' /}
    {js href="__STATIC__/bootstrap-table/extensions/cookie/bootstrap-table-cookie.min.js" /}
    {js href="__JS__/jquery.form.min.js" /}
    {js href="__STATIC__/alertifyjs/alertify.min.js" /}
    {js href="__STATIC__/bootstrap-switch-3.3.4/js/bootstrap-switch.min.js" /}
{else /}
    {js href="//fastly.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" /}
    {js href="//fastly.jsdelivr.net/npm/jquery.cookie@1.4.1/jquery.cookie.min.js" /}
    {js href="//fastly.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" /}
    {js href="//fastly.jsdelivr.net/npm/bootstrap-table@1.25.0/dist/bootstrap-table.min.js" /}
    {js href="//fastly.jsdelivr.net/npm/bootstrap-table@1.25.0/dist/extensions/toolbar/bootstrap-table-toolbar.min.js" /}
    {js href="//fastly.jsdelivr.net/npm/tableexport.jquery.plugin@1.33.0/tableExport.min.js" /}
    {js href="//fastly.jsdelivr.net/npm/tableexport.jquery.plugin@1.33.0/libs/html2canvas/html2canvas.min.js" /}
    {js href="//fastly.jsdelivr.net/npm/bootstrap-table@1.25.0/dist/extensions/export/bootstrap-table-export.min.js" /}
    {js href="//fastly.jsdelivr.net/npm/bootstrap-table@1.25.0/dist/extensions/cookie/bootstrap-table-cookie.min.js" /}
    {js href="//fastly.jsdelivr.net/npm/jquery-form@4.3.0/dist/jquery.form.min.js" /}
    {js href="//fastly.jsdelivr.net/npm/alertifyjs@1.14.0/build/alertify.min.js" /}
    {js href="//fastly.jsdelivr.net/npm/bootstrap-switch@3.3.4/dist/js/bootstrap-switch.min.js" /}
{/if}


{js href="__JS__/global.js" /}
{js href="__JS__/tools/idb.js" /}
{js href="__JS__/util.js" /}
{js href="__STATIC__/js/bilingual.js" /}
{js href="__STATIC__/js/alerty.js" /}
{js href="__STATIC__/js/form_validate_tip.js" /}
{js href="__STATIC__/csgoj/general_formatter.js" /}