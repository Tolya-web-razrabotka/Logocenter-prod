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

namespace ConvertForms\Submission;

defined('_JEXEC') or die('Restricted access');

use ConvertForms\Submission\Detail\Base;

/**
 * Domain object for a single submission row.
 *
 * Use getProp() to access any column through the Detail class hierarchy —
 * columns with no dedicated subclass fall back to Base automatically.
 * Use getData() when you need the raw DB row directly (e.g. to read multiple
 * scalar fields at once without allocating Detail objects).
 *
 * Examples:
 *   $submission->getProp('device')->getValue()          // "Desktop"
 *   $submission->getProp('country_code')->getValueHTML() // 🇬🇷 Greece
 *   $submission->getProp('id')->getValueRaw()           // "42"
 *   $submission->getData()->id                          // 42 (int)
 *   $submission->getData()->created                     // "2026-06-05 10:00:00"
 */
class Submission
{
	/**
	 * Consumers of the Detail values, one per caller. Passed to the constructor and
	 * threaded down to every Detail so a Detail can tailor its output to who is asking
	 * (e.g. ISO8601 dates for the JSON API).
	 */
	const CONSUMER_DEFAULT  = '';         // no specific consumer (e.g. label-only lookups)
	const CONSUMER_LIST     = 'list';     // submissions list table
	const CONSUMER_EDIT     = 'edit';     // single submission info card
	const CONSUMER_EXPORT   = 'export';   // CSV/JSON file export
	const CONSUMER_API      = 'api';      // REST JSON API endpoint
	const CONSUMER_SMARTTAG = 'smarttag'; // {submission.*} Smart Tags

	/**
	 * The submission database row.
	 *
	 * @var  object
	 */
	private object $item;

	/**
	 * The consumer of the Detail values (one of the CONSUMER_* constants).
	 *
	 * @var  string
	 */
	private string $consumer;

	/**
	 * Memoized Detail instances keyed by column name.
	 *
	 * @var  Base[]
	 */
	private array $props = [];

	/**
	 * @param   object  $item  The submission row as returned by ConvertFormsModelConversion::getItem().
	 *                         Contains all DB columns (id, form_id, ip, device, os, browser,
	 *                         user_agent, page_title, source_url, referrer_url, country_code,
	 *                         created, modified, state, visitor_id, user_id, campaign_id, params)
	 *                         plus the properties added by prepareItem() / prepare():
	 *                         form, campaign, user_name, prepared_fields, fields_.
	 * @param   string  $consumer  One of the CONSUMER_* constants identifying the caller,
	 *                            so Detail classes can tailor their output (e.g. ISO8601
	 *                            dates for the JSON API). Defaults to no specific consumer.
	 */
	public function __construct(object $item, string $consumer = self::CONSUMER_DEFAULT)
	{
		$this->item     = $item;
		$this->consumer = $consumer;
	}

	/**
	 * Transparent property read-through to the underlying DB row.
	 * Allows code that still accesses $submission->user_id, ->fields_, ->form, etc.
	 * to work unchanged after $submission is wrapped in this class.
	 *
	 * @param   string  $name
	 *
	 * @return  mixed
	 */
	public function __get(string $name)
	{
		return $this->item->{$name} ?? null;
	}

	/**
	 * Transparent isset() support for the underlying DB row properties.
	 *
	 * @param   string  $name
	 *
	 * @return  bool
	 */
	public function __isset(string $name): bool
	{
		return isset($this->item->{$name});
	}

	/**
	 * Returns a Detail instance for the given column name.
	 *
	 * Columns with a dedicated subclass (e.g. 'device' → DeviceDetail) return
	 * that subclass. Columns without one (e.g. 'id', 'visitor_id') return a
	 * plain Base instance — all three value methods still work normally.
	 *
	 * @param   string  $column  DB column name (e.g. 'os', 'source_url', 'id').
	 *
	 * @return  Base
	 */
	public function getProp(string $column): Base
	{
		return $this->props[$column] ??= Base::make($column, $this->item, $this->consumer);
	}

	/**
	 * Returns the raw submission database row.
	 *
	 * Use this when you need multiple scalar properties at once without going
	 * through Detail objects (e.g. building a webhook payload array).
	 *
	 * @return  object
	 */
	public function getData(): object
	{
		return $this->item;
	}

	/**
	 * @deprecated  Use getProp() instead.
	 */
	public function detail(string $column): Base
	{
		return $this->getProp($column);
	}
}
