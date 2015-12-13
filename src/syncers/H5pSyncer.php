<?php

require_once __DIR__."/../plugin/AResourceSyncer.php";

/**
 * Sync wordpress posts.
 */
class H5pSyncer extends AResourceSyncer {

	/**
	 * Construct.
	 */
	public function __construct() {
		parent::__construct("h5p");
	}

	/**
	 * List current local resources.
	 */
	public function listResourceIds() {
		global $wpdb;

		$q=$wpdb->prepare("SELECT id FROM wp_h5p_contents",NULL);
		$ids=$wpdb->get_col($q);

		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		return $ids;
	}

	/**
	 * Find H5P library by id.
	 */
	public function findH5pLibraryById($id) {
		global $wpdb;

		$q=$wpdb->prepare(
			"SELECT * ".
			"FROM   wp_h5p_libraries ".
			"WHERE  id=%s",
			$id);

		$res=$wpdb->get_row($q,ARRAY_A);

		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		return $res;
	}

	/**
	 * Find dependencies for content.
	 */
	public function findH5pContentDependencies($h5pId) {
		global $wpdb;

		$q=$wpdb->prepare(
			"SELECT    l.name, l.minor_version, l.major_version, l.patch_version, ".
			"          cl.dependency_type, cl.weight, cl.drop_css ".
			"FROM      wp_h5p_contents_libraries AS cl ".
			"LEFT JOIN wp_h5p_libraries AS l ".
			"ON        cl.library_id=l.id ".
			"WHERE     cl.content_id=%s",
			$h5pId);

		$res=$wpdb->get_results($q,ARRAY_A);

		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		return $res;
	}

	/**
	 * Find H5P library by name and version.
	 */
	public function findH5pLibraryByName($name, $major_version, $minor_version, $patch_version) {
		global $wpdb;

		$q=$wpdb->prepare(
			"SELECT * ".
			"FROM   wp_h5p_libraries ".
			"WHERE  name=%s ".
			"AND    major_version=%s ".
			"AND    minor_version=%s ".
			"AND    patch_version=%s ",
			$name,
			$major_version,
			$minor_version,
			$patch_version);

		$res=$wpdb->get_row($q,ARRAY_A);

		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		if (!$res)
			throw new Exception(
				"H5P Library $name ($major_version.".
				"$minor_version.$patch_version) ".
				"not found on this server.");

		return $res;
	}

	/**
	 * Get post by local id.
	 */
	public function getResource($localId) {
		global $wpdb;

		$q=$wpdb->prepare("SELECT * FROM wp_h5p_contents WHERE id=%s",$localId);
		$h5p=$wpdb->get_row($q,ARRAY_A);

		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		if (!$h5p)
			return NULL;

		$h5pLibrary=$this->findH5pLibraryById($h5p["library_id"]);
		if (!$h5pLibrary)
			throw new Exception("Strange, h5p library not found.");

		$dependencies=$this->findH5pContentDependencies($localId);

		return array(
			"title"=>$h5p["title"],
			"parameters"=>$h5p["parameters"],
			"filtered"=>$h5p["filtered"],
			"slug"=>$h5p["slug"],
			"embed_type"=>$h5p["embed_type"],
			"disable"=>$h5p["disable"],
			"content_type"=>$h5s["content_type"],
			"license"=>$h5p["license"],
			"keywords"=>$h5p["keywords"],
			"description"=>$h5p["description"],
			"library"=>array(
				"name"=>$h5pLibrary["name"],
				"major_version"=>$h5pLibrary["major_version"],
				"minor_version"=>$h5pLibrary["minor_version"],
				"patch_version"=>$h5pLibrary["patch_version"],
			),
			"libraries"=>$dependencies
		);
	}

	/**
	 * Update a local resource with data.
	 */
	function updateResource($localId, $data) {
		throw new Exception("updateResource not implemented");
		$post=get_post($localId);

		$post->post_name=$data["post_name"];
		$post->post_title=$data["post_title"];
		$post->post_type=$data["post_type"];
		$post->post_content=$data["post_content"];
		$post->post_excerpt=$data["post_excerpt"];
		$post->post_status=$data["post_status"];
		$post->post_parent=$this->globalToLocal($data["post_parent"]);
		$post->menu_order=$data["menu_order"];

		wp_update_post($post);
	}

