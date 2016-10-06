<div class="wrap">
    <h2>Remote Sync</h2>
    <p>
        Performing sync.<br/><br/>
    </p>
</div>
<hr/>
<textarea id="job-output"></textarea>
<hr/>
<a href='<?php echo admin_url("options-general.php?page=rs_main"); ?>' 
	class='button' id='job-back-button'>Back</a>
<script>
<?php require __DIR__."/../wp-remote-sync.js"; ?>
</script>