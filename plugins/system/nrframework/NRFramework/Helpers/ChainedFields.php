<?php
/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

namespace Tassos\Framework\Helpers;

defined('_JEXEC') or die;

class ChainedFields
{
	public static function normalizeCSVFileSource($data_source = 'custom', $csv_data = '')
	{
		$data = $csv_data;
		
		if ($data_source === 'csv_file')
		{
			/**
			 * Backwards Compatibility:
			 * 
			 * The csv_file path could be an absolute path to the file.
			 * 
			 * Now, we expect a path relative to JPATH_ROOT.
			 * 
			 * So, if the path does not start with JPATH_ROOT, we prepend JPATH_ROOT to it.
			 */
			$csv_data = urldecode($csv_data);
			if ($csv_data && strpos($csv_data, JPATH_ROOT) !== 0)
			{
				$csv_data = implode(DIRECTORY_SEPARATOR, [JPATH_ROOT, ltrim($csv_data, '/\\')]);
			}

			if (!file_exists($csv_data))
			{
				return;
			}
		
			$data = $csv_data;
		}

		return $data;
	}
	
	/**
	 * Loads a combined array of the inputs and choices of the CSV file.
	 * 
	 * @param   string  $path
	 * @param   string  $data_source
	 * @param   string  $separator
	 * @param   string  $id_prefix
	 * @param   string  $name_prefix
	 * 
	 * @return  array
	 */
	public static function loadCSV($input, $data_source = 'custom', $separator = ',', $id_prefix = '', $name_prefix = '')
	{
		if (!$separator)
		{
			return [];
		}

		if ($data_source === 'csv_file')
		{
			if (!file_exists($input))
			{
				return [];
			}
	
			if (!$input = file_get_contents($input))
			{
				return [];
			}
		}

		if (!$data = self::getData($input, $separator, $id_prefix, $name_prefix))
		{
			return [];
		}

		return $data;
	}

	/**
	 * Iterates over the given data and returns the inputs and choices.
	 * 
	 * @param   string  $data
	 * @param   string  $separator
	 * @param   string  $id_prefix
	 * @param   string  $name_prefix
	 * 
	 * @return  array
	 */
	public static function getData($data = '', $separator = ',', $id_prefix = '', $name_prefix = '')
	{
		if (!$data || !is_string($data))
		{
			return;
		}
		
		$rows = array_map(function($line) use ($separator) {
			// Explicitly provide $enclosure and $escape to avoid deprecation warning
			return str_getcsv($line, $separator, '"', '\\');
		}, explode(PHP_EOL, $data));

		// Remove empty rows
		$rows = array_filter($rows, function($row) {
			return !empty(array_filter($row, function($item) { return is_string($item) && strlen($item); }));
		});

		if (empty($rows))
		{
			return;
		}

		$choices = [];
		$inputs = [];

		foreach ($rows as $row_index => $row)
		{
			// First row contains headers
			if ($row_index === 0)
			{
				$i = 1;
				
				foreach ($row as $index => $item)
				{
					if ($i % 10 == 0)
					{
						$i++;
					}

					$inputs[] = [
						'id'    => $id_prefix . $i,
						'name'  => $name_prefix . '[' . $i . ']',
						'label' => trim($item),
					];

					$i++;
				}

				continue;
			}

			$parent = null;

			foreach($row as $item)
			{
				$item = trim($item);
				
				if ($parent === null)
				{
					$parent = &$choices;
				}

				if (!isset($parent[$item]))
				{
					$parent[$item] = [
						'text'       => $item,
						'value'      => $item,
						'isSelected' => false,
						'choices'    => []
					];
				}

				$parent = &$parent[$item]['choices'];
			}
		}

		self::array_values_recursive($choices);

		if (!isset($inputs) || !isset($choices))
		{
			return;
		}

		return compact('inputs', 'choices');
	}

	/**
	 * Transforms an array to using as key an index value instead of a alphanumeric.
	 * 
	 * @param   array   $choices
	 * @param   string  $property
	 * 
	 * @return  array
	 */
	public static function array_values_recursive(&$choices, $property = 'choices')
	{
		$choices = array_values($choices);

		for($i = 0; $i <= count($choices); $i++)
		{
			if(empty($choices[$i][$property]))
			{
				continue;
			}

			$choices[$i][$property] = self::array_values_recursive($choices[$i][$property], $property);
        }

		return $choices;
	}
}