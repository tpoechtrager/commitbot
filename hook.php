<?php
include_once "config.php";

if (@posix_isatty(STDOUT))
{
    echo "being run from terminal, using debug mode\n\n";
    $data = file_get_contents("demo/bitbucket_push");
    //$data = file_get_contents("demo/bitbucket_push_mercurial");
    //$data = file_get_contents("demo/github_push");
    //$_SERVER["HTTP_X_GITHUB_EVENT"] = "push";
    $DEBUG = true;
    $TERMINAL = true;
    $WRITETOFIFO = true;
}
else
{
    write_log("request from: %s", $_SERVER["REMOTE_ADDR"]);

    if (!isset($_SERVER["PHP_AUTH_USER"]) || !isset($_SERVER["PHP_AUTH_PW"]))
    {
        header("WWW-Authenticate: Basic realm=\"gitbot\"");
        header("HTTP/1.0 401 Unauthorized");
        write_log("request without login credentials: %s", $_SERVER["REMOTE_ADDR"]);
        exit();
    }
    else if ($_SERVER["PHP_AUTH_USER"] != $username ||
             $_SERVER["PHP_AUTH_PW"] != $password)
    {
        write_log("request with wrong login credentials: %s", $_SERVER["REMOTE_ADDR"]);
        echo "wrong login credentials!";
        http_response_code(403);
        exit();
    }

    $DEBUG = false;
    $TERMINAL = false;
    $data = $_POST['payload'];
}

if (isset($timezone)) {
    date_default_timezone_set($timezone);
}

$logfd = null;

function write_log()
{
    global $DEBUG, $logfile, $logfd;

    if (!$logfd)
    {
        $logfd = @fopen($logfile, "a+");

        if (!$logfd)
        {
            echo "cannot open log file\n";
        }
        else if (!function_exists("close_log_fd"))
        {
            function close_log_fd()
            {
                global $logfd;
                @fclose($logfd);
            }

            register_shutdown_function("close_log_fd");
        }
    }

    $args = func_get_args();
    $fmt = array_shift($args);
    $str = vsprintf($fmt, $args) . "\n";

    if ($DEBUG) {
        echo "(LOG): " . $str;
    }

    if ($logfd) {
        @fputs($logfd, $str);
    }
}

function error()
{
    $args = func_get_args();
    $fmt = array_shift($args);
    $str = "error: " . vsprintf($fmt, $args);
    write_log("error: %s", $str);

    if (http_response_code() == 200) {
        http_response_code(500);
    }

    flush();
    exit(1);
}

function check_array_key($key, &$array)
{
    if (!array_key_exists($key, $array)) {
        error("array key '$key' does not exist");
    }
}

function plural($n)
{
    return ($n != 1 ? "s" : "");
}

function strip_color_codes($text)
{
    $colorcodes = array(
        '/(\x03(?:\d{1,2}(?:,\d{1,2})?)?)/',
        '/\x02/', '/\x0F/', '/\x16/', '/\x1F/'
    );
    return preg_replace($colorcodes, "", $text);
}

function str_to_lower($text, $cond = true)
{
    return ($cond ? strtolower($text) : $text);
}

function get_name_by_user($user)
{
    global $names;

    $luser = strtolower($user);

    if (array_key_exists($luser, $names)) {
        return $names[$luser];
    }

    return $user;
}

function get_name_by_commit(&$commit)
{
    global $names;

    $email = strtolower($commit["authoremail"]);

    if (array_key_exists($email, $names)) {
        return $names[$email];
    }

    return $commit["authorname"];
}

function parse_event_bitbucket_push($json, &$out)
{
    check_array_key("user", $json);
    check_array_key("user", $json);
    check_array_key("repository", $json);
    check_array_key("name", $json["repository"]);
    check_array_key("owner", $json["repository"]);
    check_array_key("scm", $json["repository"]);

    $out["type"] = $json["repository"]["scm"];

    $out["pusher"] = $json["user"];
    $out["reponame"] = $json["repository"]["name"];
    $out["repoowner"] = $json["repository"]["owner"];

    $out["forced"] = false; // compat

    if (!array_key_exists("commits", $json)) {
        return;
    }

    foreach ($json["commits"] as $c)
    {
        check_array_key("raw_node", $c);
        check_array_key("raw_author", $c);
        check_array_key("utctimestamp", $c);
        check_array_key("message", $c);
        check_array_key("size", $c);
        check_array_key("files", $c);

        if ($out["type"] == "hg") {
            check_array_key("revision", $c);
        }

        $commit = array();

        if ($out["type"] == "hg") {
            $commit["revision"] = (int)$c["revision"];
        }

        $commit["hash"] = $c["raw_node"];
        $commit["shorthash1"] = substr($c["raw_node"], 0, 7);
        $commit["shorthash2"] = substr($c["raw_node"], 0, 6);
        $commit["shorthash3"] = substr($c["raw_node"], 0, 5);

        $tmp = explode(" ", $c["raw_author"]);
        $tmp2 = array_pop($tmp);

        $commit["authoremail"] = substr($tmp2, 1, strlen($tmp2)-2);
        $commit["authorname"] = implode(" ", $tmp);
        $commit["authortime"] = strtotime($c["utctimestamp"]);

        $commit["message"] = array_filter(explode("\n", $c["message"]));
        $commit["size"] = $c["size"];

        $commit["files"] = array();

        foreach ($c["files"] as $f)
        {
            check_array_key("type", $f);
            check_array_key("file", $f);

            $commit["files"][] = array($f["file"], $f["type"]);
        }

        $out["commits"][] = $commit;
    }
}

