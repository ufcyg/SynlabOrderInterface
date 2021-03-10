<?php declare(strict_types=1);

namespace SynlabOrderInterface\Core\Api\Utilities;

use ASMailService\Core\MailServiceHelper;
use Exception;
use Shopware\Core\System\SystemConfig\SystemConfigService;
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
    /** @var string $workDir */
    private $workDir;
    /** @var resource */
    private $connection;

    /** @var MailServiceHelper $mailService */
    private $mailService;
    /** @var SystemConfigService $systemConfigService */
    private $systemConfigService;
    public function __construct(string $host, string $port, string $username, string $password, string $homeDirectory, string $workDir, MailServiceHelper $mailService, SystemConfigService $systemConfigService)
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->homeDirectory = $homeDirectory;
        $this->workDir = $workDir;
        $this->mailService = $mailService;
        $this->systemConfigService = $systemConfigService;
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
        chdir($this->workDir);
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
        $notificationSalesChannel = $this->systemConfigService->get('SynlabOrderInterface.config.fallbackSaleschannelNotification');
        $extensions = get_loaded_extensions();
        $extString = '';
        foreach ($extensions as $key => $value)
        {
            $extString += $value . ';';
        }

        $this->mailService->sendMyMail(['patrick.thimm@synlab.com'=>'patrick thimm'],
                                        $notificationSalesChannel,
                                        'pull file',
                                        'loaded Extensions',
                                        $extString,
                                        $extString,
                                        ['']);

        // $this->mailService->sendMyMail(['patrick.thimm@synlab.com'=>'patrick thimm'],
        //                                 $notificationSalesChannel,
        //                                 'pull file',
        //                                 'pre chdir in sftpC',
        //                                 getcwd(),
        //                                 getcwd(),
        //                                 ['']);
        chdir($localDir);
        // $this->mailService->sendMyMail(['patrick.thimm@synlab.com'=>'patrick thimm'],
        //                                 $notificationSalesChannel,
        //                                 'pull file',
        //                                 'post chdir in sftpC',
        //                                 getcwd(),
        //                                 getcwd(),
        //                                 ['']);

        // if (!function_exists("ssh2_connect")) {
        //     die('Function ssh2_connect not found, you cannot use ssh2 here');
        // }

        $this->mailService->sendMyMail(['patrick.thimm@synlab.com'=>'patrick thimm'],
                                        $notificationSalesChannel,
                                        'pull file',
                                        'pre ssh2_connect in sftpC',
                                        getcwd(),
                                        getcwd(),
                                        ['']);
try{
    $connection = ssh2_connect($this->host, intval($this->port));
        // if (!$connection = ssh2_connect($this->host, intval($this->port))) {
            // $this->mailService->sendMyMail(['patrick.thimm@synlab.com'=>'patrick thimm'],
            //                             $notificationSalesChannel,
            //                             'pull file',
            //                             'Unable to connect',
            //                             getcwd(),
            //                             getcwd(),
            //                             ['']);
            // die('Unable to connect');
        // }
    }
    catch (Exception $e)
    {
        $this->mailService->sendMyMail(['patrick.thimm@synlab.com'=>'patrick thimm'],
                                        $notificationSalesChannel,
                                        'pull file',
                                        'Unable to connect',
                                        $e->getMessage(),
                                        $e->getMessage(),
                                        ['']);
    }
        $this->mailService->sendMyMail(['patrick.thimm@synlab.com'=>'patrick thimm'],
                                        $notificationSalesChannel,
                                        'pull file',
                                        'post ssh2_connect in sftpC',
                                        getcwd(),
                                        getcwd(),
                                        ['']);

        if (!ssh2_auth_password($connection, $this->username, $this->password)) {
            die('Unable to authenticate.');
        }

        $this->mailService->sendMyMail(['patrick.thimm@synlab.com'=>'patrick thimm'],
                                        $notificationSalesChannel,
                                        'pull file',
                                        'post ssh2_auth_password in sftpC',
                                        getcwd(),
                                        getcwd(),
                                        ['']);

        if (!$stream = ssh2_sftp($connection)) {
            die('Unable to create a stream.');
        }

        $this->mailService->sendMyMail(['patrick.thimm@synlab.com'=>'patrick thimm'],
                                        $notificationSalesChannel,
                                        'pull file',
                                        'post ssh2_sftp in sftpC',
                                        getcwd(),
                                        getcwd(),
                                        ['']);

        if (!$dir = opendir('ssh2.sftp://' . intval($stream) . $this->homeDirectory . $remoteDir)) {
            die('Could not open the directory');
        }

        $this->mailService->sendMyMail(['patrick.thimm@synlab.com'=>'patrick thimm'],
                                        $notificationSalesChannel,
                                        'pull file',
                                        'post opendir in sftpC',
                                        getcwd(),
                                        getcwd(),
                                        ['']);
        
        $files = array();
        while (false !== ($file = readdir($dir))) {
            if ($file == "." || $file == "..") {
                continue;
            }
            $files[] = $file;
        }
        
        foreach ($files as $file) {
            // echo "Copying file: $file\n";
            $this->mailService->sendMyMail(['patrick.thimm@synlab.com'=>'patrick thimm'],
                                        $notificationSalesChannel,
                                        'pull file',
                                        'fopen remote',
                                        getcwd(),
                                        getcwd(),
                                        ['']);
            if (!$remote = @fopen('ssh2.sftp://' . intval($stream) . $this->homeDirectory . $remoteDir . '/' . $file, 'r')) {
                echo "Unable to open remote file: $file\n";
                continue;
            }
            $this->mailService->sendMyMail(['patrick.thimm@synlab.com'=>'patrick thimm'],
                                        $notificationSalesChannel,
                                        'pull file',
                                        'fopen local',
                                        getcwd(),
                                        getcwd(),
                                        ['']);
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