# CMS usage

This guide walks you through this module's features that are surfaced in the SilverStripe CMS.

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [SilverStripe to Active Directory group mapping](#silverstripe-to-active-directory-group-mapping)
  - [Manual](#manual)
  - [Automatic](#automatic)
- [Active Directory to SilverStripe synchronisation](#active-directory-to-silverstripe-synchronisation)
  - [Users only](#users-only)
  - [Groups and users](#groups-and-users)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->

## SilverStripe to Active Directory group mapping

### Manual

Go to the Security section of the CMS and select the Group tab. Select a group you'd like to map an AD group to, and select the "LDAP" tab. Then click "Add LDAP Group Mapping" button, above the "Mapped LDAP Groups" table.

You will be presented with a dropdown containing all AD groups to map. Select one here. The "Scope" field determines whether you would like to just sync users within that group, or users within
that group and all nested groups too.

You can map multiple AD groups to the SilverStripe group, if you repeat the same process above.

The group list is cached, so for a period of time stale entries can show up. See the [troubleshooting](troubleshooting.md) for details.

It is not possible to manually configure mappings on groups imported from LDAP. In such a case the mappings will appear as read-only.

### Automatic

Groups imported from LDAP will be mapped automatically.

## Active Directory to SilverStripe synchronisation

### Users only

You can initiate a sync by going into the CMS "Jobs" section and creating a new `LDAPMemberSyncJob` using the "Create job of type" dropdown. Once the job shows up in the table after you create it, you can manually trigger the job by clicking the blue right arrow on the table row.

If you don't see the "Create job of type" dropdown, please ensure you are using the latest version of the queuedjobs module.

Note that these built-in Windows accounts will not be synced:

 * "computer" account types, e.g. domain controller accounts
 * krbtgt (Key Distribution Center Service Account)
 * Guest
 * Administrator

If you have mapped SilverStripe Groups, the sync will automatically distribute Members into relevant Groups based on their LDAP memberships.

Depending on how the site has been configured by the developer, the job might re-schedule itself once completed. Also depending on configuration, the sync job may remove users which are no longer present in LDAP. The relevant configuration details are in "Syncing AD users on a schedule" section of the [Developer guide](developer.md).

Note that when a user logs in via single sign-on, sync will also be triggered.

### Groups and users

Similarly to "users only" sync, you can also schedule regular synchronisation of both groups and users. This will automatically synchronise groups, set up group mappings, synchronise users, and configure their memberships.

To synchronise both group and users, use `LDAPAllSyncJob` instead of `LDAPMemberSyncJob` mentioned in the "users only" documentation above.

Again, depending on how the site has been configured, the job might re-schedule itself. It may also be configured to purge groups and users that are no longer present in LDAP. The relevant configuration details are in "Syncing AD groups and users on a schedule" section of the [Developer guide](developer.md).
