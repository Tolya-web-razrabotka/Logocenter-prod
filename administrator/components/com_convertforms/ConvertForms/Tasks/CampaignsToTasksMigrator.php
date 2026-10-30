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

namespace ConvertForms\Tasks;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use NRFramework\Integrations\ActiveCampaign;
use NRFramework\Integrations\Brevo;
use NRFramework\Integrations\CampaignMonitor;
use NRFramework\Integrations\Drip;
use NRFramework\Integrations\ElasticEmail;
use NRFramework\Integrations\GetResponse;
use NRFramework\Integrations\HubSpot3;
use NRFramework\Integrations\iContact;
use NRFramework\Integrations\Kit;
use NRFramework\Integrations\MailChimp;

/**
 * @deprecated Campaigns to Tasks migration shim — remove together with legacy campaigns support once migrations are no longer expected.
 */
class CampaignsToTasksMigrator
{
    const DEFAULT_BATCH_SIZE = 25;
    const MAX_BATCH_SIZE = 100;
    const NO_SYNC_CAMPAIGN_NAME = 'No Sync - Tasks Migration';

    /**
     * @var \Joomla\Database\DatabaseDriver
     */
    protected $db;

    /**
     * @var array
     */
    private $connectionCache = [];

    /**
     * @var int|null
     */
    private $noSyncCampaignId;

    /**
     * @var array
     */
    private $stats;
    private $schemaCache = [];
    private $connectionTestCache = [];

    public function __construct($db = null)
    {
        $this->db = $db ?: Factory::getDbo();
        $this->stats = self::getEmptyStats();
    }

    public static function getEmptyStats()
    {
        return [
            'processed' => 0,
            'migrated' => 0,
            'tasks_created' => 0,
            'tasks_reused' => 0,
            'connections_created' => 0,
            'connections_reused' => 0,
            'forms_updated' => 0,
            'migrated_full' => 0,
            'migrated_partial' => 0,
            'skipped' => 0,
            'ignored' => 0
        ];
    }

    public function start()
    {
        $total = $this->getCandidateTotal();

        return [
            'total' => $total,
            'cursor' => 0,
            'batch' => self::DEFAULT_BATCH_SIZE,
            'stats' => self::getEmptyStats(),
            'capabilities' => [
                'schema_aware' => true,
                'partial_mapping' => true,
                'services' => array_values(array_map(function($service, $meta)
                {
                    return [
                        'app' => $service,
                        'schema_source' => $meta['schema_source'],
                        'target_key' => $meta['target_key']
                    ];
                }, array_keys($this->getProviderMatrix()), $this->getProviderMatrix()))
            ],
            'estimated_discovery' => $total
        ];
    }

    public function run($cursor = 0, $batchSize = self::DEFAULT_BATCH_SIZE)
    {
        $cursor = max(0, (int) $cursor);
        $batchSize = max(1, min((int) $batchSize, self::MAX_BATCH_SIZE));

        $rows = $this->getCandidateForms($cursor, $batchSize);
        $lastCursor = $cursor;
        $reports = [];

        if (!$rows)
        {
            return [
                'done' => true,
                'cursor' => $lastCursor,
                'stats' => $this->stats,
                'reports' => $reports
            ];
        }

        $forms = [];
        $formIds = [];
        $campaignIds = [];

        foreach ($rows as $row)
        {
            $lastCursor = (int) $row->id;
            $formIds[] = (int) $row->id;

            $params = $this->decodeJson($row->params);

            $campaignId = isset($params['campaign']) ? (int) $params['campaign'] : 0;

            if ($campaignId)
            {
                $campaignIds[] = $campaignId;
            }

            $forms[] = [
                'id' => (int) $row->id,
                'name' => $row->name,
                'params' => $params,
                'campaign_id' => $campaignId
            ];
        }

        $campaigns = $this->getCampaignsByIds($campaignIds);
        $tasksByForm = $this->getTasksByFormIds($formIds);

        foreach ($forms as $form)
        {
            $this->stats['processed']++;

            try
            {
                $report = $this->processForm($form, isset($campaigns[$form['campaign_id']]) ? $campaigns[$form['campaign_id']] : null, isset($tasksByForm[$form['id']]) ? $tasksByForm[$form['id']] : []);

                if ($report)
                {
                    $reports[] = $report;
                }
            }
            catch (\Throwable $e)
            {
                $this->stats['skipped']++;
                $reports[] = $this->report($form, null, 'skipped', $e->getMessage());
            }
        }

        return [
            'done' => count($rows) < $batchSize,
            'cursor' => $lastCursor,
            'stats' => $this->stats,
            'reports' => $reports
        ];
    }

