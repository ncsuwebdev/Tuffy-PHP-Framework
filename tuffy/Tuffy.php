<?php

/**
 * This class contains generic functionality useful to just about all Web
 * pages.
 *
 * @author Matthew Frazier <mlfrazie@ncsu.edu>
 */
class Tuffy {
    // Settings.
    private static $settings = array(
        'debug' => FALSE,
        'useSessions' => TRUE
    );

    /**
     * Gets the value of a configuration setting.
     *
     * @param string $name The name of the setting. If it includes dots, it
     * can traverse arrays.
     * @param mixed $default The value to return if the setting has not been
     * set (defaults to NULL).
     * @return The value of the setting, or $default.
     */
    public static function setting ($name, $default = NULL) {
        if (strpos($name, '.') === FALSE) {
            return maybe(self::$settings, $name, $default);
        } else {
            $parts = explode('.', $name);
            $cursor = self::$settings;
            foreach ($parts as $part) {
                if (is_array($cursor) && array_key_exists($part, $cursor)) {
                    $cursor = $cursor[$part];
                } else {
                    return $default;
                }
            }
            return $cursor;
        }
    }

    /**
     * Updates some configuration settings.
     *
     * @param array|string $settings An array mapping setting names to new
     * values, or the name of a single setting to change.
     * @param mixed $value If $settings is a string, this is the value that
     * it should be changed to.
     */
    public static function configure ($settings, $value = NULL) {
        if (is_array($settings)) {
            foreach ($settings as $name => $val) {
                self::$settings[$name] = $val;
            }
        } else if (is_string($settings)) {
            self::$settings[$settings] = $value;
        } else {
            throw new InvalidArgumentException(
                "invalid type for Tuffy::configure: " . gettype($settings)
            );
        }
    }

    // Logging.

    /**
     * Adds a new message to the debug log, if the $debug setting is TRUE.
     *
     * @param string $title A label for the debug message describing its data.
     * @param string $data The data to display.
     * @param int $skipExtra The number of stack frames to skip when assigning
     * this message a traceback.
     */
    public static function debug ($title, $data, $skipExtra = 0) {
        if (Tuffy::setting('debug')) {
            $stack = array_slice(Tuffy_Debug::getBacktrace(), 1 + $skipExtra);
            return Tuffy_Debug::addMessage(new Tuffy_Debug_Message(
                $title, $data, $stack, 0
            ));
        }
    }

    /**
     * Adds a new warning message to the debug log, if the $debug setting is
     * TRUE.
     *
     * @param string $title A label for the debug message describing its data.
     * @param string $data The data to display.
     * @param int $skipExtra The number of stack frames to skip when assigning
     * this message a traceback.
     */
    public static function warn ($title, $data, $skipExtra = 0) {
        if (Tuffy::setting('debug')) {
            $stack = array_slice(Tuffy_Debug::getBacktrace(), 1 + $skipExtra);
            return Tuffy_Debug::addMessage(new Tuffy_Debug_Message(
                $title, $data, $stack, Tuffy_Debug::PROBLEM
            ));
        }
    }

    // URLs and HTTP responses.

    /**
     * Exits the script by throwing a Tuffy_Debug_Exit exception. Exiting the
     * script this way has the benefit that the exception can be caught in
     * testing or other scenarios where you might not always want to exit.
     */
    public static function exitScript () {
        throw new Tuffy_Debug_Exit();
    }

    /**
     * Expands a URL. Absolute URLs, and URLs with a leading slash,
     * are returned as is, but relative URLs are treated as relative to
     * REQUEST_PREFIX instead of the current directory.
     *
     * @param string $target The URL to redirect to.
     * @param array $params GET parameters to include in the built URL's
     * query string.
     * @param boolean $forceHTTPS If this is TRUE, the generated URL will
     * always begin with https://. Otherwise, it will be the same as the
     * current request's scheme.
     */
    public static function url ($target, $params = NULL, $forceHTTPS = FALSE) {
        $scheme = $forceHTTPS ? 'https://' : REQUEST_SCHEME;
        if ($target === 'index') {
            $base = $scheme . REQUEST_HOST . REQUEST_PREFIX;
        } else if (parse_url($target, PHP_URL_SCHEME) !== NULL) {
            $base = $target;
        } else if ($target[0] === '/') {
            $base = $scheme . REQUEST_HOST . $target;
        } else {
            $base = $scheme . REQUEST_HOST . REQUEST_PREFIX . $target;
        }
        return $params === NULL ? $base : (
            $base . '?' . Tuffy_Util::buildQuery($params, TRUE)
        );
    }

