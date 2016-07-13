<?php
/**
 * Class LDAPDebugController
 *
 * This controller is used to debug the LDAP connection.
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
        'ldapService' => '%$LDAPService'
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
     * @param SS_HTTPRequest $request
     *
     * @return string
     */
    public function index(\SS_HTTPRequest $request) {
        return $this->renderWith(['LDAPDebugController']);
    }

    public function Options()
    {
        $list = new ArrayList();
        foreach (Config::inst()->get('LDAPGateway', 'options') as $field => $value) {
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
        $locations = Config::inst()->get('LDAPService', 'users_search_locations');
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
        $locations = Config::inst()->get('LDAPService', 'groups_search_locations');
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
        $code = Config::inst()->get('LDAPService', 'default_group');
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
