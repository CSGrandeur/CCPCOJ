{include file="../../csgoj/view/contest/msg_show" /}

<script>
    $(document).ready(function() {
        fetchMessages(false);
        const cid = <?php echo $contest['contest_id']; ?>;
        let msg_list_local = csg.store(`contest_msg#cid${cid}`);
        renderMessages(msg_list_local.msg_list);
    });
</script>