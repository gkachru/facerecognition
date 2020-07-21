<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2017, 2018, 2020 Matias De lellis <mati86dl@gmail.com>
 *
 * @author Matias De lellis <mati86dl@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\FaceRecognition\Search;

use OCA\FaceRecognition\AppInfo\Application;

use OCA\FaceRecognition\Db\ImageMapper;

use OCA\FaceRecognition\Service\SettingsService;

use OC\FullTextSearch\Model\DocumentAccess;
use OCA\Files_FullTextSearch\Model\FilesDocument;
use OCA\Files_FullTextSearch\Provider\FilesProvider;
use OCA\Files_FullTextSearch\Service\SearchService;

/**
 * Provide search results from the 'facerecognition' app
 */
class Provider extends \OCP\Search\PagedProvider {

	/** @var ImageMapper Image mapper */
	private $imageMapper;

	/** @var SettingsService Settings service */
	private $settingsService;

	public function __construct() {
		$app = new Application();
		$container = $app->getContainer();

		$this->imageMapper     = $container->query(\OCA\FaceRecognition\Db\ImageMapper::class);
		$this->settingsService = $container->query(\OCA\FaceRecognition\Service\SettingsService::class);
	}

	/**
	 * @param string $query
	 * @param int|null $page
	 * @param int|null $size
	 * @return \OCP\Search\Result[]
	 */
	function searchPaged($query, $page, $size) {

		$userId = \OC::$server->getUserSession()->getUser()->getUID();
		$ownerView = new \OC\Files\View('/'. $userId . '/files');

		$model = $this->settingsService->getCurrentFaceModel();

		$searchresults = array();

		$results = $this->imageMapper->findFromPersonLike($userId, $model, $query, ($page - 1) * $size, $size);
		foreach($results as $result) {
			$fileId = $result->getFile();
			try {
				$path = $ownerView->getPath($fileId);
			} catch (\OCP\Files\NotFoundException $e) {
				continue;
			}
			$fileInfo = $ownerView->getFileInfo($path);
			$searchresults[] = new \OC\Search\Result\Image($fileInfo);
		}

		return $searchresults;

	}

	protected function withoutBeginSlash(string $path, bool $force = false, bool $clean = true) {
		if ($clean) {
			$path = str_replace('//', '/', $path);
		}

		if ($path === '/' && !$force) {
			return $path;
		}

		$path = ltrim($path, '/');

		return trim($path);
	}

	function handleFullTextSearch($searchResult)
	{
		$request = $searchResult->getRequest();
		$query = $request->getSearch();
		$page = $request->getPage();
		$size = $request->getSize();

		$userId = \OC::$server->getUserSession()->getUser()->getUID();
		$ownerView = new \OC\Files\View('/'. $userId . '/files');

		$model = $this->settingsService->getCurrentFaceModel();

		$searchresults = array();

		$results = $this->imageMapper->findFromPersonLike($userId, $model, $query, ($page - 1) * $size, $size);

		foreach($results as $result) {
			$fileId = $result->getFile();
			try {
				$path = $ownerView->getPath($fileId);
			} catch (\OCP\Files\NotFoundException $e) {
				continue;
			}
			$fileInfo = $ownerView->getFileInfo($path);

			// From the fulltextsearch_files app lib/Service/FilesService.php
			$document = new FilesDocument(FilesProvider::FILES_PROVIDER_ID, (string)$fileId);

			$filename = $fileInfo->getName();
			$dir = substr($path, 0, -strlen($filename));

			// Document info that may be used by the search results view
			$document->setInfo('type', $file->getType());
					 ->setInfo('dir', $dir)
                     ->setInfo('file', $filename)
                     ->setInfo('path', $path)
                     ->setInfo('mime', $fileInfo->getMimetype());

			try {
				$document->setInfoInt('size', $fileInfo->getSize())
						 ->setInfoInt('mtime', $fileInfo->getMTime())
						 ->setInfo('etag', $fileInfo->getEtag())
						 ->setInfoInt('permissions', $fileInfo->getPermissions());
			} catch (Exception $e) {
			}

			// This is the title shown in the search results
			$document->setTitle($this->withoutBeginSlash($path));

			// The link to the document
			$document->setLink(
				\OC::$server->getURLGenerator()
					   ->linkToRoute(
						   'files.view.index',
						   [
							   'dir'      => $dir,
							   'scrollto' => $filename,
						   ]
					   )
			);

			// Add this document to the search results
			$searchResult->addDocument($document);
			// Update the search count
			$searchResult->setTotal($searchResult->getTotal() + 1);
		}
	}
}
