<table
    id="problemarchive_table"
    data-toggle="table"
    data-url="__OJ__/problemarchive/problemarchive_ajax"
    data-pagination="true"
    data-page-list="[25,50,100]"
    data-page-size="100"
    data-side-pagination="client"
    data-method="get"
    data-striped="true"
    data-search="true"
    data-search-align="left"
    data-sort-name="in_date"
    data-sort-order="desc"
    data-pagination-v-align="both"
    data-pagination-h-align="left"
    data-pagination-detail-h-align="right"
    data-classes="table-no-bordered table table-hover"
    data-show-refresh="true"
    data-buttons-align="left"
>
    <thead>
    <tr>
        <th data-field="Idx" data-align="center" data-valign="middle"  data-sortable="false" data-width="30" data-formatter="FormatterIdx"></th>
        <th data-field="source" data-align="left" data-valign="middle"  data-sortable="true" data-formatter="FormatterSource">Source</th>
        <th data-field="in_date" data-align="left" data-valign="middle"  data-sortable="true" data-width="120" data-formatter="FormatterInDate">ArchivedDate</th>
        <th data-field="author" data-align="left" data-valign="middle"  data-sortable="false"  data-formatter="FormatterAuthor">Author(s)</th>
    </tr>
    </thead>
</table>
<script type="text/javascript">
function FormatterInDate(value, row, index, field) {
    return value ? value.split(' ')[0] : value;
}
function FormatterAuthor(value, row, index, field) {
    return value ? [...new Set(value.split(',')
        .map(item => item.replace(/<p>|<\/p>/g, '').trim())
        .filter(Boolean))].join(', ') : value;
}
function FormatterSource(value, row, index) {
    let tmpv = value.replace(/(<([^>]+)>)/ig, "");
    if("<p>" + tmpv + "</p>" == value) {
        let search_url = "/csgoj/problemset#search=" + tmpv;
        return "<a href='" + search_url + "'>" + tmpv + "</a>";
    }
    return value;
}
let toobar_ok = $('#toobar_ok');
let page_jump_input = $('#page_jump_input');
let problemarchive_table = $('#problemarchive_table');
let search_cookie_name = problemarchive_table.attr('data-cookie-id-table') + ".bs.table.searchText"
let search_input;
// set table cookie before table rendered for each parameter. Warning: maybe become invalid after bootstrap-table updated.
SetProblemFilter(null, true);
$(window).keydown(function(e) {
    if (e.keyCode == 116 && !e.ctrlKey) {
        if(window.event){
            try{e.keyCode = 0;}catch(e){}
            e.returnValue = false;
        }
        e.preventDefault();
        problemarchive_table.bootstrapTable('refresh');
    }
});
function SetProblemFilter(search_input=null, onlycookie=false) {
    let search_str = GetAnchor("search");
    if(search_str !== null) {
        document.cookie = [
            search_cookie_name, '=', search_str
        ].join('');
        if(!onlycookie && search_input !== null) {
            search_input.val(search_str).trigger('keyup');
        }
    }
}
// when click source
$(window).on('hashchange', function(e) {
    search_str = GetAnchor("search");
    search_input.val(search_str).trigger('keyup');
});
$(document).ready(function(){
    page_jump_input.val(problemarchive_table.bootstrapTable('getOptions')['pageNumber']);
    search_input = $(".search>input[placeholder='Search']");
    search_input.on('input', function() {
        SetAnchor(search_input.val(), 'search');
    });
    // SetProblemFilter(search_input);
});
</script>