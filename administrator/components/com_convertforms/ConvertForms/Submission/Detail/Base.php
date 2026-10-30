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

namespace ConvertForms\Submission\Detail;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;

/**
 * Base detail class for submission metadata columns.
 *
 * Provides label resolution, raw value access, and plain/HTML value rendering.
 * Subclasses override getValueHTML() for columns that need rich output.
 */
class Base
{
	/**
	 * The database column name.
	 *
	 * @var  string
	 */
	protected string $column;

	/**
	 * The submission DB row.
	 *
	 * @var  object|null
	 */
	protected ?object $item;

	/**
	 * The consumer asking for the value (one of Submission::CONSUMER_* constants),
	 * letting a Detail tailor its output to that caller.
	 *
	 * @var  string
	 */
	protected string $consumer = '';

	/**
	 * When true, the plain value is passed through ucfirst().
	 *
	 * @var  bool
	 */
	protected bool $ucfirst = false;

	/**
	 * Sort weight for the submissions-list column order. Lower = earlier.
	 * Defaults to the end so a new Detail class appears last until it sets a weight.
	 *
	 * @var  int
	 */
	protected static int $listOrder = 1000;

	/**
	 * Group key used to organise this detail into a section. Defaults to 'other'
	 * so a new Detail class still surfaces (in the Other group) until it sets one.
	 *
	 * @var  string
	 */
	protected static string $group = 'other';

	/**
	 * Icon class shown next to this detail in the submission info card (e.g.
	 * 'icon-key'). Empty by default so a detail renders without an icon until
	 * its class sets one.
	 *
	 * @var  string
	 */
	protected static string $icon = '';

	/**
	 * Whether this column is a Pro-only feature. Visitor tracking (IP, geolocation,
	 * device, source/campaign) is available only in the Pro edition; in the Free
	 * edition these columns are not collected and surface as locked upgrade prompts.
	 * Defaults to false so a detail is free until its class opts in.
	 *
	 * @var  bool
	 */
	protected static bool $proOnly = false;

	public function __construct(string $column, ?object $item, string $consumer = '')
	{
		$this->column   = $column;
		$this->item     = $item;
		$this->consumer = $consumer;
	}

	/**
	 * Returns the column's sort weight (see $listOrder).
	 *
	 * @return  int
	 */
	public static function getListOrder(): int
	{
		return static::$listOrder;
	}

	/**
	 * Returns the group key this detail belongs to (see $group).
	 *
	 * @return  string
	 */
	public static function getGroup(): string
	{
		return static::$group;
	}

	/**
	 * Returns the icon class for this detail (see $icon).
	 *
	 * @return  string
	 */
	public static function getIcon(): string
	{
		return static::$icon;
	}

	/**
	 * Returns whether this column is a Pro-only feature (see $proOnly).
	 *
	 * @return  bool
	 */
	public static function isProOnly(): bool
	{
		return static::$proOnly;
	}

	/**
	 * Discovers selectable submissions-list columns from the concrete *Detail.php
	 * files in this directory, ordered by each class's $listOrder (ties: alphabetical).
	 *
	 * This is the single source of truth for the submissions-list columns and, via
	 * getProp()/fetchValue(), for the {submission.NAME} Smart Tags. Base.php is
	 * skipped (no *Detail suffix); abstract Detail classes (shared parents such as
	 * UrlDetail) declare themselves out — there is no hardcoded blocklist.
	 *
	 * @return  string[]  Column keys (e.g. 'country_code') in display order.
	 */
	public static function discoverColumns(): array
	{
		$columns = [];

		foreach (glob(__DIR__ . '/*Detail.php') as $file)
		{
			$base  = basename($file, 'Detail.php');                 // CountryCode
			$class = __NAMESPACE__ . '\\' . $base . 'Detail';

			// Skip shared/abstract parents (e.g. UrlDetail) — not real columns.
			if ((new \ReflectionClass($class))->isAbstract())
			{
				continue;
			}

			// Convert the class base name to its snake_case column key.
			$key           = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $base)); // country_code
			$columns[$key] = $class::getListOrder();
		}

		// Sort by weight; equal weights keep glob's alphabetical order (stable sort).
		asort($columns);

		return array_keys($columns);
	}

	/**
	 * Factory: returns the appropriate Detail subclass for the given column.
	 * Pass null for $item when only a label is needed.
	 *
	 * @param   string       $column
	 * @param   object|null  $item
	 * @param   string       $consumer  One of Submission::CONSUMER_* (optional).
	 *
	 * @return  static
	 */
	public static function make(string $column, ?object $item, string $consumer = ''): self
	{
		$class = __NAMESPACE__ . '\\' . str_replace('_', '', ucwords($column, '_')) . 'Detail';

		if (!class_exists($class))
		{
			$class = self::class;
		}

		return new $class($column, $item, $consumer);
	}

	/**
	 * Returns the raw Joomla language key for this column, suitable for passing
	 * to helpers that call Text::_() internally (e.g. searchtools.sort).
	 * Subclasses override this method to return their specific key.
	 *
	 * @return  string
	 */
	public function getLangKey(): string
	{
		return 'COM_CONVERTFORMS_' . strtoupper($this->column);
	}

	/**
	 * Returns the translated label for this column.
	 *
	 * @return  string
	 */
	public function getLabel(): string
	{
		return Text::_($this->getLangKey());
	}

	/**
	 * Returns the translated description for this column, used as a tooltip in the
	 * export picker and the submission info card. The key is column-based (always in
	 * the COM_CONVERTFORMS_ namespace) so it stays independent of getLangKey(), which
	 * some subclasses point at framework/Joomla keys.
	 *
	 * @return  string
	 */
	public function getDescription(): string
	{
		return Text::_('COM_CONVERTFORMS_' . strtoupper($this->column) . '_DESC');
	}

	/**
	 * Returns the column value exactly as stored in the database.
	 * No ucfirst, no HTML encoding.
	 *
	 * WARNING: the value is unescaped and may be attacker-controlled (e.g. user_agent,
	 * page_title, source_url are visitor-supplied). NEVER echo this into HTML — that is a
	 * stored-XSS hole. Use only in non-HTML contexts: CSV export, JSON API, webhook
	 * payloads, the {tag.raw} Smart Tag. For browser output use getValue() (escaped) or
	 * getValueHTML() (rich, escaped).
	 *
	 * @return  string
	 */
	public function getValueRaw(): string
	{
		return (string) ($this->item->{$this->column} ?? '');
	}

	/**
	 * Returns the column value safe for browser output (plain text node, not HTML markup).
	 * Applies ucfirst() when the subclass sets $ucfirst = true, then HTML-encodes.
	 *
	 * @return  string
	 */
	public function getValue(): string
	{
		$value = $this->getValueRaw();

		if ($this->ucfirst)
		{
			$value = ucfirst($value);
		}

		return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Returns the column value as HTML markup.
	 * Subclasses override this for rich output (flag + country name, anchor links, etc.).
	 * Falls back to getValue() for columns with no rich representation.
	 *
	 * @return  string
	 */
	public function getValueHTML(): string
	{
		return $this->getValue();
	}
}
