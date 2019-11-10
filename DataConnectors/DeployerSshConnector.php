<?php
namespace axenox\Deployer\DataConnectors;

use exface\Core\CommonLogic\AbstractDataConnectorWithoutTransactions;
use exface\Core\Interfaces\DataSources\DataQueryInterface;
use exface\Core\CommonLogic\UxonObject;

/**
 * Special connector to give deployer SSH access to hosts.
 * 
 * ## Example:
 * 
 * ```
 * {
 *  "host_name": "127.0.0.1",
 *  "ssh_private_key": "-----BEGIN RSA PRIVATE KEY-----
 *                      MIIJKQIBAAKCAgEA2ZGCXJocb9lUiAcIAWP3ZPb/TQdOvjC+Ek193oLkJHXN9Amh
 *                      XvDH5QjlGzgFakHa+u7HB+tLBbpstMkdQuIFrw3t1VunVb+udfB6X8bLle+jT7Az
 *                      lhGEQ+YbwRAmkqs+eSBOTtnrJRfIxgmaA64EvS70tzHih8f24S459lVR0ZR4ZtSY
 *                      cjcq/dJV+rKrcXLP7IzPy8pi+2iNZPuu8frET3kVE5IdKSxfP87riRhKYGVrxsrP
 *                      D7SG9ZHX4WzDBVLlRKedWLRte4eDC+9y/9+RBLmcQpAFJUXlggLl7UuHawhe/rFX
 *                      H09vYGoR9Zycxr9iwo8lvQ6ln4QVLd0Iz5s7zJ2MZvb2PX/PpPOAKnSDqyeruzS3
 *                      jFuwJNDWax4IWjPIGPRPOM8uCWJ1LbzetGnry7RFTNp4P5b6G6YIVK5Z9ruNuwht
 *                      qTBJe0Y5UDRQqaGCkjeaqg84a6s54dfa985a4sdfBmGykBE9nrTe1vvL7fl6iYvW
 *                      9BZ1FNQVRJbpXLNj1NtCjiAIwoRoI8efSJ++ZtIWZMAvK+l0BFWSc4phxeGdzDQS
 *                      N9m5BnptvyyjypvNuKFpjG2H4NDk/g4JSuz5FRpcJKa3aTZ/oji+iVlgGXJjRkfO
 *                      ouu3kIJ2yfIJyIMANX1CYRNziXDnR9rSoZC24JqsfktiUsKxMfES9ARuxG8CAwEA
 *                      AQKCAgAY2hQn+7qP1CVhvFvfvMl/kO8sn08RToQHly5vgkgJGiPCYm86ZwqOUOvK
 *                      piWIM6mNzEST1P6m0tqj8+0RvLuleVPXcTa2BsUo16VC13Rd1hezfI8H70xKbThy
 *                      XyPo0QK710Laou3HOOZIKMSc8v27lmeBnYMgu2ip5Bve1XQZGnp+VH5tXXvdrm7/
 *                      yfTnapPxk0wRHTcdPJ4aEN206k4OPeh4adJG57ihk0M4T7v/MtaSyIvKYXahCl28
 *                      dC36p7NkmjjQ7xsqZxpC/MEIFUN9ZK5CtCzCSy+iIC6fYmc/hJ9FPICVJP+15afT
 *                      hGYsFaR0UOGgrNGiXPYGXR8qh7LWwJTbCJ+d5xCKsAp7GpV7VC6bTH6Uy0l+xMDG
 *                      LH+dsdKpPaozEavucDFbdhSGfYckrjxmitiN00E1B1i242A+k2+uFOGCDbL3vBhj
 *                      wT1bTTwYl5prJJ0aXdRsxN4k7N+o4xP4QAljPGAtdR3Z6K9kI19j/7Xzf95lISFa
 *                      okl2h/TLZKtdpyG1Yganr57xGh/VExRQJIJYEi82ORPwFAzEUGLpH3rZ/A3Mqz9W
 *                      Qc31h57lkuR4Rd/dBgcjz1ribbFxlqeqNaGcbW0KSy37i25Xp5L2YdV+7uMicweq
 *                      ZCYV+zAoYmnRDo99QBuUrpN15oDtQb4EPYU1/h+wGSdpxI6EsQKCAQEA9/55J7Ge
 *                      mSk0CRt2fK+M/E81ZRObgIysc4HUFv6CzO1XYUx/fMIi5R5Nx4HiCLjaQDsr8GSn
 *                      GQkpH/8Fhn1PVxtAUGJc1E4QXH+XGlv8LDso0ARThrQMNL77P0Rd6kA0MgTnblGd
 *                      ZfkLSYrW44l49k7QWyjKIajuXx3a96clqrlYN6PNZaF+MqcrWseArJ5jyYDE6dAa
 *                      6IPv8XByKQUVOYLFkk4wEtzwnlJiL+CVGYN/dTisi6dN5cJmbtzxRXL5xjCRMpZZ
 *                      pcL6Zhvn2p89pFE4YeBPDoOFaux0i95kdC35g1qPJoTTjzF+XYFtcobf9JugSvhR
 *                      s2FaUqYZQTj9tQKCAQEA4JeV8NPSCWEZAkqbCT/GBUSaOS1NDlskKaPTyVqbx/ZZ
 *                      4JbFxi3LZrUz1EKbdQu+/953Y/x0l2q/e4MuDL0xslbu1VS4ypv1YAseEGkz+DTQ
 *                      f38OaTmFiX13kKyCAbDE7fmRlFKtqs8mAy/r5myPUW4WX7zCRs5OAUuLsdvM+V5i
 *                      33BwWQgc3WnqDYxRP8Nda29rJUgibRgCDTK4833q0iBPHdV9P088moQ6i8SIAcMs
 *                      DFFSblrDjPNWW01kX19IiLj8ZQUnZkhCC2SkC7fLHp4yHrPS/vzfLVquunu/77Jy
 *                      DnxA/Cps2a32jlevTB7GfZnuFxJFVxGHBYMenFowEwKCAQBDVEqOdVHK4X3oLxWX
 *                      Oo47fkHP5GfmmcrEPW5Yo9bdTl7X4s4GECsrK3QQg3nbxlwy7h260YjwaiVJM5LL
 *                      dcARtStb56iuV1dn1ZgvpuOrGpC1EUegHcfmlidegPBChhXlsqEmuW/TXK8s004O
 *                      TqeRr8ovxb5DLzswhcmKTU4TsOh7irRcMGEz3WEO73VG6GXNMnHDzSVRFWkSkuXb
 *                      ry6ZA6EiXKn+pQ+K3HEd8Ipqd+Il8DIgZFbo10O6O7Ahm9qmbU8ufdVvBKW5DUCA
 *                      gZVZxFdbc2vjU9/oLLRjuQhq5oSEnhSZb5yElvpo5pfRbT7miU4WrJ555ieAoune
 *                      ZFu1AoIBAQChrh5/500d+Wtyjdi8OM/J/Q/1N1pwikYnP8v3+SWKNxuOpZusxkzW
 *                      HH46QNT+1rziH/nc3eHlGzDLrqzY+N4s345BvyLkoI9tW7OB5upFtWefUQ1DzOgI
 *                      CW2olbdllia+llop57cj7soTo0z0bZRi75hlxVIqfNwE7KidGnmdz0foSF5oiYGW
 *                      F2gp5qia+X5oGCaPCTXXSWA4thoVF8GTETVDaewnRlh/d89ZzNqIQkOUfnqT/P0n
 *                      nAm/4p/uVH64BkuUQbiSVlRNNV8vHFm6KfI0zgmIDOxxDwYYM3wCB3k6WlLB9Zy0
 *                      vBpxcEg+ySzlQIn4y+tk+bY0zqabsgVHAoIBAQC8jZdQkCPuhrM7wmprZTC/chzd
 *                      ZYkHmmRmss4XcuCrj/JjH08L5uk4TjMDDLHMgrqu6nFS+n8gjybI7yes+l16ke3H
 *                      adrzytJbcAFfEbg+cWUQxpcnEvMKXn7FmlUzQiVASjF3I9umqg7eN0V2XTl4ohpZ
 *                      sf73E0N4+Gt4VB9Hv8HdKh26wDYhdCBl4VL1HkPIrxO71Tyo4aucffLg7SOSn93O
 *                      Hj8Oivln7Yc/g8XRTgLkxZOSw/zI0kjUF1fh3L1yX94Y/zHq9GwrRCpNXrGwic/F
 *                      snn/Z0XVLzLAozxdsA9F0PsHh580O7/J9YLmq+fzRR/UshCoL3mLDthmoNGb
 *                      -----END RSA PRIVATE KEY-----"
 * }
 * 
 * ```
 *
 * @author Andrej Kabachnik
 *
 */
