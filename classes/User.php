<?php

namespace Stanford\ExternalModuleDeployment;

/**
 * Class User
 * @package Stanford\ExternalModuleDeployment
 * @property array $record
 */
class User
{

    private $record;

    public function __construct($record)
    {
        try {
            $this->setRecord($record);
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    public function hasDesignRights()
    {
        if (defined('SUPER_USER') && SUPER_USER == "1") {
            return true;
        }
        if ($this->getRecord()['design'] === "1") {
            return true;
        }
        return false;
    }

    /**
     * @return array
     */
    public function getRecord(): array
    {
        return $this->record;
    }

    /**
     * @param array $record
     */
    public function setRecord(array $record): void
    {
        $this->record = $record;
    }


}
