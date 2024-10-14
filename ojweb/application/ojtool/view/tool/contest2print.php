{__NOLAYOUT__}

{include file="public/global_head" /}
<link rel="stylesheet" type="text/css" href="__CSS__/markdownhtml.css" />
<script type="text/javascript" src="__STATIC__/csgoj/oj_problem.js"></script>

<input type="hidden" id="page_info" OJ_MODE="{$OJ_MODE}">
<div class="md_display_div" id="problems_print_div"></div>
<script>
    let page_info = csg.getdom("#page_info");
    let sc = {
        OJ_MODE: page_info.getAttribute("OJ_MODE")
    };
    var page_module = sc.OJ_MODE == "cpcsys" ? "cpcsys" : "csgoj";
    let urlParam = csg.GetUrlParam();
    let cid;
    let with_page = 'with_page' in urlParam && urlParam.with_page == 1;
   
    marked.use({
        mangle: false,
        headerIds: false,
    });
    function AM(st, prefix='', suffix='\n\n') {
        return `${prefix}${st}${suffix}`;
    }
    function SampleJoin(sample_in_str, sample_out_str) {
        // 处理多组样例拼接
        if(typeof(sample_in_str) != 'string') {
            sample_in_str = '';
        }
        if(typeof(sample_out_str) != 'string') {
            sample_in_str = '';
        }
        let sample_in_list = sample_in_str.split(pro_sample_split_reg);
        let sample_out_list = sample_out_str.split(pro_sample_split_reg);
        let sample_num_max = Math.max(sample_in_list.length, sample_out_list.length);
        let sample_tbody = ``;
        for(let i = 0; i < sample_num_max; i ++) {
            sample_tbody += `<tr>
            <td><pre>${DomSantize("#" + i)}</pre></td>
            <td class="sample_td"><pre>${i < sample_in_list.length ? DomSantize(sample_in_list[i]) : ''}</pre></td>
            <td class="sample_td"><pre>${i < sample_out_list.length ? DomSantize(sample_out_list[i]) : ''}</pre></td>
            </tr>`
        }
        return `\n\n<table class="score_table">
            <thead><tr><th>序</th><th>输入样例</th><th>输出样例</th></tr></thead>
            <tbody>${sample_tbody}</tbody>
        </table>\n\n`;
    }
    function ProblemHtml(ith, problem) {
        let md = '';
        md += AM(`### ${String.fromCharCode('A'.charCodeAt(0) + ith)}. ${problem.title}`);
        md += AM(`> 时限: ${problem.time_limit}s&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;内存限制: ${problem.memory_limit}MB&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;${problem.spj == '0' ? "" : "<span class='text-danger'>特殊评测</span>"}`);  
        md += AM(`${problem.description}`);
        md += AM(`#### 输入`);
        md += AM(`${problem.input}`);
        md += AM(`#### 输出`);
        md += AM(`${problem.output}`);
        md += AM(`#### 样例`);
        md += SampleJoin(problem.sample_input, problem.sample_output);
        if(typeof(problem.hint) == 'string' && problem.hint.trim() !== '') {
            md += AM(`#### 提示`);
            md += AM(`${problem.hint}`);
        }
        
        return marked.parse(md);
    }
    function LoadContestLogo(element, imageName) {
        const extensions = ['svg', 'png', 'jpg'];
        let index = 0;
        function TryLoadImage() {
            if (index >= extensions.length) {
                element.outerHTML = `<div id="contest_logo_img"></div>`;
                return;
            }
            const src = imageName + '.' + extensions[index];
            const img = new Image();
            img.onload = function() {
                element.src = src;
            };
            img.onerror = function() {
                index++;
                TryLoadImage();
            };
            img.src = src;
        }
        TryLoadImage();
    }
    function CalculateTimeDifference(time1, time2) {
        const date1 = new Date(time1);
        const date2 = new Date(time2);
        let diff = Math.abs(date1 - date2);
        diff = Math.round((diff / 1000 / 60 / 60) * 10) / 10;
        return diff;
    }
    function HeadPage(contest, pro_list) {
        let ret = `<div id="head_page">`;
        let title = contest.title.split('#');
        $('title').text(title[0] + (title.length > 1 ? title[1] : ""));
        ret += `<h1 id="main_title_h">${title[0]}</h1>`;
        ret += `<h2 id="sub_title_h">${title.length > 1 ? title[1] : ""}</h2>`;
        ret += `<img id="contest_logo_img" src="" />`;
        ret += `<div class="head_page_info_list">`;
        ret += `<div class="head_page_info"><div class="head_info_key">题数:</div><div class="head_info_val">${pro_list.length}题</div></div>`;
        ret += `<div class="head_page_info"><div class="head_info_key">时长:</div><div class="head_info_val">${CalculateTimeDifference(contest.end_time, contest.start_time)}小时</div></div>`;
        ret += `</div>`;
        ret += `<div class="bottom_title_div_list">`;
        for(let i = 2; i < title.length; i ++) {
            ret += `<div class="bottom_title_div">${title[i]}</div>`;
        }
        ret += `<div class="bottom_title_div">${TimeLocal(contest.start_time, "yyyy年MM月dd日")}</div>`;
        ret += `</div>`;
        ret += `</div>`;
        return ret;
    }
    // function SetPageNumber() {
    //     let number = 1; // 开始的数字
    //     let distance = 330; // 每个数字之间的距离，单位为mm
    //     let dpi = window.devicePixelRatio * 96;

    //     // 获取页面的高度
    //     let page_item_list = document.getElementsByClassName('page-break');
    //     let toheight = 0;
    //     for(let i = 0; i < page_item_list.length; i ++) {
    //         toheight += page_item_list[i].offsetHeight;
    //     }
    //     let pageHeight = Math.max(
    //         document.body.scrollHeight, document.documentElement.scrollHeight,
    //         document.body.offsetHeight, document.documentElement.offsetHeight,
    //         document.body.clientHeight, document.documentElement.clientHeight,
    //         $('#problems_print_div').height()
    //     );

    //     // 将页面高度从px转换为mm
    //     pageHeight = pageHeight * 25.4 / dpi;
    //     let max_page = Math.floor(pageHeight / distance) - 1;
    //     // 在页面上放置数字
    //     for(let i = distance * 2 - 5; i < pageHeight; i += distance) {
    //         let div = document.createElement('div');
    //         div.style.position = 'absolute';
    //         div.style.top = `${i}mm`;
    //         div.style.left = '50%';
    //         div.textContent = `${number++}/${max_page}`;
    //         document.body.appendChild(div);
    //     }
    // }
    csg.docready(function(){
        if('cid' in urlParam && 'module' in urlParam) {
            cid = urlParam['cid'];
            $.ajax({
                url: '/' + urlParam['module'] + '/contest/contest_problem_description_export',
                data: {
                    'cid': urlParam['cid'],
                    'with_source': 0,
                    'with_author': 0
                },
                success: function(ret) {
                    if('code' in ret && ret.code != 1) {
                        csg.getdom('#problems_print_div').innerHTML = ret.msg;
                    } else {
                        let all_pro_str = "";
                        let contest = ret['contest'];
                        let pro_list = ret['problem_list'];
                        let cnt = 0;
                        all_pro_str += `<div class="page-break">${HeadPage(contest, pro_list)}</div>`;
                        for(let i = 0; i < pro_list.length; i ++) {;
                            let pro_html = ProblemHtml(i, pro_list[i]);
                            all_pro_str += `<div class="page-break">${pro_html}</div>`;
                        }
                        csg.getdom('#problems_print_div').innerHTML = all_pro_str;
                        MathJax.typeset();
                        LoadContestLogo(csg.getdom('#contest_logo_img'), `/upload/contest_attach/${contest.attach}/contest_logo`);
                        // if(with_page) {;
                        //     SetPageNumber();
                        // }
                    }
                },
                dataType: 'json',
                type: 'post'
            });
        }
    });
