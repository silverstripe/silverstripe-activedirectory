## Mapping a SilverStripe Group to an Active Directory group

Go to the Security section of the CMS and select the Group tab. Select a group you'd like to
map an AD group to, then click "Add LDAP Group Mapping" button above the "Mapped LDAP Groups" table.

You will be presented with a dropdown containing all AD groups to map. Select one here.
The "Scope" field determines whether you would like to just sync users within that group, or users within
that group and all nested groups too.

You can map multiple AD groups to the SilverStripe group, if you repeat the same process above.

NOTE: The list of groups here is cached, with a default lifetime of 8 hours. You can clear and regenerate
the cache by adding `?flush=1` in the URL.

To change the cache lifetime, for example to make sure caches only last for an hour, a developer can set 
this the your `mysite/_config.php`:

	SS_Cache::set_lifetime('ldap', 3600);

## Syncing AD users to SilverStripe

Once you have mapped your AD groups to SilverStripe groups, you can inititate a sync
to bring over all AD users to SilverStripe, and put them into mapped groups.

Note that when a user logs in via single sign-on they will also be synced.

You can initiate a sync by going into the CMS "Jobs" section and creating a new LDAPMemberSyncJob
using the "Create job of type" dropdown. Once the job shows up in the table after you create it, you can
manually trigger the job by clicking the blue right arrow on the table row.

If you don't see the "Create job of type" dropdown, please ensure you are using the latest
version of the queuedjobs module.

Note that these built-in Windows accounts will not be synced:

 * "computer" account types, e.g. domain controller accounts
 * krbtgt (Key Distribution Center Service Account)
 * Guest
 * Administrator

## Syncing AD users on a schedule

You can schedule a job to run, then have it re-schedule itself so it runs again in the future, but some
configuration needs to be set to have it work.

To configure when the job should re-run itself, set the `LDAPMemberSyncJob.regenerate.time` configuration.
In this example, this configures the job to run every 8 hours:

	LDAPMemberSyncJob:
	  regenerate_time: 28800

Once the job runs, it will enqueue itself again, so it's effectively run on a schedule.
Keep in mind that you'll need to have `queuedjobs` setup on a cron so that it can automatically run those queued jobs.
See the [module docs](https://github.com/silverstripe-australia/silverstripe-queuedjobs) on how to configure that.

## Overcoming the 1000 users limit

Active Directory has a default max LDAP page size limit of 1000. This means if you have over 1000 users some
of them won't be imported. Unfortunately due to a missing paging feature with the LDAP PHP extension,
paging results is not currently possible. The workaround is to modify an LDAP policy `MaxPageSize` on your
Active Directory server using `ntdsutil.exe`:

	C:\Documents and Settings\username>ntdsutil.exe
	ntdsutil: ldap policies
	ldap policy: connections
	server connections: connect to server <yourservername>
	Binding to <yourservername> ...
	Connected to <yourservername> using credentials of locally logged on user.
	server connections: q
	ldap policy: show values
	
	Policy                          Current(New)
	
	MaxPoolThreads                  4
	MaxDatagramRecv                 1024
	MaxReceiveBuffer                10485760
	InitRecvTimeout                 120
	MaxConnections                  5000
	MaxConnIdleTime                 900
	MaxPageSize                     1000
	MaxQueryDuration                120
	MaxTempTableSize                10000
	MaxResultSetSize                262144
	MaxNotificationPerConn          5
	MaxValRange                     0
	
	ldap policy: set maxpagesize to 10000
	ldap policy: commit changes
	ldap policy: q
	ntdsutil: q

