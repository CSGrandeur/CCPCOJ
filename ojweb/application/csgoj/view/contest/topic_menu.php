<li role="presentation" class="dropdown {if strpos($action, 'topic') === 0 } active {/if}">
    <a href="/{$module}/{$contest_controller}/topic_list?cid={$contest['contest_id']}" class="dropdown-toggle" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
    提问<br/>Clarification <span class="caret"></span>
    </a>
    <ul class="dropdown-menu">
        {if $running}
        <li>
            <a href="/{$module}/{$contest_controller}/topic_add?cid={$contest['contest_id']}">发送提问(Add Topic)</a>
        </li>
        {/if}
        <li>
            <a href="/{$module}/{$contest_controller}/topic_list?cid={$contest['contest_id']}">提问列表(Topic List)</a>
        </li>
    </ul>
</li>