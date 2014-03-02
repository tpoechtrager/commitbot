<?php

// comment it out if the time zone is set in php.ini
$timezone = "Europe/Vienna";

// user / pw for basic auth
// e.g.: https://username:password@1.2.3.4/path/to/hook.php

$username = "username";
$password = "password";

// replace names of users by their name or email address
// they are compared in lower case

$names = array(
    "some@email.com" => "marc",
    "some name" => "marc"
);

// lower case the name of the repository
$lower_case_repo_name = true;

// lower case the name of the repository owner
$lower_case_repo_owner = true;

// lower case the name of the author
// this also affects the name of the pusher
$lower_case_author_name = true;

// lower case the commit message
$lower_case_commit_message = false;

// maximum shown commits
$max_commits = 5;

// maximum line length of each commit log line
$max_message_line_len = 120;

// limit commit message to N lines
$max_message_lines = 5;

// strip 'git-svn-id: ' in commit messages
// (only matters for git svn mirrors)
$strip_svn_id = true;

// show svn revision
// (only matters for git svn mirrors)
$show_svn_rev = true;

// ** experimentally **
// do not use fork in a production environment
$use_fork = false;

// fifo pipe (you must change it in commitbot.py too)
$fifo = "/tmp/commitbot_fifo";

// log file
$logfile = "/tmp/commitbot_log";

?>
