<h1>{$user_id}'s Mailbox</h1>
<ul class="nav nav-tabs">
    <li class="nav-item" role="presentation">
        <a class="nav-link {if $action == 'mail_inbox' } active {/if}" href="mail_inbox">Inbox</a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {if $action == 'mail_outbox' } active {/if}" href="mail_outbox">Outbox</a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {if $action == 'mail_add' } active {/if}" href="mail_add">Add Mail</a>
    </li>
</ul>