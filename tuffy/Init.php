<?php

/**
 * Tuffy is halfway between a library and a full-fledged Web framework.
 * It is designed to function on PHP 5.2 with open_basedir, no advanced
 * debugging extensions, and assorted other restrictions.
 * Its main attraction is the advanced suite of debugging and logging tools.
 *
 * This file contains the initial setup code that sets up some basic
 * environmental information, loads the app's settings file, and then
 * loads the main Tuffy code.
 *
 * @author Matthew Frazier <mlfrazie@ncsu.edu>
 */


/**
 * The time at which the request started, down to the microsecond.
 */
define('REQUEST_START_TIME',    microtime(TRUE));

// 0. Compensate for legacy PHP settings.

if (get_magic_quotes_gpc()) {
    // Don't call this. Ever.
    function _tuffyRecursiveStripSlashes ($value) {
        return is_array($value)
             ? array_map('_tuffyRecursiveStripSlashes', $value)
             : stripslashes($value);
    }
    $_GET = array_map('_tuffyRecursiveStripSlashes', $_GET);
    $_POST = array_map('_tuffyRecursiveStripSlashes', $_POST);
    $_COOKIE = array_map('_tuffyRecursiveStripSlashes', $_COOKIE);
    $_REQUEST = array_map('_tuffyRecursiveStripSlashes', $_REQUEST);
}


if (ini_get('register_globals')) {
    // FIXME: It's conceivable that we could simply *unregister* the globals,
    // and then only die if a critical variable was overwritten.
    die("You have register_globals turned on.");
}


// 1. Compute some environmental information.

/**
 * TRUE if this script is being run from the console, FALSE if it is live
 * on the Web.
 */
define('REQUEST_CONSOLE',       PHP_SAPI === 'cli');
if (!REQUEST_CONSOLE) {
    /**
     * TRUE if the request was made over HTTPS, FALSE if not.
     */
    define('REQUEST_SECURE',    !empty($_SERVER['HTTPS']) &&
                                $_SERVER['HTTPS'] === 'on');
    /**
     * The URL scheme associated with the current request.
     */
    define('REQUEST_SCHEME',    REQUEST_SECURE ? 'https://' : 'http://');
    /**
     * The hostname attached to the current request.
     */
    define('REQUEST_HOST',      $_SERVER['HTTP_HOST']);
    /**
     * The request method (e.g. GET, POST, BREW) for the current request.
     */
    define('REQUEST_METHOD',    $_SERVER['REQUEST_METHOD']);
}


// 2. Compute the app's location.

if (!defined('TUFFY_SCRIPT_DEPTH')) {
    define('TUFFY_SCRIPT_DEPTH', 1);
}

$tuffyScriptDepth = TUFFY_SCRIPT_DEPTH;
$tuffyAppPath = realpath($_SERVER['SCRIPT_FILENAME']);
$tuffyAppPrefix = $_SERVER['SCRIPT_NAME'];

while ($tuffyScriptDepth-- > 0) {
    $tuffyAppPath = dirname($tuffyAppPath);
    $tuffyAppPrefix = dirname($tuffyAppPrefix);
}

/**
 * The path to the application's root directory.
 */
define('TUFFY_APP_PATH',        rtrim($tuffyAppPath, '/\\') . '/');
if (!REQUEST_CONSOLE) {
    /**
     * The prefix for this request (i.e., the directory all the scripts
     * are in from an HTTP perspective).
     */
    define('REQUEST_PREFIX',    rtrim($tuffyAppPrefix, '/') . '/');
}

unset($tuffyScriptDepth, $tuffyAppPath, $tuffyAppPrefix);

/**
 * The path to Tuffy's module files.
 */
define('TUFFY_PATH',        rtrim(dirname(__FILE__), '/\\') . '/');


// 3. Load the debugging tools.

require_once(TUFFY_PATH . 'Debug.php');

Tuffy_Debug::registerHandlers();


// 4. Set up the autoloader.

require_once(TUFFY_PATH . 'Loader.php');

$tuffyCoreLoader = new Tuffy_Loader_ForTuffy('Tuffy', TUFFY_PATH, array(
    'Tuffy' => TUFFY_PATH . 'Tuffy.php'
));
$tuffyCoreLoader->register();


// 5. Load and normalize settings.

$tuffySettingsFile = Tuffy_Util::interpretPath(
    defined('TUFFY_SETTINGS_FILE') ? TUFFY_SETTINGS_FILE : '_data/settings.php'
);

if (is_file($tuffySettingsFile)) {
    $tuffyFileSettings = Tuffy_Util::loadVariables($tuffySettingsFile);
    Tuffy::configure($tuffyFileSettings);
    unset($tuffyFileSettings);
}

unset($tuffySettingsFile);


if (isset($GLOBALS['tuffySettings']) && is_array($GLOBALS['tuffySettings'])) {
    Tuffy::configure($GLOBALS['tuffySettings']);
}


if (!Tuffy::setting('appName')) {
    die("You must define the appName setting.");
}


// 6. Configure the environment.

$tuffyTimezone = Tuffy::setting('timezone');
if ($tuffyTimezone !== NULL) {
    date_default_timezone_set(Tuffy::setting('timezone'));
}
unset($tuffyTimezone);

if (Tuffy::setting('useSessions')) {
    session_start();
    if (Tuffy::setting('debug')) {
        Tuffy_Debug::restoreLogFromSession();
        Tuffy::debug("Session " . session_id(), $_SESSION);
    }
}

if ($libraryPath = Tuffy::setting('libraryPath')) {
    $tuffyAppLoader = new Tuffy_Loader(
        Tuffy::setting('appName'), Tuffy_Util::interpretPath($libraryPath)
    );
    $tuffyAppLoader->register();
}
unset($libraryPath);

foreach (Tuffy::setting('initializers', array()) as $init) {
    call_user_func($init);
}


// 7. Here are a couple of completely non-namespaced utility functions
// that are just too useful not to have.

/**
 * If the $key exists in $array, returns the value stored therein. Otherwise,
 * returns $default.
 *
 * @param array $array The array to check.
 * @param mixed $key The key to search for.
 * @param mixed $default The value to return if the key does not exist.
 * (It defaults to NULL.)
 */
function maybe ($array, $key, $default = NULL) {
    return array_key_exists($key, $array) ? $array[$key] : $default;
}


/**
 * Escapes HTML. This will always escape quotes as well, which does nothing
 * when in body text and helps quite a bit in attributes.
 *
 * @param string $data The HTML to escape.
 */
function esc ($data) {
    return htmlspecialchars($data, ENT_QUOTES);
}