    protected function processForm($form, $campaign, $existingTasks)
    {
        if (!$form['campaign_id'])
        {
            $this->stats['ignored']++;
            return $this->report($form, $campaign, 'ignored', Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_IGNORE_NO_CAMPAIGN'));
        }

        if (!$campaign)
        {
            return $this->skip($form, null, Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_SKIP_CAMPAIGN_MISSING'));
        }

        if ($this->isNoSyncService($campaign['service']))
        {
            // Already migrated by an earlier run. Reported as "no action needed" rather than
            // "migrated": this run did not migrate it, and the mapping quality is whatever the run
            // that created the Task reported. Counting it as migrated filed every previously
            // migrated form under "Migrated (partial)" on each re-run, since no mapping is computed
            // here and report() defaults to a mode that is not "full".
            if (!empty($existingTasks))
            {
                $this->stats['ignored']++;
                return $this->report($form, $campaign, 'ignored', Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_MIGRATED_ALREADY'));
            }

            $this->stats['ignored']++;
            return $this->report($form, $campaign, 'ignored', Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_IGNORE_NO_SYNC'));
        }

        $migration = $this->buildMigration($form, $campaign);

        if (isset($migration['skip']))
        {
            return $this->skip($form, $campaign, $migration['skip'], !empty($migration['unmigratable']), isset($migration['docs']) ? $migration['docs'] : '');
        }

        if (!Apps::exists($migration['app']))
        {
            return $this->skip($form, $campaign, Text::sprintf('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_SKIP_PLUGIN', $migration['app']));
        }

        $connectionId = $this->ensureConnection($campaign, $migration);

        if (!empty($migration['connection']) && !$connectionId)
        {
            return $this->skip($form, $campaign, Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_SKIP_CONNECTION'));
        }

        $task = $this->prepareTask($form, $campaign, $migration, $connectionId, $existingTasks);

        $created = false;

        if ($this->findEquivalentTask($existingTasks, $task))
        {
            $this->stats['tasks_reused']++;
        }
        else
        {
            $this->insertTask($task);
            $this->stats['tasks_created']++;
            $created = true;
        }

        $this->moveFormToNoSyncCampaign($form);
        $this->stats['migrated']++;
        $mapping = isset($migration['mapping']) ? $migration['mapping'] : [
            'mapping_mode' => 'partial',
            'mapped_count' => 0,
            'unmapped_fields' => [],
            'required_unmapped_fields' => []
        ];

        if ($mapping['mapping_mode'] === 'full')
        {
            $this->stats['migrated_full']++;
        }
        else
        {
            $this->stats['migrated_partial']++;
        }

        $report = $this->report(
            $form,
            $campaign,
            'migrated',
            $created
                ? Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_MIGRATED_CREATED')
                : Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_MIGRATED_REUSED')
        );

        return array_merge($report, $mapping);
    }

    private function getCandidateTotal()
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__convertforms'))
            ->where($this->db->quoteName('params') . ' LIKE ' . $this->db->quote('%"campaign"%'));

        $this->db->setQuery($query);

        return (int) $this->db->loadResult();
    }

    private function getCandidateForms($cursor, $limit)
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['id', 'name', 'params']))
            ->from($this->db->quoteName('#__convertforms'))
            ->where($this->db->quoteName('id') . ' > ' . (int) $cursor)
            ->where($this->db->quoteName('params') . ' LIKE ' . $this->db->quote('%"campaign"%'))
            ->order($this->db->quoteName('id') . ' ASC');

        $this->db->setQuery($query, 0, $limit);

        return $this->db->loadObjectList();
    }

    private function getCampaignsByIds($ids)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if (!$ids)
        {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__convertforms_campaigns'))
            ->where($this->db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');

        $this->db->setQuery($query);

        $campaigns = [];

        foreach ($this->db->loadAssocList() as $campaign)
        {
            $params = $this->decodeJson($campaign['params']);

            $campaigns[(int) $campaign['id']] = array_merge($campaign, $params);
        }

        return $campaigns;
    }

    private function getTasksByFormIds($formIds)
    {
        $formIds = array_values(array_unique(array_filter(array_map('intval', $formIds))));

        if (!$formIds)
        {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__convertforms_tasks'))
            ->where($this->db->quoteName('form_id') . ' IN (' . implode(',', $formIds) . ')')
            ->order($this->db->quoteName('ordering') . ' ASC');

        $this->db->setQuery($query);

        $tasks = [];

        foreach ($this->db->loadAssocList() as $task)
        {
            $task['options'] = $this->decodeJson($task['options']);
            $task['conditions'] = $this->decodeJson($task['conditions']);
            $tasks[(int) $task['form_id']][] = $task;
        }

        return $tasks;
    }

    /**
     * Services that have a Task app but whose legacy campaign stores a deprecated API key the new
     * Task cannot use, so they cannot be migrated automatically and are skipped without attempting
     * any connection. Keyed by campaign service; value carries the display name and the docs URL
     * shown in the post-run notice (JFormFieldCampaignsToTasks::getUnsafeNotice()).
     *
     * - hubspot: legacy campaigns hold a HubSpot API key (hapikey, sunset by HubSpot in 2022);
     *   the HubSpot3 client the app uses needs a Private App access token (Bearer).
     * - convertkit: legacy campaigns hold a ConvertKit v3 key; the Kit app uses the v4 API,
     *   which only accepts v4 keys.
     */
    public static function getUnsafeServices()
    {
        return [
            'hubspot' => [
                'name' => 'HubSpot',
                'docs' => 'https://www.tassos.gr/docs/convert-forms/integrations/crm/hubspot'
            ],
            'convertkit' => [
                'name' => 'ConvertKit',
                'docs' => 'https://www.tassos.gr/docs/convert-forms/integrations/email-marketing/kit'
            ]
        ];
    }

    /**
     * The legacy campaign services that have a migratable Task equivalent.
     *
     * Kept in sync with buildMigration()'s switch: every service here has a `case` there, and
     * every migratable `case` there is listed here. Deliberately excluded (they never count as
     * "pending"): the unsafe set (hubspot, convertkit — getUnsafeServices()), the discontinued
     * set (aweber, zoho, zohocrm) and any unknown/unsupported service. Default / no-sync
     * campaigns carry an empty service and are excluded by construction.
     *
     * @return  string[]
     */
    public static function getMigratableServices()
    {
        return [
            'activecampaign',
            'acymailing',
            'campaignmonitor',
            'drip',
            'elasticemail',
            'getresponse',
            'icontact',
            'mailchimp',
            'salesforce',
            'sendinblue'
        ];
    }

    /**
     * Counts the forms still assigned to a migratable legacy campaign.
     *
     * This is the whole "is the migration done?" signal: zero means every form has been moved
     * off its migratable campaign (to the No Sync campaign, or manually) and the notice can
     * disappear. Forms on default/no-sync campaigns and on never-migratable services (unsafe,
     * discontinued, unknown) are not counted — they can never be pending.
     *
     * @return  int
     */
    public function getPendingMigratableCount()
    {
        $migratableCampaignIds = $this->getMigratableCampaignIds();

        if (!$migratableCampaignIds)
        {
            return 0;
        }

        $lookup = array_flip($migratableCampaignIds);
        $count = 0;
        $cursor = 0;

        do
        {
            $rows = $this->getCandidateForms($cursor, self::MAX_BATCH_SIZE);

            foreach ($rows as $row)
            {
                $cursor = (int) $row->id;

                $params = $this->decodeJson($row->params);
                $campaignId = isset($params['campaign']) ? (int) $params['campaign'] : 0;

                if ($campaignId && isset($lookup[$campaignId]))
                {
                    $count++;
                }
            }
        }
        while (count($rows) === self::MAX_BATCH_SIZE);

        return $count;
    }

    /**
     * The ids of every campaign whose service is in the migratable whitelist.
     *
     * Returns an empty list if the legacy campaigns table is absent — on such a site there is
     * nothing to migrate, and the read-only notice check must never fatal an admin page.
     *
     * @return  int[]
     */
    private function getMigratableCampaignIds()
    {
        $services = array_map([$this->db, 'quote'], self::getMigratableServices());

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__convertforms_campaigns'))
            ->where($this->db->quoteName('service') . ' IN (' . implode(',', $services) . ')');

        $this->db->setQuery($query);

        try
        {
            return array_map('intval', (array) $this->db->loadColumn());
        }
        catch (\Throwable $e)
        {
            return [];
        }
    }

    protected function buildMigration($form, $campaign)
    {
        $service = strtolower((string) $campaign['service']);

        // Services whose legacy credentials cannot talk to the new API are skipped up-front, with
        // no connection attempt — the admin is pointed to the docs (in the post-run notice) to set
        // the Task up manually with the new credential type.
        $unsafe = self::getUnsafeServices();

        if (isset($unsafe[$service]))
        {
            return [
                'skip' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_UNSAFE_REASON'),
                'docs' => $unsafe[$service]['docs']
            ];
        }

        $fields = $this->getFieldTags($form['params']);
        $email = $this->getEmailTag($form['params'], $fields);

        if (!$email)
        {
            return ['skip' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_SKIP_EMAIL')];
        }

        switch ($service)
        {
            case 'activecampaign':
                return $this->withRemoteSchemaConnection('activecampaign', ['api_url' => $this->getValue($campaign, 'endpoint'), 'api_key' => $this->getValue($campaign, 'api')], [
                    'list' => $this->getValue($campaign, 'list'),
                    'update_existing_subscriber' => $this->boolString($this->getValue($campaign, 'updateexisting', '1'), '1'),
                    'email' => $email,
                    'firstName' => $this->findFieldTag($fields, ['firstName', 'firstname', 'first_name', 'name']),
                    'lastName' => $this->findFieldTag($fields, ['lastName', 'lastname', 'last_name']),
                    'phone' => $this->findFieldTag($fields, ['phone']),
                    'tags' => $this->repeatSelect($this->findFieldTag($fields, ['tags']))
                ], $fields, 'customfields', $campaign);

            case 'acymailing':
                return $this->withAcyMailingSchema($campaign, $fields, $email);

            case 'campaignmonitor':
                return $this->withRemoteSchemaConnection('campaignmonitor', ['api_key' => $this->getValue($campaign, 'api')], [
                    'list' => $this->getValue($campaign, 'list'),
                    'email' => $email,
                    'name' => $this->findFieldTag($fields, ['name'])
                ], $fields, 'customfields', $campaign);

            case 'drip':
                return $this->withRemoteSchemaConnection('drip', ['api_key' => $this->getValue($campaign, 'api'), 'account_id' => $this->getValue($campaign, 'account_id')], [
                    'list' => $this->getValue($campaign, 'list'),
                    'update_existing_subscriber' => $this->boolString($this->getValue($campaign, 'updateexisting', '1'), '1'),
                    'double_optin' => $this->boolString($this->getValue($campaign, 'doubleoptin', '0'), '0'),
                    'email' => $email,
                    'name' => $this->findFieldTag($fields, ['name']),
                    'tags' => $this->repeatSelect($this->findFieldTag($fields, ['tags']))
                ], $fields, 'customfields', $campaign);

            case 'elasticemail':
                return $this->withRemoteSchemaConnection('elasticemail', ['api_key' => $this->getValue($campaign, 'api')], [
                    'list' => $this->getValue($campaign, 'list'),
                    'update_existing_subscriber' => $this->boolString($this->getValue($campaign, 'updateexisting', '1'), '1'),
                    'double_optin' => $this->boolString($this->getValue($campaign, 'doubleoptin', '0'), '0'),
                    'email' => $email,
                    'firstName' => $this->findFieldTag($fields, ['firstName', 'firstname', 'first_name']),
                    'lastName' => $this->findFieldTag($fields, ['lastName', 'lastname', 'last_name'])
                ], $fields, 'customfields', $campaign);

            case 'getresponse':
                return $this->withRemoteSchemaConnection('getresponse', ['api_key' => $this->getValue($campaign, 'api')], [
                    'list' => $this->getValue($campaign, 'list'),
                    'update_existing_subscriber' => $this->boolString($this->getValue($campaign, 'updateexisting', '1'), '1'),
                    'email' => $email,
                    'name' => $this->findFieldTag($fields, ['name']),
                    'dayOfCycle' => '0',
                    'tags' => $this->repeatSelect($this->findFieldTag($fields, ['tags'])),
                    'tags_replace' => 'add_only'
                ], $fields, 'custom_fields', $campaign);

            case 'icontact':
                return $this->withRemoteSchemaConnection('icontact', ['api_app_id' => $this->getValue($campaign, 'appID'), 'api_username' => $this->getValue($campaign, 'username'), 'api_password' => $this->getValue($campaign, 'appPassword')], array_merge([
                    'list' => $this->getValue($campaign, 'list'),
                    'email' => $email
                ], $this->knownOptions($fields, ['prefix', 'firstName', 'lastName', 'suffix', 'street', 'street2', 'city', 'state', 'postalCode', 'phone', 'fax', 'business'])), $fields, 'customfields', $campaign);

            case 'mailchimp':
                return $this->withRemoteSchemaConnection('mailchimp', ['api_key' => $this->getValue($campaign, 'api')], [
                    'audience' => $this->getValue($campaign, 'list'),
                    'update_existing_subscriber' => $this->boolString($this->getValue($campaign, 'updateexisting', '1'), '1'),
                    'double_optin' => $this->boolString($this->getValue($campaign, 'doubleoptin', '0'), '0'),
                    'email' => $email,
                    'tags_replace' => 'add_only',
                    'interests_replace' => 'add_only'
                ], $fields, 'flat', $campaign);

            case 'salesforce':
                $lead = $this->promoteKnownFields($fields, ['first_name', 'last_name', 'phone', 'company', 'title', 'street', 'city', 'state', 'zip', 'description'], $email);

                return $this->withoutConnection('salesforcewebtolead', array_merge([
                    'org_id' => $this->getValue($campaign, 'organizationID'),
                    'test_mode' => $this->boolString($this->getValue($campaign, 'test_mode', '0'), '0'),
                    'email' => $email
                ], $lead['options'], [
                    'customfields' => $this->keyValues($fields, $lead['consumed'])
                ]), $fields);

            case 'sendinblue':
                if ((int) $this->getValue($campaign, 'version', 3) !== 3)
                {
                    return ['skip' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_SKIP_SENDINBLUE_V2'), 'unmigratable' => true];
                }

                return $this->withRemoteSchemaConnection('brevo', ['api_key' => $this->getValue($campaign, 'api')], [
                    'list' => $this->getValue($campaign, 'list'),
                    'update_existing_subscriber' => $this->boolString($this->getValue($campaign, 'updateexisting', '1'), '1'),
                    'email' => $email
                ], $fields, 'properties', $campaign);

            case 'aweber':
            case 'zoho':
            case 'zohocrm':
                return ['skip' => Text::sprintf('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_SKIP_DISCONTINUED', $campaign['service']), 'unmigratable' => true];
        }

        return ['skip' => Text::sprintf('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_SKIP_UNSUPPORTED', $campaign['service']), 'unmigratable' => true];
    }

    protected function withRemoteSchemaConnection($app, $connection, $baseOptions, $fields, $container, $campaign)
    {
        foreach ($connection as $value)
        {
            if ($value === '' || is_null($value))
            {
                return ['skip' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_SKIP_CREDENTIALS')];
            }
        }

        // Validate the credentials with the app's own connection test before anything is created,
        // so a wrong/expired key never produces a Task that fails at run time. This catches keys
        // that a schema fetch would miss — some services return an empty result on a bad key
        // instead of erroring.
        if ($connectionError = $this->testAppConnection($app, $connection))
        {
            return ['skip' => Text::sprintf('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_SKIP_CONNECTION_TEST', $connectionError)];
        }

        $schema = $this->getRemoteSchema($app, $connection, $baseOptions, $campaign);

        $providerMeta = $this->getProviderMatrix();
        $source = isset($providerMeta[$app]['schema_source']) ? $providerMeta[$app]['schema_source'] : 'none';

        if ($source === 'remote' && !empty($schema['error']))
        {
            // A schema fetch failure means the stored credentials no longer work. Skip the form so
            // we never create a Task that can't authenticate — even when there are no custom fields
            // to map. The form keeps its campaign, so the admin fixes the connection and re-runs.
            // (Kit gets a dedicated, actionable message.)
            if ($app === 'kit')
            {
                return ['skip' => Text::sprintf('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_SKIP_KIT_CONNECTION', $schema['error'])];
            }

            return ['skip' => Text::sprintf('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_SKIP_SCHEMA', $schema['error'])];
        }

        $match = $this->matchFormFieldsToSchema($fields, isset($schema['fields']) ? $schema['fields'] : []);
        $customPayload = $this->buildMappedPayload($match['mapped'], $container);
        $options = $baseOptions;

        if ($container === 'flat')
        {
            $options = array_merge($options, $customPayload);
        }
        else
        {
            $options[$container] = $customPayload;
        }

        $options = $this->cleanOptions($options);

        if ($missing = $this->missingRequiredOptions($app, $options))
        {
            return ['skip' => Text::sprintf('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_SKIP_REQUIRED_SETTING', $missing)];
        }

        return [
            'app' => $app,
            'action' => 'subscribe',
            'connection' => $connection,
            'options' => $options,
            'mapping' => $this->buildMappingDiagnostics($match, $fields)
        ];
    }

    /**
     * Builds an AcyMailing migration.
     *
     * AcyMailing has no remote API — its custom fields live in the local #__acym_field table
     * and the task app keys each option by the exact field name (case-sensitive). So the schema
     * is fetched locally and form fields are name-matched against it, emitting the stored name
     * (e.g. a form field "age" maps to the AcyMailing field "Age"). Unmatched fields are reported,
     * not silently emitted, so the report reflects what the task can actually populate.
     */
    protected function withAcyMailingSchema($campaign, $fields, $email)
    {
        $schema = $this->getAcyMailingSchema();

        // Match on names only — AcyMailing is lenient about field types and the legacy plugin
        // ran no type check — so blank the types before matching to avoid false vetoes.
        $matchSchema = array_map(function($field)
        {
            return ['key' => $field['key'], 'label' => $field['label'], 'required' => $field['required'], 'type' => ''];
        }, $schema);

        $match = $this->matchFormFieldsToSchema($fields, $matchSchema);
        $custom = $this->buildMappedPayload($match['mapped'], 'flat');

        // AcyMailing language fields hold a locale code; the task app defaults them to "auto"
        // (resolved to the current Joomla language at submission, see the app's
        // prepareLanguageFields()). Emit "auto" so required language fields are populated
        // instead of left blank and reported as required-unmapped.
        foreach ($schema as $field)
        {
            if ($field['type'] !== 'language')
            {
                continue;
            }

            $custom[$field['key']] = 'auto';

            $match['required_unmapped'] = array_values(array_filter($match['required_unmapped'], function($key) use ($field)
            {
                return strtolower($key) !== strtolower($field['key']);
            }));
        }

        $options = $this->cleanOptions(array_merge([
            'list' => $this->acyMailingListId($this->getValue($campaign, 'list')),
            'double_optin' => $this->boolString($this->getValue($campaign, 'doubleoptin', '0'), '0'),
            'trigger_acym_notifications' => '0',
            'email' => $email
        ], $custom));

        if ($missing = $this->missingRequiredOptions('acymailing', $options))
        {
            return ['skip' => Text::sprintf('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_SKIP_REQUIRED_SETTING', $missing)];
        }

        return [
            'app' => 'acymailing',
            'action' => 'subscribe',
            'connection' => [],
            'options' => $options,
            'mapping' => $this->buildMappingDiagnostics($match, $fields)
        ];
    }

    /**
     * Normalizes a legacy AcyMailing campaign list value to the id the task app's dropdown uses.
     *
     * Legacy campaigns stored lists as an array; AcyMailing v6+ list ids carry a "6:" prefix
     * (v5 ids have none) which the legacy plugin stripped before subscribing. The new app's
     * dropdown uses the bare integer id, so strip the prefix and take the first list — the app
     * subscribes to a single list, whereas a legacy campaign could target several.
     */
    private function acyMailingListId($value)
    {
        if (is_array($value))
        {
            $value = reset($value);
        }

        $value = preg_replace('/^6:/', '', (string) $value);

        return $value === '' ? '' : (string) (int) $value;
    }

    private function getAcyMailingSchema()
    {
        try
        {
            return $this->fetchAcyMailingFields();
        }
        catch (\Throwable $e)
        {
            return [];
        }
    }

    /**
     * Fetches AcyMailing's custom fields from the local #__acym_field table.
     *
     * Mirrors the acymailing task app's getCustomFields(): active fields except the built-in
     * email field (id 2), with the ACYM_NAME/ACYM_LANGUAGE name→tag remap the app applies. The
     * real field type is kept only so language fields can be defaulted to "auto"; matching itself
     * ignores the type (see withAcyMailingSchema). Tests override this method with canned fields.
     */
    protected function fetchAcyMailingFields()
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['name', 'type', 'required']))
            ->from($this->db->quoteName('#__acym_field'))
            ->where($this->db->quoteName('active') . ' = 1')
            ->where($this->db->quoteName('id') . ' <> 2')
            ->order($this->db->quoteName('ordering') . ' ASC');

        $this->db->setQuery($query);

        $fields = [];

        foreach ($this->db->loadObjectList() as $field)
        {
            $tag = $field->name;

            if ($field->name === 'ACYM_NAME')
            {
                $tag = 'Name';
            }
            elseif ($field->name === 'ACYM_LANGUAGE')
            {
                $tag = 'Language';
            }

            $fields[] = [
                'key' => $tag,
                'label' => $tag,
                'required' => (int) $field->required === 1,
                'type' => strtolower((string) $field->type)
            ];
        }

        return $fields;
    }

    /**
     * Runs the target app's own connection test against the given credentials — the same check as
     * the "Test connection" button in the Task UI. Returns an empty string when the connection is
     * valid (or the app has no connection to test), or an error message when it fails. This is the
     * authoritative auth check: a schema fetch can silently succeed with a wrong key (some services
     * return an empty result rather than erroring), whereas testConnection inspects the API's own
     * success flag. Overridden in tests.
     */
    protected function testAppConnection($app, $connection)
    {
        $cacheKey = $app . '|' . $this->connectionKey($connection);

        if (array_key_exists($cacheKey, $this->connectionTestCache))
        {
            return $this->connectionTestCache[$cacheKey];
        }

        return $this->connectionTestCache[$cacheKey] = $this->runAppConnectionTest($app, $connection);
    }

    private function runAppConnectionTest($app, $connection)
    {
        try
        {
            $instance = Apps::getApp($app);

            if (!method_exists($instance, 'testConnection'))
            {
                return '';
            }

            if ($instance->testConnection($connection))
            {
                return '';
            }

            $error = method_exists($instance, 'getError') ? (string) $instance->getError() : '';

            return $error !== '' ? $error : Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_CONNECTION_TEST_FAILED');
        }
        catch (\Throwable $e)
        {
            return $e->getMessage() ?: Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_CONNECTION_TEST_FAILED');
        }
    }

    protected function withoutConnection($app, $options, $fields = [])
    {
        if ($app === 'salesforcewebtolead' && !$this->getValue($options, 'org_id'))
        {
            return ['skip' => Text::_('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_SKIP_CREDENTIALS')];
        }

        $options = $this->cleanOptions($options);

        if ($missing = $this->missingRequiredOptions($app, $options))
        {
            return ['skip' => Text::sprintf('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_SKIP_REQUIRED_SETTING', $missing)];
        }

        return [
            'app' => $app,
            'action' => 'subscribe',
            'connection' => [],
            'options' => $options,
            'mapping' => $this->mappingFromPackedOptions($fields, $options)
        ];
    }

    /**
     * Mapping diagnostics for an app with no remote schema to diff against, derived from the
     * options that were actually packed rather than assumed.
     *
     * A field is only counted as mapped when its tag reached the options, either promoted to a
     * known option or carried in customfields. knownOptions() matches field names exactly while
     * keyValues() excludes several spellings of the same name, so a field named "firstName" is
     * promoted by neither and excluded from customfields: it is dropped, and must be reported as
     * unmapped rather than counted.
     *
     * @param   array  $fields   The form's fields, keyed by name.
     * @param   array  $options  The packed task options.
     *
     * @return  array
     */
    private function mappingFromPackedOptions($fields, $options)
    {
        $packed = [];

        foreach ($options as $key => $value)
        {
            if ($key === 'customfields')
            {
                foreach ((array) $value as $item)
                {
                    if (isset($item['value']))
                    {
                        $packed[$item['value']] = true;
                    }
                }

                continue;
            }

            if (is_string($value) && $value !== '')
            {
                $packed[$value] = true;
            }
        }

        $mapped = 0;
        $unmapped = [];

        foreach ($fields as $name => $field)
        {
            if (strtolower($name) === 'email')
            {
                continue;
            }

            if (isset($packed[$field['tag']]))
            {
                $mapped++;
                continue;
            }

            $unmapped[] = $name;
        }

        return [
            'mapping_mode' => $unmapped ? ($mapped ? 'partial' : 'none') : 'full',
            'mapped_count' => $mapped,
            'unmapped_fields' => $unmapped,
            'required_unmapped_fields' => []
        ];
    }

    private function missingRequiredOptions($app, $options)
    {
        $required = [
            'activecampaign' => ['list'],
            'acymailing' => ['list'],
            'campaignmonitor' => ['list'],
            'drip' => ['list'],
            'elasticemail' => ['list'],
            'getresponse' => ['list'],
            'icontact' => ['list'],
            'mailchimp' => ['audience'],
            'brevo' => ['list'],
            'salesforcewebtolead' => ['org_id']
        ];

        if (!isset($required[$app]))
        {
            return null;
        }

        foreach ($required[$app] as $field)
        {
            if (!isset($options[$field]) || $options[$field] === '' || $options[$field] === [])
            {
                return $field;
            }
        }

        return null;
    }

    private function ensureConnection($campaign, $migration)
    {
        if (empty($migration['connection']))
        {
            return null;
        }

        $app = $migration['app'];
        $key = $this->connectionKey($migration['connection']);

        if (!isset($this->connectionCache[$app]))
        {
            $this->connectionCache[$app] = $this->getConnectionCache($app);
        }

        if (isset($this->connectionCache[$app][$key]))
        {
            $this->stats['connections_reused']++;
            return $this->connectionCache[$app][$key];
        }

        $title = $this->connectionTitle($app, $campaign['name']);
        $connectionId = Connections::add($app, $title, $migration['connection']);

        $this->connectionCache[$app][$key] = (int) $connectionId;
        $this->stats['connections_created']++;

        return (int) $connectionId;
    }

    private function getConnectionCache($app)
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['id', 'params']))
            ->from($this->db->quoteName('#__convertforms_connections'))
            ->where($this->db->quoteName('app') . ' = ' . $this->db->quote($app));

        $this->db->setQuery($query);

        $cache = [];

        foreach ($this->db->loadAssocList() as $connection)
        {
            $params = $this->decodeJson($connection['params']);

            $cache[$this->connectionKey($params)] = (int) $connection['id'];
        }

        return $cache;
    }