function parse_event_github_push($json, &$out)
{
    check_array_key("name", $json["repository"]);
    check_array_key("pusher", $json);
    check_array_key("name", $json["pusher"]);
    check_array_key("owner", $json["repository"]);
    check_array_key("name", $json["repository"]["owner"]);

    $out["type"] = "git";

    $out["pusher"] = $json["pusher"]["name"];
    $out["reponame"] = $json["repository"]["name"];
    $out["repoowner"] = $json["repository"]["owner"]["name"];

    if (!array_key_exists("commits", $json)) {
        return;
    }

    foreach ($json["commits"] as $c)
    {
        check_array_key("id", $c);
        check_array_key("message", $c);
        check_array_key("timestamp", $c);
        check_array_key("author", $c);
        check_array_key("name", $c["author"]);
        check_array_key("email", $c["author"]);
        check_array_key("added", $c);
        check_array_key("removed", $c);
        check_array_key("modified", $c);

        $commit = array();

        $commit["hash"] = $c["id"];
        $commit["shorthash1"] = substr($c["id"], 0, 7);
        $commit["shorthash2"] = substr($c["id"], 0, 6);
        $commit["shorthash3"] = substr($c["id"], 0, 5);

        $commit["authoremail"] = $c["author"]["email"];
        $commit["authorname"] = $c["author"]["name"];
        $commit["authortime"] = strtotime($c["timestamp"]);

        $commit["message"] = array_filter(explode("\n", $c["message"]));
        $commit["size"] = -1;

        if (!function_exists("parse_github_commit_files"))
        {
            function parse_github_commit_files(&$c, $type, &$commit)
            {
                foreach ($c[$type] as $f) {
                    $commit["files"][] = array($f, $type);
                }
            }
        }

        $commit["files"] = array();

        parse_github_commit_files($c, "added", $commit);
        parse_github_commit_files($c, "removed", $commit);
        parse_github_commit_files($c, "modified", $commit);

        $out["commits"][] = $commit;
    }
}

/*
 * mIRC colors:
 * 0 white
 * 1 black
 * 2 blue (navy)
 * 3 green
 * 4 red
 * 5 brown (maroon)
 * 6 purple
 * 7 orange (olive)
 * 8 yellow
 * 9 light green (lime)
 * 10 teal (a green/blue cyan)
 * 11 light cyan (cyan) (aqua)
 * 12 light blue (royal)
 * 13 pink (light purple) (fuchsia)
 * 14 grey
 * 15 light grey (silver)
 */

function get_svn_rev(&$commit)
{
    $lastline = end($commit["message"]);

    if (strncmp($lastline, "git-svn-id: ", 12)) {
        return "";
    }

    $tmp = explode("@", $lastline);

    if (count($tmp) != 2) {
        return "";
    }

    $tmp = explode(" ", $tmp[1]);
    $rev = $tmp[0];

    if (is_numeric($rev)) {
        return sprintf(" (svn: r%d)", $rev);
    }

    return "";
}

