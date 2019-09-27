<?php
namespace axenox\Deployer\DeployerSshConnector;

use exface\Core\CommonLogic\AbstractDataConnectorWithoutTransactions;
use exface\Core\Interfaces\DataSources\DataQueryInterface;

/**
 * TODO
 *
 * @author Andrej Kabachnik
 *
 */
class DeployerSshConnector extends AbstractDataConnectorWithoutTransactions
{
    private $host = null;
    
    private $ssh_config = null;
    
    private $ssh_private_key = null;
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performQuery()
     */
    protected function performQuery(DataQueryInterface $query)
    {
        // TODO
        return;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performConnect()
     */
    protected function performConnect()
    {
        // TODO
        return;
    }
    
    /**
     *
     * {@inheritdoc}
     *
     * @see \exface\Core\CommonLogic\AbstractDataConnector::performDisconnect()
     */
    protected function performDisconnect()
    {
        // TODO
        return;
    }
    
    /**
     *
     * @return string
     */
    public function getHost() : string
    {
        return $this->host;
    }
    
    /**
     * 
     * @param string $value
     * @return DeployerSshConnector
     */
    public function setHost(string $value) : DeployerSshConnector
    {
        $this->host = $value;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getSshConfig() : string
    {
        return $this->ssh_config;
    }
    
    /**
     * 
     * @param string $value
     * @return DeployerSshConnector
     */
    public function setSshConfig(string $value) : DeployerSshConnector
    {
        $this->ssh_config = $value;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getSshPrivateKey() : string
    {
        return $this->ssh_private_key;
    }
    
    /**
     * 
     * @param string $value
     * @return DeployerSshConnector
     */
    public function setSshPrivateKey(string $value) : DeployerSshConnector
    {
        $this->ssh_private_key = $value;
        return $this;
    }
}