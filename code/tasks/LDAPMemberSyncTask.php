<?php
/**
 * Sync all users from AD.
 */
class LDAPMemberSyncTask extends BuildTask {

	private static $dependencies = array(
		'ldapService' => '%$LDAPService'
	);

	public function getTitle() {
		return _t('LDAPMemberSyncJob.SYNCTITLE', 'Sync all users from Active Directory');
	}

	public function run($request) {
		// get all users from LDAP, but only get the attributes we need.
		// this is useful to avoid holding onto too much data in memory
		// especially in the case where getUser() would return a lot of users
		$users = $this->ldapService->getUsers(array_merge(
			array('objectguid', 'whenchanged', 'samaccountname', 'useraccountcontrol', 'memberof'),
			array_keys(Config::inst()->get('Member', 'ldap_field_mappings'))
		));

		$start = time();

		$count = 0;

		foreach($users as $data) {
			$member = Member::get()->filter('GUID', $data['objectguid'])->limit(1)->first();

			if(!($member && $member->exists())) {
				// create the initial Member with some internal fields
				$member = new Member();
				$member->GUID = $data['objectguid'];
				$member->write();

				$this->log(sprintf(
					'[+] Creating new Member (ID: %s, GUID: %s, sAMAccountName: %s)',
					$member->ID,
					$data['objectguid'],
					$data['samaccountname']
				));
			}

			// sync attributes from LDAP to the Member record
			// this is also responsible for putting the user into mapped groups
			try {
				$result = $this->ldapService->updateMemberFromLDAP($member, $data);
				if($result) {
					$this->log(sprintf(
						'- Synced Member "%s" (ID: %s, GUID: %s, sAMAccountName: %s)',
						$member->getName(),
						$member->ID,
						$data['objectguid'],
						$data['samaccountname']
					));
				} else {
					$this->log(sprintf(
						'- Skipped Member "%s", as already up to date (GUID: %s, sAMAccountName: %s)',
						$member->getName(),
						$member->ID,
						$data['objectguid'],
						$data['samaccountname']
					));
				}
			} catch(Exception $e) {
				$this->log($e->getMessage());
			}

			// cleanup object from memory
			$member->destroy();

			$count++;
		}

		// remove Member records that were previously imported, but no longer exist in the directory
		// NOTE: DB::query() here is used for performance and so we don't run out of memory
		foreach(DB::query('SELECT "ID", "GUID" FROM "Member" WHERE "IsImportedFromLDAP" = 1') as $record) {
			if(!isset($users[$record['GUID']])) {
				$member = Member::get()->byId($record['ID']);
				$member->delete();

				$this->log(sprintf(
					'[-] Removing Member "%s" (GUID: %s) that no longer exists in LDAP.',
					$member->getName(),
					$member->GUID
				));

				// cleanup object from memory
				$member->destroy();
			}
		}

		$end = time() - $start;

		$this->log(sprintf('Done. Processed %s records. Duration: %s seconds', $count, round($end, 0)));
	}

	protected function log($message) {
		$message = sprintf('[%s] ', date('Y-m-d H:i:s')) . $message;
		echo Director::is_cli() ? ($message . PHP_EOL) : ($message . '<br>');
	}

}
