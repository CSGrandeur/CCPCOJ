<hr />
<footer class="text-muted">
    <p style="float:right !important;">
        <a href="#">Back to top</a>
    </p>
    <p >Copyright © 2016-<?php echo date('Y'); ?> CSGrandeur. All Rights Reserved. <a href=https://beian.miit.gov.cn/ target="_blank">{$ICP_RECORD}</a><br/>
        Designer & Developer : <a href="http://blog.csgrandeur.cn" target="_blank">CSGrandeur</a>. <a href="https://github.com/CSGrandeur/CCPCOJ" target="_blank">CCPCOJ-一站式XCPC比赛系统 </a>.<br/>

    </p>
    {if isset($GA_CODE) && $GA_CODE != false}
    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={$GA_CODE}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', "{$GA_CODE}");
    </script>
    {/if}
</footer>
