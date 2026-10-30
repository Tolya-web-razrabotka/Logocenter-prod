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

namespace ConvertForms\SmartTags;

defined('_JEXEC') or die('Restricted access');

use NRFramework\SmartTags\SmartTag;
use ConvertForms\Submission\Detail\Groups;
use Tassos\Vendor\Pelago\Emogrifier\CssInliner;

class Submission extends SmartTag
{
	/**
	 * Outputs the submission's IDENTITY details (ID, status, dates, form) as a
	 * label/value list, using the same markup and CSS classes as {submission.fields}.
	 *
	 * The columns are the ones in the Identity section (see Detail\Groups), discovered
	 * automatically, minus 'notes' (admin free-text, not part of the identity block).
	 *
	 * Syntax:						{submission.identity}
	 * Include empty values:		{submission.identity --excludeEmpty=false}
	 * Exclude certain details:		{submission.identity --exclude=status_label}
	 * Hide labels:					{submission.identity --hideLabels=true}
	 * Table layout:				{submission.identity --tmpl=table}
	 * Table without inline styles:	{submission.identity --tmpl=table --styles=false}
	 *
	 * @return  string
	 */
	public function getIdentity()
	{
		return $this->renderDetails(Groups::getColumnsBySection('identity'), ['notes']);
	}

	/**
	 * Outputs the submission's CONTEXT details (IP, country, device, OS, browser,
	 * page/source/referrer, UTM, visitor and account info) as a label/value list,
	 * using the same markup and CSS classes as {submission.fields}.
	 *
	 * The columns are the ones in the Context section (see Detail\Groups), discovered
	 * automatically, so a new Detail class shows up here without any change to this tag.
	 *
	 * Syntax:						{submission.context}
	 * Include empty values:		{submission.context --excludeEmpty=false}
	 * Exclude certain details:		{submission.context --exclude=ip,user_agent}
	 * Hide labels:					{submission.context --hideLabels=true}
	 * Table layout:				{submission.context --tmpl=table}
	 * Table without inline styles:	{submission.context --tmpl=table --styles=false}
	 *
	 * @return  string
	 */
	public function getContext()
	{
		return $this->renderDetails(Groups::getColumnsBySection('context'));
	}

	/**
	 * Shared renderer for the {submission.identity} and {submission.context} blocks:
	 * walks the given detail columns and renders them as a label/value list, honouring
	 * the same options as {submission.fields} (--excludeEmpty, --exclude, --hideLabels,
	 * --tmpl, --styles).
	 *
	 * @param   string[]  $columns        Detail column keys to render, in order.
	 * @param   string[]  $alwaysExclude  Columns to always skip regardless of options.
	 *
	 * @return  string
	 */
	private function renderDetails(array $columns, array $alwaysExclude = [])
	{
		$submission = isset($this->data['submission']) ? $this->data['submission'] : null;

		if (!$submission)
		{
			return;
		}

		$excludeEmpty   = (bool) $this->parsedOptions->get('excludeempty', true);
		$excludeDetails = array_filter(array_map('trim', explode(',', (string) $this->parsedOptions->get('exclude', ''))));
		$excludeDetails = array_merge($excludeDetails, $alwaysExclude);

		$rows = [];

		foreach ($columns as $column)
		{
			if (in_array($column, $excludeDetails, true))
			{
				continue;
			}

			$detail = $submission->getProp($column);
			$value  = $detail->getValueHTML();

			// Compare against the text content so values rendered as empty markup count as empty too.
			if ($excludeEmpty && trim(strip_tags((string) $value)) === '')
			{
				continue;
			}

			$rows[] = [
				'label' => $detail->getLabel(),
				'value' => $value
			];
		}

		return $this->renderRows($rows);
	}

	/**
	 * Outputs all submitted field values as a label/value list.
	 *
	 * This is the canonical implementation; the deprecated {all_fields} and
	 * {all_fields_filled} tags delegate here.
	 *
	 * Note: unlike the {submission.identity}/{submission.context} blocks, empty
	 * values are included by default to preserve the historical {all_fields} behavior.
	 *
	 * Syntax:						{submission.fields}
	 * Hide labels:					{submission.fields --hideLabels=true}
	 * Exclude empty values:		{submission.fields --excludeEmpty=true}
	 * Exclude certain fields:		{submission.fields --excludeFields=text1,dropdown2}
	 * Exclude certain field types:	{submission.fields --excludeTypes=text,hidden}
	 * Table layout:				{submission.fields --tmpl=table}
	 * Table without inline styles:	{submission.fields --tmpl=table --styles=false}
	 *
	 * @return  string
	 */
	public function getFields()
	{
		if (!$fields = $this->filteredFields())
		{
			return;
		}

		$rows = [];

		foreach ($fields as $field)
		{
			$rows[] = [
				'label' => $field->class->getLabel(), 
				'value' => $field->value_html
			];
		}

		return $this->renderRows($rows);
	}

