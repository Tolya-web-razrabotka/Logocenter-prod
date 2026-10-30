<?php

/**
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            http://www.tassos.gr
 * @copyright       Copyright © 2020 Tassos Marinos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

namespace Tassos\Framework\Widgets;

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;

abstract class Video extends Widget
{
	/**
	 * Widget default options
	 *
	 * @var array
	 */
	protected $widget_options = [
		// Video URL
		'value' => '',

		// Video width
		'width' => '480px',

		// Video height
		'height' => '270px',

		// Whether the video will autoplay
		'autoplay' => false,

		// Whether the video will autopause whenever we scroll and it hides from our viewport
		'autopause' => false
	];

	public function __construct($options = [])
	{
		$this->widget_options = array_merge($this->widget_options, $this->video_widget_options);

		parent::__construct($options);

		$this->populateTimeOptionsFromUrl(['start', 'end']);

		$this->prepare();

		$this->styles();
	}

	protected function prepare()
	{}

	/**
	 * Populate time options from the source video URL query string.
	 *
	 * @param   array  $options
	 *
	 * @return  void
	 */
	protected function populateTimeOptionsFromUrl($options)
	{
		foreach ($options as $option)
		{
			if (!array_key_exists($option, $this->options) || ($this->options[$option] !== null && $this->options[$option] !== '' && $this->options[$option] !== 0 && $this->options[$option] !== '0'))
			{
				continue;
			}

			$optionValue = $this->getUrlQueryParam($option);

			if ($optionValue === null)
			{
				continue;
			}

			$this->options[$option] = $optionValue;
		}
	}

	/**
	 * Get a query parameter from the source video URL.
	 *
	 * @param   string  $parameter
	 *
	 * @return  string|null
	 */
	protected function getUrlQueryParam($parameter)
	{
		if (empty($this->options['value']) || empty($parameter))
		{
			return null;
		}

		$query = parse_url($this->options['value'], PHP_URL_QUERY);

		if (empty($query))
		{
			return null;
		}

		parse_str($query, $queryParams);

		if (!array_key_exists($parameter, $queryParams) || $queryParams[$parameter] === '')
		{
			return null;
		}

		return $queryParams[$parameter];
	}

	public function render()
	{
		$this->loadMedia();

		return parent::render();
	}

	public function styles()
	{
		if (!$this->options['load_css_vars'])
		{
			return;
		}
	
		$controls = [
            [
                'property' => '--video-width',
                'value' => $this->options['width']
            ],
            [
                'property' => '--video-height',
                'value' => $this->options['height']
            ]
		];

		$selector = '.nrf-widget.tf-video.' . $this->options['id'];
		
		$controlsInstance = new \Tassos\Framework\Controls\Controls(null, $selector);

        if (!$controlsCSS = $controlsInstance->generateCSS($controls))
        {
            return;
        }

        Factory::getDocument()->addStyleDeclaration($controlsCSS);
	}

	/**
	 * Loads media files
	 * 
	 * @return  void
	 */
	public function loadMedia()
	{
		if ($this->options['load_stylesheet'])
		{
			HTMLHelper::stylesheet('plg_system_nrframework/widgets/video.css', ['relative' => true, 'version' => 'auto']);
		}

		HTMLHelper::script('plg_system_nrframework/widgets/video.js', ['relative' => true, 'version' => 'auto']);

		if (method_exists($this, 'videoAssets'))
		{
			$this->videoAssets();
		}
		
		HTMLHelper::script('plg_system_nrframework/widgets/videos.js', ['relative' => true, 'version' => 'auto']);
	}
}