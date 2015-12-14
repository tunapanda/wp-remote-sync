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
		sort($dependencies);

		return array(
			"title"=>$h5p["title"],
			"parameters"=>$h5p["parameters"],
			"filtered"=>$h5p["filtered"],
			"slug"=>$h5p["slug"],
			"embed_type"=>$h5p["embed_type"],
			"disable"=>$h5p["disable"],
			"content_type"=>$h5s["content_type"],
			"keywords"=>$h5p["keywords"]?$h5p["keywords"]:"",
			"description"=>$h5p["description"]?$h5p["description"]:"",
			"license"=>$h5p["license"]?$h5p["license"]:"",
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
	 * Update a local resource with data.
	 */
	function updateResource($localId, $data) {
		throw new Exception("updateResource not tested");

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
			"UPDATE  wp_h5p_contents ".
			"SET     title=%s, parameters=%s, filtered=%s, slug=%s, ".
			"        embed_type=%s, disable=%s, content_type=%s, license=%s, ".
			"        keywords=%s, description=%s, library_id=%s ".
			"WHERE   id=%s ",
			$data["title"], $data["parameters"], $data["filtered"], $data["slug"],
			$data["embed_type"], $data["disable"], $data["content_type"], $data["license"],
			$data["keywords"], $data["description"], $h5pLibrary["id"],
			$localId);

		$wpdb->query($q);
		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		foreach ($data["libraries"] as $libraryData)
			$this->ensureDependency($localId,$libraryData);
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
		throw new Exception("deleteResource not tested yet");

		$q=$wpdb->prepare(
			"DELETE FROM  wp_h5p_contents ".
			"WHERE        id=%s",
			$localId
		);

		$wpdb->query($q);
		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		$q=$wpdb->prepare(
			"DELETE FROM  wp_h5p_contents_libraries ".
			"WHERE        content_id=%s",
			$localId
		);

		$wpdb->query($q);
		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);
	}

/*
			"title"=>$h5p["title"],
			"parameters"=>$h5p["parameters"],
			"filtered"=>$h5p["filtered"],
			"slug"=>$h5p["slug"],
			"embed_type"=>$h5p["embed_type"],
			"disable"=>$h5p["disable"],
			"content_type"=>$h5s["content_type"],
			"keywords"=>$h5p["keywords"]?$h5p["keywords"]:"",
			"description"=>$h5p["description"]?$h5p["description"]:"",
			"description"=>$h5p["license"]?$h5p["license"]:"",
			"library"=>array(
				"name"=>$h5pLibrary["name"],
				"major_version"=>$h5pLibrary["major_version"],
				"minor_version"=>$h5pLibrary["minor_version"],
				"patch_version"=>$h5pLibrary["patch_version"],
			),
			"libraries"=>$dependencies
*/

	/**
	 * Merge resource data.
	 */
	function mergeResourceData($base, $local, $remote) {
		throw new Exception("mergeResourceData not implemented");
		return array(
			"title"=>$this->pickKeyValue("title",$base,$local,$remote),
			"parameters"=>$this->pickKeyValue("parameters",$base,$local,$remote),
			"filtered"=>$this->pickKeyValue("filtered",$base,$local,$remote),
			"slug"=>$this->pickKeyValue("slug",$base,$local,$remote),
			"embed_type"=>$this->pickKeyValue("embed_type",$base,$local,$remote),
			"disable"=>$this->pickKeyValue("disable",$base,$local,$remote),
			"content_type"=>$this->pickKeyValue("content_type",$base,$local,$remote),
			"keywords"=>$this->pickKeyValue("keywords",$base,$local,$remote),
			"description"=>$this->pickKeyValue("description",$base,$local,$remote),
			"license"=>$this->pickKeyValue("license",$base,$local,$remote),
			"library"=>$this->pickKeyValue("library",$base,$local,$remote),
			"libraries"=>$this->pickKeyValue("libraries",$base,$local,$remote)
		);
	}

	/**
	 * Get sync label from data.
	 */
	function getResourceLabel($data) {
		return $data["title"];
	}
}