</script>

<style type='text/css'>
    @page {
        size: A4;
        margin: 15mm 15mm 20mm 15mm;
        padding: 0;
    }
    body {
        width: 210mm;
        height: 297mm;
        padding: 20mm 10mm 10mm 10mm;
    }
    .md_display_div:not(.math) {
        width: 200mm;
        text-align: justify;
        font-size: 12pt !important;
        line-height: 18pt;
        font-family: "等线", Helvetica Neue,Helvetica,PingFang SC,Hiragino Sans GB,Microsoft YaHei,Noto Sans CJK SC,WenQuanYi Micro Hei,Arial,sans-serif !important;
    }
    .md_display_div pre:not(.sampledata) {
        background-color: transparent !important;
    }
    .md_display_div p {
        margin-bottom: 8pt;
    }
    .math {
        font-family: "Cambria Math", "STIX", "XITS", "TeX Gyre Termes", "Latin Modern Math", "Asana Math";
    }
    h1, h2 {
        font-weight: bolder;
    }
    .page-break {
        width: 100%;
        page-break-after: always;
        padding-top: 5mm;
    }
    p {
        font-size: 12pt;
    }
    
    .sample_td {
        overflow-x: hidden;
        vertical-align: top;
        border: 1px solid;
    }
    .sample_td>pre {
        width: 300px !important; 
        text-align: left;
        color: black;
    }
    
blockquote {
    margin-top: 0;
    margin-bottom: 1rem;
    margin-left: 0;
    font-size: .875em;
    color: #6c757d;
    border: none;
}

#head_page {
    display: flex;
    flex-direction: column;
    align-items: center;
}
#main_title_h, #sub_title_h{
    text-align: center;
    font-size: 40px;
}
#main_title_h {
    margin-top: 20mm;
}
#contest_logo_img {
    margin-top: 10mm;
    height: 90mm;
}
.head_page_info {
    display: flex;
    font-size: 24px;
    margin-top: 5mm;
}
.head_page_info_list {
    margin-top: 20mm;
    display: flex;
    flex-direction: column;
    align-items: left;
}
.head_info_val {
    margin-left: 5mm;
}
.bottom_title_div_list {
    margin-top: 20mm;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.bottom_title_div {
    font-size: 24px;
    margin-top: 5mm;
}
</style>

