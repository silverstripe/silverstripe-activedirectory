<?php

namespace SilverStripe\ActiveDirectory\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\Core\Convert;
use SilverStripe\Control\Director;
use SilverStripe\Security\Member;

/**
 * Class LDAPMigrateExistingMembersTask
 *
 * Migrate existing Member records in SilverStripe into "LDAP Members"
 * by matching existing emails with ones that exist in AD.
 *
 * @package activedirectory
 */
class LDAPMigrateExistingMembersTask extends BuildTask
{
    /**
     * {@inheritDoc}
     * @var string
     */
    private static $segment = 'LDAPMigrateExistingMembersTask';

    /**
     * @var array
     */
    private static $dependencies = [
        'ldapService' => '%$SilverStripe\\ActiveDirectory\\Services\\LDAPService'
    ];

    /**
     * {@inheritDoc}
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        $users = $this->ldapService->getUsers(['objectguid', 'mail']);
        $start = time();
        $count = 0;

        foreach ($users as $user) {
            // Empty mail attribute for the user, nothing we can do. Skip!
            if (empty($user['mail'])) {
                continue;
            }

            $member = Member::get()->where(
                sprintf('"Email" = \'%s\' AND "GUID" IS NULL', Convert::raw2sql($user['mail']))
            )->first();

            if (!($member && $member->exists())) {
                continue;
            }

            // Member was found, migrate them by setting the GUID field
            $member->GUID = $user['objectguid'];
            $member->write();

            $count++;

            $this->log(sprintf(
                'Migrated Member %s (ID: %s, Email: %s)',
                $member->getName(),
                $member->ID,
                $member->Email
            ));
        }

        $end = time() - $start;

        $this->log(sprintf('Done. Migrated %s Member records. Duration: %s seconds', $count, round($end, 0)));
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