	/**
	 * Renders label/value rows as the classic <span> list (default) or a <table>.
	 * Layout is chosen by the --tmpl option (list|table). Shared by
	 * {submission.fields}, {submission.identity} and {submission.context} so the
	 * markup lives in one place.
	 *
	 * @param   array  $rows  List of ['label' => string, 'value' => string (HTML)]
	 *
	 * @return  string
	 */
	private function renderRows(array $rows)
	{
		if (!$rows)
		{
			return '';
		}

		$hideLabels = (bool) $this->parsedOptions->get('hidelabels', false);
		$tmpl       = strtolower((string) $this->parsedOptions->get('tmpl', 'list'));

		return $tmpl === 'table'
			? $this->renderRowsTable($rows, $hideLabels)
			: $this->renderRowsList($rows, $hideLabels);
	}

	/**
	 * Classic <span>-based layout. Output is byte-identical to the historical
	 * {all_fields} markup, so existing templates and styling keep working.
	 *
	 * @param   array  $rows
	 * @param   bool   $hideLabels
	 *
	 * @return  string
	 */
	private function renderRowsList(array $rows, $hideLabels)
	{
		$html = '';

		foreach ($rows as $row)
		{
			$value = '<span class="cf-fields__value">' . $row['value'] . '</span>';

			if ($hideLabels)
			{
				$html .= $value . '<br>';
				continue;
			}

			$html .= '<span class="cf-fields__row"><strong class="cf-fields__label">' . $row['label'] . ':</strong> ' . $value . '</span><br>';
		}

		return $html;
	}

	/**
	 * <table>-based layout. The single cf-fields-table wrapper class is enough to
	 * target the rows and cells via descendant selectors (eg .cf-fields-table th).
	 * It uses cf-fields-table rather than the bare cf-fields class, which is a flex
	 * container and would break the table.
	 *
	 * By default a minimal stylesheet is applied as inline styles (so it survives
	 * email clients that strip <style> blocks). The --styles=false option skips this
	 * and returns the bare table for templates that bring their own CSS.
	 *
	 * @param   array  $rows
	 * @param   bool   $hideLabels
	 *
	 * @return  string
	 */
	private function renderRowsTable(array $rows, $hideLabels)
	{
		$body = '';

		foreach ($rows as $row)
		{
			$value = '<td>' . $row['value'] . '</td>';

			if ($hideLabels)
			{
				$body .= '<tr>' . $value . '</tr>';
				continue;
			}

			$body .= '<tr><th>' . $row['label'] . ':</th>' . $value . '</tr>';
		}

		$table = '<table class="cf-fields-table"><tbody>' . $body . '</tbody></table>';

		if (!(bool) $this->parsedOptions->get('styles', true))
		{
			return $table;
		}

		// Minimal, readable stylesheet — inlined below so it works in email too.
		// Zebra striping targets the cells (not the <tr>) because several email
		// clients ignore a background set on the row.
		$css = '
			.cf-fields-table {
				width:100%;
				max-width:900px;
			 	background-color:#fff;
				border-collapse: collapse;
				border: 1px solid #e5e5e5;
				color:#333;
			}
			.cf-fields-table th,
			.cf-fields-table td {
				text-align: left;
				vertical-align: top;
				padding: 10px 12px;
			}
			.cf-fields-table th {
				white-space: nowrap;
				width:30%;
			}
			.cf-fields-table tr:nth-child(even) th,
			.cf-fields-table tr:nth-child(even) td {
				background-color: #f9f9f9;
			}
		';

		return $this->inlineCss($table, $css);
	}

	/**
	 * Converts a CSS stylesheet into inline style attributes on the given HTML
	 * fragment, reusing the framework's Emogrifier wrapper. Inlining here (rather
	 * than relying on each consumer) keeps the output correct in email, on-page
	 * and PDF contexts alike. The class attributes survive, so a stylesheet can
	 * still override the defaults.
	 *
	 * @param   string  $html
	 * @param   string  $css
	 *
	 * @return  string
	 */
	private function inlineCss($html, $css)
	{
		try
		{
			return CssInliner::fromHtml($html)->inlineCss($css)->renderBodyContent();
		}
		catch (\Throwable $e)
		{
			// Never break rendering over styling. Fall back to a <style> block so
			// email's own inliner or the browser can still apply it.
			return '<style>' . $css . '</style>' . $html;
		}
	}

	/**
	 * Filter submitted data with given filter options
	 *
	 * @return mixed	Null when no submission is found, array otherwise
	 */
	private function filteredFields()
	{
		$submission = isset($this->data['submission']) ? $this->data['submission'] : '';

		if (!$submission)
		{
			return '';
		}

		$excludeEmpty  = $this->parsedOptions->get('excludeempty', false);
		$excludeTypes  = explode(',', $this->parsedOptions->get('excludetypes', ''));
		$excludeFields = explode(',', $this->parsedOptions->get('excludefields', ''));
		$excludeHiddenByLogic = $this->parsedOptions->get('excludehiddenbylogic', false);

		// Set by the submission model on the request that created the submission. Absent everywhere else,
		// such as backend PDF regeneration, cron tasks and the front-end submission view, where nothing is excluded.
		$hiddenFieldKeys = isset($submission->hidden_field_keys) && is_array($submission->hidden_field_keys)
			? array_map('strval', $submission->hidden_field_keys)
			: [];

		return array_filter($submission->prepared_fields, function($field) use ($excludeTypes, $excludeFields, $excludeEmpty, $excludeHiddenByLogic, $hiddenFieldKeys)
		{
			if ($excludeEmpty && trim((string) $field->value) == '')
			{
				return;
			}

			if ($excludeTypes && in_array($field->options->get('type'), $excludeTypes))
			{
				return;
			}

			if ($excludeFields && in_array($field->options->get('name'), $excludeFields))
			{
				return;
			}

			if ($excludeHiddenByLogic && in_array((string) $field->options->get('key'), $hiddenFieldKeys, true))
			{
				return;
			}

			return true;
		});
	}

