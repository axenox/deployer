<?php
// terminal_downloader.php
// All-in-one PHP file that:
// 1) Downloads a file from a hard-coded URL into a sibling ".dep" folder (uses the basename from the URL)
// 2) Starts the command `php -d memory_limit=2G .dep/<downloaded-file>` exactly once in the background
//    and saves all output to a timestamped log file in .dep
// 3) Creates a small PHP runner which writes its own PID into .dep/current.pid and removes it on exit
// 4) Shows the entire log in the browser (auto-refresh while running). Works on Linux and Windows.

// ---------------- CONFIG ----------------
$downloadUrl = 'https://example.com/path/to/somename.phx'; // <-- set your hard-coded URL here
$depDirName  = '.dep';
$refreshSec  = 5; // auto-refresh interval while running
$memoryLimit = '2G';

// ---------------- helpers ----------------
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function now_ts(){ return date('Ymd_His'); }
function human_bytes($bytes){
    if ($bytes < 1024) return $bytes . ' B';
    $units = ['KB','MB','GB','TB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i-1];
}

// Determine paths
$scriptPath = realpath(__FILE__);
$baseDir = dirname($scriptPath);
$depDir = $baseDir . DIRECTORY_SEPARATOR . $depDirName;

// Ensure .dep exists
if (!is_dir($depDir)) {
    @mkdir($depDir, 0755, true);
}

// Prepare download target
$parsed = parse_url($downloadUrl);
$pathPart = isset($parsed['path']) ? $parsed['path'] : '';
$basename = basename($pathPart);
if (!$basename) $basename = 'downloaded.phx';
$targetFile = $depDir . DIRECTORY_SEPARATOR . $basename;

// Helper: safe shell arg (works for both windows and unix)
function sharg($s){
    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        // On Windows double-quote and escape internal quotes
        return '"' . str_replace('"', '\"', $s) . '"';
    }
    return escapeshellarg($s);
}

// ----------------- Download step -----------------
$downloaded = false;
if (!file_exists($targetFile) || filesize($targetFile) === 0) {
    // Try cURL
    if (function_exists('curl_version')) {
        $tmp = $targetFile . '.tmp';
        $ch = curl_init($downloadUrl);
        $fp = @fopen($tmp, 'w');
        if ($fp) {
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_FAILONERROR, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            curl_setopt($ch, CURLOPT_USERAGENT, 'PHP-Terminal-Downloader/1.0');
            $ok = curl_exec($ch);
            if ($ok !== false) {
                @fclose($fp);
                @rename($tmp, $targetFile);
                $downloaded = file_exists($targetFile) && filesize($targetFile) > 0;
            } else {
                @fclose($fp);
                @unlink($tmp);
            }
            curl_close($ch);
        }
    }
    // Fallback to file_get_contents
    if (!$downloaded && ini_get('allow_url_fopen')) {
        $contents = @file_get_contents($downloadUrl);
        if ($contents !== false) {
            $w = @file_put_contents($targetFile, $contents);
            if ($w !== false && $w > 0) $downloaded = true;
        }
    }
} else {
    $downloaded = true; // already present
}

// ----------------- Runner & Logging -----------------
// Log file name
// If a running log already exists (current.log) we'll reuse the most recent timestamped log
$existingLogs = glob($depDir . DIRECTORY_SEPARATOR . '*.log');
rsort($existingLogs);
$activeLog = $existingLogs[0] ?? null;
$pidFile = $depDir . DIRECTORY_SEPARATOR . 'current.pid';
$runnerFile = $depDir . DIRECTORY_SEPARATOR . 'runner.php';

// If there's no active log or the user removed it, create a new one when launching
if (!$activeLog) {
    $activeLog = $depDir . DIRECTORY_SEPARATOR . now_ts() . '.log';
}

// Decide whether to start the process: only if pid file does not exist and no runner is alive
$shouldStart = !file_exists($pidFile);

// Create runner script (safe and portable). The runner will: write its PID to current.pid,
// execute the actual php command and stream output to stdout (which we redirect to log when launching),
// and finally remove the pid file when done.
$downloadedBasename = basename($targetFile);
$phpBinary = PHP_BINARY; // full path to php used to run this script
$cmdToRun = $phpBinary . ' -d memory_limit=' . $memoryLimit . ' ' . sharg($depDir . DIRECTORY_SEPARATOR . $downloadedBasename);

$runnerPhp = <<<'PHP'
<?php
// Auto-generated runner. It writes its own PID to current.pid, runs the target command, then removes the pid file.
$depDir = __DIR__ . DIRECTORY_SEPARATOR . '.dep';
$pidFile = $depDir . DIRECTORY_SEPARATOR . 'current.pid';
// Write our PID early
@file_put_contents($pidFile, getmypid());
// Flush to disk
@fflush(fopen($pidFile, 'r'));
// Execute the command passed as argv[1] (safe invocation from shell)
$cmd = isset($argv[1]) ? $argv[1] : '';
if ($cmd === '') {
    // nothing to do
    @unlink($pidFile);
    exit(1);
}
// Execute
// We use passthru so child process inherits STDOUT/STDERR and output flows to the log that the parent redirected.
passthru($cmd . ' 2>&1', $exitCode);
// Remove pid file to signal completion
@unlink($pidFile);
exit($exitCode);
PHP;

