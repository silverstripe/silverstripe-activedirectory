<?php
/**
 * Class LDAPMemberExtension.
 *
 * Adds mappings from AD attributes to SilverStripe {@link Member} fields.
 */
class LDAPMemberExtension extends DataExtension
{
    /**
     * @var array
     */
    private static $db = [
        // Unique user identifier, same field is used by SAMLMemberExtension
        'GUID' => 'Varchar(50)',
        'Username' => 'Varchar(64)',
        'IsExpired' => 'Boolean',
        'LastSynced' => 'SS_Datetime',
    ];

    /**
     * These fields are used by {@link LDAPMemberSync} to map specific AD attributes
     * to {@link Member} fields.
     *
     * @var array
     * @config
     */
    private static $ldap_field_mappings = [
        'givenname' => 'FirstName',
        'samaccountname' => 'Username',
        'sn' => 'Surname',
        'mail' => 'Email',
    ];

    /**
     * The location (relative to /assets) where to save thumbnailphoto data.
     *
     * @var string
     * @config
     */
    private static $ldap_thumbnail_path = 'Uploads';

    /**
     * When enabled, LDAP managed Member records (GUID flag)
     * have their data written back to LDAP on write.
     *
     * This requires setting write permissions on the user configured in the LDAP
     * credentials, which is why this is disabled by default.
     *
     * @var bool
     * @config
     */
    private static $update_ldap_from_local = false;

    /**
     * If enabled, Member records are created in LDAP on write.
     *
     * This requires setting write permissions on the user configured in the LDAP
     * credentials, which is why this is disabled by default.
     *
     * @var bool
     * @config
     */
    private static $create_users_in_ldap = false;

    /**
     * If enabled, deleting Member records mapped to LDAP deletes the LDAP user.
     *
     * This requires setting write permissions on the user configured in the LDAP
     * credentials, which is why this is disabled by default.
     *
     * @var bool
     * @config
     */
    private static $delete_users_in_ldap = false;

    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        // Redo LDAP metadata fields as read-only and move to LDAP tab.
        $ldapMetadata = [];
        $fields->replaceField('GUID', $ldapMetadata[] = new ReadonlyField('GUID'));
        $fields->replaceField('IsExpired', $ldapMetadata[] = new ReadonlyField(
            'IsExpired',
            _t('LDAPMemberExtension.ISEXPIRED', 'Has user\'s LDAP/AD login expired?'))
        );
        $fields->replaceField('LastSynced', $ldapMetadata[] = new ReadonlyField(
            'LastSynced',
            _t('LDAPMemberExtension.LASTSYNCED', 'Last synced'))
        );
        $fields->addFieldsToTab('Root.LDAP', $ldapMetadata);

        $message = '';
        if ($this->owner->GUID && $this->owner->config()->update_ldap_from_local) {
            $message = _t(
                'LDAPMemberExtension.CHANGEFIELDSUPDATELDAP',
                'Changing fields here will update them in LDAP.'
            );
        } elseif ($this->owner->GUID && !$this->owner->config()->update_ldap_from_local) {
            // Transform the automatically mapped fields into read-only. This doesn't
            // apply if updating LDAP from local is enabled, as changing data locally can be written back.
            foreach ($this->owner->config()->ldap_field_mappings as $name) {
                $field = $fields->dataFieldByName($name);
                if (!empty($field)) {
                    // Set to readonly, but not disabled so that the data is still sent to the
                    // server and doesn't break Member_Validator
                    $field->setReadonly(true);
                    $field->setTitle($field->Title()._t('LDAPMemberExtension.IMPORTEDFIELD', ' (imported)'));
                }
            }
            $message = _t(
                'LDAPMemberExtension.INFOIMPORTED',
                'This user is automatically imported from LDAP. '.
                    'Manual changes to imported fields will be removed upon sync.'
            );
        }
        if ($message) {
            $fields->addFieldToTab(
                'Root.Main',
                new LiteralField(
                    'Info',
                    sprintf('<p class="message warning">%s</p>', $message)
                ),
                'FirstName'
            );
        }
    }

    public function validate(ValidationResult $validationResult)
    {
        // We allow empty Username for registration purposes, as we need to
        // create Member records with empty Username temporarily. Forms should explicitly
        // check for Username not being empty if they require it not to be.
        if (!$this->owner->config()->create_users_in_ldap) {
            return;
        }

        if (!preg_match('/^[a-z0-9\.]+$/', $this->owner->Username)) {
            $validationResult->error(
                'Username must only contain lowercase alphanumeric characters and dots.',
                'bad'
            );
            throw new ValidationException($validationResult);
        }
    }

    /**
     * Generate a username for the user based on their name and/or email
     * @return string
     */
    public function generateLDAPUsername()
    {
        $service = Injector::inst()->get('LDAPService');
        if (!$service->enabled()) {
            return;
        }

        if (!$this->owner->FirstName && !$this->owner->Surname && !$this->owner->Email) {
            throw new ValidationException('Please ensure first name, surname, and email are set');
        }

        // Prepare the base string based on first name and surname.
        $baseArray = [];
        if ($this->owner->FirstName) $baseArray[] = strtolower($this->owner->FirstName);
        if ($this->owner->Surname) $baseArray[] = strtolower($this->owner->Surname);
        $baseUsername = implode('.', $baseArray);

        // Fallback to the first part of email.
        if (!$baseUsername) $baseUsername = preg_replace('/@.*/', '', $this->owner->Email);

        // Sanitise so it passes LDAP validator.
        $baseUsername = preg_replace('/[^a-z0-9\.]/', '', $baseUsername);

        // Ensure uniqueness.
        $suffix = 0;
        $tryUsername = $baseUsername;
        while ($service->getUserByUsername($tryUsername)) {
            $suffix++;
            $tryUsername = sprintf('%s%d', $baseUsername, $suffix);
        }

        return $tryUsername;
    }

    /**
     * Create the user in LDAP, provided this configuration is enabled
     * and a username was passed to a new Member record.
     */
    public function onBeforeWrite()
    {
        $service = Injector::inst()->get('LDAPService');
        if (
            !$service->enabled() ||
            !$this->owner->config()->create_users_in_ldap ||
            $this->owner->GUID
        ) {
            return;
        }

        // If a username wasn't provided, generate one
        if (!$this->owner->Username) {
            $this->owner->Username = $this->generateLDAPUsername();
        }

        $service->createLDAPUser($this->owner);
    }

    /**
     * Update the local data with LDAP, and ensure local membership is also set in
     * LDAP too. This writes into LDAP, provided that feature is enabled.
     */
    public function onAfterWrite()
    {
        $service = Injector::inst()->get('LDAPService');
        if (
            !$service->enabled() ||
            !$this->owner->config()->update_ldap_from_local ||
            !$this->owner->GUID
        ) {
            return;
        }

        $service->updateLDAPFromMember($this->owner);
        $service->updateLDAPGroupsForMember($this->owner);
    }

    public function onAfterDelete() {
        $service = Injector::inst()->get('LDAPService');
        if (
            !$service->enabled() ||
            !$this->owner->config()->delete_users_in_ldap ||
            !$this->owner->GUID
        ) {
            return;
        }

        $service->deleteLDAPMember($this->owner);
    }

    /**
     * Triggered by {@link Member::logIn()} when successfully logged in,
     * this will update the Member record from AD data.
     */
    public function memberLoggedIn()
    {
        if ($this->owner->GUID) {
            Injector::inst()->get('LDAPService')->updateMemberFromLDAP($this->owner);
        }
    }
}