    /**
     * Redirects to another page and exits. This includes a brief HTML message
     * explaining to where the redirect goes. It also saves the debug log
     * for this request in the session, if both the debug log and sessions
     * are enabled.
     *
     * @param string $target The URL to redirect to. This is passed through
     * Tuffy::expand_url.
     * @param string $code The HTTP status line (e.g. "303 See Other").
     */
    public static function redirect ($target, $code = '303 See Other') {
        $dest = self::url($target);
        if (self::setting('debug') && self::setting('useSessions')) {
            Tuffy::debug("Redirecting", $dest);
            Tuffy_Debug::saveLogInSession();
        }
        header('HTTP/1.1 ' . $code);
        header('Location: ' . $dest);
        echo "<!doctype html>\n";
        echo "<p>Redirecting you to <a href=\"$dest\">$dest</a>.</p>";
        self::exitScript();
    }

    /**
     * If this request is not over HTTPS, redirects to the HTTPS equivalent.
     * This uses a 307 response, so the request method and data should be
     * preserved...but if the user already sent a password over an insecure
     * connection, you're already borked.
     */
    public static function requireSSL () {
        if (!REQUEST_SECURE) {
            self::redirect('https://' . REQUEST_HOST . $_SERVER['REQUEST_URI'],
                           '307 Temporary Redirect');
        }
    }

    // Sessions.
    private static $_flashKey;

    /**
     * Saves a flash message in the user's sessions, to be retrieved
     * and displayed at a later time (possibly within the same request).
     *
     * @param string $type The type of message. (Possiblities include "info",
     * "error", "success", and "warning".)
     * @param string $message The actual message to display. This will
     * probably not be HTML-escaped.
     * @see Tuffy::getFlashes
     */
    public static function flash ($type, $message) {
        if (!self::setting('useSessions')) {
            trigger_error("sessions are disabled", E_USER_NOTICE);
            return;
        }

        if (self::$_flashKey === NULL) {
            self::$_flashKey = self::setting('appName') . ':flashes';
        }
        $_SESSION[self::$_flashKey][] = array(
            'type' => $type, 'message' => $message
        );
    }

    /**
     * Retrieves all of the user's flash message from the session. They
     * are returned as an array of arrays with two keys each - `type` and
     * `message`.
     *
     * @param boolean $remove If this is TRUE (the default), the flashes will
     * also be removed from the session.
     */
    public static function getFlashes ($remove = TRUE) {
        if (!self::setting('useSessions')) {
            trigger_error("sessions are disabled", E_USER_NOTICE);
            return;
        }
        
        if (self::$_flashKey === NULL) {
            self::$_flashKey = self::setting('appName') . ':flashes';
        }
        if (array_key_exists(self::$_flashKey, $_SESSION)) {
            if ($remove) {
                $flashes = $_SESSION[self::$_flashKey];
                unset($_SESSION[self::$_flashKey]);
                return $flashes;
            } else {
                return $_SESSION[self::$_flashKey];
            }
        } else {
            return array();
        }
    }

    // Mail sending.

    /**
     * Sends email using the PHP standard mail() function. (This assumes your
     * system administrator has configured it properly.)
     *
     * @param string $from The email address to send the message from.
     * @param string $to The email address(es) to send the message to.
     * @param string $replyTo The email address to which replies should be
     * delivered.
     * @param string $subject The subject of the message.
     * @param string $body The content of the message.
     */
    public static function mail ($from, $to, $replyTo, $subject, $body) {
        $headers = "From: $from\r\nReply-To: $replyTo";
        $sendmailParams = "-f" . $from;
        Tuffy::debug("Mail", "To:       $to\r\n" .
                             "From:     $from\r\n" .
                             "Reply-To: $replyTo\r\n" .
                             "Subject:  $subject\r\n\r\n" . $body);
        mail($to, $subject, $body, $headers, $sendmailParams);
    }
}


