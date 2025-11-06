{include file="../../csgoj/view/public/global_head" /}
{include file="../../csgoj/view/public/mathjax_js" /}
{include file="../../csgoj/view/public/code_highlight" /}
{css href="__STATIC__/csgoj/oj_problem.css" /}
<script type="text/javascript" src="__STATIC__/csgoj/oj_problem.js"></script>
<div id="problems_print_div">
    <!-- 封面页 -->
    <div class="page-break">
        <div id="head_page">
            <?php
            $title_parts = explode('#', $contest['title']);
            ?>
            <h1 id="main_title_h">{$title_parts[0]}</h1>
            {if count($title_parts) > 1}
            <h2 id="sub_title_h">{$title_parts[1]}</h2>
            {/if}
            <img id="contest_logo_img" src="" />
            <div class="head_page_info_list">
                <div class="head_page_info">
                    <div class="head_info_key">题数:</div>
                    <div class="head_info_val">{$problem_list|count}题</div>
                </div>
                <div class="head_page_info">
                    <div class="head_info_key">时长:</div>
                    <div class="head_info_val"><?php
                        $start = strtotime($contest['start_time']);
                        $end = strtotime($contest['end_time']);
                        $diff = abs($end - $start);
                        $hours = round(($diff / 3600) * 10) / 10;
                        echo $hours;
                    ?>小时</div>
                </div>
            </div>
            <div class="bottom_title_div_list">
                <?php
                for ($i = 2; $i < count($title_parts); $i++) {
                    echo '<div class="bottom_title_div">' . htmlspecialchars($title_parts[$i]) . '</div>';
                }
                ?>
                <div class="bottom_title_div"><?php echo date('Y年m月d日', strtotime($contest['start_time'])); ?></div>
            </div>
        </div>
    </div>

    <!-- 题目列表 -->
    {volist name="problem_list" id="problem" key="k"}
    <div class="page-break">
        <div class="md_display_div">
            <h2>{$problem.apid}. {$problem.title}</h2>
            <blockquote>
                时限: {$problem.time_limit}s&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                内存限制: {$problem.memory_limit}MB
                {if $problem.spj != '0'}
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <span class="text-danger">特殊评测</span>
                {/if}
            </blockquote>
        </div>

        {if !empty($problem.description)}
        <div name="description" class="md_display_div">
            <h2 class="text-info bilingual-inline">题目描述<span class="en-text">Description</span></h2>
            {$problem.description}
        </div>
        {/if}

        {if !empty($problem.input)}
        <div name="input" class="md_display_div">
            <h2 class="text-info bilingual-inline">输入格式<span class="en-text">Input</span></h2>
            {$problem.input}
        </div>
        {/if}

        {if !empty($problem.output)}
        <div name="output" class="md_display_div">
            <h2 class="text-info bilingual-inline">输出格式<span class="en-text">Output</span></h2>
            {$problem.output}
        </div>
        {/if}

        {if !empty($problem.sample_input) || !empty($problem.sample_output)}
        <div name="Sample" class="md_display_div">
            <h2 class="text-info bilingual-inline">样例<span class="en-text">Sample</span></h2>
            <div class="sample_div">
                <div class="sample_row">
                    <div class="sample_col sample_input">
                        <pre class="sampledata sample_input_area">{$problem.sample_input|htmlspecialchars}</pre>
                    </div>
                    <div class="sample_col sample_output">
                        <pre class="sampledata sample_output_area">{$problem.sample_output|htmlspecialchars}</pre>
                    </div>
                </div>
            </div>
        </div>
        {/if}

        {if !empty($problem.hint)}
        <div name="hint" class="md_display_div">
            <h2 class="text-info bilingual-inline">提示<span class="en-text">Hint</span></h2>
            {$problem.hint}
        </div>
        {/if}
    </div>
    {/volist}
</div>

<script>
    // 加载比赛Logo
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

    $(document).ready(function() {
        // 使用 oj_problem.js 处理样例显示
        $('.sample_div').each(function() {
            let sample_div = $(this);
            let sample_in_str = sample_div.find('.sample_input_area').text();
            let sample_out_str = sample_div.find('.sample_output_area').text();
            
            if (sample_in_str || sample_out_str) {
                sample_div.html(ProblemSampleHtml(sample_in_str, sample_out_str));
            }
        });

        // 加载比赛Logo
        const contestLogoImg = document.getElementById('contest_logo_img');
        if (contestLogoImg) {
            LoadContestLogo(contestLogoImg, '/upload/contest_attach/{$contest.attach}/contest_logo');
        }

        // 渲染 MathJax
        if (typeof MathJax !== 'undefined') {
            MathJax.typeset();
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
    
    /* 打印模式下的样例样式 */
    @media print {
        .sample-item {
            page-break-inside: avoid;
        }
        .sample-item .card {
            border: 1px solid #000 !important;
            box-shadow: none !important;
        }
        .sample-item .card-header {
            background: #f8f9fa !important;
            border-bottom: 1px solid #000 !important;
        }
        .sample-content {
            background: #fff !important;
        }
        .sample-content pre {
            background: #fff !important;
            border: 1px solid #ddd !important;
            padding: 0.5rem !important;
        }
        /* 强制保持左右两栏布局 */
        .sample-item .row {
            display: flex !important;
            flex-direction: row !important;
        }
        .sample-item .col-6 {
            width: 50% !important;
            max-width: 50% !important;
            flex: 0 0 50% !important;
            border-right: 1px solid #dee2e6 !important;
            border-bottom: none !important;
        }
        .sample-item .col-6:last-child {
            border-right: none !important;
            border-bottom: none !important;
        }
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