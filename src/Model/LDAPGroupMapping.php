<?php

namespace SilverStripe\ActiveDirectory\Model;

use SilverStripe\Forms\DropdownField;
use SilverStripe\ORM\DataObject;

/**
 * Class LDAPGroupMapping
 *
 * An individual mapping of an LDAP group to a SilverStripe {@link Group}
 */
class LDAPGroupMapping extends DataObject
{
    /**
     * {@inheritDoc}
     * @var string
     */
    private static $table_name = 'LDAPGroupMapping';

    /**
     * @var array
     */
    private static $db = [
        'DN' => 'Text', // the DN value of the LDAP object in AD, e.g. CN=Users,DN=playpen,DN=local
        'Scope' => 'Enum("Subtree,OneLevel","Subtree")' // the scope of the mapping
    ];

    /**
     * @var array
     */
    private static $has_one = [
        'Group' => 'SilverStripe\\Security\\Group'
    ];

    /**
     * @var array
     */
    private static $summary_fields = [
        'DN'
    ];

    /**
     * @var array
     */
    private static $dependencies = [
        'ldapService' => '%$SilverStripe\\ActiveDirectory\\Services\\LDAPService'
    ];

    /**
     * {@inheritDoc}
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('DN');

        $field = DropdownField::create('DN', _t('LDAPGroupMapping.LDAPGROUP', 'LDAP Group'));
        $field->setEmptyString(_t('LDAPGroupMapping.SELECTONE', 'Select one'));
        $groups = $this->ldapService->getGroups(true, ['dn', 'name']);
        if ($groups) {
            foreach ($groups as $dn => $record) {
                $source[$dn] = sprintf('%s (%s)', $record['name'], $dn);
            }
        }
        asort($source);
        $field->setSource($source);
        $fields->addFieldToTab('Root.Main', $field);

        $fields->removeByName('Scope');
        $fields->addFieldToTab(
            'Root.Main',
            DropdownField::create('Scope', _t('LDAPGroupMapping.SCOPE', 'Scope'), [
                'Subtree' => _t(
                    'LDAPGroupMapping.SUBTREE_DESCRIPTION',
                    'Users within this group and all nested groups within'
                ),
                'OneLevel' => _t('LDAPGroupMapping.ONELEVEL_DESCRIPTION', 'Only users within this group'),
            ])
        );

        return $fields;
    }
}
