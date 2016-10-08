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
                    <th scope="row">Remote site url</th>
                    <td>
                        <input type="text" name="rs_remote_site_url" 
                            value="<?php echo esc_attr(get_option("rs_remote_site_url")); ?>" 
                            class="regular-text"/>
                        <p class="description">This is the remote site to pull changes from and push changes to.</p>
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
                            Please enter the key so that you authenticate syncing.
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
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
                    <th>Incoming Access Key</th>
                    <td>
                        <input type="text"
                            name="rs_incoming_access_key" 
                            value="<?php echo esc_attr(get_option("rs_incoming_access_key"));?>"
                            class="regular-text"/>
                        <p class="description">
                            This is the key that other sites should use when connecting to this site.
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    <?php } ?>
</div>