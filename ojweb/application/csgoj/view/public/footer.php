<hr class="my-4">
<footer class="text-muted py-4 mt-5">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center">
                    <div class="me-md-3 mb-1 mb-md-0">
                        <div class="d-flex flex-column" style="line-height: 1.2;">
                            <small class="text-muted">
                                © 2016-<?php echo date('Y'); ?> CSGrandeur. 版权所有.
                            </small>
                            <small class="text-muted" style="font-size: 0.7em; margin-top: -2px;">
                                Copyright © 2016-<?php echo date('Y'); ?> CSGrandeur. All Rights Reserved.
                            </small>
                        </div>
                    </div>
                    <div class="me-md-3 mb-1 mb-md-0">
                        <a href="https://beian.miit.gov.cn/" target="_blank" class="text-decoration-none text-muted">
                            <small>{$ICP_RECORD}</small>
                        </a>
                    </div>
                </div>
                <div class="mt-1">
                    <div class="d-flex flex-column" style="line-height: 1.2;">
                        <small class="text-muted">
                            设计开发: 
                            <a href="http://blog.csgrandeur.cn" target="_blank" class="text-decoration-none text-primary">
                                CSGrandeur
                            </a>
                            <span class="mx-1">•</span>
                            <a href="https://github.com/CSGrandeur/CCPCOJ" target="_blank" class="text-decoration-none text-primary">
                                CCPCOJ-一站式XCPC比赛系统
                            </a>
                        </small>
                        <small class="text-muted" style="font-size: 0.7em; margin-top: -2px;">
                            Designer & Developer: 
                            <a href="http://blog.csgrandeur.cn" target="_blank" class="text-decoration-none text-primary">
                                CSGrandeur
                            </a>
                            <span class="mx-1">•</span>
                            <a href="https://github.com/CSGrandeur/CCPCOJ" target="_blank" class="text-decoration-none text-primary">
                                CCPCOJ - All-in-One XCPC Contest System
                            </a>
                        </small>
                    </div>
                </div>
            </div>
            <div class="col-md-4 text-md-end">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" title="返回顶部 (Back to top)">
                    <i class="bi bi-arrow-up"></i>
                </button>
            </div>
        </div>
    </div>
    
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
    
    {if isset($BA_CODE) && $BA_CODE != false}
    <script>
        var _hmt = _hmt || [];
        (function() {
        var hm = document.createElement("script");
        hm.src = "https://hm.baidu.com/hm.js?{$BA_CODE}";
        var s = document.getElementsByTagName("script")[0]; 
        s.parentNode.insertBefore(hm, s);
        })();
    </script>
    {/if}
</footer>
