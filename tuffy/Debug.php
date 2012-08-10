<?php

/**
 * This file contains Tuffy's suite of debugging tools.
 * It is loaded by tuffy/Init.php fairly early in the setup process.
 *
 * @author Matthew Frazier <mlfrazie@ncsu.edu>
 */


/**
 * The exception handler will silently ignore this exception, and exit the
 * script immediately. Use this instead of exit() to cover the eventuality
 * that your caller *doesn't* actually want to exit.
 */
class Tuffy_Debug_Exit extends Exception {
    // this class intentionally left blank
}


class Tuffy_Debug {
    const PROBLEM = 1;

    private static $log = array();

    /**
     * Gets a backtrace for the current code. It will include everything up
     * to the call to getBacktrace -- if you need more frames to be omitted,
     * use array_slice or array_splice.
     *
     * @return The backtrace, as an array of Tuffy_Debug_StackFrame instances.
     */
    public static function getBacktrace () {
        return Tuffy_Debug_StackFrame::rewriteBacktrace(debug_backtrace());
    }

    /**
     * Returns (and possibly removes) the current debugging log.
     *
     * @param boolean $remove If this is TRUE (the default), the log will be
     * reset to an empty array after it is retrieved.
     * @return The log, as an array of Tuffy_Debug_Message instances.
     */
    public static function getLog ($remove = TRUE) {
        $log = self::$log;
        if ($remove) {
            self::$log = array();
        }
        return $log;
    }

    public static function saveLogInSession () {
        $key = Tuffy::setting('appName') . ':debugLeftovers';
        $_SESSION[$key] = self::getLog(FALSE);
    }

    public static function restoreLogFromSession () {
        $key = Tuffy::setting('appName') . ':debugLeftovers';
        if (array_key_exists($key, $_SESSION)) {
            self::$log = array_merge($_SESSION[$key], self::$log);
            unset($_SESSION[$key]);
        }
    }

    /**
     * Adds a new message to the debug log.
     *
     * @param Tuffy_Debug_Message $msg The message to add.
     * @return The index of the message, for completeEvent.
     */
    public static function addMessage ($msg) {
        self::$log[] = $msg;
        return count(self::$log);
    }

    /**
     * Adds the current time to a message in the debugging log.
     *
     * @param int $msgIndex The index of the message to complete, as
     * returned by addMessage.
     */
    public static function completeEvent ($msgIndex) {
        self::$log[$msgIndex - 1]->complete();
    }

    /**
     * Writes the debugging log in a plain-text format. You can use the
     * output-buffering functions to capture it.
     *
     * @param array $log The debugging log, as an array of Tuffy_Debug_Message
     * instances. (Get it using Tuffy_Debug::getLog.)
     */
    public static function writeLog ($log) {
        foreach ($log as $entry) {
            echo $entry->getTitle() . " [" . $entry->getTimeString() . "]\n";
            foreach ($entry->getStack() as $frame) {
                echo "    - $frame\n";
            }
            echo "\n    ";
            echo str_replace("\n", "\n    ", wordwrap($entry->getData(), 96));
            echo "\n\n";
        }
    }

    /**
     * Writes information about an exception in a plain-text format.
     * It also includes the debugging log. You can use the output-buffering
     * functions to capture it.
     *
     * @param Exception $exc The exception.
     * @param array $log The debugging log to print.
     * @param boolean $html Whether to use HTML tags for emphasis.
     * The default is TRUE.
     */
    public static function writeException ($exc, $log = array(), $html = TRUE) {
        if ($html) {
            echo "<pre><strong>" . get_class($exc) . "</strong>: " .
                                   $exc->getMessage() . "\n\n";
        } else {
            echo get_class($exc) . ": " . $exc->getMessage() . "\n\n";
        }
        
        $trace = Tuffy_Debug_StackFrame::rewriteBacktraceFromException($exc);
        if (($exc instanceof ErrorException) &&
            ($trace[0]->getName() === "Tuffy_Debug::handleError")) {
                array_splice($trace, 0, 1);
        }
        
        echo "Stack Trace:\n";
        foreach ($trace as $frame) {
            echo "- $frame\n";
        }
        echo "\n\n";

        self::writeLog($log);
        if ($html) {
            echo "</pre>";
        }
    }

    const PRODUCTION_HANDLER = 'Tuffy_Debug::handleProductionException';
    const DEV_HANDLER = 'Tuffy_Debug::handleDevException';

