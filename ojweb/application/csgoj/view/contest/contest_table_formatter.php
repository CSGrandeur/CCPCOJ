<style>
    .contest_title {
        display: inline-block;
        max-width: 400px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
</style>
<script>
let now_time = $('#page_info').attr('time_stamp');
if(typeof(now_time) == 'undefined' || now_time == null || now_time.length == 0) {
    now_time = new Date().getTime();
} else {
    now_time *= 1000;
}
now_time = Timestamp2Time(now_time);
function FormatterTitle(value, row, index, field) {
    let contest_controller = row.private % 10 == 4 ? 'contestexp' : 'contest';
    return `<a href='/csgoj/${contest_controller}/problemset?cid=${row.contest_id}' class="contest_title" title="${row.title}">${row.title}</a>`;
}
function FormatterType(value, row, index, field) {
    let private = parseInt(value);
    let attach = Math.floor(private / 10 + 1e-8);
    let ckind = private % 10;
    let ckind_str, cl
    switch(ckind) {
        case 0: 
            if(row.password != '') {
                cl = 'warning';ckind_str = "Encrypted";
            } else {
                cl = 'success';ckind_str = "Public";
            }
            break;
        case 1: cl = 'danger';ckind_str = "Private"; break;
        case 2: cl = 'primary';ckind_str = "Standard"; break;
        case 4: cl = 'info';ckind_str = "习题"; break;
        case 5: cl = 'info';ckind_str = "试题"; break;
    }
    if(attach) {
        ckind_str += "/有附加";
    }
    return `<strong class='text-${cl}'>${ckind_str}</strong>`;
}
function FormatterStatus(value, row, index, field) {
    let cl, wd, sta;
    if(value == '0') {
        cl = 'success';
        wd = 'Available';
        sta = '0';
    } else {
        cl = 'warning';
        wd = 'Reserved';
        sta = '1';
    }
    return `<button type='button' field='defunct' itemid='${row.contest_id}' class='change_status btn btn-${cl}' status='${sta}'>${wd}</button>`;
}
function FormatterContestStatus(value, row, index, field) {
    if(now_time < row.start_time) {
        return "<strong class='text-success'>Coming</strong>";
    } else if(now_time <= row.end_time) {
        return "<strong class='text-danger'>Running</strong>";
    } else {
        return "<strong class='text-info'>Ended</strong>";
    }
}
function FormatterEdit(value, row, index, field) {
    return `<a href='/admin/contest/contest_edit?id=${row.contest_id}'>Edit</a>`;
}
function FormatterCopy(value, row, index, field) {
    return "<a href='__ADMIN__/contest/contest_copy?id=" + row['contest_id'] + "'>Copy</a>";
}
function FormatterAttach(value, row, index, field) {
    return `<a href='/admin/filemanager/filemanager?item=contest&id=${row.contest_id}' target='_blank'>Attach</a>`;
}
function FormatterRejudge(value, row, index, field) {
    return `<a href='/cpcsys/admin/contest_rejudge?cid=${row.contest_id}' target='_blank'>Rejudge</a>`;
}
</script>