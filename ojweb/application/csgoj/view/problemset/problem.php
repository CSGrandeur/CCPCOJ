{include file="../../csgoj/view/problemset/problem_header" /}
{include file="../../csgoj/view/public/mathjax_js" /}
{include file="../../csgoj/view/public/code_highlight" /}
{css href="__STATIC__/csgoj/oj_problem.css" /}
{js href="__STATIC__/csgoj/oj_problem.js" /}
<div>
    <?php
    $items = [
        'description' => ['article', $problem['description'], '题目描述', 'Description'],
        'input' => ['article', $problem['input'], '输入格式', 'Input'],
        'output' => ['article', $problem['output'], '输出格式', 'Output'],
        'sample_input' => ['pre', $problem['sample_input'], 'Sample Input', 'Sample Input'],
        'sample_output' => ['pre', $problem['sample_output'], 'Sample Output', 'Sample Output'],
        'hint' => ['article', $problem['hint'], '提示', 'Hint'],
    ];
    if($controller == 'problemset') {
        if($problem['source'] == "<p>" . strip_tags($problem['source']) . "</p>") {
            $problem['source'] = strip_tags($problem['source']);
            $problem['source'] = "<a href='/csgoj/problemset#search=" . $problem['source'] . "'>" . $problem['source'] . "</a>";
        }
        if(isset($problem['author']) && $problem['author'] != null && strlen(trim($problem['author'])) > 0) {
            $items['author'] = ['article', $problem['author'], '出题', 'Author'];
        }
        $items['source'] = ['article', $problem['source'], '来源', 'Source'];
    }
    foreach($items as $key => $value)
    {
        $type = $value[0];
        $content = $value[1];
        $title_cn = $value[2];
        $title_en = $value[3];
        ?>
        {if $key=='sample_input'}
        <div name="Sample" class="md_display_div">
            <h2 class="text-info bilingual-inline">样例<span class="en-text">Sample</span></h2>
            <div class="sample_div">
                <textarea id="sample_input_hidden" style="display: none;">{$content|htmlspecialchars}</textarea>
        {elseif $key=='sample_output'}
                <textarea id="sample_output_hidden" style="display: none;">{$content|htmlspecialchars}</textarea>
            </div>
        </div>
        {else /}
        {if !empty($content)}
        <div name="{$key}" class="md_display_div">
            <h2 class="text-info bilingual-inline">{$title_cn}<span class="en-text">{$title_en}</span></h2>
            {$content}
        </div>
        {/if}
        {/if}
        <?php
    }
    ?>
</div>
{include file="../../csgoj/view/problemset/problem_footer" /}

{if $controller == 'problemset' }
<script type="text/javascript">
    $('.disabled_problem_submit_button').on('click', function(){
        alertify.error('Please login before submit!');
    });
</script>
{/if}


