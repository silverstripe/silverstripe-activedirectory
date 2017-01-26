<?php

namespace SilverStripe\ActiveDirectory\Tasks;

use Exception;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Member;

/**
 * Class LDAPMemberSyncTask
 *
 * A task to sync all users to the site using LDAP.
 *
 * @package activedirectory
 */
class LDAPMemberSyncTask extends BuildTask
{
    /**
     * {@inheritDoc}
     * @var string
     */
    private static $segment = 'LDAPMemberSyncTask';

    /**
     * @var array
     */
    private static $dependencies = [
        'ldapService' => '%$SilverStripe\\ActiveDirectory\\Services\\LDAPService'
    ];

    /**
     * Setting this to true causes the sync to delete any local Member
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
        return _t('LDAPMemberSyncJob.SYNCTITLE', 'Sync all users from Active Directory');
    }

    /**
     * {@inheritDoc}
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        // get all users from LDAP, but only get the attributes we need.
        // this is useful to avoid holding onto too much data in memory
        // especially in the case where getUser() would return a lot of users
        $users = $this->ldapService->getUsers(array_merge(
            ['objectguid', 'samaccountname', 'useraccountcontrol', 'memberof'],
            array_keys(Config::inst()->get('SilverStripe\\Security\\Member', 'ldap_field_mappings'))
        ));

        $start = time();

        $count = 0;

        foreach ($users as $data) {
            $member = Member::get()->filter('GUID', $data['objectguid'])->limit(1)->first();

            if (!($member && $member->exists())) {
                // create the initial Member with some internal fields
                $member = new Member();
                $member->GUID = $data['objectguid'];

                $this->log(sprintf(
                    'Creating new Member (GUID: %s, sAMAccountName: %s)',
                    $data['objectguid'],
                    $data['samaccountname']
                ));
            } else {
                $this->log(sprintf(
                    'Updating existing Member "%s" (ID: %s, GUID: %s, sAMAccountName: %s)',
                    $member->getName(),
                    $member->ID,
                    $data['objectguid'],
                    $data['samaccountname']
                ));
            }

            // Sync attributes from LDAP to the Member record. This will also write the Member record.
            // this is also responsible for putting the user into mapped groups
            try {
                $this->ldapService->updateMemberFromLDAP($member, $data);
            } catch (Exception $e) {
                $this->log($e->getMessage());
            }

            // cleanup object from memory
            $member->destroy();

            $count++;
        }

        // remove Member records that were previously imported, but no longer exist in the directory
        // NOTE: DB::query() here is used for performance and so we don't run out of memory
        if ($this->config()->destructive) {
            foreach (DB::query('SELECT "ID", "GUID" FROM "Member" WHERE "GUID" IS NOT NULL') as $record) {
                if (!isset($users[$record['GUID']])) {
                    $member = Member::get()->byId($record['ID']);
                    $member->delete();

                    $this->log(sprintf(
                        'Removing Member "%s" (GUID: %s) that no longer exists in LDAP.',
                        $member->getName(),
                        $member->GUID
                    ));

                    // cleanup object from memory
                    $member->destroy();
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
