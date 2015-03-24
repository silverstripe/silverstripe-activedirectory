<?php

/**
 * Class SAMLMemberExtension
 */
class SAMLMemberExtension extends DataExtension {

	/**
	 * @var array
	 */
	private static $db = array(
		// Pointer to the session object held by the IdP
		'SAMLSessionIndex' => 'Varchar(255)',
		// Unique user identifier, same field is used by LDAPMemberExtension
		'GUID' => 'Varchar(50)',
	);

	/**
	 * @param FieldList $fields
	 */
	public function updateCMSFields(FieldList $fields) {
		$fields->replaceField('GUID', new ReadonlyField('GUID'));
		$fields->removeFieldFromTab("Root", "SAMLSessionIndex");
	}

	/**
	 * Clear out SAML specific data
	 *
	 * @todo(stig): try to logout the user from the IdP if possible
	 */
	public function beforeMemberLoggedOut() {
		if(!$this->owner->SAMLSessionIndex) {
			return;
		}
		$this->owner->SAMLSessionIndex = null;
	}

}
