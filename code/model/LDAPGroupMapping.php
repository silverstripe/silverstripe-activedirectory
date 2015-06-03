<?php
/**
 * Class LDAPGroupMapping
 *
 * An individual mapping of an LDAP group to a SilverStripe {@link Group}
 */
class LDAPGroupMapping extends DataObject {

	/**
	 * @var array
	 */
	private static $db = array(
		'DN' => 'Text', // the DN value of the LDAP object in AD, e.g. CN=Users,DN=playpen,DN=local
		'Scope' => 'Enum("Subtree,OneLevel","Subtree")' // the scope of the mapping
	);

	/**
	 * @var array
	 */
	private static $has_one = array(
		'Group' => 'Group'
	);

	/**
	 * @var array
	 */
	private static $summary_fields = array(
		'DN'
	);

	/**
	 * @var array
	 */
	private static $dependencies = array(
		'ldapService' => '%$LDAPService'
	);

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		$fields->removeByName('DN');

		// @todo This might be better represented as a field showing the directory nesting levels etc
		$field = new DropdownField('DN', _t('LDAPGroupMapping.LDAPGROUP', 'LDAP Group'));
		$field->setEmptyString(_t('LDAPGroupMapping.SELECTONE', 'Select one'));
		$groups = $this->ldapService->getGroups(true, array('dn', 'name'));
		if($groups) foreach($groups as $dn => $record) {
			$source[$dn] = sprintf('%s (%s)', $record['name'], $dn);
		}
		asort($source);
		$field->setSource($source);
		$fields->addFieldToTab('Root.Main', $field);

		$fields->removeByName('Scope');
		$fields->addFieldToTab(
			'Root.Main',
			new DropdownField('Scope', _t('LDAPGroupMapping.SCOPE', 'Scope'), array(
				'Subtree' => _t('LDAPGroupMapping.SUBTREE_DESCRIPTION', 'Users within this group and all nested groups within'),
				'OneLevel' => _t('LDAPGroupMapping.ONELEVEL_DESCRIPTION', 'Only users within this group'),
			))
		);

		return $fields;
	}

}
