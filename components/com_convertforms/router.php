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

defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterBase;

class ConvertFormsRouter extends RouterBase
{
	/**
	 * Route a download link under the menu item that publishes its own form.
	 *
	 * Download links are minted without an Itemid, from emails, exports and the
	 * administrator. Without one the link falls back to /component/convertforms/download,
	 * which stays the answer for a form nobody published through a menu item.
	 *
	 * form_id reaches this method only to name the form; it is dropped here so it never
	 * appears in the address. This is the one place that can drop it, since build() runs
	 * only when SEF URLs are on.
	 *
	 * @param   array  $query  An array of URL arguments
	 *
	 * @return  array  The modified URL arguments
	 */
	public function preprocess($query)
	{
		if (!isset($query['view']) || $query['view'] !== 'download')
		{
			return $query;
		}

		$form_id = isset($query['form_id']) ? (int) $query['form_id'] : 0;

		unset($query['form_id']);

		if (!$form_id || !empty($query['Itemid']))
		{
			return $query;
		}

		foreach ($this->menu->getItems('component', 'com_convertforms') ?: [] as $item)
		{
			if (!isset($item->query['view']) || $item->query['view'] !== 'form')
			{
				continue;
			}

			if ((int) $item->getParams()->get('form_id') === $form_id)
			{
				$query['Itemid'] = $item->id;
				break;
			}
		}

		return $query;
	}

	/**
	 * Build the route for the com_convertforms component
	 *
	 * @param   array &$query  An array of URL arguments
	 *
	 * @return  array  The URL arguments to use to assemble the subsequent URL.
	 *
	 * @since   2.8.1
	 */
	public function build(&$query)
	{
		$segments = [];

		if (!isset($query['view']))
		{
			return $segments;
		}

		$view = $query['view'];
		unset($query['view']);

		if ($view == 'download')
		{
			// The view has already left the query, so the segment is the only thing naming
			// it. A borrowed menu item prefixes its own route in front of it.
			$segments[] = $view;
		}

		if ($view == 'submission')
		{
			$segments[] = $view;

			if (isset($query['id']))
			{
				$segments[] = $query['id'];
				unset($query['id']);
			}

			if (isset($query['print']))
			{
				$segments[] = 'print';
				unset($query['print']);
			}

			if (isset($query['tmpl']))
			{
				unset($query['tmpl']);
			}
		}

		return $segments;
	}

	/**
	 * Parse the segments of a URL.
	 *
	 * @param   array &$segments  The segments of the URL to parse.
	 *
	 * @return  array  The URL attributes to be used by the application.
	 *
	 * @since   2.5.1
	 */
	public function parse(&$segments)
	{
		$vars = [];

		// View is always the first element of the array
		$view = $segments[0];

		if ($view == 'download')
		{
			$vars['view'] = $view;

			// Reset segments to make J4 Router happy.
			$segments = [];
		}

		if ($view == 'submission')
		{
			$vars['view'] = $view;
			$vars['id'] = $segments[1];

			if (isset($segments[2]) && $segments[2] == 'print')
			{
				$vars['tmpl'] = 'component';
				$vars['print'] = 1;
			}

			// Reset segments to make J4 Router happy.
			$segments = [];
		}

		return $vars;
	}
}

/**
 * Convert Forms router functions
 *
 * These functions are proxies for the new router interface
 * for old SEF extensions.
 *
 * @param   array &$query  An array of URL arguments
 *
 * @return  array  The URL arguments to use to assemble the subsequent URL.
 */

function ConvertFormsBuildRoute(&$query)
{
	$router = new ConvertFormsRouter();

	return $router->build($query);
}

function ConvertFormsParseRoute($segments)
{
	$router = new ConvertFormsRouter();

	return $router->parse($segments);
}