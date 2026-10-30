<?php
/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/treeselect.php';

class JFormFieldNRSEOGlossaries extends JFormFieldNRTreeSelect
{
	/**
	 * Get a list of all SEO Glossary glossaries
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		$db = $this->db;

		// Glossaries are a flat list, so every option sits at the root level.
		$query = $db->getQuery(true)
			->select('id as value, name as text, 0 as level, 0 as parent, IF (state = 1, 0, 1) as disable')
			->from($db->quoteName('#__seoglossaries'))
			->order($db->quoteName('name') . ' ASC');

		$db->setQuery($query);

		return $db->loadObjectList();
	}
}
