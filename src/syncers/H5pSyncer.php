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
	 * Is the underlying resource available?
	 */
	public function isAvailable() {
		return is_plugin_active("h5p/h5p.php");
	}

	/**
	 * Get id by slug.
	 */
	private function getIdBySlug($slug) {
		global $wpdb;

		$q=$wpdb->prepare("SELECT id FROM {$wpdb->prefix}h5p_contents WHERE slug=%s",$slug);
		$id=$wpdb->get_var($q);

		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		return $id;
	}

	/**
	 * List current local resources.
	 */
	public function listResourceSlugs() {
		global $wpdb;

		$slugs=$wpdb->get_col("SELECT slug FROM {$wpdb->prefix}h5p_contents");

		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		$res=array();
		foreach ($slugs as $slug)
			if ($slug)
				$res[]=$slug;

		return $res;
	}

	/**
	 * scandir recursive
	 */
	private function scandirRecursive($basedir, $subdir="") {
		$res=array();
		$files=scandir($basedir."/".$subdir);

		foreach ($files as $file) {
			if ($file[0]!=".") {
				if (is_dir($basedir."/".$subdir."/".$file)) {
					if ($subdir)
						$res=array_merge($res,$this->scandirRecursive($basedir,"$subdir/$file"));

					else
						$res=array_merge($res,$this->scandirRecursive($basedir,$file));
				}

				else {
					if ($subdir)
						$res[]="$subdir/$file";

					else
						$res[]=$file;
				}
			}
		}

		return $res;
	}

	/**
	 * Get resource attachments.
	 */
	public function getResourceAttachments($slug) {
		$localId=$this->getIdBySlug($slug);
		if (!$localId)
			throw new Exception("getResourceAttachments: H5P doesn't exist: ".$slug);

		$uploadBasedir=wp_upload_dir()["basedir"];
		$attachmentDir=$uploadBasedir."/h5p/content/$localId/";

		if (!file_exists($attachmentDir))
			return array();

		return $this->scandirRecursive($attachmentDir);
	}

	/**
	 * Find H5P library by id.
	 */
	private function findH5pLibraryById($id) {
		global $wpdb;

		$q=$wpdb->prepare(
			"SELECT * ".
			"FROM   {$wpdb->prefix}h5p_libraries ".
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
	private function findH5pContentDependencies($h5pId) {
		global $wpdb;

		$q=$wpdb->prepare(
			"SELECT    l.name, l.minor_version, l.major_version, l.patch_version, ".
			"          cl.dependency_type, cl.weight, cl.drop_css ".
			"FROM      {$wpdb->prefix}h5p_contents_libraries AS cl ".
			"LEFT JOIN {$wpdb->prefix}h5p_libraries AS l ".
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
	private function findH5pLibraryByName($name, $major_version, $minor_version, $patch_version) {
		global $wpdb;

		$q=$wpdb->prepare(
			"SELECT * ".
			"FROM   {$wpdb->prefix}h5p_libraries ".
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
	public function getResource($slug) {
		$localId=$this->getIdBySlug($slug);
		global $wpdb;

		$q=$wpdb->prepare("SELECT * FROM {$wpdb->prefix}h5p_contents WHERE id=%s",$localId);
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
			"content_type"=>$h5p["content_type"]?$h5p["content_type"]:"",
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
	private function ensureDependency($h5pId, $dependency) {
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
			"FROM   {$wpdb->prefix}h5p_contents_libraries ".
			"WHERE  content_id=%s ".
			"AND    library_id=%s ".
			"AND    dependency_type=%s",
			$h5pId,$libraryId,$dependency["dependency_type"]);
		$row=$wpdb->get_row($q);

		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		/*print_r($dependency);
		echo "libid=$libraryId";*/

		if (!$row) {
			$q=$wpdb->prepare(
				"INSERT INTO  {$wpdb->prefix}h5p_contents_libraries ".
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

		else {
			$q=$wpdb->prepare(
				"UPDATE  {$wpdb->prefix}h5p_contents_libraries ".
				"SET     weight=%s, drop_css=%s ".
				"WHERE   content_id=%s ".
				"AND     library_id=%s ".
				"AND     dependency_type=%s ",
				$dependency["weight"],$dependency["drop_css"],
				$h5pId,$libraryId,$dependency["dependency_type"]
			);

			$wpdb->query($q);

			if ($wpdb->last_error)
				throw new Exception($wpdb->last_error);
		}
	}

	/**
	 * Update a local resource with data.
	 */
	public function updateResource($slug, $data) {
		$localId=$this->getIdBySlug($slug);
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
			"UPDATE  {$wpdb->prefix}h5p_contents ".
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

		$saved=$this->getResource($localId);
		if ($saved!==$data) {

			throw new Exception(
				"**** data:\n".
				json_encode($data)."\n".
				"**** saved:\n".
				json_encode($saved)."\n".
				"update: the data in the db is not what we saved!"
			);
		}
	}

	/**
	 * Create a local resource.
	 */
	function createResource($slug, $data) {
		global $wpdb;

		if (/*!$slug || */$data["slug"]!=$slug)
			throw new Exception("sanity test failed, slug!=slug");

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
			"INSERT INTO  {$wpdb->prefix}h5p_contents ".
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

		try {
			foreach ($data["libraries"] as $libraryData) {
				$this->ensureDependency($localId,$libraryData);
			}
		}

		catch (Exception $e) {
			$this->deleteResource($slug);
			throw $e;
		}

		$saved=$this->getResource($slug);
		if ($saved!==$data) {
			echo "in local db=".sizeof($saved["libraries"])." incoming=".sizeof($data["libraries"])."<br>";

			$this->deleteResource($localId);
			throw new Exception("create: the data in the db is not what we saved!");
		}

		return $localId;
	}

	/**
	 * Delete a local resource.
	 */
	function deleteResource($slug) {
		$slug=$this->getIdBySlug($slug);
		global $wpdb;

		$q=$wpdb->prepare(
			"DELETE FROM  {$wpdb->prefix}h5p_contents ".
			"WHERE        id=%s",
			$localId
		);

		$wpdb->query($q);
		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);

		$q=$wpdb->prepare(
			"DELETE FROM  {$wpdb->prefix}h5p_contents_libraries ".
			"WHERE        content_id=%s",
			$localId
		);

		$wpdb->query($q);
		if ($wpdb->last_error)
			throw new Exception($wpdb->last_error);
	}

	/**
	 * Get directory for storing attachments.
	 */
	public function getAttachmentDirectory($slug) {
		$localId=$this->getIdBySlug($slug);

		if (!$localId)
			throw new Exception("H5P not found for attachments dir: $slug");

		return wp_upload_dir()["basedir"]."/h5p/content/".$localId."/";
	}
}