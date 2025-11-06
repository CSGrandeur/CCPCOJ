{if $running && !$isContestAdmin && (!isset($proctorAdmin) || !$proctorAdmin)}
    <!-- 有两个菜单项，使用下拉菜单 -->
    <li class="nav-item dropdown{if strpos($action, 'topic') === 0} active{/if}" title="提问">
        <a class="nav-link dropdown-toggle{if strpos($action, 'topic') === 0} active{/if}" href="#" id="topicDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-chat-quote me-1"></i> 
            问<span id="topic_num">0</span> 
            <span class="en-text">Clarification</span>
        </a>
        <ul class="dropdown-menu" aria-labelledby="topicDropdown">
            <li>
                <a class="dropdown-item{if $action=='topic_add'} active{/if}" href="/{$module}/{$contest_controller}/topic_add?cid={$contest['contest_id']}" title="发起提问">
                    <span class="cn-text"><i class="bi bi-plus-circle me-2"></i> 
                    发起提问</span><span class="en-text">Add Topic</span>
                </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item{if $action=='topic_list'} active{/if}" href="/{$module}/{$contest_controller}/topic_list?cid={$contest['contest_id']}" title="提问列表">
                    <span class="cn-text"><i class="bi bi-list-ul me-2"></i> 
                    提问列表</span><span class="en-text">Topic List</span>
                </a>
            </li>
        </ul>
    </li>
{else}
    <!-- 只有一个菜单项，使用独立菜单项 -->
    <li class="nav-item{if strpos($action, 'topic') === 0} active{/if}">
        <a class="nav-link{if $action=='topic_list'} active{/if}" href="/{$module}/{$contest_controller}/topic_list?cid={$contest['contest_id']}" title="提问列表">
            <i class="bi bi-chat-quote me-1"></i> 
            问<span id="topic_num">0</span> 
            <span class="en-text">Clarification</span>
        </a>
    </li>
{/if}