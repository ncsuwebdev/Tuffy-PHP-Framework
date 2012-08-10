<?php

/**
 * This is a general autoloader class based on the concept of modules.
 * Instead of adhering to the one-class-per-file model, it breaks down a
 * class's name into parts by underscores, then removes parts from the end
 * and tries that file.
 *
 * @author Matthew Frazier <mlfrazie@ncsu.edu>
 */
class Tuffy_Loader {
    protected $baseModule;
    protected $baseDir;
    protected $overrides = array();

    /**
     * Creates a new autoloader.
     * 
     * @param string $baseModule The first component of the module name to
     * search for (e.g. "Tuffy").
     * @param string $baseDir The directory that all of $baseModule's class
     * files are contained in (i.e. with a $baseModule of "Tuffy", Tuffy_Curl
     * would be looked for in "$baseDir/Curl.php").
     * @param array $overrides This is a mapping of module names to the files
     * they are contained in.
     */
    public function __construct ($baseModule, $baseDir, $overrides = NULL) {
        $this->baseModule = $baseModule;
        $this->baseDir = rtrim($baseDir, '/\\') . '/';
        if (is_array($overrides)) {
            $this->overrides = $overrides;
        }
    }

    /**
     * Registers this loader on the SPL autoload stack.
     */
    public function register () {
        spl_autoload_register(array($this, 'loadClass'));
    }

    /**
     * Adds a new override for a module. The named $module will be loaded
     * from $file instead of the usual place. You can use this to set a
     * location for the base module.
     */
    public function override ($module, $file) {
        $this->overrides[$module] = $file;
    }

    /**
     * Finds the module that would contain a class and loads it.
     * 
     * @param string $className The full class name. This doesn't deal with
     * namespaces.
     */
    public function loadClass ($className) {
        $parts = explode('_', $className);
        if ($parts[0] !== $this->baseModule) {
            return;
        }

        for ($i = count($parts); $i > 0; $i--) {
            $module = $i === 1 ? $parts[0]
                    : implode('_', array_slice($parts, 0, $i));

            if (array_key_exists($module, $this->overrides)) {
                $filename = $this->overrides[$module];
            } else if ($i > 1) {
                $filename = $this->baseDir . (
                                $i === 2 ? $parts[1] :
                                implode('/', array_slice($parts, 1, $i - 1))
                            ) . '.php';
            } else {
                // We don't try to load the base module unless they set an
                // override.
                break;
            }

            if (is_file($filename)) {
                if ($module === 'Tuffy') {
                    // a bit of special casing to avoid circular requires
                    require($filename);
                    return;
                }
                Tuffy::debug("Loading Class", "$className from $filename",
                             0, 3);
                require($filename);
                $this->postLoad($module);
                return;
            }
        }
    }

    /**
     * After loading a module, loadClass calls this method. You can do
     * whatever you want to initialize the module's contents.
     *
     * @param string $module The name of the module that was just loaded.
     */
    public function postLoad ($module) {
        // this function intentionally left blank
    }
}


/**
 * This is a specialized Tuffy_Loader that calls the `::configure` method
 * on the main class of a module after it is loaded.
 */
class Tuffy_Loader_Configuring extends Tuffy_Loader {
    /**
     * Decides whether to configure the module or not. The default
     * implementation ALWAYS configures the module if the method exists.
     * You may wish to override this.
     *
     * @param string $module The module that was just loaded.
     */
    public function shouldConfigure ($module) {
        return TRUE;
    }

    /**
     * Calls `::configure` on the loaded module. (This will only call
     * `::configure` if it actually exists, so you shouldn't need to worry
     * about bizarre errors.)
     *
     * @param string $module The module to configure.
     */
    public function postLoad ($module) {
        $initMethod = $module . '::configure';
        if (is_callable($initMethod) && $this->shouldConfigure($module)) {
            call_user_func($initMethod);
        }
    }
}


/**
 * This is the loader that Tuffy actually uses to load itself. It is a
 * subclass of `Tuffy_Loader_Configuring`.
 */
class Tuffy_Loader_ForTuffy extends Tuffy_Loader_Configuring {
    /**
     * Decides whether to configure the module or not. If the configuration
     * setting configure.Module_Name is TRUE, it will configure it.
     *
     * @param string The module to check the settings for.
     */
    public function shouldConfigure ($module) {
        return (boolean)(Tuffy::setting("modules.$module"));
    }
}

