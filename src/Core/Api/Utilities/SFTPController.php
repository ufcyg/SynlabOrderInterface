<?php declare(strict_types=1);

namespace SynlabOrderInterface\Core\Api\Utilities;

use Exception;

class SFTPController
{
    //server data
    /** @var string $ipAddress */
    private $host;
    /** @var string $port */
    private $port;
    //login data
    /** @var string $username */
    private $username;
    /** @var string $password */
    private $password;
    /** @var string $homeDirectory */
    private $homeDirectory;

    private $connection;

    public function __construct(string $host, string $port, string $username, string $password, string $homeDirectory)
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->homeDirectory = $homeDirectory;
    }
    private function openConnection(): bool
    {
        $this->connection = ssh2_connect($this->host, intval($this->port));
        if (! $this->connection) {
            throw new Exception("Could not connect to $this->host on port $this->port.");
            return false;
        }
        return true;
    }

    private function authConnection():bool
    {
        if (ssh2_auth_password($this->connection, $this->username, $this->password)) {
            return true;
        }
        throw new Exception("Could not authenticate with username $this->username " . "and password $this->password.");
        return false;
    }

    public function pushFile(string $originPath, string $destinationPath)
    {
        if ($this->openConnection()) {//connected
            if ($this->authConnection()) {//authenticated
                $sftp = ssh2_sftp($this->connection);
                $stream = fopen('ssh2.sftp://' . intval($sftp) . $this->homeDirectory . $destinationPath, 'w');
                $file = file_get_contents($originPath);
                fwrite($stream, $file);
                fclose($stream);
            }
        }
    }
    public function pullFile(string $localDir, string $remoteDir)
    {
        $this->openConnection();
        if($this->authConnection())
        {
            $sftp = ssh2_sftp($this->connection);
            $files    = scandir('ssh2.sftp://' . intval($sftp) . $this->homeDirectory . $remoteDir);
            if (!empty($files)) 
            {
                foreach ($files as $file) 
                {
                    if ($file != '.' && $file != '..') 
                    {
                        $stream = fopen('ssh2.sftp://' . intval($sftp) . $this->homeDirectory . $remoteDir . '/' . $file, 'r');
                        file_put_contents($localDir . '/' . $file,$stream);

                        ssh2_sftp_unlink($sftp, $this->homeDirectory . $remoteDir . '/' . $file);
                    }
                }
            }
        }
        else
        {
            throw new Exception("Could not authenticate with username $this->username " .
            "and password $this->password.");
        }        
    }
}