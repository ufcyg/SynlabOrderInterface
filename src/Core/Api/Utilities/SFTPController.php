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
    private $ftpSubsystem;
    private $connectionString;

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
        if (! $this->connection)
        {
            throw new Exception("Could not connect to $this->host on port $this->port.");
            return false;
        }
        return true;
    }
    private function authConnection():bool
    {
        if(ssh2_auth_password($this->connection, $this->username, $this->password))
        {
            return true;
        }
        throw new Exception("Could not authenticate with username $this->username " . "and password $this->password.");
        return false;
    }

    public function pushFile(string $originPath, string $destinationPath)
    {
        if($this->openConnection())
        {//connected
            if($this->authConnection())
            {//authenticated
                $sftp = ssh2_sftp($this->connection);
                $stream = fopen('ssh2.sftp://' . intval($sftp) . $this->homeDirectory . $destinationPath, 'w');
                $file = file_get_contents($originPath);
                fwrite($stream, $file);
                fclose($stream); 
            }
        }
    }

    public function pullFile(string $originPath, string $filename)
    {
        $this->openConnection();
        if(! $this->authConnection())
        {
            throw new Exception("Could not authenticate with username $this->username " .
            "and password $this->password.");
        }
        ssh2_scp_recv($this->connection, 'testfile.csv', $originPath);
    }
    private function writeFileOnServer(string $filePathOrigin)
    {
        $localFile=$filePathOrigin;
        $remoteFile='/myfile.csv';
        $host = $this->host;
        $port = intval($this->port);
        $user = $this->username;
        $pass = $this->password;
        
        $connection = ssh2_connect($host, $port);
        ssh2_auth_password($connection, $user, $pass);
        $sftp = ssh2_sftp($connection);
        
        $stream = fopen("ssh2.sftp://".$sftp.$remoteFile, 'w');
        $file = file_get_contents($localFile);
        fwrite($stream, $file);
        fclose($stream);
    }



    public function uploadFile($local_file, $remote_file)
    {
        $sftp = $this->sftp;
        $stream = @fopen("ssh2.sftp://$sftp$remote_file", 'w');

        if (! $stream)
            throw new Exception("Could not open file: $remote_file");

        $data_to_send = @file_get_contents($local_file);
        if ($data_to_send === false)
            throw new Exception("Could not open local file: $local_file.");

        if (@fwrite($stream, $data_to_send) === false)
            throw new Exception("Could not send data from file: $local_file.");

        @fclose($stream);
    }
}