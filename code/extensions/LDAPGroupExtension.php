<?php
/**
 * Class LDAPGroupExtension
 *
 * Adds a field to map an LDAP group to a SilverStripe {@link Group}
 */
class LDAPGroupExtension extends DataExtension {

	/**
	 * @var array
	 */
	private static $db = array(
		// Unique user identifier, same field is used by SAMLMemberExtension
		'GUID' => 'Varchar(50)',
		'IsImportedFromLDAP' => 'Boolean',
		'LastSynced' => 'SS_Datetime'
	);

	/**
	 * A SilverStripe group can have several mappings to LDAP groups.
	 * @var array
	 */
	private static $has_many = array(
		'LDAPGroupMappings' => 'LDAPGroupMapping'
	);

	/**
	 * Add a field to the Group_Members join table so we can keep track
	 * of Members added to a mapped Group.
	 *
	 * See {@link LDAPService::updateMemberFromLDAP()} for more details
	 * on how this gets used.
	 *
	 * @var array
	 */
	private static $many_many_extraFields = array(
		'Members' => array(
			'IsImportedFromLDAP' => 'Boolean'
		)
	);

	public function updateCMSFields(FieldList $fields) {
		$fields->addFieldToTab('Root.Members', new ReadonlyField('GUID'));
		$fields->addFieldToTab('Root.Members', new ReadonlyField('IsImportedFromLDAP', 'Is group imported from LDAP/AD?'));
		$fields->addFieldToTab('Root.Members', new ReadonlyField('LastSynced', _t('Group.LASTSYNCED', 'Last synced')));

		if ($this->owner->IsImportedFromLDAP) {
			$fields->addFieldToTab('Root.Members', new LiteralField(
				'Caution',
				'<p class="message warning">Caution: LDAP group mapping is maintained automatically on this group. ' .
					'Your modifications will be removed as soon as the sync task runs.</p>'
			));
		}

		$field = GridField::create(
			'LDAPGroupMappings',
			_t('LDAPGroupExtension.MAPPEDGROUPS', 'Mapped LDAP Groups'),
			$this->owner->LDAPGroupMappings()
		);
		$config = GridFieldConfig_RecordEditor::create();
		$config->getComponentByType('GridFieldAddNewButton')
			->setButtonName(_t('LDAPGroupExtension.ADDMAPPEDGROUP', 'Add LDAP group mapping'));

		$field->setConfig($config);
		$fields->addFieldToTab('Root.Members', $field);
	}

}