	/**
	 * Ensure that dependency record exists.
	 */
	function ensureDependency($h5pId, $dependency) {
		global $wpdb;

		$library=$this->findH5pLibraryByName(
			$dependency["name"],
			$dependency["major_version"],
			$dependency["minor_version"],
			$dependency["patch_version"]
		);

		$libraryId=$library["id"];

		if (!$libraryId)
			throw new Exception("Library id is 0.");

		$q=$wpdb->prepare(
			"SELECT * ".
			"FROM   wp_h5p_contents_libraries ".
			"WHERE  content_id=%s ".
			"AND    library_id=%s");
		$row=$wpdb->get_row($q);

		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		/*print_r($dependency);
		echo "libid=$libraryId";*/

		if (!$row) {
			$q=$wpdb->prepare(
				"INSERT INTO  wp_h5p_contents_libraries ".
				"SET          content_id=%s, library_id=%s, ".
				"             dependency_type=%s, weight=%s, drop_css=%s ",
				$h5pId,$libraryId,
				$dependency["dependency_type"],$dependency["weight"],
				$dependency["drop_css"]
			);

			$wpdb->query($q);

			if ($wpdb->last_error)
				throw new Exception($wpdb->last_error);
		}
	}

	/**
	 * Create a local resource.
	 */
	function createResource($data) {
		global $wpdb;

		$library=$data["library"];
		$h5pLibrary=$this->findH5pLibraryByName(
			$library["name"],
			$library["major_version"],
			$library["minor_version"],
			$library["patch_version"]
		);

		if (!$h5pLibrary)
			throw new Exception(
				"H5P Library $library[name] ($library[major_version].".
				"$library[minor_version].$library[patch_version]) ".
				"not found on this server.");

		$q=$wpdb->prepare(
			"INSERT INTO  wp_h5p_contents ".
			"SET          title=%s, parameters=%s, filtered=%s, slug=%s, ".
			"             embed_type=%s, disable=%s, content_type=%s, license=%s, ".
			"             keywords=%s, description=%s, library_id=%s",
			$data["title"], $data["parameters"], $data["filtered"], $data["slug"],
			$data["embed_type"], $data["disable"], $data["content_type"], $data["license"],
			$data["keywords"], $data["description"], $h5pLibrary["id"]);

		$wpdb->query($q);

		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		$localId=$wpdb->insert_id;

		foreach ($data["libraries"] as $libraryData)
			$this->ensureDependency($localId,$libraryData);

		return $localId;
	}

	/**
	 * Delete a local resource.
	 */
	function deleteResource($localId) {
		throw new Exception("deleteResource not implemented");
		wp_trash_post($localId);
	}

	/**
	 * Merge resource data.
	 */
	function mergeResourceData($base, $local, $remote) {
		throw new Exception("mergeResourceData not implemented");
		/*print_r($base);
		print_r($local);
		print_r($remote);*/

		return array(
			"post_name"=>$this->mergeKeyValue("post_name",$base,$local,$remote),
			"post_title"=>$this->mergeKeyValue("post_title",$base,$local,$remote),
			"post_type"=>$this->pickKeyValue("post_type",$base,$local,$remote),
			"post_content"=>$this->mergeKeyValue("post_content",$base,$local,$remote),
			"post_excerpt"=>$this->mergeKeyValue("post_excerpt",$base,$local,$remote),
			"post_status"=>$this->pickKeyValue("post_status",$base,$local,$remote),
			"post_parent"=>$this->pickKeyValue("post_parent",$base,$local,$remote),
			"menu_order"=>$this->pickKeyValue("menu_order",$base,$local,$remote)
		);
	}

	/**
	 * Get sync label from data.
	 */
	function getResourceLabel($data) {
		return $data["title"];
	}
}