class DeployerSshConnector extends AbstractDataConnectorWithoutTransactions
{
    private $host = null;
    
    private $port = '22';
    
    private $user = null;
    
    private $ssh_config = [];
    
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
    public function getHostName() : string
    {
        return $this->host;
    }
    
    /**
     * IP address or resolvable host name
     * 
     * @uxon-property host_name
     * @uxon-type string
     * @uxon-required true
     * 
     * @param string $value
     * @return DeployerSshConnector
     */
    public function setHostName(string $value) : DeployerSshConnector
    {
        $this->host = $value;
        return $this;
    }
    
    /**
     *
     * @return string
     */
    public function getSshConfig() : array
    {
        return $this->ssh_config;
    }
    
    /**
     * Custom SSH config options
     * 
     * @uxon-property ssh_config
     * @uxon-type object
     * @uxon-template {"":""}
     * 
     * @param string $value
     * @return DeployerSshConnector
     */
    public function setSshConfig(UxonObject $uxon) : DeployerSshConnector
    {
        $this->ssh_config = $uxon->toArray();
        return $this;
    }
    
    /**
     * SSL private key to connect to the server (i.e. the contents of id_rsa).
     * 
     * @uxon-property ssh_privat_key
     * @uxon-type password
     * @uxon-required true
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
    
    /**
     * 
     * 
     * @return string
     */
    public function getUser() : string
    {
        return $this->user;
    }
    
    /**
     * SSH user
     * 
     * @uxon-property user
     * @uxon-type string
     * @uxon-required
     * 
     * @param string $value
     * @return DeployerSshConnector
     */
    public function setUser(string $value) : DeployerSshConnector
    {
        $this->user = $value;
        return $this;
    }
    
    /**
     *
     * @return int
     */
    public function getPort() : int
    {
        return $this->port;
    }
    
    /**
     * SSH port
     * 
     * @uxon-property port
     * @uxon-type integer
     * @uoxn-default 22
     * 
     * @param int $value
     * @return DeployerSshConnector
     */
    public function setPort(int $value) : DeployerSshConnector
    {
        $this->port = $value;
        return $this;
    }
    
}