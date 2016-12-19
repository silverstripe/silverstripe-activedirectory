<?php

namespace SilverStripe\ActiveDirectory\Jobs;

use Exception;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\QueuedJobs\Services\AbstractQueuedJob;
use SilverStripe\QueuedJobs\Services\QueuedJob;

/**
 * Class LDAPMemberSyncJob
 *
 * A {@link QueuedJob} job to sync all users to the site using LDAP.
 * This doesn't do the actual sync work, but rather just triggers {@link LDAPMemberSyncTask}
 *
 * @package activedirectory
 */
class LDAPMemberSyncJob extends AbstractQueuedJob
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

    /**
     * @return string
     */
    public function getJobType()
    {
        return QueuedJob::QUEUED;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return _t('LDAPMemberSyncJob.SYNCTITLE', 'Sync all users from Active Directory');
    }

    /**
     * @return string
     */
    public function getSignature()
    {
        return md5(get_class($this));
    }

    /**
     * @throws Exception
     */
    public function validateRegenerateTime()
    {
        $regenerateTime = Config::inst()->get(
            'SilverStripe\\ActiveDirectory\\Jobs\\LDAPMemberSyncJob',
            'regenerate_time'
        );

        // don't allow this job to run less than every 15 minutes, as it could take a while.
        if ($regenerateTime !== null && $regenerateTime < 900) {
            throw new Exception('LDAPMemberSyncJob::regenerate_time must be 15 minutes or greater');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function process()
    {
        $regenerateTime = Config::inst()->get(
            'SilverStripe\\ActiveDirectory\\Jobs\\LDAPMemberSyncJob',
            'regenerate_time'
        );
        if ($regenerateTime) {
            $this->validateRegenerateTime();

            $nextJob = Injector::inst()->create('SilverStripe\\ActiveDirectory\\Jobs\\LDAPMemberSyncJob');
            singleton('QueuedJobService')->queueJob($nextJob, date('Y-m-d H:i:s', time() + $regenerateTime));
        }

        $task = Injector::inst()->create('SilverStripe\\ActiveDirectory\\Tasks\\LDAPMemberSyncTask');
        $task->run(null);

        $this->isComplete = true;
    }
}
