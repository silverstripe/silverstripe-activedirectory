<?php
/**
 * Class LDAPMemberExtension
 *
 * Adds mappings from AD attributes to SilverStripe {@link Member} fields.
 */
class LDAPMemberExtension extends DataExtension
{
    /**
     * @var array
     */
    private static $db = array(
        // Unique user identifier, same field is used by SAMLMemberExtension
        'GUID' => 'Varchar(50)',
        'IsImportedFromLDAP' => 'Boolean',
        'IsExpired' => 'Boolean',
        'LastSynced' => 'SS_Datetime'
    );

    /**
     * These fields are used by {@link LDAPMemberSync} to map specific AD attributes
     * to {@link Member} fields.
     *
     * @var array
     * @config
     */
    private static $ldap_field_mappings = array(
        'givenname' => 'FirstName',
        'sn' => 'Surname',
        'mail' => 'Email'
    );

    /**
     * The location (relative to /assets) where to save thumbnailphoto data.
     * @var string
     * @config
     */
    private static $ldap_thumbnail_path = 'Uploads';

    /**
     * @var array
     */
    private static $dependencies = array(
        'ldapService' => '%$LDAPService'
    );

    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        // Redo LDAP metadata fields as read-only and move to LDAP tab.
        $ldapMetadata = array();
        $fields->replaceField('IsImportedFromLDAP', $ldapMetadata[] = new ReadonlyField(
            'IsImportedFromLDAP',
            _t('LDAPMemberExtension.ISIMPORTEDFROMLDAP', 'Is user imported from LDAP/AD?')
        ));
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

        if ($this->owner->IsImportedFromLDAP) {
            // Transform the automatically mapped fields into read-only.
            $mappings = Config::inst()->get('Member', 'ldap_field_mappings');
            foreach ($mappings as $ldap=>$ss) {
                $field = $fields->dataFieldByName($ss);
                if (!empty($field)) {
                    // This messes up the Member_Validator, preventing the record from saving :-(
                    // $field->setReadonly(true);
                    $field->setTitle($field->Title() . _t('LDAPMemberExtension.IMPORTEDFIELD', ' (imported)'));
                }
            }

            // Display alert message at the top.
            $message = _t(
                'LDAPMemberExtension.INFOIMPORTED',
                'This user is automatically imported from LDAP. ' .
                    'Manual changes to imported fields will be removed upon sync.'
            );
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

    /**
     * Triggered by {@link Member::logIn()} when successfully logged in,
     * this will update the Member record from AD data.
     */
    public function memberLoggedIn()
    {
        if ($this->owner->GUID) {
            $this->ldapService->updateMemberFromLDAP($this->owner);
        }
    }
}
