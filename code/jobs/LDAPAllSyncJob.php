<?php
/**
 * Class LDAPAllSyncJob
 *
 * A {@link QueuedJob} job to sync all groups and members to the site using LDAP.
 * This doesn't do the actual sync work, but rather just triggers {@link LDAPGroupSyncTask} and
 * {@link LDAPMemberSyncTask}
 */
class LDAPAllSyncJob extends AbstractQueuedJob
{
    /**
     * If you specify this value in seconds, it tells the completed job to queue another of itself
     * x seconds ahead of time.
     *
     * @var mixed
     * @config
     */
    private static $regenerate_time = null;

    public function __construct()
    {
        // noop, but needed for QueuedJobsAdmin::createjob() to work
    }

    public function getJobType()
    {
        return QueuedJob::QUEUED;
    }

    public function getTitle()
    {
        return _t('LDAPAllSyncJob.SYNCTITLE', 'Sync all groups and users from Active Directory, and set mappings up.');
    }

    public function getSignature()
    {
        return md5(get_class($this));
    }

    public function validateRegenerateTime()
    {
        $regenerateTime = Config::inst()->get('LDAPAllSyncJob', 'regenerate_time');

        // don't allow this job to run less than every 15 minutes, as it could take a while.
        if ($regenerateTime !== null && $regenerateTime < 900) {
            throw new Exception('LDAPAllSyncJob::regenerate_time must be 15 minutes or greater');
        }
    }

    public function process()
    {
        $regenerateTime = Config::inst()->get('LDAPAllSyncJob', 'regenerate_time');
        if ($regenerateTime) {
            $this->validateRegenerateTime();

            $nextJob = Injector::inst()->create('LDAPAllSyncJob');
            singleton('QueuedJobService')->queueJob($nextJob, date('Y-m-d H:i:s', time() + $regenerateTime));
        }

        $task = Injector::inst()->create('LDAPGroupSyncTask');
        $task->run(null);

        $task = Injector::inst()->create('LDAPMemberSyncTask');
        $task->run(null);

        $this->isComplete = true;
    }
}