    protected function prepareTask($form, $campaign, $migration, $connectionId, $existingTasks)
    {
        return [
            'form_id' => (int) $form['id'],
            'title' => $this->taskTitle($campaign['name']),
            'state' => 1,
            'action' => $migration['action'],
            'app' => $migration['app'],
            'trigger' => 'onNewSubmission',
            'connection_id' => $connectionId ?: null,
            'options' => $migration['options'],
            'conditions' => [
                'enabled' => false,
                'conditions' => []
            ],
            'silentfail' => 0,
            'ordering' => $this->getNextOrdering($existingTasks)
        ];
    }

    protected function insertTask($task)
    {
        $row = (object) [
            'form_id' => $task['form_id'],
            'title' => $task['title'],
            'state' => $task['state'],
            'action' => $task['action'],
            'app' => $task['app'],
            'trigger' => $task['trigger'],
            'connection_id' => $task['connection_id'],
            'options' => json_encode($task['options']),
            'conditions' => json_encode($task['conditions']),
            'silentfail' => $task['silentfail'],
            'created' => $this->now(),
            'created_by' => $this->currentUserId(),
            'ordering' => $task['ordering']
        ];

        $this->db->insertObject('#__convertforms_tasks', $row);
    }

    protected function now()
    {
        return Factory::getDate()->toSql();
    }

