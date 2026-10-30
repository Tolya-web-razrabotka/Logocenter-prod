<?php

/**
 * @author          Tassos.gr
 * @link            https://www.tassos.gr
 * @copyright       Copyright © 2026 Tassos All Rights Reserved
 * @license         GNU GPLv3 <http://www.gnu.org/licenses/gpl.html> or later
*/

namespace Tassos\Framework\Ajax\Handlers;

use Joomla\Registry\Registry;

defined('_JEXEC') or die;

/**
 * Handler for condition builder UI operations.
 * 
 * URL: ?option=com_ajax&format=raw&plugin=nrframework&handler=conditionbuilder&subtask={subtask}
 * 
 * Available subtasks:
 * - add: Add a condition item or group
 * - options: Render condition options
 * - init_load: Initialize condition builder with existing data
 */
class ConditionBuilderHandler extends BaseHandler
{
    public function init()
    {
        $this->requireAdmin();

        $input = new Registry(json_decode(file_get_contents('php://input')));

        switch ($input->get('subtask', null))
        {
            // Adding a condition item or group
            case 'add':
                $conditionItemGroup = $input->get('conditionItemGroup', null);
                $groupKey = intval($input->get('groupKey'));
                $conditionKey = intval($input->get('conditionKey'));
                $include_rules = $input->get('include_rules', null);
                $exclude_rules = $input->get('exclude_rules', null);
                $exclude_rules_pro = $input->get('exclude_rules_pro', null) === '1';

                $conditionItem = \Tassos\Framework\Conditions\ConditionBuilder::add($conditionItemGroup, $groupKey, $conditionKey, null, $include_rules, $exclude_rules, $exclude_rules_pro);

                // Adding a single condition item
                if (!$input->get('addingNewGroup', false)) {
                    echo $conditionItem;
                    break;
                }

                $payload = [
                    'name' => $conditionItemGroup,
                    'groupKey' => $groupKey,
                    'groupConditions' => ['enabled' => 1],
                    'include_rules' => $include_rules,
                    'exclude_rules' => $exclude_rules,
                    'exclude_rules_pro' => $exclude_rules_pro,
                    'condition_items_parsed' => [$conditionItem],
                ];

                // Adding a condition group
                echo \Tassos\Framework\Conditions\ConditionBuilder::getLayout('conditionbuilder_group', $payload);
                break;
            case 'options':
                $conditionItemGroup = $input->get('conditionItemGroup', null);
                $name = $input->get('name', null);

                echo \Tassos\Framework\Conditions\ConditionBuilder::renderOptions($name, $conditionItemGroup);
                break;
            case 'init_load':
                $payload = [
                    'data' => $input->get('data', []),
                    'name' => $input->get('name', null),
                    'include_rules' => $input->get('include_rules', null),
                    'exclude_rules' => $input->get('exclude_rules', null),
                    'exclude_rules_pro' => $input->get('exclude_rules_pro', null) === '1'
                ];
                
                echo \Tassos\Framework\Conditions\ConditionBuilder::initLoad($payload);
                break;
        }
    }
}