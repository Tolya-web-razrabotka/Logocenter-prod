<?php

/**
 * @package         Convert Forms
 * @version         5.2.6 Free
 * 
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use ConvertForms\FileStorage;
use ConvertForms\Form;
use Tassos\Framework\File;

/**
 * Conversion controller class
 */
class ConvertFormsControllerConversion extends FormController
{
	protected $text_prefix = 'COM_CONVERTFORMS_SUBMISSION';

	/**
	 * Serve a file attached to a submission.
	 *
	 * Files stored outside the site have no URL of their own, so the administrator
	 * reaches them through here. Authorisation is the component's own ACL rather than a
	 * token: the user is already authenticated, so there is no reason to mint a bearer
	 * credential that would then sit in the page and outlive the session.
	 *
	 * No path component comes from the request: the submission is looked up by id and
	 * the field name is only ever used as an array key.
	 *
	 * That alone is not enough, though. Submitted values are stored verbatim, so a
	 * visitor can put any string into an ordinary field, and without the two checks
	 * below this method would happily stream whatever that string resolved to, handing
	 * anyone with core.manage (which includes Manager, not just a Super User) the
	 * contents of any readable file in the site. So the named field must really be a
	 * File Upload field, and its value must really be an external reference.
	 *
	 * @return  void
	 */
	public function download()
	{
		// Cheap insurance against an administrator being lured into fetching a file
		// through a crafted link.
		if (!Session::checkToken('get'))
		{
			throw new NotAllowed(Text::_('JINVALID_TOKEN'), 403);
		}

		if (!Factory::getUser()->authorise('core.manage', 'com_convertforms'))
		{
			throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$id    = $this->input->getInt('id');
		$field = strtolower($this->input->getString('field', ''));
		$index = $this->input->getInt('index', 0);

		if (!$id || $field === '' || $index < 0)
		{
			$this->fail();
		}

		$db = Factory::getContainer()->get(DatabaseInterface::class);

		$query = $db->getQuery(true)
			->select($db->quoteName(['params', 'form_id']))
			->from($db->quoteName('#__convertforms_conversions'))
			->where($db->quoteName('id') . ' = :id')
			->bind(':id', $id, ParameterType::INTEGER);

		$submission = $db->setQuery($query)->loadObject();

		if (!$submission || !$submission->params)
		{
			$this->fail();
		}

		// The request names a field, so confirm against the form definition that it is
		// actually a File Upload field. Without this any field would do, and ordinary
		// fields hold whatever the visitor typed.
		if (!$this->isFileUploadField($submission->form_id, $field))
		{
			$this->fail();
		}

		$params = json_decode($submission->params, true);
		$values = isset($params[$field]) ? (array) $params[$field] : [];

		if (!array_key_exists($index, $values))
		{
			$this->fail();
		}

		$stored = $values[$index];

		// Only files stored outside the site are served from here. Files inside it keep
		// their own URL and are fetched straight from the web server. Refusing the in-site
		// case keeps the containment base off JPATH_ROOT entirely, so even a stored value
		// that somehow became attacker-influenced cannot reach beyond the upload folder.
		if (!FileStorage::isExternal($stored))
		{
			$this->fail();
		}

		$path = FileStorage::toAbsolutePath($stored);
		$base = FileStorage::getBaseFor($stored);

		if ($path === '' || $base === '' || !is_file($path))
		{
			$this->fail();
		}

		File::stream($path, $base, ['filename' => basename($path)]);
	}

	/**
	 * Whether a form has a File Upload field going by this name.
	 *
	 * Compared case-insensitively because submission params are stored with their keys
	 * lower-cased, while the form definition keeps whatever case the author typed.
	 *
	 * @param   int     $form_id  The form the submission belongs to
	 * @param   string  $name     The lower-cased field name from the request
	 *
	 * @return  bool
	 */
	private function isFileUploadField($form_id, $name)
	{
		return isset(Form::fileUploadKeys($form_id)[$name]);
	}

	/**
	 * Reject the request with a uniform error.
	 *
	 * @return  void
	 *
	 * @throws  \Exception
	 */
	private function fail()
	{
		throw new \Exception(Text::_('COM_CONVERTFORMS_DOWNLOAD_LINK_INVALID'), 404);
	}
}
