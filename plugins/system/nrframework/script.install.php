<?php
/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

defined('_JEXEC') or die;

require_once __DIR__ . '/script.install.helper.php';

class PlgSystemNRFrameworkInstallerScript extends PlgSystemNRFrameworkInstallerScriptHelper
{
	public $name = 'TASSOS_FRAMEWORK';
	public $alias = 'nrframework';
	public $extension_type = 'plugin';

	public function onBeforeInstall()
	{
		if (!$this->isNewer())
		{
			return false;
		}
	}

	public function onAfterInstall()
	{
		$this->removeObsoleteFiles();
	}

	/**
	 * Delete files that were removed from the package in past releases but are
	 * left behind on the site because Joomla's updater never deletes them.
	 * Idempotent: deleteFiles() skips anything that is already gone.
	 */
	private function removeObsoleteFiles()
	{
		$installed = $this->installedVersion;                   // version before this update
		$plugin    = $this->getMainFolder();                    // .../plugins/system/nrframework
		$media     = JPATH_SITE . '/media/plg_system_nrframework';

		// Inline file upload field retired in the task=include security fix.
		// This cleanup first ships in 6.1.7; skip sites that already ran it.
		if ($installed !== '' && version_compare($installed, '6.1.7', '<'))
		{
			$this->deleteFiles([
				$plugin . '/fields/nrinlinefileupload.php',
				$plugin . '/layouts/inlinefileupload.php',
				$media  . '/js/inlinefileupload.js',
				$media  . '/css/inlinefileupload.css',
			]);
		}
	}
}
