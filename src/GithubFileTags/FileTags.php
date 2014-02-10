<?php

namespace GithubFileTags;

use Github;

/**
 *
 * Used to get file versions for all tags in a repository
 *
 * @package GithubFileTags
 *
 * @author Doug Reese <doug@reesesystems.com>
 *
 */
class FileTags
{
	protected $filePath;

	protected $owner;

	protected $repo;

	protected $authToken;

	protected $client;

	protected $tagFiles;

	private $tags;

	private $tagCommits;

	/**
	 *
	 * Contstructor
	 *
	 * @param array $params Aguments needed for retrieving data
	 * 
	 */
	public function __construct($params = array())
	{
		if (isset($params['filePath'])) {
			$this->filePath = $params['filePath'];
		}
		if (isset($params['owner'])) {
			$this->owner = $params['owner'];
		}
		if (isset($params['repo'])) {
			$this->repo = $params['repo'];
		}
		if (isset($params['authToken'])) {
			$this->authToken = $params['authToken'];
		}

		$this->client = new Github\Client;
	}

	/**
	 *
	 * Sets file path to retrieve
	 *
	 * @param string $filePath The file path to retrieve
	 *
	 */
	public function setFilePath($filePath)
	{
		$this->filePath = $filePath;
	}

	/**
	 *
	 * Gets file path to retrieve
	 *
	 * @return string The file path to retrieve
	 *
	 */
	public function getFilePath()
	{
		return $this->filePath;
	}

	/**
	 *
	 * Sets repository owner
	 *
	 * @param string $owner Repository owner
	 *
	 */
	public function setOwner($owner)
	{
		$this->owner = $owner;
	}

	/**
	 *
	 * Gets repository owner
	 *
	 * @return string Repository owner
	 *
	 */
	public function getOwner()
	{
		return $this->owner;
	}

	/**
	 *
	 * Sets repository
	 *
	 * @param string $repo Repository name
	 *
	 */
	public function setRepo($repo)
	{
		$this->repo = $repo;
	}

	/**
	 *
	 * Gets repository
	 *
	 * @return string Repository name
	 *
	 */
	public function getRepo()
	{
		return $this->repo;
	}

	/**
	 *
	 * Sets auth token
	 *
	 * @param string $authToken Auth token
	 *
	 */
	public function setAuthToken($authToken)
	{
		$this->authToken = $authToken;
	}

	/**
	 *
	 * Gets auth token
	 *
	 * @return string Auth token
	 *
	 */
	public function getAuthToken()
	{
		return $this->authToken;
	}

	/**
	 *
	 * Retrieve specified tag file data
	 *
	 * @param string $filePath File path (optional)
	 *
	 * @return array Associative array of <tag namne> => <file contents>
	 *
	 */
	public function getData($filePath = null)
	{
		if (!empty($filePath)) {
			$this->filePath = $filePath;
		}

		$this->client->authenticate($this->authToken, false, Github\Client::AUTH_HTTP_TOKEN);
		$this->getRepoTags();
		$this->getTagCommits();
		$this->getTagFileContents();

		return $this->tagFiles;
	}

	/**
	 *
	 * Used to get repository tags
	 *
	 */
	protected function getRepoTags()
	{
		// $releases = $this->client->api('repo')->releases()->all($this->owner, $this->repo);
		// var_dump(__METHOD__, $releases, $this->owner, $this->repo);

		$request = "repos/{$this->owner}/{$this->repo}/tags";
		$response   = $this->client->getHttpClient()->get($request);
		$tags = Github\HttpClient\Message\ResponseMediator::getContent($response);
		foreach ($tags as $tagData) {
			$this->tags[$tagData['name']] = $tagData;
		}
		// var_dump(__METHOD__, $this->tags);
	}

	/**
	 *
	 * Used to get tag commits
	 *
	 */
	protected function getTagCommits()
	{
		foreach ($this->tags as $tagName => $tagData) {
			$commit = $this->client->api('repo')->commits()->show($this->owner, $this->repo, $tagData['commit']['sha']);
			// var_dump(__METHOD__, $tagName, $commit);
			$this->tagCommits[$tagName] = $commit;
		}
	}

	/**
	 *
	 * Used to get tag file contents
	 *
	 */
	protected function getTagFileContents()
	{
		foreach ($this->tagCommits as $tagName => $tagCommit) {
			// var_dump(__METHOD__, $tagCommit);
			$sha = $tagCommit['commit']['tree']['sha'];
			$tree = $this->getTree($sha);
			$fileContents = $this->getTreeFile($this->filePath, $tree);
			// var_dump(__METHOD__, $tagName, $fileContents);
			$this->tagFiles[$tagName] = $fileContents;
		}
	}

	/**
	 *
	 * Used to get file from commit tree data
	 *
	 */
	protected function getTreeFile($fileName, $tree)
	{
		$fileContents = false;
		$pathParts = explode('/', $fileName);
		// var_dump(__METHOD__, $tree, $fileName, $pathParts);
		$target = array_shift($pathParts);
		foreach ($tree['tree'] as $nodeItemData) {
			if ($nodeItemData['path'] == $target) {
				$type = $nodeItemData['type'];
				// var_dump(__METHOD__, "found target item $target, $type", $nodeItemData['sha']);
				if ('tree' == $type) {
					$nextTree = $this->getTree($nodeItemData['sha']);
					$fileContents = $this->getTreeFile(implode('/', $pathParts), $nextTree);
				} else if ('blob' == $type) {
					$fileContents = $this->getFileContents($nodeItemData['sha']);
				}
				// found tree item, don't need to go any further
				break;
			}
		}

		return $fileContents;
	}

	/**
	 *
	 * Used to get tag file contents from a repository
	 *
	 */
	protected function getFileContents($sha)
	{
		$request = "repos/{$this->owner}/{$this->repo}/git/blobs/$sha";
		$response   = $this->client->getHttpClient()->get($request);
		$blob = Github\HttpClient\Message\ResponseMediator::getContent($response);
		$fileContents = base64_decode($blob['content']);
		// var_dump(__METHOD__, $sha, $blob, $fileContents);
	
		return $fileContents;
	}	

	/**
	 *
	 * Used to get a tree from a repository
	 *
	 */
	protected function getTree($sha)
	{
		$request = "repos/{$this->owner}/{$this->repo}/git/trees/$sha";
		$response   = $this->client->getHttpClient()->get($request);
		$tree = Github\HttpClient\Message\ResponseMediator::getContent($response);
		// var_dump(__METHOD__, $sha, $tree);

		return $tree;
	}
}