<?php

namespace SilverStripe\ActiveDirectory\Tasks;

use SilverStripe\Control\Director;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Group;

/**
 * Class LDAPGroupSyncTask
 *
 * A task to sync all groups to the site using LDAP.
 *
 * @package activedirectory
 */
class LDAPGroupSyncTask extends BuildTask
{
    /**
     * {@inheritDoc}
     * @var string
     */
    private static $segment = 'LDAPGroupSyncTask';

    /**
     * @var array
     */
    private static $dependencies = [
        'ldapService' => '%$SilverStripe\\ActiveDirectory\\Services\\LDAPService'
    ];

    /**
     * Setting this to true causes the sync to delete any local Group
     * records that were previously imported, but no longer existing in LDAP.
     *
     * @config
     * @var bool
     */
    private static $destructive = false;

    /**
     * @return string
     */
    public function getTitle()
    {
        return _t('LDAPGroupSyncJob.SYNCTITLE', 'Sync all groups from Active Directory');
    }

    /**
     * {@inheritDoc}
     * @var HTTPRequest $request
     */
    public function run($request)
    {
        // get all groups from LDAP, but only get the attributes we need.
        // this is useful to avoid holding onto too much data in memory
        // especially in the case where getGroups() would return a lot of groups
        $ldapGroups = $this->ldapService->getGroups(
            false,
            ['objectguid', 'samaccountname', 'dn', 'name', 'description'],
            // Change the indexing attribute so we can look up by GUID during the deletion process below.
            'objectguid'
        );

        $start = time();

        $count = 0;

        foreach ($ldapGroups as $data) {
            $group = Group::get()->filter('GUID', $data['objectguid'])->limit(1)->first();

            if (!($group && $group->exists())) {
                // create the initial Group with some internal fields
                $group = new Group();
                $group->GUID = $data['objectguid'];

                $this->log(sprintf(
                    'Creating new Group (ID: %s, GUID: %s, sAMAccountName: %s)',
                    $group->ID,
                    $data['objectguid'],
                    $data['samaccountname']
                ));
            } else {
                $this->log(sprintf(
                    'Updating existing Group "%s" (ID: %s, GUID: %s, sAMAccountName: %s)',
                    $group->getTitle(),
                    $group->ID,
                    $data['objectguid'],
                    $data['samaccountname']
                ));
            }

            $this->ldapService->updateGroupFromLDAP($group, $data);

            // cleanup object from memory
            $group->destroy();

            $count++;
        }

        // remove Group records that were previously imported, but no longer exist in the directory
        // NOTE: DB::query() here is used for performance and so we don't run out of memory
        if ($this->config()->destructive) {
            foreach (DB::query('SELECT "ID", "GUID" FROM "Group" WHERE "GUID" IS NOT NULL') as $record) {
                if (!isset($ldapGroups[$record['GUID']])) {
                    $group = Group::get()->byId($record['ID']);
                    // Cascade into mappings, just to clean up behind ourselves.
                    foreach ($group->LDAPGroupMappings() as $mapping) {
                        $mapping->delete();
                    }
                    $group->delete();

                    $this->log(sprintf(
                        'Removing Group "%s" (GUID: %s) that no longer exists in LDAP.',
                        $group->Title,
                        $group->GUID
                    ));

                    // cleanup object from memory
                    $group->destroy();
                }
            }
        }

        $end = time() - $start;

        $this->log(sprintf('Done. Processed %s records. Duration: %s seconds', $count, round($end, 0)));
    }

    /**
     * Sends a message, formatted either for the CLI or browser
     *
     * @param string $message
     */
    protected function log($message)
    {
        $message = sprintf('[%s] ', date('Y-m-d H:i:s')) . $message;
        echo Director::is_cli() ? ($message . PHP_EOL) : ($message . '<br>');
    }
}
