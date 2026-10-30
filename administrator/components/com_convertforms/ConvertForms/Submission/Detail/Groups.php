<?php

/**
 * @package         Convert Forms
 * @version         5.2.6 Free
 *
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

namespace ConvertForms\Submission\Detail;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;

/**
 * Organises the submission details into named groups (Submission, Location,
 * Device, etc.).
 *
 * Each detail's group is declared on its own Detail class via Base::$group;
 * this class owns only the display order and the group labels, then walks the
 * auto-discovered columns and buckets them. A newly added Detail therefore
 * surfaces automatically — in its declared group, or in the trailing "Other"
 * group until one is set.
 */
class Groups
{
	/**
	 * Group keys in display order. The group label is derived by convention:
	 * COM_CONVERTFORMS_DETAIL_GROUP_<KEY>. Any group not listed here (e.g.
	 * 'other') is appended last, so nothing is ever dropped.
	 *
	 * @var  string[]
	 */
	protected static array $order = [
		'identity',
		'account',
		'location',
		'device',
		'source'
	];

	/**
	 * Maps the two top-level sections to the groups they contain. Identity is the
	 * record's own identity/state; Context is the visitor/environment/attribution.
	 * Groups not listed under a section fall back to 'context' in getColumnsBySection().
	 * The sections drive the {submission.identity}/{submission.context} Smart Tags,
	 * the export dialog groups and the edit-page Identity block.
	 *
	 * @var  array<string, string[]>
	 */
	protected static array $sections = [
		'identity' => ['identity'],
		'context'  => ['account', 'location', 'device', 'source', 'other'],
	];

	/**
	 * Returns the submission details as an ordered list of group objects:
	 *
	 *   [
	 *     (object) [
	 *       'key'   => 'identity',
	 *       'label' => 'Identity',
	 *       'items' => [
	 *         (object) ['column' => 'id', 'label' => 'ID', 'description' => '…'],
	 *         …
	 *       ],
	 *     ],
	 *     …
	 *   ]
	 *
	 * @return  object[]
	 */
	public static function get(): array
	{
		// Bucket each discovered column (already $listOrder-sorted) by its declared group.
		$buckets = [];

		foreach (Base::discoverColumns() as $column)
		{
			$detail = Base::make($column, null);

			$buckets[$detail::getGroup()][] = (object) [
				'column'      => $column,
				'label'       => $detail->getLabel(),
				'description' => $detail->getDescription(),
			];
		}

		// Emit the known groups first in display order, then any leftover group
		// (e.g. 'other') so a newly added or unassigned Detail never disappears.
		$groupKeys = array_merge(static::$order, array_diff(array_keys($buckets), static::$order));

		$groups = [];

		foreach ($groupKeys as $key)
		{
			if (empty($buckets[$key]))
			{
				continue;
			}

			$groups[] = (object) [
				'key'   => $key,
				'label' => Text::_('COM_CONVERTFORMS_DETAIL_GROUP_' . strtoupper($key)),
				'items' => $buckets[$key],
			];
		}

		return $groups;
	}

	/**
	 * Returns every submission detail column key as one flat, ordered list: grouped in
	 * display order (see $order), then alphabetically by label within each group.
	 *
	 * This is the single source of truth for the detail order shared by the submissions-list
	 * Property Visibility picker and the Export Submissions Identity/Context groups, so
	 * neither has to re-implement the group-rank + alphabetical sort.
	 *
	 * @return  string[]  Column keys (e.g. 'country_code') in display order.
	 */
	public static function getColumns(): array
	{
		$columns = [];

		foreach (static::get() as $group)
		{
			$items = $group->items;

			usort($items, static function ($a, $b) {
				return strcasecmp($a->label, $b->label);
			});

			foreach ($items as $item)
			{
				$columns[] = $item->column;
			}
		}

		return $columns;
	}

	/**
	 * Returns the detail column keys that belong to the given section ('identity' or
	 * 'context'), in the shared canonical order. Any group not explicitly mapped to a
	 * section is treated as 'context', so a newly added Detail never disappears.
	 *
	 * Single source of truth for the {submission.identity}/{submission.context} Smart
	 * Tags, the export dialog's Identity/Context groups and the edit-page Identity block.
	 *
	 * @param   string  $section  'identity' or 'context'.
	 *
	 * @return  string[]  Column keys in display order.
	 */
	public static function getColumnsBySection(string $section): array
	{
		$groups = static::$sections[$section] ?? [];

		return array_values(array_filter(static::getColumns(), static function ($column) use ($section, $groups) {
			$group = Base::make($column, null)::getGroup();

			// Explicit membership wins; otherwise an unmapped group falls back to 'context'.
			$isMapped = in_array($group, static::$sections['identity'], true)
				|| in_array($group, static::$sections['context'], true);

			return $isMapped ? in_array($group, $groups, true) : ($section === 'context');
		}));
	}
}
