<?php
/**
 * Automated Cloud FTP Uploader for EVChargePlus
 * Usage: php deploy_ftp.php <ftp_host> <ftp_user> <ftp_pass> [remote_dir]
 */

$ftpHost = $argv[1] ?? '';
$ftpUser = $argv[2] ?? '';
$ftpPass = $argv[3] ?? '';
$remoteDir = $argv[4] ?? '/htdocs';

if (!$ftpHost || !$ftpUser || !$ftpPass) {
    echo "Usage: php deploy_ftp.php <ftp_host> <ftp_user> <ftp_pass> [remote_dir]\n";
    exit(1);
}

echo "Connecting to FTP server: $ftpHost ...\n";
$conn = ftp_connect($ftpHost, 21, 30);
if (!$conn) {
    die("Failed to connect to FTP host: $ftpHost\n");
}

if (!ftp_login($conn, $ftpUser, $ftpPass)) {
    die("FTP login failed for user: $ftpUser\n");
}

ftp_pasv($conn, true);
echo "FTP Login Successful! Uploading files to $remoteDir ...\n";

$sourceDir = __DIR__;
$exclude = [
    '.git',
    'deploy_ftp.php',
    'cloudflared.exe',
    'START_PUBLIC_LIVE_WEBSITE.bat',
    'run_live_tunnel.ps1',
    'evchargeplus_complete.sql'
];

function uploadDirectory($conn, $localDir, $remoteDir, $exclude = []) {
    @ftp_mkdir($conn, $remoteDir);
    $files = scandir($localDir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || in_array($file, $exclude, true)) continue;
        $localPath = $localDir . '/' . $file;
        $remotePath = $remoteDir . '/' . $file;

        if (is_dir($localPath)) {
            echo "Creating remote directory: $remotePath\n";
            uploadDirectory($conn, $localPath, $remotePath, $exclude);
        } else {
            echo "Uploading: $file ... ";
            if (ftp_put($conn, $remotePath, $localPath, FTP_BINARY)) {
                echo "OK\n";
            } else {
                echo "FAILED\n";
            }
        }
    }
}

uploadDirectory($conn, $sourceDir, $remoteDir, $exclude);
ftp_close($conn);
echo "\nDeployment Complete!\n";
