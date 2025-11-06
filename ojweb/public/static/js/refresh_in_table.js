var bootstraptable_refresh_local = $('.bootstraptable_refresh_local');
$(window).keydown(function(e) {
    if (e.keyCode == 116 && !e.ctrlKey) {
        if(window.event){
            try{e.keyCode = 0;}catch(e){}
            e.returnValue = false;
        }
        e.preventDefault();
        bootstraptable_refresh_local.bootstrapTable('refresh');
    }
});

var widthAlreadyReset = false;
bootstraptable_refresh_local.on('post-body.bs.table', function(){
    if(!widthAlreadyReset)
    {
        ResetBootstrapTableWidth(bootstraptable_refresh_local, bootstraptable_refresh_local.closest('.bootstrap-table'));
    }
});

bootstraptable_refresh_local.on('load-success.bs.table', function(){
    if(!widthAlreadyReset)
    {
        ResetBootstrapTableWidth(bootstraptable_refresh_local, bootstraptable_refresh_local.closest('.bootstrap-table'));
    }
});
$(window).resize(function () {
    // bootstraptable_refresh_local.bootstrapTable('resetWidth');
    bootstraptable_refresh_local.bootstrapTable('resetView');
    ResetBootstrapTableWidth(bootstraptable_refresh_local, bootstraptable_refresh_local.closest('.bootstrap-table'));
});
function ResetBootstrapTableWidth(table, table_div)
{
    if(table?.[0]?.scrollWidth && table[0].scrollWidth > table_div.width()) {
        table_div.width(table[0].scrollWidth + 20);
        widthAlreadyReset = true;
    }
}