<?php declare(strict_types=1);

namespace SynlabOrderInterface\Core\Api\Utilities;

use Exception;
use Symfony\Component\HttpFoundation\Response;

/*

Establishes a secure file transfer protocoll connection to the server defined in the plugins configuration in shopware administration frontend

*/
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
    /** @var resource */
    private $connection;

    public function __construct(string $host, string $port, string $username, string $password, string $homeDirectory)
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->homeDirectory = $homeDirectory;
    }

    /* Opens the connection to the $host via $port */
    private function openConnection(): bool
    {
        $this->connection = ssh2_connect($this->host, intval($this->port));
        if (! $this->connection) {
            return false;
        }
        return true;
    }

    /* Authentificates on the opened connection */
    private function authConnection():bool
    {
        $authentificationSuccessful = ssh2_auth_password($this->connection, $this->username, $this->password);
        return $authentificationSuccessful;
    }

    /* Writes local file on remote sFTP server */
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

    /* Copies a file from the remote sFTP server to local disc for evaluation */
    public function pullFile(string $localDir, string $remoteDir)
    {
        if (!function_exists("ssh2_connect")) {
            die('Function ssh2_connect not found, you cannot use ssh2 here');
        }

        if (!$connection = ssh2_connect($this->host, intval($this->port))) {
            die('Unable to connect');
        }

        if (!ssh2_auth_password($connection, $this->username, $this->password)) {
            die('Unable to authenticate.');
        }

        if (!$stream = ssh2_sftp($connection)) {
            die('Unable to create a stream.');
        }
        if (!$dir = opendir('ssh2.sftp://' . intval($stream) . $this->homeDirectory . $remoteDir)) {
            die('Could not open the directory');
        }
        
        $files = array();
        while (false !== ($file = readdir($dir))) {
            if ($file == "." || $file == "..") {
                continue;
            }
            $files[] = $file;
        }
        
        foreach ($files as $file) {
            // echo "Copying file: $file\n";
            if (!$remote = @fopen('ssh2.sftp://' . intval($stream) . $this->homeDirectory . $remoteDir . '/' . $file, 'r')) {
                echo "Unable to open remote file: $file\n";
                continue;
            }

            if (!$local = @fopen($localDir . '/' . $file, 'w')) {
                echo "Unable to create local file: $file\n";
                continue;
            }

            $read = 0;
            $filesize = filesize('ssh2.sftp://' . intval($stream) . $this->homeDirectory . $remoteDir . '/' . $file);
            while ($read < $filesize && ($buffer = fread($remote, $filesize - $read))) {
                $read += strlen($buffer);
                if (fwrite($local, $buffer) === false) {
                    echo "Unable to write to local file: $file\n";
                    break;
                }
            }
            fclose($local);
            // ssh2_sftp_unlink($stream, $this->homeDirectory . $remoteDir . '/' . $file);
            fclose($remote);            
        }
        closedir($dir);
    }
}