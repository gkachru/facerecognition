<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018-2019 Branko Kokanovic <branko@kokanovic.org>
 * @copyright Copyright (c) 2018-2020 Matias De lellis <mti86dl@gmail.com>
 *
 * @author Branko Kokanovic <branko@kokanovic.org>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\FaceRecognition\Helper;

use OCP\Image;
use OCP\ILogger;
use OCP\ITempManager;

class TempImage extends Image {

	/** @var string */
	private $imagePath;

	/** @var string */
	private $tempPath;

	/** @var string */
	private $preferredMimeType;

	/** @var int */
	private $maxImageArea;

	/** @var ITempManager */
	private $tempManager;

	/** @var int */
	private $minImageSide;

	/** @var float */
	private $ratio = -1.0;

	/** @var bool */
	private $skipped = false;

	public function __construct(string $imagePath,
	                            string $preferredMimeType,
	                            int    $maxImageArea,
	                            int    $minImageSide)
	{
		parent::__construct();

		$this->imagePath         = $imagePath;
		$this->preferredMimeType = $preferredMimeType;
		$this->maxImageArea      = $maxImageArea;
		$this->minImageSide      = $minImageSide;

		$this->tempManager       = \OC::$server->getTempManager();

		$this->prepareImage();
	}

	/**
	 * Get the path of temporary image
	 *
	 * @return string
	 */
	public function getTempPath(): string {
		return $this->tempPath;
	}

	/**
	 * Get the path of orig image
	 *
	 * @return string
	 */
	public function getImagePath(): string {
		return $this->imagePath;
	}

	/**
	 * Obtain the ratio of the temporary image against the original
	 *
	 * @return float
	 */
	public function getRatio(): float {
		return $this->ratio;
	}

	/** Return if image was skipped
	 *
	 * @return bool
	 */
	public function getSkipped(): bool {
		return $this->skipped;
	}

	/**
	 * Clean temporary files
	 */
	public function clean() {
		$this->tempManager->clean();
	}

	/**
	 * Obtain a temporary image according to the imposed restrictions.
	 *
	 */
	private function prepareImage() {
		$this->loadFromFile($this->imagePath);
		$this->fixOrientation();

		if (!$this->valid()) {
			throw new \RuntimeException("Image is not valid, probably cannot be loaded");
		}

		if ((imagesx($this->resource()) < $this->minImageSide) ||
		    (imagesy($this->resource()) < $this->minImageSide)) {
			$this->skipped = true;
			return;
		}

		$this->ratio = $this->resizeImage();
		$this->tempPath = $this->tempManager->getTemporaryFile();

		$this->save($this->tempPath, $this->preferredMimeType);
	}

	/**
	 * Resizes the image to reach max image area, but preserving ratio.
	 *
	 * @return float Ratio of resize. 1 if there was no resize
	 */
	private function resizeImage(): float {
		$widthOrig = imagesx($this->resource());
		$heightOrig = imagesy($this->resource());

		if (($widthOrig <= 0) || ($heightOrig <= 0)) {
			$message = "Image is having non-positive width or height, cannot continue";
			throw new \RuntimeException($message);
		}

		$areaRatio = $this->maxImageArea / ($widthOrig * $heightOrig);
		$scaleFactor = sqrt($areaRatio);

		$newWidth = intval(round($widthOrig * $scaleFactor));
		$newHeight = intval(round($heightOrig * $scaleFactor));

		$success = $this->preciseResize($newWidth, $newHeight);
		if ($success === false) {
			throw new \RuntimeException("Error during image resize");
		}

		return 1 / $scaleFactor;
	}

}