<?php

/**
 * @author          Tassos.gr <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\Security;

defined('_JEXEC') or die('Restricted access');

/**
 * Portable encryption using Framework secret.
 * Data encrypted with this class can be decrypted across different Joomla installations
 * that share the same TF_PORTABLE_SECRET constant.
 */
final class PortableEncryptor extends Encryptor
{
	/**
	 * Gets the encryption secret from the Framework TF_PORTABLE_SECRET constant.
	 *
	 * @return  string  Portable encryption secret
	 */
	protected function getSecret()
	{
		return TF_PORTABLE_SECRET;
	}
}
