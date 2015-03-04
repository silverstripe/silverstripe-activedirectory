<?php
/**
 * Adds mappings from AD attributes to SilverStripe {@link Member} fields.
 */
class LDAPMemberExtension extends DataExtension {

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
	public function updateCMSFields(FieldList $fields) {
		$fields->replaceField('GUID', new ReadonlyField('GUID'));
		$fields->replaceField('IsImportedFromLDAP', new ReadonlyField('IsImportedFromLDAP', 'Is user imported from LDAP/AD?'));
		$fields->replaceField('IsExpired', new ReadonlyField('IsExpired', _t('Member.ISEXPIRED', 'Has user\'s LDAP/AD login expired?')));
		$fields->replaceField('LastSynced', new ReadonlyField('LastSynced', _t('Member.LASTSYNCED', 'Last synced')));
	}

	/**
	 * Triggered by {@link Member::logIn()} when successfully logged in,
	 * this will update the Member record from AD data.
	 */
	public function memberLoggedIn() {
		if($this->owner->GUID) {
			$this->ldapService->updateMemberFromLDAP($this->owner);
		}
	}

}
