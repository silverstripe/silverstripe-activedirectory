<?php
/**
 * Class LDAPMigrateExistingMembersTask
 *
 * Migrate existing Member records in SilverStripe into "LDAP Members"
 * by matching existing emails with ones that exist in AD.
 */
class LDAPMigrateExistingMembersTask extends BuildTask
{
    private static $dependencies = [
        'ldapService' => '%$LDAPService'
    ];

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

    protected function log($message)
    {
        $message = sprintf('[%s] ', date('Y-m-d H:i:s')) . $message;
        echo Director::is_cli() ? ($message . PHP_EOL) : ($message . '<br>');
    }
}
