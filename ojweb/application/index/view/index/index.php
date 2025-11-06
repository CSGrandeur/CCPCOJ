{if $showCarousel}
    <div class="mb-4">
        <div id="indexCarousel" class="carousel slide" data-bs-ride="carousel">
            <!-- 轮播指标 -->
            <div class="carousel-indicators">
                {for start="0" end="3"}
                    <button type="button" data-bs-target="#indexCarousel" data-bs-slide-to="{$i}" {if($i == 0)} class="active" aria-current="true"{/if} aria-label="Slide {$i}"></button>
                {/for}
            </div>
            <!-- 轮播项目 -->
            <div class="carousel-inner">
                {for start="0" end="3"}
                    <div class="carousel-item {if($i == 0)} active{/if}">
                        <img src="{if(strlen($carousel['src'][$i]) > 0)} {$carousel['src'][$i]}{else/}__IMG__/carousel_default/carousel{$i}.png{/if}" class="d-block w-100" alt="{$carousel['header'][$i]}">
                        <div class="carousel-caption d-none d-md-block">
                            <h5>{$carousel['header'][$i]}</h5>
                            <p>{$carousel['content'][$i]}</p>
                        </div>
                    </div>
                {/for}
            </div>
            <!-- 轮播导航 -->
            <button class="carousel-control-prev" type="button" data-bs-target="#indexCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#indexCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    </div>
{/if}
    <br/>
    <div class="row g-4" id="news-categories-container">
        <!-- 分类文章将通过 JavaScript 动态加载到这里 -->
    </div>


{css href="__STATIC__/csgoj/news/news.css" /}