    protected function currentUserId()
    {
        return (int) Factory::getUser()->id;
    }

    private function findEquivalentTask($existingTasks, $task)
    {
        foreach ($existingTasks as $existingTask)
        {
            if ($existingTask['app'] !== $task['app'] || $existingTask['action'] !== $task['action'] || $existingTask['trigger'] !== $task['trigger'])
            {
                continue;
            }

            if ((int) $existingTask['connection_id'] !== (int) $task['connection_id'])
            {
                continue;
            }

            if ($this->canonical($existingTask['options']) !== $this->canonical($task['options']))
            {
                continue;
            }

            return true;
        }

        return false;
    }

    private function moveFormToNoSyncCampaign($form)
    {
        $noSyncCampaignId = $this->getNoSyncCampaignId();

        if ((int) $form['campaign_id'] === (int) $noSyncCampaignId)
        {
            return;
        }

        $params = $form['params'];
        $params['campaign'] = (string) $noSyncCampaignId;

        $row = (object) [
            'id' => (int) $form['id'],
            'params' => json_encode($params, JSON_UNESCAPED_UNICODE)
        ];

        $this->db->updateObject('#__convertforms', $row, 'id');
        $this->stats['forms_updated']++;
    }

    private function getNoSyncCampaignId()
    {
        if ($this->noSyncCampaignId)
        {
            return $this->noSyncCampaignId;
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__convertforms_campaigns'))
            ->where('(' . $this->db->quoteName('service') . ' = ' . $this->db->quote('0') . ' OR ' . $this->db->quoteName('service') . ' = ' . $this->db->quote('') . ' OR ' . $this->db->quoteName('service') . ' IS NULL)')
            ->where($this->db->quoteName('state') . ' = 1')
            ->order($this->db->quoteName('id') . ' ASC');

        $this->db->setQuery($query, 0, 1);

        if ($id = (int) $this->db->loadResult())
        {
            $this->noSyncCampaignId = $id;
            return $this->noSyncCampaignId;
        }

        $campaign = (object) [
            'name' => self::NO_SYNC_CAMPAIGN_NAME,
            'state' => 1,
            'created' => $this->now(),
            'ordering' => 0,
            'service' => '0',
            'params' => '{}'
        ];

        $this->db->insertObject('#__convertforms_campaigns', $campaign);
        $this->noSyncCampaignId = (int) $this->db->insertid();

        return $this->noSyncCampaignId;
    }

    private function isNoSyncService($service)
    {
        return is_null($service) || $service === '' || (string) $service === '0';
    }

    private function getFieldTags($params)
    {
        $fields = isset($params['fields']) && is_array($params['fields']) ? $params['fields'] : [];
        $result = [];

        foreach ($fields as $field)
        {
            if (!is_array($field) || empty($field['name']))
            {
                continue;
            }

            $result[$field['name']] = [
                'type' => isset($field['type']) ? $field['type'] : '',
                'tag' => '{field.' . $field['name'] . '}'
            ];
        }

        return $result;
    }

    private function getEmailTag($params, $fields)
    {
        foreach ($fields as $name => $field)
        {
            if (strtolower($name) === 'email')
            {
                return $field['tag'];
            }
        }

        foreach ($fields as $field)
        {
            if ($field['type'] === 'email')
            {
                return $field['tag'];
            }
        }

        return null;
    }

    private function countCustomFields($fields)
    {
        $count = 0;

        foreach (array_keys($fields) as $name)
        {
            if (strtolower($name) === 'email')
            {
                continue;
            }

            $count++;
        }

        return $count;
    }

    private function findFieldTag($fields, $names)
    {
        $lookup = [];

        foreach ($fields as $name => $field)
        {
            $lookup[strtolower($name)] = $field['tag'];
        }

        foreach ($names as $name)
        {
            $key = strtolower($name);

            if (isset($lookup[$key]))
            {
                return $lookup[$key];
            }
        }

        return '';
    }

    private function knownOptions($fields, $names)
    {
        $options = [];

        foreach ($names as $name)
        {
            $options[$name] = $this->findFieldTag($fields, [$name]);
        }

        return $options;
    }

    /**
     * Promotes the form's fields onto an app's fixed set of named options, reporting which form
     * fields were consumed so the custom-field sweep can exclude exactly those.
     *
     * Matching goes through fieldAliases(), so "firstName" and "First Name" both reach first_name.
     * The consumed list is what keeps promotion and the sweep in step: a hardcoded exclusion list
     * drops any spelling it names but promotion does not recognise, and sends twice any spelling
     * promotion recognises but it does not name.
     *
     * @param   array   $fields     The form's fields, keyed by name.
     * @param   array   $names      The app's option names, in the app's own spelling.
     * @param   string  $emailTag   Tag of the field already sent as the email, excluded from the sweep.
     *
     * @return  array               ['options' => name => tag, 'consumed' => form field names]
     */
    private function promoteKnownFields($fields, $names, $emailTag = '')
    {
        $lookup = [];

        foreach ($fields as $name => $field)
        {
            $lookup[$this->normalizeMatchKey($name)] = $name;
        }

        $options  = [];
        $consumed = [];

        foreach ($fields as $name => $field)
        {
            if ($emailTag !== '' && $field['tag'] === $emailTag)
            {
                $consumed[] = $name;
            }
        }

        foreach ($names as $target)
        {
            foreach ($this->fieldAliases($target) as $alias)
            {
                if (!isset($lookup[$alias]))
                {
                    continue;
                }

                $formName = $lookup[$alias];

                if (in_array($formName, $consumed))
                {
                    break;
                }

                $options[$target] = $fields[$formName]['tag'];
                $consumed[] = $formName;
                break;
            }
        }

        return ['options' => $options, 'consumed' => $consumed];
    }

    private function keyValues($fields, $exclude)
    {
        $exclude = array_map('strtolower', $exclude);
        $items = [];

        foreach ($fields as $name => $field)
        {
            if (in_array(strtolower($name), $exclude))
            {
                continue;
            }

            $items[] = [
                'key' => $name,
                'value' => $field['tag']
            ];
        }

        return $items;
    }

    private function repeatSelect($value)
    {
        return $value ? [['value' => $value]] : [];
    }

    private function cleanOptions($options)
    {
        foreach ($options as $key => $value)
        {
            if (is_null($value))
            {
                $options[$key] = '';
            }
        }

        return $options;
    }

    private function decodeJson($value, $default = [])
    {
        if (!is_string($value) || $value === '')
        {
            return $default;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $default;
    }

    private function boolString($value, $default = '0')
    {
        if ($value === '' || is_null($value))
        {
            return (string) $default;
        }

        return ((string) $value === '1' || $value === true) ? '1' : '0';
    }

    private function getValue($data, $key, $default = '')
    {
        return array_key_exists($key, $data) ? $data[$key] : $default;
    }

    private function connectionKey($params)
    {
        unset($params['title'], $params['id']);
        ksort($params);

        return md5(json_encode($params));
    }

    private function canonical($value)
    {
        $this->sortRecursive($value);
        return json_encode($value);
    }

    private function sortRecursive(&$value)
    {
        if (!is_array($value))
        {
            return;
        }

        foreach ($value as &$item)
        {
            $this->sortRecursive($item);
        }

        if ($this->isAssoc($value))
        {
            ksort($value);
        }
    }

    private function isAssoc($array)
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function getNextOrdering($existingTasks)
    {
        $ordering = -1;

        foreach ($existingTasks as $task)
        {
            $ordering = max($ordering, (int) $task['ordering']);
        }

        return $ordering + 1;
    }

    private function taskTitle($campaignName)
    {
        return substr(Text::sprintf('COM_CONVERTFORMS_CAMPAIGNS_TO_TASKS_TASK_TITLE', $campaignName), 0, 100);
    }

    private function connectionTitle($app, $campaignName)
    {
        return substr(ucfirst($app) . ' - ' . $campaignName, 0, 50);
    }

    /**
     * Records a skipped form, optionally re-pointing it to the No Sync campaign.
     *
     * Only forms that can never be migrated (unsupported/unknown, discontinued, Sendinblue v2)
     * are moved off their legacy campaign — those are dead ends with nothing to review. Skips
     * that "need review" (missing credentials, unavailable schema, disabled plugin, connection
     * failure, no email field) keep their campaign so the admin can fix the cause and re-run.
     */
    private function skip($form, $campaign, $reason, $move = false, $docs = '')
    {
        $this->stats['skipped']++;

        if ($move)
        {
            $this->moveFormToNoSyncCampaign($form);
        }

        $report = $this->report($form, $campaign, 'skipped', $reason);
        $report['docs'] = $docs;

        return $report;
    }

    private function report($form, $campaign, $status, $reason)
    {
        return [
            'status' => $status,
            'form_id' => $form['id'],
            'form_name' => $form['name'],
            'campaign_id' => $campaign ? (int) $campaign['id'] : $form['campaign_id'],
            'campaign_name' => $campaign ? $campaign['name'] : '',
            'reason' => $reason,
            'mapping_mode' => 'none',
            'mapped_count' => 0,
            'unmapped_fields' => [],
            'required_unmapped_fields' => [],
            'warning' => '',
            'docs' => ''
        ];
    }

    private function getRemoteSchema($app, $connection, $options, $campaign)
    {
        $matrix = $this->getProviderMatrix();

        if (!isset($matrix[$app]))
        {
            return ['fields' => []];
        }

        $target = '';

        if ($app === 'mailchimp')
        {
            $target = (string) $this->getValue($options, 'audience', $this->getValue($campaign, 'list'));
        }
        elseif ($app === 'campaignmonitor')
        {
            $target = (string) $this->getValue($options, 'list', $this->getValue($campaign, 'list'));
        }

        $cacheKey = md5($app . '|' . json_encode($connection) . '|' . $target);

        if (isset($this->schemaCache[$cacheKey]))
        {
            return $this->schemaCache[$cacheKey];
        }

        try
        {
            $this->schemaCache[$cacheKey] = ['fields' => $this->fetchRemoteSchema($app, $connection, $target)];
        }
        catch (\Throwable $e)
        {
            $this->schemaCache[$cacheKey] = ['error' => $e->getMessage()];
        }

        return $this->schemaCache[$cacheKey];
    }

    /**
     * Fetches the remote custom-field schema of a service.
     *
     * Every field is normalized to ['key', 'label', 'required', 'type'] where `key` is the
     * exact string the target Task app must store so its runtime custom-field handling
     * matches it. Tests override this method with canned schemas.
     *
     * @throws \Throwable on any API failure
     */
    protected function fetchRemoteSchema($app, $connection, $target)
    {
        $fields = [];

        if ($app === 'mailchimp')
        {
            $api = new MailChimp($connection['api_key']);
            $result = $api->get('/lists/' . $target . '/merge-fields');
            $items = isset($result['merge_fields']) && is_array($result['merge_fields']) ? $result['merge_fields'] : [];

            foreach ($items as $item)
            {
                $fields[] = [
                    'key' => strtoupper((string) $item['tag']),
                    'label' => (string) $item['name'],
                    'required' => !empty($item['required']),
                    'type' => strtolower((string) $item['type'])
                ];
            }
        }
        elseif ($app === 'hubspot')
        {
            $api = new HubSpot3($connection['api_key']);
            $result = $api->get('properties/Contact');
            $items = isset($result['results']) && is_array($result['results']) ? $result['results'] : [];

            foreach ($items as $item)
            {
                $fields[] = [
                    // The framework validates task option keys against the property
                    // name case-sensitively, so emit it untouched.
                    'key' => (string) $item['name'],
                    'label' => (string) $item['label'],
                    'required' => false,
                    'type' => strtolower((string) $item['type'])
                ];
            }
        }
        elseif ($app === 'brevo')
        {
            $api = new Brevo($connection['api_key']);
            $result = $api->get('contacts/attributes');
            $items = isset($result['attributes']) && is_array($result['attributes']) ? $result['attributes'] : [];

            foreach ($items as $item)
            {
                $fields[] = [
                    'key' => strtoupper((string) $item['name']),
                    'label' => (string) $item['name'],
                    'required' => false,
                    'type' => strtolower((string) ($item['type'] ?? ''))
                ];
            }
        }
        elseif ($app === 'activecampaign')
        {
            $api = new ActiveCampaign([
                'endpoint' => $connection['api_url'],
                'api' => $connection['api_key']
            ]);
            $items = $api->getAllCustomFields();

            if (is_array($items))
            {
                foreach ($items as $key => $item)
                {
                    $fields[] = [
                        'key' => (string) $key,
                        'label' => isset($item['title']) ? (string) $item['title'] : (string) $key,
                        'required' => false,
                        'type' => 'text'
                    ];
                }
            }
        }
        elseif ($app === 'drip')
        {
            // The Drip integration keys on 'api' (not 'api_key') and only sets its endpoint when
            // that key is present — mirror the drip app's getApiOrDie() exactly.
            $api = new Drip([
                'api' => $connection['api_key'],
                'account_id' => $connection['account_id']
            ]);
            $result = $api->get('custom_field_identifiers');
            $items = isset($result['custom_field_identifiers']) && is_array($result['custom_field_identifiers']) ? $result['custom_field_identifiers'] : [];

            // The Drip app seeds these standard keys in its own dropdown and the framework
            // promotes them to subscriber attributes, so a form field named after one of
            // them must be mappable even when no matching custom field exists yet.
            $items = array_values(array_unique(array_merge(
                ['address1', 'address2', 'city', 'state', 'zip', 'country', 'phone'],
                $items
            )));

            foreach ($items as $item)
            {
                $fields[] = [
                    'key' => (string) $item,
                    'label' => (string) $item,
                    'required' => false,
                    'type' => 'text'
                ];
            }
        }
        elseif ($app === 'getresponse')
        {
            $api = new GetResponse($connection['api_key']);
            $items = $api->get('custom-fields');

            if (is_array($items))
            {
                foreach ($items as $item)
                {
                    if (isset($item['hidden']) && $item['hidden'] === 'true')
                    {
                        continue;
                    }

                    $fields[] = [
                        'key' => (string) $item['name'],
                        'label' => (string) $item['name'],
                        'required' => false,
                        'type' => strtolower((string) $item['fieldType'])
                    ];
                }
            }
        }
        elseif ($app === 'icontact')
        {
            // The iContact integration takes a single options array (appID/username/appPassword),
            // not positional args — mirror the icontact app's getApiOrDie() exactly.
            $api = new iContact([
                'appID' => $connection['api_app_id'],
                'username' => $connection['api_username'],
                'appPassword' => $connection['api_password']
            ]);
            $result = $api->get($api->accountID . '/c/' . $api->clientFolderID . '/customfields');
            $items = isset($result['customfields']) ? $result['customfields'] : [];

            foreach ($items as $item)
            {
                $fields[] = [
                    'key' => (string) $item['customFieldId'],
                    'label' => (string) $item['publicName'],
                    'required' => false,
                    'type' => 'text'
                ];
            }
        }
        elseif ($app === 'campaignmonitor')
        {
            $api = new CampaignMonitor($connection['api_key']);

            // Same per-list endpoint the framework's own validateCustomFields() reads. The
            // framework matches task option keys against FieldName case-sensitively, so
            // FieldName (not the bracketed Key) must be both key and label.
            $items = $api->get('lists/' . $target . '/customfields.json');

            foreach ((array) $items as $item)
            {
                if (!isset($item['FieldName']))
                {
                    continue;
                }

                $fields[] = [
                    'key' => (string) $item['FieldName'],
                    'label' => (string) $item['FieldName'],
                    'required' => false,
                    'type' => isset($item['DataType']) ? strtolower((string) $item['DataType']) : 'text'
                ];
            }
        }
        elseif ($app === 'elasticemail')
        {
            $api = new ElasticEmail($connection['api_key']);
            $result = $api->get('account/load?api_key=' . $connection['api_key']);
            $items = isset($result['data']['publicAccount']['customFields']) ? $result['data']['publicAccount']['customFields'] : [];

            if (is_array($items))
            {
                foreach ($items as $item)
                {
                    $fields[] = [
                        'key' => (string) $item['name'],
                        'label' => (string) $item['name'],
                        'required' => false,
                        'type' => 'text'
                    ];
                }
            }
        }
        elseif ($app === 'kit')
        {
            // Kit only supports v4 API keys; with a legacy ConvertKit v3 key this call
            // fails and the caller turns the failure into a per-form skip message.
            $api = new Kit($connection['api_key']);
            $result = $api->get('custom_fields');
            $items = isset($result['custom_fields']) && is_array($result['custom_fields']) ? $result['custom_fields'] : [];

            foreach ($items as $item)
            {
                $fields[] = [
                    'key' => (string) $item['key'],
                    'label' => (string) $item['label'],
                    'required' => false,
                    'type' => 'text'
                ];
            }
        }

        return $fields;
    }

    private function getProviderMatrix()
    {
        return [
            'activecampaign' => ['schema_source' => 'remote', 'target_key' => 'list', 'container' => 'customfields'],
            'acymailing' => ['schema_source' => 'local', 'target_key' => 'list', 'container' => 'flat'],
            'campaignmonitor' => ['schema_source' => 'remote', 'target_key' => 'list', 'container' => 'customfields'],
            'drip' => ['schema_source' => 'remote', 'target_key' => 'list', 'container' => 'customfields'],
            'elasticemail' => ['schema_source' => 'remote', 'target_key' => 'list', 'container' => 'customfields'],
            'getresponse' => ['schema_source' => 'remote', 'target_key' => 'list', 'container' => 'custom_fields'],
            'hubspot' => ['schema_source' => 'remote', 'target_key' => 'list', 'container' => 'properties'],
            'icontact' => ['schema_source' => 'remote', 'target_key' => 'list', 'container' => 'customfields'],
            'kit' => ['schema_source' => 'remote', 'target_key' => '', 'container' => 'customfields'],
            'mailchimp' => ['schema_source' => 'remote', 'target_key' => 'audience', 'container' => 'flat'],
            'salesforcewebtolead' => ['schema_source' => 'none', 'target_key' => 'org_id', 'container' => 'customfields'],
            'brevo' => ['schema_source' => 'remote', 'target_key' => 'list', 'container' => 'properties']
        ];
    }

    /**
     * Normalizes a candidate matching key so both sides of the comparison share one format.
     */
    private function normalizeMatchKey($value)
    {
        return strtolower(str_replace([' ', '-', '.'], '_', (string) $value));
    }

    /**
     * Matches form fields against a remote schema.
     *
     * Matching is case- and separator-insensitive, but each mapped entry emits the schema's
     * exact key so case-sensitive services (Campaign Monitor, GetResponse, HubSpot) receive
     * the casing their runtime validation expects.
     */
    private function matchFormFieldsToSchema($fields, $schemaFields)
    {
        $schemaByKey = [];
        $schemaByLabel = [];

        foreach ($schemaFields as $field)
        {
            $schemaByKey[$this->normalizeMatchKey($field['key'])] = $field;
            $schemaByLabel[$this->normalizeMatchKey($field['label'])] = $field;
        }

        $mapped = [];
        $unmapped = [];

        foreach ($fields as $name => $field)
        {
            if (strtolower($name) === 'email')
            {
                continue;
            }

            $aliases = $this->fieldAliases($name);
            $candidate = null;

            foreach ($aliases as $alias)
            {
                if (isset($schemaByKey[$alias]))
                {
                    $candidate = $schemaByKey[$alias];
                    break;
                }

                if (isset($schemaByLabel[$alias]))
                {
                    $candidate = $schemaByLabel[$alias];
                    break;
                }
            }

            if (!$candidate || !$this->isTypeCompatible($field['type'], $candidate['type']))
            {
                $unmapped[] = $name;
                continue;
            }

            $mapped[] = [
                'remote_key' => $candidate['key'],
                'value' => $field['tag']
            ];
        }

        $requiredUnmapped = [];

        foreach ($schemaFields as $schemaField)
        {
            if (empty($schemaField['required']))
            {
                continue;
            }

            $isMapped = false;

            foreach ($mapped as $mappedField)
            {
                if (strtolower($mappedField['remote_key']) === strtolower($schemaField['key']))
                {
                    $isMapped = true;
                    break;
                }
            }

            if (!$isMapped)
            {
                $requiredUnmapped[] = $schemaField['key'];
            }
        }

        return [
            'mapped' => $mapped,
            'unmapped' => $unmapped,
            'required_unmapped' => $requiredUnmapped
        ];
    }

    private function buildMappedPayload($mapped, $container)
    {
        if ($container === 'flat')
        {
            $payload = [];
            foreach ($mapped as $item)
            {
                $payload[$item['remote_key']] = $item['value'];
            }
            return $payload;
        }

        $payload = [];
        foreach ($mapped as $item)
        {
            $payload[] = [
                'key' => $item['remote_key'],
                'value' => $item['value']
            ];
        }
        return $payload;
    }

    private function buildMappingDiagnostics($match, $fields)
    {
        $customTotal = $this->countCustomFields($fields);
        $mapped = count($match['mapped']);

        if ($customTotal === 0)
        {
            // Nothing to map means nothing was lost.
            $mode = 'full';
        }
        else
        {
            $mode = $mapped === 0 ? 'none' : ($mapped === $customTotal ? 'full' : 'partial');
        }

        return [
            'mapping_mode' => $mode,
            'mapped_count' => $mapped,
            'unmapped_fields' => array_values($match['unmapped']),
            'required_unmapped_fields' => array_values($match['required_unmapped'])
        ];
    }

    private function fieldAliases($name)
    {
        $base = $this->normalizeMatchKey($name);
        $aliases = [$base];
        $map = [
            'first_name' => ['fname', 'firstname', 'first name'],
            'last_name' => ['lname', 'lastname', 'last name'],
            'phone' => ['phone_number', 'mobile'],
            'company' => ['organization'],
            'zip' => ['postal_code', 'zipcode'],
            'zip_code' => ['zip', 'postal_code']
        ];

        if (isset($map[$base]))
        {
            $aliases = array_merge($aliases, $map[$base]);
        }

        return array_values(array_unique(array_map([$this, 'normalizeMatchKey'], $aliases)));
    }

    private function isTypeCompatible($formType, $remoteType)
    {
        $formType = strtolower((string) $formType);
        $remoteType = strtolower((string) $remoteType);

        if ($formType === '' || $remoteType === '')
        {
            return true;
        }

        if ($formType === 'email')
        {
            return in_array($remoteType, ['email', 'text', 'string']);
        }

        if (in_array($formType, ['number', 'range']))
        {
            return in_array($remoteType, ['number', 'float', 'integer', 'int']);
        }

        if (in_array($formType, ['date', 'datetime']))
        {
            return in_array($remoteType, ['date', 'datetime', 'text', 'string']);
        }

        return true;
    }
}
