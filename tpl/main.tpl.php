<script>
    var e=document.getElementById("rs-resourcelist-loading");
    if (e)
        e.style.display="none";
</script>
<div class="wrap">
    <h2>Remote Sync</h2>

    <?php if (isset($message)) { ?>
        <div id="setting-error-settings_updated" class="updated settings-error">
            <p><strong><?php echo $message; ?></strong></p>
        </div>
    <?php } ?>

    <?php if (isset($error)) { ?>
        <div id="setting-error-settings_updated" class="error settings-error">
            <p><strong><?php echo $error; ?></strong></p>
        </div>
    <?php } ?>

    <p>
        This plugin lets you sync content with another WordPress site, like a 
        distributed version control system.
    </p>

    <h2 class="nav-tab-wrapper">
		<a class="nav-tab <?php echo ($tab=="sync"?"nav-tab-active":"nav-tab"); ?>" 
            href="<?php echo admin_url("options-general.php?page=rs_main&tab=sync"); ?>">
            Sync
        </a>
		<a class="nav-tab <?php echo ($tab=="connection"?"nav-tab-active":"nav-tab"); ?>"
            href="<?php echo admin_url("options-general.php?page=rs_main&tab=connection"); ?>">
            Connection
        </a>
        <a class="nav-tab <?php echo (($tab=="scheduled"||$tab=="scheduled_log")?"nav-tab-active":"nav-tab"); ?>"
            href="<?php echo admin_url("options-general.php?page=rs_main&tab=scheduled"); ?>">
            Scheduled Sync
        </a>
        <a class="nav-tab <?php echo ($tab=="remote"?"nav-tab-active":"nav-tab"); ?>"
            href="<?php echo admin_url("options-general.php?page=rs_main&tab=remote"); ?>">
            Act as Remote
        </a>
    </h2>

    <?php if ($tab=="sync") { ?>
        <p>
            Click here to check the differences between this WordPress site and the remote site.
        </p>
        <a class="button button-primary"
            href="<?php echo admin_url("options.php?page=rs_sync_preview"); ?>">
            Check Differences and Start Sync
        </a>
    <?php } else if ($tab=="connection") { ?>
        <p>
            These settings are used when connecting to a remote WordPress
            site to upload and download resources.
        </p>
        <form method="post"
            action="<?php echo admin_url("options-general.php?page=rs_main&tab=connection"); ?>">
            <?php settings_fields( 'rs' ); ?>
            <?php do_settings_sections( 'rs' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Remote Site Url</th>
                    <td>
                        <input type="text" name="rs_remote_site_url" 
                            value="<?php echo esc_attr(get_option("rs_remote_site_url")); ?>" 
                            class="regular-text"/>
                        <p class="description">This is the remote site to sync with.</p>
                    </td>
                </tr>
                <tr>
                    <th>Access Key</th>
                    <td>
                        <input type="text" 
                            name="rs_access_key" 
                            value="<?php echo esc_attr(get_option("rs_access_key"));?>"
                            class="regular-text"/>
                        <p class="description">
                            This access key will be used when connecting to the remote site.
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    <?php } else if ($tab=="scheduled") { ?>
        <p>
            You can set up this WordPress site to automatically sync its content with the remote.
        </p>
        <form method="post"
            action="<?php echo admin_url("options-general.php?page=rs_main&tab=scheduled"); ?>">
            <?php settings_fields( 'rs' ); ?>
            <?php do_settings_sections( 'rs' ); ?>
            <table class="form-table">
                <tr>
                    <th>Sheduled Syncs</th>
                    <td>
                        <select name="schedule">
                            <?php $schedule=wp_get_schedule("rs_scheduled_sync"); ?>
                            <option value="">Disabled</option>
                            <option value="hourly"
                                <?php if ($schedule=="hourly") echo "selected"; ?>
                            >Hourly</option>
                            <option value="twicedaily"
                                <?php if ($schedule=="twicedaily") echo "selected"; ?>
                            >Twice daily</option>
                            <option value="daily"
                                <?php if ($schedule=="daily") echo "selected"; ?>
                            >Daily</option>
                        </select>
                        <p class="description">
                            How often do you want scheduled syncs to occur?
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>Conflicts</th>
                    <td>
                        <select name="rs_resulotion_strategy">
                            <?php $r=get_option("rs_resulotion_strategy"); ?>
                            <option value="none"
                                <?php if ($r=="none") echo "selected"; ?>
                            >Leave for manual resolution</option>
                            <option value="useRemote"
                                <?php if ($r=="useRemote") echo "selected"; ?>
                            >Download remote content, overwrite local changes</option>
                            <option value="useLocal"
                                <?php if ($r=="useLocal") echo "selected"; ?>
                            >Upload local content, overwrite remote changes</option>
                        </select>
                        <p class="description">
                            What if content is changed on both servers when syncing?
                        </p>
                    </td>
                </tr>

                <?php if (wp_get_schedule("rs_scheduled_sync")) { ?>
                    <tr>
                        <th>Current Schedule</th>
                        <td>
                            Next sync starts in: <?php echo $nextScheduled; ?>
                            <?php if ($prevScheduled) { ?>
                                <br/>
                                Previous sync started: <?php echo $prevScheduled; ?> ago
                                (<a href="?page=rs_main&tab=scheduled_log">view log</a>) 
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </table>
            <?php submit_button(); ?>
        </form>
    <?php } else if ($tab=="scheduled_log") { ?>
        <p>
            This is the log for the last sync job, which ran at <?php echo $lastScheduledTime; ?>.
        </p>
        <pre><?php echo $scheduledLogContents; ?></pre>
    <?php } else if ($tab=="remote") { ?>
        <p>
            These settings are used when this WordPress site acts as a remote for
            other sites to connect to.
        </p>
        <form method="post"
            action="<?php echo admin_url("options-general.php?page=rs_main&tab=remote"); ?>">
            <?php settings_fields( 'rs' ); ?>
            <?php do_settings_sections( 'rs' ); ?>
            <table class="form-table">
                <tr>
                    <th>Access key for downloading</th>
                    <td>
                        <input type="text"
                            name="rs_download_access_key" 
                            value="<?php echo esc_attr(get_option("rs_download_access_key"));?>"
                            class="regular-text"/>
                        <p class="description">
                            Clients using this key will be able to download content, 
                            but not modify content on this server.<br>
                            If this field is left blank, clients can connect and download content without a key.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>Access key for uploading</th>
                    <td>
                        <input type="text"
                            name="rs_upload_access_key" 
                            value="<?php echo esc_attr(get_option("rs_upload_access_key"));?>"
                            class="regular-text"/>
                        <p class="description">
                            Clients using this key will be able to download content and modify content on
                            this server.<br>
                            If this field is left blank, uploading will be disabled.
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    <?php } ?>
</div>