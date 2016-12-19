<?php

namespace SilverStripe\ActiveDirectory\Extensions;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;

/**
 * Class LDAPGroupExtension
 *
 * Adds a field to map an LDAP group to a SilverStripe {@link Group}
 *
 * @package activedirectory
 */
class LDAPGroupExtension extends DataExtension
{
    /**
     * @var array
     */
    private static $db = [
        // Unique user identifier, same field is used by SAMLMemberExtension
        'GUID' => 'Varchar(50)',
        'DN' => 'Text',
        'LastSynced' => 'DBDatetime'
    ];

    /**
     * A SilverStripe group can have several mappings to LDAP groups.
     * @var array
     */
    private static $has_many = [
        'LDAPGroupMappings' => 'SilverStripe\\ActiveDirectory\\Model\\LDAPGroupMapping'
    ];

    /**
     * Add a field to the Group_Members join table so we can keep track
     * of Members added to a mapped Group.
     *
     * See {@link LDAPService::updateMemberFromLDAP()} for more details
     * on how this gets used.
     *
     * @var array
     */
    private static $many_many_extraFields = [
        'Members' => [
            'IsImportedFromLDAP' => 'Boolean'
        ]
    ];

    /**
     * {@inheritDoc}
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        // Add read-only LDAP metadata fields.
        $fields->addFieldToTab('Root.LDAP', ReadonlyField::create('GUID'));
        $fields->addFieldToTab('Root.LDAP', ReadonlyField::create('DN'));
        $fields->addFieldToTab(
            'Root.LDAP',
            ReadonlyField::create(
                'LastSynced',
                _t('LDAPGroupExtension.LASTSYNCED', 'Last synced')
            )
        );

        if ($this->owner->GUID) {
            $fields->replaceField('Title', ReadonlyField::create('Title'));
            $fields->replaceField('Description', ReadonlyField::create('Description'));
            // Surface the code which is normally hidden from the CMS user.
            $fields->addFieldToTab('Root.Members', ReadonlyField::create('Code'), 'Members');

            $message = _t(
                'LDAPGroupExtension.INFOIMPORTED',
                'This group is automatically imported from LDAP.'
            );
            $fields->addFieldToTab(
                'Root.Members',
                LiteralField::create(
                    'Info',
                    sprintf('<p class="message warning">%s</p>', $message)
                ),
                'Title'
            );

            $fields->addFieldToTab('Root.LDAP', ReadonlyField::create(
                'LDAPGroupMappingsRO',
                _t('LDAPGroupExtension.AUTOMAPPEDGROUPS', 'Automatically mapped LDAP Groups'),
                implode('; ', $this->owner->LDAPGroupMappings()->column('DN'))
            ));
        } else {
            $field = GridField::create(
                'LDAPGroupMappings',
                _t('LDAPGroupExtension.MAPPEDGROUPS', 'Mapped LDAP Groups'),
                $this->owner->LDAPGroupMappings()
            );
            $config = GridFieldConfig_RecordEditor::create();
            $config->getComponentByType('GridFieldAddNewButton')
                ->setButtonName(_t('LDAPGroupExtension.ADDMAPPEDGROUP', 'Add LDAP group mapping'));

            $field->setConfig($config);
            $fields->addFieldToTab('Root.LDAP', $field);
        }
    }

    /**
     * LDAPGroupMappings are inherently relying on groups and can be removed now.
     */
    public function onBeforeDelete()
    {
        foreach ($this->owner->LDAPGroupMappings() as $mapping) {
            $mapping->delete();
        }
    }
}
