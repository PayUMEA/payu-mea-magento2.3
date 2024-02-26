<?php

namespace PayU\EasyPlus\Model\Api\Data;

interface GridInterface
{
    /**
     * Constants for keys of data array. Identical to the name of the getter in snake case.
     */
    const ENTITY_ID = 'entity_id';
    const INCREMENT_ID = 'increment_id';
    const LOCK = 'lock';
    const STATUS = 'status';
    const PROCESS_ID = 'process_id';

    const PROCESS_CLASS = 'process_class';
    const CREATED_AT = 'created_at';
    /**
     * Get EntityId.
     *
     * @return int
     */
    public function getEntityId();
    /**
     * Set EntityId.
     */
    public function setEntityId($entityId);
    /**
     * Get Increment Id.
     *
     * @return varchar
     */
    public function getIncrementId();
    /**
     * Set Increment Id.
     */
    public function setIncrementId($incrementId);
    /**
     * Get Lock.
     *
     * @return int
     */
    public function getLock();
    /**
     * Set Lock.
     */
    public function setLock($lock);
    /**
     * Get Status.
     *
     * @return string
     */
    public function getStatus();
    /**
     * Set Status.
     */
    public function setStatus($status);
    /**
     * Get Process Id.
     *
     * @return varchar
     */
    public function getProcessId();
    /**
     * Set Process Id.
     */
    public function setProcessId($processId);
    /**
     * Get Process Class.
     *
     * @return varchar
     */
    public function getProcessClass();
    /**
     * Set Process Class.
     */
    public function setProcessClass($processClass);
    /**
     * Get CreatedAt.
     *
     * @return varchar
     */
    public function getCreatedAt();
    /**
     * Set CreatedAt.
     */
    public function setCreatedAt($createdAt);
}
