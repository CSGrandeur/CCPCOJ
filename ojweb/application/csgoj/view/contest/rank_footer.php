{if $action == 'ranklist' || $action == 'schoolrank' }
<input type="hidden" id="award_ratio"
gold="<?php echo isset($ratio_gold) ? $ratio_gold : 10;?>"
silver="<?php echo isset($ratio_silver) ? $ratio_silver : 15;?>"
bronze="<?php echo isset($ratio_bronze) ? $ratio_bronze : 20;?>"
/>

{js href="__STATIC__/csgoj/rank_common.js" /}
{js href="__STATIC__/csgoj/rank_only.js" /}
{/if}

{js href="__STATIC__/csgoj/rank_pub.js" /}


<style type="text/css">
    .fixed-table-toolbar {
        display: flex
    }
    .fixed-table-toolbar .columns {
        order: -1;
    }
    
    .frozen_mask {
        position:fixed;
        /* top:30%; */
        /* left:30%; */
        opacity:0.05;
        font-style: oblique;
        font-size: 320px;
		font-family: "Consolas", "Courier New", "Liberation Mono", Menlo, monospace;
        font-weight: bold;
        color: blue;
        text-align: center;
        z-index: 5;
        pointer-events: none;
        display: <?php if($rankFrozen){ ?>inline<?php }else{ ?>none<?php } ?>;
    }
    
/* #ranklist_table_div .bootstrap-table .fixed-table-container .fixed-table-body {
    overflow-x: unset; 
    overflow-y: unset; 
    height: auto; 
} */

.rank_fullscreen {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    width: 100%;
    height: 100%;
    z-index: 1024;
    background-color: white;
    display: flex;
    justify-content: center;
    overflow-x: auto; 
}


@media (max-width: 2500px) {
    .rank_fullscreen > div {
        min-width: 100%; 
    }
}

.d-inline-block {
  display: inline-block !important;
}
.text-truncate {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>

</div> <!-- id="rank_div" -->