<?php

/**
 * @package         Convert Forms
 * @version         5.2.6 Free
 *
 * @author          Tassos Marinos <info@tassos.gr>
 * @link            http://www.tassos.gr
 * @copyright       Copyright © 2024 Tassos Marinos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\ListField;

// Define a new class extending Joomla's ListField for Google Product Categories
class JFormFieldTassos_GoogleProductCategory extends ListField
{
	/**
	 *  Dropdown options array
	 *
	 *  @var  array
	 */
	private $options;

	/**
	 * Static array defining 1st and 2nd level categories for Google Product Categories
	 * 
	 * @reference: https://www.google.com/basepages/producttype/taxonomy-with-ids.en-US.xls
	 *
	 * @var array
	 */
	private static $categories = [
		1 => [
			'title' => 'Animals & Pet Supplies',
			'subcategories' => [
				3237 => 'Live Animals',
				2 => 'Pet Supplies'
			]
		],
		166 => [
			'title' => 'Apparel & Accessories',
			'subcategories' => [
				1604 => 'Clothing',
				167 => 'Clothing Accessories',
				184 => 'Costumes & Accessories',
				6552 => 'Handbag & Wallet Accessories',
				6551 => 'Handbags, Wallets & Cases',
				188 => 'Jewelry',
				1933 => 'Shoe Accessories',
				187 => 'Shoes'
			]
		],
		8 => [
			'title' => 'Arts & Entertainment',
			'subcategories' => [
				499969 => 'Event Tickets',
				5710 => 'Hobbies & Creative Arts',
				5709 => 'Party & Celebration'
			]
		],
		537 => [
			'title' => 'Baby & Toddler',
			'subcategories' => [
				4678 => 'Baby Bathing',
				5859 => 'Baby Gift Sets',
				5252 => 'Baby Health',
				540 => 'Baby Safety',
				2847 => 'Baby Toys & Activity Equipment',
				2764 => 'Baby Transport',
				4386 => 'Baby Transport Accessories',
				548 => 'Diapering',
				561 => 'Nursing & Feeding',
				6952 => 'Potty Training',
				6899 => 'Swaddling & Receiving Blankets'
			]
		],
		111 => [
			'title' => 'Business & Industrial',
			'subcategories' => [
				5863 => 'Advertising & Marketing',
				112 => 'Agriculture',
				7261 => 'Automation Control Components',
				114 => 'Construction',
				7497 => 'Dentistry',
				2155 => 'Film & Television',
				1813 => 'Finance & Insurance',
				135 => 'Food Service',
				1827 => 'Forestry & Logging',
				7240 => 'Hairdressing & Cosmetology',
				1795 => 'Heavy Machinery',
				1475 => 'Hotel & Hospitality',
				5830 => 'Industrial Storage',
				8025 => 'Industrial Storage Accessories',
				500086 => 'Janitorial Carts & Caddies',
				1556 => 'Law Enforcement',
				1470 => 'Manufacturing',
				6987 => 'Material Handling',
				2496 => 'Medical',
				2187 => 'Mining & Quarrying',
				4285 => 'Piercing & Tattooing',
				138 => 'Retail',
				1624 => 'Science & Laboratory',
				976 => 'Signage',
				2047 => 'Work Safety Protective Gear'
			]
		],
		141 => [
			'title' => 'Cameras & Optics',
			'subcategories' => [
				2096 => 'Camera & Optic Accessories',
				142 => 'Cameras',
				156 => 'Optics',
				39 => 'Photography'
			]
		],
		222 => [
			'title' => 'Electronics',
			'subcategories' => [
				3356 => 'Arcade Equipment',
				223 => 'Audio',
				3702 => 'Circuit Boards & Components',
				262 => 'Communications',
				1801 => 'Components',
				278 => 'Computers',
				2082 => 'Electronics Accessories',
				3895 => 'GPS Accessories',
				339 => 'GPS Navigation Systems',
				6544 => 'GPS Tracking Devices',
				340 => 'Marine Electronics',
				342 => 'Networking',
				345 => 'Print, Copy, Scan & Fax',
				912 => 'Radar Detectors',
				500091 => 'Speed Radars',
				4488 => 'Toll Collection Devices',
				386 => 'Video',
				1270 => 'Video Game Console Accessories',
				1294 => 'Video Game Consoles'
			]
		],
		412 => [
			'title' => 'Food, Beverages & Tobacco',
			'subcategories' => [
				413 => 'Beverages',
				422 => 'Food Items',
				435 => 'Tobacco Products'
			]
		],
		436 => [
			'title' => 'Furniture',
			'subcategories' => [
				554 => 'Baby & Toddler Furniture',
				6433 => 'Beds & Accessories',
				441 => 'Benches',
				6356 => 'Cabinets & Storage',
				442 => 'Carts & Islands',
				7248 => 'Chair Accessories',
				443 => 'Chairs',
				457 => 'Entertainment Centers & TV Stands',
				6345 => 'Furniture Sets',
				6860 => 'Futon Frames',
				2786 => 'Futon Pads',
				450 => 'Futons',
				6362 => 'Office Furniture',
				503765 => 'Office Furniture Accessories',
				458 => 'Ottomans',
				4299 => 'Outdoor Furniture',
				6963 => 'Outdoor Furniture Accessories',
				6915 => 'Room Divider Accessories',
				4163 => 'Room Dividers',
				464 => 'Shelving',
				8023 => 'Shelving Accessories',
				7212 => 'Sofa Accessories',
				460 => 'Sofas',
				6913 => 'Table Accessories',
				6392 => 'Tables'
			]
		],
		632 => [
			'title' => 'Hardware',
			'subcategories' => [
				503739 => 'Building Consumables',
				115 => 'Building Materials',
				128 => 'Fencing & Barriers',
				543575 => 'Fuel',
				502975 => 'Fuel Containers & Tanks',
				2878 => 'Hardware Accessories',
				500096 => 'Hardware Pumps',
				499873 => 'Heating, Ventilation & Air Conditioning',
				1974 => 'Locks & Keys',
				133 => 'Plumbing',
				127 => 'Power & Electrical Supplies',
				499982 => 'Small Engines',
				1910 => 'Storage Tanks',
				3650 => 'Tool Accessories',
				1167 => 'Tools'
			]
		],
		469 => [
			'title' => 'Health & Beauty',
			'subcategories' => [
				491 => 'Health Care',
				5573 => 'Jewelry Cleaning & Care',
				2915 => 'Personal Care'
			]
		],
		536 => [
			'title' => 'Home & Garden',
			'subcategories' => [
				574 => 'Bathroom Accessories',
				359 => 'Business & Home Security',
				696 => 'Decor',
				5835 => 'Emergency Preparedness',
				2862 => 'Fireplace & Wood Stove Accessories',
				6792 => 'Fireplaces',
				1679 => 'Flood, Fire & Gas Safety',
				3348 => 'Household Appliance Accessories',
				604 => 'Household Appliances',
				630 => 'Household Supplies',
				638 => 'Kitchen & Dining',
				689 => 'Lawn & Garden',
				594 => 'Lighting',
				2956 => 'Lighting Accessories',
				4171 => 'Linens & Bedding',
				4358 => 'Parasols & Rain Umbrellas',
				985 => 'Plants',
				729 => 'Pool & Spa',
				600 => 'Smoking Accessories',
				6173 => 'Umbrella Sleeves & Cases',
				2639 => 'Wood Stoves'
			]
		],
		5181 => [
			'title' => 'Luggage & Bags',
			'subcategories' => [
				100 => 'Backpacks',
				101 => 'Briefcases',
				108 => 'Cosmetic & Toiletry Bags',
				549 => 'Diaper Bags',
				502974 => 'Dry Boxes',
				103 => 'Duffel Bags',
				104 => 'Fanny Packs',
				105 => 'Garment Bags',
				110 => 'Luggage Accessories',
				106 => 'Messenger Bags',
				5608 => 'Shopping Totes',
				107 => 'Suitcases',
				6553 => 'Train Cases'
			]
		],
		772 => [
			'title' => 'Mature',
			'subcategories' => [
				773 => 'Erotic',
				780 => 'Weapons'
			]
		],
		783 => [
			'title' => 'Media',
			'subcategories' => [
				784 => 'Books',
				499995 => 'Carpentry & Woodworking Project Plans',
				839 => 'DVDs & Videos',
				886 => 'Magazines & Newspapers',
				855 => 'Music & Sound Recordings',
				5037 => 'Product Manuals',
				887 => 'Sheet Music'
			]
		],
		922 => [
			'title' => 'Office Supplies',
			'subcategories' => [
				6174 => 'Book Accessories',
				8078 => 'Desk Pads & Blotters',
				923 => 'Filing & Organization',
				932 => 'General Office Supplies',
				5829 => 'Impulse Sealers',
				8499 => 'Lap Desks',
				2435 => 'Name Plates',
				6519 => 'Office & Chair Mats',
				6373 => 'Office Carts',
				950 => 'Office Equipment',
				2986 => 'Office Instruments',
				2014 => 'Paper Handling',
				964 => 'Presentation Supplies',
				2636 => 'Shipping Supplies'
			]
		],
		5605 => [
			'title' => 'Religious & Ceremonial',
			'subcategories' => [
				5606 => 'Memorial Ceremony Supplies',
				97 => 'Religious Items',
				5455 => 'Wedding Ceremony Supplies'
			]
		],
		2092 => [
			'title' => 'Software',
			'subcategories' => [
				313 => 'Computer Software',
				5032 => 'Digital Goods & Currency',
				1279 => 'Video Game Software'
			]
		],
		988 => [
			'title' => 'Sporting Goods',
			'subcategories' => [
				499713 => 'Athletics',
				990 => 'Exercise & Fitness',
				1001 => 'Indoor Games',
				1011 => 'Outdoor Recreation'
			]
		],
		1239 => [
			'title' => 'Toys & Games',
			'subcategories' => [
				4648 => 'Game Timers',
				3793 => 'Games',
				1249 => 'Outdoor Play Equipment',
				3867 => 'Puzzles',
				1253 => 'Toys'
			]
		],
		888 => [
			'title' => 'Vehicles & Parts',
			'subcategories' => [
				5613 => 'Vehicle Parts & Accessories',
				5614 => 'Vehicles'
			]
		]
	];

    /**
     * Returns all options to dropdown field
     *
     * This function merges options and returns the complete list of options to be displayed in the dropdown field of the form.
     *
     * @return array Array of options for the dropdown
     */
	protected function getOptions()
	{
		return array_merge(parent::getOptions(), $this->buildTree());
	}

	/**
	 *  Recursive traversal of the businessTypes array tree
	 *
	 *  @param   Array    $types   The business types
	 *  @param   integer  $level   The array level
	 *
	 *  @return  array
	 */
	private function buildTree()
	{
		foreach (self::$categories as $id => $firstLevelData)
		{
			$this->options[] = [
				'value'    => $id,
				'text'     => $firstLevelData['title'],
			];

			foreach ($firstLevelData['subcategories'] as $id => $title)
			{
				$this->options[] = [
					'value'    => $id,
					'text'     => '- ' . $title,
				];
			}
		}

		return $this->options;
	}
}