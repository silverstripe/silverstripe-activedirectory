<?php
/**
 * Class SAMLMemberExtension
 *
 * Adds mappings from IdP claim rules to SilverStripe {@link Member} fields.
 */
class SAMLMemberExtension extends DataExtension
{
    /**
     * @var array
     */
    private static $db = [
        // Pointer to the session object held by the IdP
        'SAMLSessionIndex' => 'Varchar(255)',
        // Unique user identifier, same field is used by LDAPMemberExtension
        'GUID' => 'Varchar(50)',
    ];

    /**
     * These are used by {@link SAMLController} to map specific IdP claim rules
     * to {@link Member} fields. Availability of these claim rules are defined
     * on the IdP.
     *
     * @var array
     * @config
     */
    private static $claims_field_mappings = [
        'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname' => 'FirstName',
        'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname' => 'Surname',
        'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => 'Email'
    ];

    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        $fields->replaceField('GUID', new ReadonlyField('GUID'));
        $fields->removeFieldFromTab('Root', 'SAMLSessionIndex');
    }
}
