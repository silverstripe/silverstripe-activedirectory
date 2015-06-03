<?php
/**
 * Class LDAPGroupExtension
 *
 * Adds a field to map an LDAP group to a SilverStripe {@link Group}
 */
class LDAPGroupExtension extends DataExtension {

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