    /**
     * The default exception handler for production (i.e. $debug is FALSE).
     * This will write out a message simply stating that an error occurred,
     * without giving any technical details.
     *
     * @param Exception $exc The exception to handle.
     * @param array $log The current debugging log.
     */
    public static function handleProductionException ($exc, $log) {
        ob_start();
        self::writeException($exc, $log);
        $detail = ob_get_clean();

        if (!headers_sent()) echo "<!doctype html>\n\n";
        echo "<h1>An internal error occurred</h1>\n";
        echo "<p>The error has been logged and sent to this site's " .
                "administrators. We apologize for the inconvenience.</p>";
    }

    /**
     * The default exception handler for development (i.e. $debug is TRUE).
     * This will print a message to the screen with the error details and
     * debug log.
     *
     * @param Exception $exc The exception to handle.
     * @param array $log The current debugging log.
     */
    public static function handleDevException ($exc, $log) {
        if (!headers_sent()) echo "<!doctype html>\n\n";
        self::writeException($exc, $log);
    }

    // Internal handlers
    private static $_handlersActive = FALSE;
    
    public static function handleError ($errno, $errstr, $errfile, $errline) {
        if ($errno & (E_NOTICE | E_USER_NOTICE | E_STRICT)) {
            Tuffy_Debug::addMessage(new Tuffy_Debug_Message(
                "Notice", $errstr,
                array_slice(Tuffy_Debug::getBacktrace(), 1),
                Tuffy_Debug::PROBLEM
            ));
            return TRUE;
        } else {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        }
    }

    public static function handleException ($exc) {
        if ($exc instanceof Tuffy_Debug_Exit) {
            exit;
        }
        if (!($exc instanceof Exception)) {
            $exc = new Exception(var_export($exc));
        }
        $log = self::getLog(TRUE);

        $tuffyLoaded = class_exists('Tuffy', FALSE);
        if ($tuffyLoaded && !Tuffy::setting('debug')) {
            $callback = $tuffyLoaded
                      ? Tuffy::setting('errorHandlerProduction',
                                       self::PRODUCTION_HANDLER)
                      : self::PRODUCTION_HANDLER;
        } else {
            $callback = $tuffyLoaded
                      ? Tuffy::setting('errorHandlerDev', self::DEV_HANDLER)
                      : self::DEV_HANDLER;
        }
        call_user_func($callback, $exc, $log);
        return TRUE;
    }

    private static $errorTypes = array(
        E_ERROR =>          'Fatal error: ',
        E_CORE_ERROR =>     'PHP core error: ',
        E_COMPILE_ERROR =>  'Compile error: ',
        E_PARSE =>          'Parse error: '
    );

    public static function handleShutdown () {
        if (!self::$_handlersActive) {
            return;
        }
        $error = error_get_last();
        if ($error === NULL) {
            return;
        }
        $errorType = maybe(self::$errorTypes, $error['type'], '');
        return self::handleException(new ErrorException(
            $errorType . $error['message'], 0, $error['type'],
            $error['file'], $error['line']
        ));
    }

    /**
     * Registers Tuffy's error, exception, and shutdown handlers.
     * This is called automatically by Tuffy/Init.php.
     */
    public static function registerHandlers () {
        self::$_handlersActive = TRUE;
        set_error_handler('Tuffy_Debug::handleError');
        set_exception_handler('Tuffy_Debug::handleException');
        register_shutdown_function('Tuffy_Debug::handleShutdown');
    }

    /**
     * Unregisters Tuffy's error, exception, and shutdown handlers, with
     * some caveats. Since shutdown functions are not unregisterable, all it
     * does for handleShutdown is set a flag that causes it to return. And
     * for the error and exception functions, properly unregistering them
     * is contingent on them being the actual exception handlers at call time.
     */
    public static function unregisterHandlers () {
        self::$_handlersActive = FALSE;
        restore_exception_handler();
        restore_error_handler();
    }
}


/**
 * This represents a single message in the debug log. It tracks an assortment
 * of useful information.
 */
class Tuffy_Debug_Message {
    private $title;
    private $data;
    private $stack;
    private $time;
    private $completeTime = NULL;
    private $flags;

    /**
     * Initializes the debug message.
     *
     * @param string $title A label for the debug message describing its data.
     * @param mixed $data The data to display.
     * @param Tuffy_Debug_StackFrame[] $stack The backtrace from when the
     * message was generated. (This should be treated with
     * Tuffy_Debug_StackFrame::rewriteBacktrace()).
     * @param int $flags Flags describing the message. Tuffy_Debug::PROBLEM
     * is the only one used currently.
     * @param float $time The time relative to the request start at which the
     * message was generated.
     */
    public function __construct ($title, $data, $stack, $flags, $time = NULL) {
        $this->title = $title;
        if (!is_string($data)) {
            if (is_object($data) && method_exists($data, 'toDebug')) {
                $data = $data->toDebug();
            } else {
                $data = var_export($data, TRUE);
            }
        }
        $this->data = $data;
        $this->stack = $stack;
        $this->flags = $flags;
        $this->time = $time === NULL
                    ? microtime(TRUE) - REQUEST_START_TIME : $time;
    }

