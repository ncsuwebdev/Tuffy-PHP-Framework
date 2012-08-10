# Tuffy

Tuffy is halfway between a Web framework and a library. It's not designed
to completely change the way you write PHP Web applications, just to
give you a hand with the common stuff and the frustrating stuff.


## Origins

Tuffy was written at [ITECS](http://www.itecs.ncsu.edu/), the information
technology unit at the College of Engineering at NC State University,
primarily by Matthew Frazier. Because ITECS Web servers are available
to anyone in the College of Engineering, they are set up with numerous
security restrictions -- one that most modern PHP frameworks find it
difficult to work in.

Tuffy was written to support best-practice (or at least good-practice)
application development in this environment, while remaining lightweight
and flexible enough to support the wide range of applications that ITECS
deals in.


## Requirements

* PHP 5.2
* `register_globals` turned off

That's it! Tuffy doesn't require any optional PHP extensions, and it works
perfectly well even in the strict confines of `open_basedir`. It doesn't
need `mod_rewrite` either. And the rule about `register_globals` is likely
to go away in the future.


## Installation

1.  Drop the `tuffy` folder in your code somewhere.
2.  `require('path/to/tuffy/Init.php');` in all of the PHP scripts that
    receive requests, before the script does anything else.

Tuffy will automatically detect its own path (`TUFFY_PATH`) and the
application's path (`TUFFY_APP_PATH`). It then loads your application's config
file and sets up the autoloader. (It even fixes all your input if
`magic_quotes_gpc` is turned on.)

By default, Tuffy assumes that the directory your script is contained in
is your application's root. If your script is in a subfolder of the root,
`define('TUFFY_SCRIPT_DEPTH', 2)` before requiring `tuffy/Init.php`. If your
script is in a subfolder of a subfolder, `define('TUFFY_SCRIPT_DEPTH', 3)`,
and so on.


## Configuration

Tuffy automatically loads a settings file, where your app can define constants
to be used by the rest of Tuffy. By default, it will use
`TUFFY_APP_PATH/_data/settings.php`, but if you define `TUFFY_SETTINGS_FILE`
before requiring `tuffy/Init.php`, it will require that file (relative to
`TUFFY_APP_PATH`) instead.

The settings file is just a PHP file. Settings are defined as variables, like
this:

    $appName = 'MyBlog';
    $timezone = 'America/New_York';
    $libraryPath = '_data/lib';

If you don't want to use a settings file, you can define a $tuffySettings
variable before requiring Tuffy, which is an array of settings, like this:

    $tuffySettings = array(
        'useSessions' => FALSE
    );
    require('_data/vendor/tuffy/Init.php');

Anything you define in `$tuffySettings` overrides your settings file.
Some important settings include:


### Code Settings

* `appName`: This is a name for your app. It is used to namespace session
variables used by Tuffy, and also as a prefix for your app's library
classes if you use them. (For example, if your `appName` is `MyApp`, all
of your app's library classes take the format `MyApp_Class`.) This setting
is required.

* `libraryPath`: This is a path relative to your `TUFFY_APP_ROOT`. If this is
defined, Tuffy will install a loader that searches for classes beginning with
`appName` and an underscore in this path. (For `MyApp`, `MyApp_Class` would
be searched in `$libraryPath/Class.php`. It will *not* be searched for in
`$libraryPath/MyApp/Class.php`.)

* `initializers`: This is an array of functions that will always be called at
startup. (This is only intended for setting up the autoloaders of additional
libraries and the like.)


### Environment Settings

* `timezone`: This is your app's timezone, as would be passed to
`date_default_timezone_set`. If this is not defined, Tuffy will not call
`date_default_timezone_set` during setup -- which could result in horrible
warnings later on if you attempt to use the date functions.

* `useSessions`: This defaults to `TRUE`. If you set it to `FALSE`,
Tuffy will not start a session or use any session-backed functionality. You
can set it in your settings file to disable sessions across your app, or
set it in specific scripts that don't need session access.


### Error Handling Settings

* `debug`: This defaults to `FALSE`. If you set it to `TRUE`, Tuffy will
collect a bunch of debug information that you can display later if you wish.
(Note that this noticeably increases memory usage, but it is useful when
debugging complicated business logic.)

* `errorHandlerProduction`: This is a callback that will be called in the
event of an unhandled exception, when `debug` is `FALSE`. It should accept
the exception object and the current debugging log.

* `errorHandlerDev`: This is a callback that will be called in the event
of an unhandled exception, when `debug` is `TRUE`. It should accept the
exception object and the current debugging log.


## Class Loading

Rather than the strict one-class-per-file approach used by many PHP libraries,
Tuffy uses a more modular approach. After removing the prefix (e.g. `Tuffy_`,
`MyApp_`) from the class name, Tuffy splits the rest of it on underscores and
looks for a file corresponding to each group of components.

For example, if your app was named `MyApp` and `libraryPath` was defined
as `lib/`, the class `MyApp_Model_List_Helpers` would be searched for in:

* `lib/Model/List/Helpers.php`
* `lib/Model/List.php`
* `lib/Model.php`

Tuffy will `require` the first one of those files that it finds.


## Modules

Most of Tuffy's functionality besides the core is split into modules. Each
module lives in its own PHP file, and contains a group of classes. Note that
by default, loading a module won't set anything up like database connections
or template environments -- to do that, you must add a setting:

    $modules['Tuffy_ModuleName'] = TRUE;

Then, when Tuffy loads a module, it will call the `configure` static method
on the module's main class, if it exists. (For example, if you load
`Tuffy_Database_Statement`, Tuffy will call `Tuffy_Database::configure()`.)

Most modules configure themselves using Tuffy settings if necessary -- check
each module's inline documentation to find out what settings it uses.

`Init.php`, `Debug.php`, and `Loader.php` are not intended to be used as
modules. (`Loader.php` technically could be used as a module, but that would
be rather difficult since it actually contains the module loader.)


