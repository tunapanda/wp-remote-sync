<div class="wrap">
    <h2>Remote Sync</h2>
    <p>
        This plugin lets you sync content with a remote site, like a 
        distributed version control system.<br/><br/>

        <?php echo plugins_url(); ?>
    </p>

    <h3>Settings</h3>
    <form method="post" action="options.php">
        <?php settings_fields( 'rs' ); ?>
        <?php do_settings_sections( 'rs' ); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Remote Site Url</th>
                <td>
                    <input type="text" name="rs_remote_site_url" 
                        value="<?php echo esc_attr(get_option("rs_remote_site_url")); ?>" 
                        class="regular-text"/>
                    <p class="description">This is the remote site to pull changes from and push changes to.</p>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>

    <h3>Operations</h3>
    <p>
        Once you have set up the information above, you can run these operations to synchronize
        the sites.<br/><br/> 
        Note that these operations should be run on the "local" site, i.e. the site pulling 
        and pushing information. On the site acting as the "remote", you don't need to do anything
        other than install this plugin.
    </p>
    <form method="get" action="options.php">
        <input type="hidden" name="page" value="rs_operations"/>
        <p class="submit">
            <input type="submit" name="action" id="status_button" class="button" value="Status">
            Show information about the current differences between this site and the remote site.<br/><br/>
            <input type="submit" name="action" id="pull_button" class="button" value="Pull">
            Pull remote changes and apply them to this site.<br/><br/>
            <input type="submit" name="action" id="push_button" class="button" value="Push">
            Push local changes up to the remote site.<br/><br/>
            <input type="submit" name="action" id="sync_button" class="button" value="Sync">
            Pull remote changes, then push local changes.<br/><br/>
        </p>
    </form>
</div>