function format_message_push(&$info, $service)
{
    global $max_commits;
    global $max_message_lines, $max_message_line_len;
    global $lower_case_repo_name, $lower_case_repo_owner;
    global $lower_case_author_name, $lower_case_commit_message;
    global $strip_svn_id, $show_svn_rev;

    $message = array();
    $count = isset($info["commits"]) ? count($info["commits"]) : 0;

    $message[] = sprintf("\x02\x0310%s\x02\x03 pushed\x0310\x02 %d commit%s\x03\x02 " .
                         "to\x02\x0310 %s\x02\x03 ->\x02\x0312 %s \x02\x03(%s)",
                         str_to_lower(get_name_by_user($info["pusher"]), $lower_case_commit_message),
                         $count, plural($count), str_to_lower($info["repoowner"], $lower_case_repo_owner),
                         str_to_lower($info["reponame"], $lower_case_repo_name),
                         $service);

    if (!$count)
    {
        $message[] = "Something mysterious is going on!";
        return $message;
    }

    $c = 0;
    foreach ($info["commits"] as $commit)
    {
        $messagecount = count($commit["message"]);
        $svnrev = "";

        if ($show_svn_rev && $messagecount > 0) {
            $svnrev = get_svn_rev($commit);
        }

        $m = sprintf("\x02\x033%s\x03\x02\x035 %s%s \x03(%d file%s):",
                     str_to_lower(get_name_by_commit($commit), $lower_case_author_name),
                     $commit["shorthash2"], $svnrev, count($commit["files"]),
                     plural(count($commit["files"])));

        $message[] = $m;

        $empty = 0;
        $i = 0;

        $lineprefix = "\x02*\x02 ";

        for (; ($i<$messagecount && $i<$max_message_lines+$empty); $i++)
        {
            $line = str_to_lower($commit["message"][$i], $lower_case_commit_message);
            $len = strlen($line);

            if ($strip_svn_id && $i+1 >= $messagecount)
            {
                if (!strncmp($line, "git-svn-id: ", 12))
                {
                    $empty++;
                    break;
                }
            }

            if (trim($line) == "") // skip white space lines
            {
                $empty++;
                continue;
            }

            if ($len > $max_message_line_len)
            {
                $skipped = $len - $max_message_line_len;

                $line = substr($line, 0, $max_message_line_len) .
                        sprintf("\x0314... (+ %d more character%s)\x03", $skipped, plural($skipped));
            }

            $message[] = $lineprefix . $line;
        }

        $i -= $empty;
        $messagecount -= $empty;

        if (!$messagecount)
        {
            $message[] = $lineprefix . "<empty commit message>";
        }
        else
        {
            $skipped = $messagecount - $i;


            if ($skipped > 0)
            {
                if ($strip_svn_id && !strncmp(end($commit["message"]), "git-svn-id: ", 12)) {
                    $skipped--;
                }

                if ($skipped > 0) {
                    $message[] = sprintf("\x0314... and %d more line%s\x03", $skipped, plural($skipped));
                }
            }
        }

        if (++$c >= $max_commits) {
            break;
        }
    }

    $skipped = $count - $c;

    if ($skipped > 0) {
        $message[] = sprintf("\x0314... and %d more commit%s\x03", $skipped, plural($skipped));
    }

    return $message;
}

function push_to_fifo(&$message)
{
    global $DEBUG, $WRITETOFIFO, $use_fork, $fifo;

    write_log("writing %d line%s to fifo pipe",
              count($message), plural(count($message)));

    if ($DEBUG)
    {
        echo "> " . strip_color_codes(implode("\n> ", $message) . "\n");

        if (!$WRITETOFIFO) {
            return;
        }
    }

    if ($use_fork)
    {
        $pid = pcntl_fork();

        if ($pid == -1) {
            error("cannot fork()");
        } else if ($pid) {
            exit();
        }
    }

    $c = 0;
    while ($c <= 1)
    {
        if (!file_exists($fifo)) {
            return;
        }

        $f = @fopen("/tmp/gitbot_fifo", "w");

        if ($f === FALSE)
        {
            error("cannot open fifo pipe: " . $fifo);
            unlink($file);
            $c++;
            continue;
        }

        break;
    }

    fputs($f, str_replace("\r", "", implode("\n", $message)));

    fclose($f);
}

$out = array();
$message = array();

if (!($json = json_decode($data, true))) {
    error("cannot decode json content");
}

if (isset($json["canon_url"]) && strstr($json["canon_url"], "://bitbucket.org"))
{
    // BitBucket

    parse_event_bitbucket_push($json, $out);
    $message = format_message_push($out, "bb");
}
else if (isset($_SERVER["HTTP_X_GITHUB_EVENT"]))
{
    // GitHub

    if ($_SERVER["HTTP_X_GITHUB_EVENT"] == "ping") {
        return;
    } else if ($_SERVER["HTTP_X_GITHUB_EVENT"] == "push") {
        parse_event_github_push($json, $out);
    } else {
        http_response_code(501);
        echo "event type not supported";
        error("github event: {$_SERVER["HTTP_X_GITHUB_EVENT"]} not supported");
    }

    $message = format_message_push($out, "gh");
}
else {
    error("unknown service");
}
push_to_fifo($message);
echo "request ok!";

?>
