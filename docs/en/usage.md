# CMS usage

This guide walks you through this module's features that are surfaced in the SilverStripe CMS.

## Mapping a SilverStripe Group to an Active Directory group

Go to the Security section of the CMS and select the Group tab. Select a group you'd like to map an AD group to, then click "Add LDAP Group Mapping" button above the "Mapped LDAP Groups" table.

You will be presented with a dropdown containing all AD groups to map. Select one here. The "Scope" field determines whether you would like to just sync users within that group, or users within
that group and all nested groups too.

You can map multiple AD groups to the SilverStripe group, if you repeat the same process above.

The group list is cached, so for a period of time stale entries can show up. See the [troubleshooting](troubleshooting.md) for details.

## Syncing AD users to SilverStripe

Once you have mapped your AD groups to SilverStripe groups, you can inititate a sync to bring over all AD users to SilverStripe, and put them into mapped groups.

Note that when a user logs in via single sign-on they will also be synced.

You can initiate a sync by going into the CMS "Jobs" section and creating a new LDAPMemberSyncJob using the "Create job of type" dropdown. Once the job shows up in the table after you create it, you can manually trigger the job by clicking the blue right arrow on the table row.

If you don't see the "Create job of type" dropdown, please ensure you are using the latest version of the queuedjobs module.

Note that these built-in Windows accounts will not be synced:

 * "computer" account types, e.g. domain controller accounts
 * krbtgt (Key Distribution Center Service Account)
 * Guest
 * Administrator

Depending on how the site has been configured by the developer, the job might re-schedule itself once completed. The relevant configuration details are in "Syncing AD users on a schedule" section of the [Developer guide](developer.md).