// Ensure runner file exists and is up to date
if (!file_exists($runnerFile)) {
    @file_put_contents($runnerFile, $runnerPhp);
    @chmod($runnerFile, 0755);
}

// Start background process if needed
$started = false;
if ($shouldStart) {
    // Launch runner.php in background and redirect its output to $activeLog
    $activeLog = $depDir . DIRECTORY_SEPARATOR . now_ts() . '.log';
    // Ensure log file exists
    @file_put_contents($activeLog, "--- started at " . date('c') . "
");

    if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
        // Windows: use start /B to detach
        // Build command: start /B php "runner.php" "<cmdToRun>" > "log" 2>&1
        $command = 'start /B ' . sharg($phpBinary) . ' ' . sharg($runnerFile) . ' ' . sharg($cmdToRun) . ' > ' . sharg($activeLog) . ' 2>&1';
        // Use pclose(popen()) to run without waiting
        pclose(popen($command, 'r'));
        $started = true;
        // Note: runner will create current.pid once it starts; we can't reliably capture PID here.
    } else {
        // Unix-like: use nohup and capture PID
        $command = 'nohup ' . sharg($phpBinary) . ' ' . sharg($runnerFile) . ' ' . sharg($cmdToRun) . ' > ' . sharg($activeLog) . ' 2>&1 & echo $!';
        $output = [];
        @exec($command, $output);
        if (!empty($output)) {
            $maybePid = (int)$output[0];
            if ($maybePid > 0) {
                // write pid file in case runner hasn't yet
                @file_put_contents($pidFile, $maybePid);
            }
        }
        $started = true;
    }
}

// ----------------- UI Output -----------------
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Terminal - Downloader & Runner</title>
    <style>
        html,body{height:100%;margin:0;background:#0b1220;color:#cbd5e1;font-family: monospace, monospace}
        .term{box-sizing:border-box;padding:20px;max-width:980px;margin:20px auto;border-radius:6px;background:#02040a;box-shadow:0 8px 30px rgba(2,4,10,.7);min-height:60vh}
        .title{font-weight:700;color:#9ae6b4;margin-bottom:6px}
        .line{padding:6px 10px;border-radius:4px;margin:4px 0}
        .success{background:linear-gradient(90deg, rgba(10,64,32,.3), rgba(6,40,20,.15));color:#9ae6b4}
        .fail{background:linear-gradient(90deg, rgba(64,10,10,.2), rgba(40,6,6,.1));color:#feb2b2}
        pre{white-space:pre-wrap;word-break:break-word}
        .meta{color:#94a3b8;font-size:0.9em;margin-top:8px}
        .footer{margin-top:14px;color:#94a3b8;font-size:0.9em}
    </style>
    <?php
    // If runner is running (pid file exists), add meta refresh
    $running = false;
    if (file_exists($pidFile)) {
        // If file exists, assume the runner is running (runner removes on exit)
        $running = true;
    }
    if ($running) {
        echo "<meta http-equiv=\"refresh\" content=\"$refreshSec\">
";
    }
    ?>
</head>
<body>
<div class="term">
    <div class="title">php terminal — downloader & runner</div>
    <div class="line">Download URL: <?php echo esc($downloadUrl); ?></div>
    <div class="line">Target file: <?php echo esc($depDirName . '/' . $basename); ?> (<?php echo $downloaded ? '<span class="success">present</span>' : '<span class="fail">not present</span>'; ?>)</div>
    <div class="line">Log file: <?php echo esc(basename($activeLog)); ?></div>
    <div style="margin-top:12px; background:#071018; padding:10px; border-radius:6px; max-height:60vh; overflow:auto;">
    <pre>
<?php
// Output the entire log (if exists). If runner just started it may be empty or growing.
if (file_exists($activeLog)) {
    // read and print entire file
    $data = @file_get_contents($activeLog);
    if ($data === false) {
        echo esc("(failed to read log: $activeLog)");
    } else {
        echo esc($data);
    }
} else {
    echo esc("(log file not found yet: " . basename($activeLog) . ")");
}
?>
    </pre>
    </div>
    <div class="meta">
        <?php
        if ($running) {
            echo 'Process appears <strong>running</strong>. This page will auto-refresh every ' . intval($refreshSec) . ' seconds.';
        } else {
            if ($started) {
                echo '<span class="success">Process started.</span> It will create <code>' . esc(basename($pidFile)) . '</code> while running.';
            } else {
                // Not running and not started now - show final state
                echo '<span class="success">No running process.</span> The last log shown above is final.';
            }
        }
        ?>
    </div>
    <div class="footer">&copy; PHP Terminal Downloader — runner</div>
</div>
</body>
</html>