	/**
	 * Returns any submission column value, routed through the Detail class
	 * hierarchy so that subclass transformations (ucfirst, label resolution,
	 * etc.) are applied automatically.
	 *
	 * Covers all DB-column tags: {submission.ip}, {submission.device},
	 * {submission.browser}, {submission.os}, {submission.user_agent},
	 * {submission.page_title}, {submission.source_url}, {submission.referrer_url},
	 * {submission.country_code}, {submission.country_name}, {submission.id},
	 * {submission.user_id}, {submission.visitor_id}, {submission.campaign_id},
	 * {submission.form_id}, etc.
	 *
	 * Every concrete Submission\Detail class is automatically available here as
	 * {submission.<column>} — getProp() resolves the matching Detail class — so this
	 * stays in sync with the submissions-list columns (single source of truth).
	 *
	 * An optional output modifier may be appended to the column to control how
	 * the value is rendered, mirroring the Field Smart Tag syntax:
	 *
	 *   {submission.ip}       Safe plain text, HTML-encoded for browser output (default).
	 *   {submission.ip.raw}   The value exactly as stored in the database. No encoding.
	 *   {submission.ip.html}  Rich HTML markup (eg flag + country name, anchor links).
	 *
	 * @param   string  $key
	 *
	 * @return  string
	 */
	public function fetchValue($key)
	{
		if (!isset($this->data['submission']))
		{
			return '';
		}

		// Separate the column name from an optional output modifier.
		// Eg: {submission.ip.raw} → column 'ip', modifier 'raw'
		$keyParts = explode('.', $key);
		$column   = $keyParts[0];
		$modifier = isset($keyParts[1]) ? strtolower($keyParts[1]) : null;

		$prop = $this->data['submission']->getProp($column);

		switch ($modifier)
		{
			case 'raw':
				// The value exactly as stored in the database. No encoding.
				return $prop->getValueRaw();

			case 'html':
				// The value as rich HTML markup.
				return $prop->getValueHTML();

			default:
				// Safe plain-text value, HTML-encoded for browser output.
				return $prop->getValue();
		}
	}

	/**
	 * Returns the login name (Joomla username) of the user that submitted the form.
	 * Routed through the user_login_name Detail class so it stays in sync with
	 * {submission.user_login_name}.
	 *
	 * Usage: {submission.user_username} (deprecated) — use {submission.user_login_name} instead.
	 *
	 * @return  string
	 */
	public function getUser_UserName()
	{
		return $this->fetchValue('user_login_name');
	}

	/**
	 * Returns the submission created date.
	 * Provides the named tag {submission.dateCreated} which differs from the column name 'created'.
	 *
	 * Usage: {submission.dateCreated}
	 *
	 * @return  string
	 */
	public function getDateCreated()
	{
		return $this->fetchValue('created');
	}

	/**
	 * Returns the submission modified date.
	 * Provides the named tag {submission.dateModified} which differs from the column name 'modified'.
	 *
	 * Usage: {submission.dateModified}
	 *
	 * @return  string
	 */
	public function getDateModified()
	{
		return $this->fetchValue('modified');
	}

	/**
	 * Returns the submission created date.
	 *
	 * Usage: {submission.created} (deprecated) — use {submission.dateCreated} instead.
	 *
	 * @deprecated Use getDateCreated() instead.
	 */
	public function getCreated()
	{
		return $this->fetchValue('created');
	}

	/**
	 * Returns the submission modified date.
	 *
	 * Usage: {submission.modified} (deprecated) — use {submission.dateModified} instead.
	 *
	 * @deprecated Use getDateModified() instead.
	 */
	public function getModified()
	{
		return $this->fetchValue('modified');
	}

	/**
	 * Returns the submission created date.
	 *
	 * Usage: {submission.date} (deprecated) — use {submission.dateCreated} instead.
	 *
	 * @deprecated Use getDateCreated() instead.
	 */
	public function getDate()
	{
		return $this->fetchValue('created');
	}

	/**
	 * Returns the submission PDF URL/path (PRO feature).
	 *
	 * Usage: {submission.pdf}
	 *
	 * @return  string
	 */
	public function getPDF()
	{
		if (!isset($this->data['extra_data']['pdf']))
		{
			return '';
		}

		return $this->data['extra_data']['pdf'];
	}
}