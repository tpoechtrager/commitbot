### Commit IRC Bot ###

### Currently supported services: ###

 * Bitbucket (git + mercurial)
 * GitHub

### Requirements ###

  * `A Unix compatible system (fifo pipe)`
  * `A PHP5 webserver with json support (php5-json / php5-common)`
  * `Python + python-twisted-words`

### How does it work? ###

  * It uses the post hooks from
    [Bitbucket](https://confluence.atlassian.com/display/BITBUCKET/POST+hook+management) and
    [Github](https://help.github.com/articles/post-receive-hooks)
    which are calling  
    the PHP script which then parses the json content and forwards it to  
    the IRC bot using a fifo pipe.

### Setup ###

  * Make sure all requirements are installed
  * Edit `config.php` and `commitbot.py`
  * Start the IRC bot: `./commitbot.py`
  * Symlink or copy `*.php` to your www directory. e.g.: `/var/www/commitbot`

  * Bitbucket: 
     * Go to your repository settings (the cogwheel), then click on `hooks` and  
       add a `post hook`, e.g.: `http://userbot:password@1.2.3.4/commitbot/hook.php`

  * Github:
      * Go to your respository settings, then click on `Webhooks & Services` and  
        add a `webhook` (*just push*) (content type: *application/x-www-form-urlencoded*),  
        e.g.: `http://userbot:password@1.2.3.4/commitbot/hook.php`

  * **Important:** If you are using 
    [`suhosin`](http://www.hardened-php.net/suhosin/),
    then you must disable it in the  
    directory where ever you put the php files. Suhosin is adding backslashes  
    ("magic quotes") which will break the json content in `$_POST['payload']`.  
    Please see `suhosin_example_htaccess` (rename it to `.htaccess`).

### Debugging hook.php ###

   * Simply start it from a terminal: `php5 hook.php`

### Example Output ###

   * See [example_output.txt](https://github.com/tpoechtrager/commitbot/blob/master/example_output.txt)

### License ###

   * GPLv2
