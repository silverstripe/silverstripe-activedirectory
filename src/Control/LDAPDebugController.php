<?php

namespace SilverStripe\ActiveDirectory\Control;

use SilverStripe\ActiveDirectory\Model\LDAPGroupMapping;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Security\Group;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\View\ArrayData;

/**
 * Class LDAPDebugController
 *
 * This controller is used to debug the LDAP connection.
 *
 * @package activedirectory
 */
class LDAPDebugController extends ContentController
{
    /**
     * @var array
     */
    private static $allowed_actions = [
        'index',
    ];

    /**
     * @var array
     */
    private static $dependencies = [
        'ldapService' => '%$SilverStripe\\ActiveDirectory\\Services\\LDAPService'
    ];

    /**
     * @var LDAPService
     */
    public $ldapService;

    public function init()
    {
        parent::init();

        if (!Permission::check('ADMIN')) {
            Security::permissionFailure();
        }
    }

    /**
     * @param HTTPRequest $request
     *
     * @return string
     */
    public function index(HTTPRequest $request)
    {
        return $this->renderWith(['SilverStripe\\ActiveDirectory\\Control\\LDAPDebugController']);
    }

    public function Options()
    {
        $list = new ArrayList();
        foreach (Config::inst()->get('SilverStripe\\ActiveDirectory\\Model\\LDAPGateway', 'options') as $field => $value) {
            if ($field === 'password') {
                $value = '***';
            }

            $list->push(new ArrayData([
                'Name' => $field,
                'Value' => $value
            ]));
        }
        return $list;
    }

    public function UsersSearchLocations()
    {
        $locations = Config::inst()->get('SilverStripe\\ActiveDirectory\\Services\\LDAPService', 'users_search_locations');
        $list = new ArrayList();
        if ($locations) {
            foreach ($locations as $location) {
                $list->push(new ArrayData([
                    'Value' => $location
                ]));
            }
        } else {
            $list->push($this->Options()->find('Name', 'baseDn'));
        }

        return $list;
    }

    public function GroupsSearchLocations()
    {
        $locations = Config::inst()->get('SilverStripe\\ActiveDirectory\\Services\\LDAPService', 'groups_search_locations');
        $list = new ArrayList();
        if ($locations) {
            foreach ($locations as $location) {
                $list->push(new ArrayData([
                    'Value' => $location
                ]));
            }
        } else {
            $list->push($this->Options()->find('Name', 'baseDn'));
        }

        return $list;
    }

    public function DefaultGroup()
    {
        $code = Config::inst()->get('SilverStripe\\ActiveDirectory\\Services\\LDAPService', 'default_group');
        if ($code) {
            $group = Group::get()->filter('Code', $code)->limit(1)->first();
            if (!($group && $group->exists())) {
                return sprintf(
                    'WARNING: LDAPService.default_group configured with \'%s\' but there is no Group with that Code in the database!',
                    $code
                );
            } else {
                return sprintf('%s (Code: %s)', $group->Title, $group->Code);
            }
        }

        return null;
    }

    public function MappedGroups()
    {
        return LDAPGroupMapping::get();
    }

    public function Nodes()
    {
        $groups = $this->ldapService->getNodes(false);
        $list = new ArrayList();
        foreach ($groups as $record) {
            $list->push(new ArrayData([
                'DN' => $record['dn']
            ]));
        }
        return $list;
    }

    public function Groups()
    {
        $groups = $this->ldapService->getGroups(false);
        $list = new ArrayList();
        foreach ($groups as $record) {
            $list->push(new ArrayData([
                'DN' => $record['dn']
            ]));
        }
        return $list;
    }

    public function Users()
    {
        return count($this->ldapService->getUsers());
    }
}
