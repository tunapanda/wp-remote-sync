<div class="wrap">
    <h2>Remote Sync</h2>
    <p>
        The following table shows the resources that differ between your local server and the remote 
        server.
    </p>

    <p>
		Next to each resource, you see a suggested action in order to bring the systems
        up to date.
    </p>

    <p>
        Please select the resources where you want this action applied, and then click
        <i>Start Sync</i> to start the sync of the two systems.
    </p>

    <form method="post"
    	action="<?php echo admin_url("options.php?page=rs_sync"); ?>">
    	<p>
	        <a class="button"
	            href="<?php echo admin_url("options-general.php?page=rs_main"); ?>">
	            Back
	        </a>
	        <input id="submit" type="submit" value="Start Sync" class="button button-primary"/>
	    </p>
		<table class="wp-list-table widefat fixed">
			<thead>
				<tr>
					<td class='check-column'>
						<input type="checkbox" checked="true"/>
					</td>
					<th><b>Resource</b></th>
					<th><b>State</b></th>
					<th><b>Action</b></th>
				</tr>
			</thead>

			<tbody>
				<?php foreach ($resources as $label=>$categoryResources) { ?>
					<tr class="no-items" style="background: #F9F9F9">
						<th scope='row' class='check-column'></th>
						<td class="colspanchange" colspan="3"><b><?php echo $label; ?></b></td>
					</tr>

					<?php foreach ($categoryResources as $resource) { ?>
						<tr>
							<th scope='row' class='check-column'>
								<input type='checkbox' checked="true"
									name="slugs[]"
									value="<?php echo $resource["uniqueSlug"]; ?>"
								/>
							</th>
							<td><?php echo $resource["slug"];?></td>
							<td><?php echo $resource["stateLabel"];?></td>
							<td style="position: relative">
								<?php if (isset($resource["conflict"]) && $resource["conflict"]) { ?>
									<select style="position: absolute; top: 2px">
										<option>Upload locate version to remote</option>
										<option>Download remote version to local</option>
									</select>
								<?php } else { ?>
									<?php echo $resource["actionLabel"];?>
								<?php } ?>
							</td>
						</tr>
					<?php } ?>
				<?php } ?>
			</tbody>
			<tfoot>
				<tr>
					<td class='check-column'>
						<input type="checkbox" checked="true"/>
					</td>
					<th><b>Resource</b></th>
					<th><b>State</b></th>
					<th><b>Action</b></th>
				</tr>
			</tfoot>
		</table>
	</form>
</div>
