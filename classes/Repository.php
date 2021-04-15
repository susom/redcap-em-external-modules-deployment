<?php


namespace Stanford\ExternalModuleDeployment;

/**
 * Class repository
 * @package Stanford\ExternalModuleDeployment
 * @property array
 */
class Repository
{

    private $record;

    public function __construct($record)
    {
        $this->setRecord($record);
    }

    /**
     * @param string $url
     * @return string|string[]
     */
    public static function getGithubKey($url)
    {
        $parts = explode('/', $url);
        $key = end($parts);
        $key = str_replace('.git', '', $key);
        return $key;
    }

    /**
     * @return mixed
     */
    public function getRecord()
    {
        return $this->record;
    }

    /**
     * @param mixed $record
     */
    public function setRecord($record): void
    {
        $this->record = $record;
    }


}