    public function getTitle () {
        return $this->title;
    }

    public function getData () {
        return $this->data;
    }

    public function getStack () {
        return $this->stack;
    }

    public function getTime () {
        return $this->time;
    }

    /**
     * Formats the time(s) associated with this message as a string.
     */
    public function getTimeString () {
        return $this->completeTime
             ? sprintf("start: %.4f sec, end: %.4f sec, time: %.4f sec",
                       $this->time, $this->completeTime,
                       $this->completeTime - $this->time)
             : sprintf("at: %.4f sec", $this->time);
    }

    /**
     * Returns TRUE if the PROBLEM flag has been set. Messages that represent
     * problems should be highlighted.
     */
    public function isProblem () {
        return $this->flags & Tuffy_Debug::PROBLEM;
    }

    /**
     * Adds a complete time to this message. Useful for things like queries
     * and HTTP requests that take some amount of time.
     *
     * @param float|null $time If this is NULL, use the current time.
     * Otherwise, this is the time relative to the request's start time.
     */
    public function complete ($time = NULL) {
        $this->completeTime = $time === NULL
                            ? (microtime(TRUE) - REQUEST_START_TIME)
                            : $time;
    }
}


/**
 * Represents a stack frame as part of a backtrace. This uses Python-format
 * backtraces, as opposed to the PHP backtraces which mismatch functions with
 * files and line numbers (at least in my opinion).
 */
class Tuffy_Debug_StackFrame {
    public $class;
    public $function;
    public $file;
    public $line;
    public $type;

    /**
     * Takes an exception and generates an array of Tuffy_Debug_StackFrames.
     *
     * @param Exception $exc The traceback to read the exception from.
     */
    public static function rewriteBacktraceFromException ($exc) {
        return self::rewriteBacktrace($exc->getTrace(), $exc->getFile(),
                                      $exc->getLine());
    }

    /**
     * Takes a backtrace as returned by debug_backtrace or
     * Exception->getTrace() and rewrites it into an array of
     * Tuffy_Debug_StackFrames.
     *
     * @param array $tb The backtrace to rewrite.
     * @param string|null $fFile The file that the backtrace was generated at.
     * @param int|null $fLine The line that the backtrace was generated at.
     */
    public static function rewriteBacktrace ($tb, $fFile = NULL, $fLine = NULL) {
        if ($fFile || $fLine) {
            array_unshift($tb, array('file' => $fFile, 'line' => $fLine));
        }
        $level = 1;
        $calls = array();
        $count = count($tb);

        while ($level < $count) {
            $name = $tb[$level];
            $loc = $tb[$level - 1];
            $calls[] = new self(
                maybe($name, 'class'), maybe($name, 'type'),
                maybe($name, 'function'), maybe($loc, 'file'),
                maybe($loc, 'line')
            );
            $level++;
        }
        $loc = $tb[$level - 1];
        $calls[] = new self(NULL, NULL, '[main]', maybe($loc, 'file'),
                            maybe($loc, 'line'));
        return $calls;
    }

    /**
     * Initializes the new stack frame.
     *
     * @param string|null $class The name of the class associated with this
     * call.
     * @param string|null $type The type of call - `->`, `::`, or NULL.
     * @param string|null $function The name of the function associated with
     * this call.
     * @param string|null $file The name of the file associated with this
     * call.
     * @param int|null $line The line number associated with this call.
     */
    public function __construct ($class, $type, $function, $file, $line) {
        $this->class = $class;
        $this->type = $type;
        $this->function = $function;
        $this->file = strpos($file, TUFFY_APP_PATH) === 0
                    ? substr($file, strlen(TUFFY_APP_PATH)) : $file;
        $this->line = $line;
    }

    /**
     * The qualified name of the function that generated this call.
     */
    public function getName () {if (is_object($data) && method_exists($data, 'toDebug')) {
                    $data = $data->toDebug();
                } else {
                    $data = var_export($data, TRUE);
                }
        return isset($this->type)
             ? ($this->class . $this->type . $this->function)
             : $this->function;
    }

    /**
     * The file and line where this call was generated, together.
     */
    public function getLocation () {
        return $this->file === NULL && $this->line === NULL
             ? "[internal code]"
             : $this->file . ':' . $this->line;
    }

    public function __toString () {
        return $this->getName() === "[main]"
             ? $this->getLocation()
             : $this->getName() . "() at " . $this->getLocation();
    }
}



