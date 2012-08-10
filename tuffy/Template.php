<?php

require defined('TUFFY_TEMPLATE_TWIG_PATH')
      ? TUFFY_TEMPLATE_TWIG_PATH
      : 'Twig/Autoloader.php';
Twig_Autoloader::register();


/**
 * This manages rendering Twig templates. Twig is amazing, and if you're not
 * using it, you should at least take a look.
 *
 * @author Matthew Frazier <mlfrazie@ncsu.edu>
 */
class Tuffy_Template {
    /**
     * The shared template environment. This will only exist if you use
     * Tuffy_Template::configure, or set it yourself.
     */
    public static $env;

    /**
     * Creates the shared template environment. This will register Tuffy's
     * default set of template helpers, and if you have defined the setting
     * `template.initializers`, it will call each of those functions in turn,
     * which should register more template helpers.
     *
     * This method will be called automatically if you define the
     * `modules.Tuffy_Template` setting to be TRUE.
     *
     * @param object $loader The loader to use. If this is NULL,
     * it will use the loader from Tuffy_Template::getDefaultLoader().
     * @param array $options Any additional options to pass to the Twig
     * environment. If not provided, it will load options from the
     * `template.twigOptions` setting.
     */
    public static function configure ($loader = NULL, $extraOptions = NULL) {
        if ($loader === NULL) {
            $loader = self::getDefaultLoader();
        }

        $options = array('debug' => Tuffy::setting('debug'));
        $settings = Tuffy::setting('template', array());
        
        if (array_key_exists('cachePath', $settings)) {
            $options['cache'] = $settings['cachePath'];
        }
        if ($extraOptions === NULL) {
            $extraOptions = maybe($settings, 'twigOptions', array());
        }
        $options = $extraOptions + $options;

        self::$env = new Twig_Environment($loader, $options);
        self::registerDefaults();
        
        foreach (maybe($settings, 'initializers', array()) as $init) {
            call_user_func($init);
        }
    }

    /**
     * Creates the default template loader. It will load templates from the
     * directory indicated by the `template.path` setting if defined,
     * otherwise it will use `TUFFY_APP_PATH/templates`.
     */
    public static function getDefaultLoader () {
        $templatePath = Tuffy_Util::interpretPath(
            Tuffy::setting('template.path', 'templates')
        );
        return new Twig_Loader_Filesystem($templatePath);
    }

    /**
     * Renders a template, and outputs it directly to the browser. This is
     * slightly faster than `echo Tuffy_Template::render` because it doesn't
     * have to buffer the output.
     *
     * @param string $template The name of the template to display.
     * @param array $context An array of variables for the template.
     */
    public static function display ($template, $context = array()) {
        self::$env->display($template, $context);
    }

    /**
     * Renders a template, and returns the output as a string.
     *
     * @param string $template The name of the template to display.
     * @param array $context An array of variables for the template.
     */
    public static function render ($template, $context = array()) {
        return self::$env->render($template, $context);
    }

    // Template customizations

    /**
     * Adds a global variable to the template environment.
     *
     * @param string $name The name of the global.
     * @param mixed $value The value to use for the global.
     */
    public static function addGlobal ($name, $value) {
        self::$env->addGlobal($name, $value);
    }

    /**
     * Adds a function to the shared template environment.
     *
     * @param string $name The name of the function. (If this includes a
     * Paamayim Nekudotayim, the class name will be removed.)
     * @param mixed $func This can be any callable, or a
     * Twig_FunctionInterface. If this is NULL, $name will be treated as
     * the function to register.
     * @param array $options Options to pass to the Twig_Function_Function
     * or Twig_Function_Method constructor.
     */
    public static function addFunction ($name, $func = NULL, $options = array()) {
        if ($func === NULL) {
            $func = new Twig_Function_Function($name, $options);
            $pos = strpos($name, '::');
            if ($pos !== FALSE) {
                $name = substr($name, $pos + 2);
            }
        } else if (is_string($func)) {
            $func = new Twig_Function_Function($func, $options);
        } else if (is_array($func)) {
            $func = new Twig_Function_Method($func[0], $func[1], $options);
        }
        self::$env->addFunction($name, $func);
    }

    /**
     * Adds a filter to the shared template environment.
     *
     * @param string $name The name of the function. (If this includes a
     * Paamayim Nekudotayim, the class name will be removed.)
     * @param mixed $func This can be any callable, or a
     * Twig_FilterInterface. If this is NULL, $name will be treated as
     * the function to register.
     * @param array $options Options to pass to the Twig_Filter_Function
     * or Twig_Filter_Method constructor.
     */
    public static function addFilter ($name, $func = NULL, $options = array()) {
        if ($func === NULL) {
            $func = new Twig_Filter_Function($name, $options);
            $pos = strpos($name, '::');
            if ($pos !== FALSE) {
                $name = substr($name, $pos + 2);
            }
        } else if (is_string($func)) {
            $func = new Twig_Filter_Function($func, $options);
        } else if (is_array($func)) {
            $func = new Twig_Filter_Method($func[0], $func[1], $options);
        }
        self::$env->addFilter($name, $func);
    }

    /**
     * Registers a default set of template functions, filter, and globals
     * to the shared template environment.
     */
    public static function registerDefaults () {
        // General
        // hash is very useful when creating ID's for ephemeral objects
        self::addFunction('hash', 'spl_object_hash');
        // Tuffy
        self::addFunction('Tuffy::url');
        self::addFunction('Tuffy::getFlashes');
        // Tuffy_Debug
        self::addGlobal('DEBUG', Tuffy::setting('debug'));
        self::addFunction('getDebugLog', 'Tuffy_Debug::getLog');
    }
}

