<?php

/**
 * Artax Bootstrap File
 * 
 * PHP version 5.4
 * 
 * ### QUICK START
 * 
 * You need to do one thing in order to fire up an Artax application: require
 * the **Artax.php** bootstrap file like below ...
 * 
 * `require '/hard/path/to/Artax.php'`
 * 
 * That's it. From there it's a simple matter of pushing event listeners onto
 * the `$artax` mediator object and (optionally) adding dependency definitions
 * (if necessary) to the `$axDeps` dependency provider instance. 
 * 
 * The only configuration directive to specify is an optional `AX_DEBUG` constant.
 * If not specified, application-wide debug output will be turned off. This
 * setting is appropriate for production environments. However, development
 * environments should turn debug mode on **before** including the bootstrap
 * file like so:
 * 
 * ```php
 * define('AX_DEBUG', TRUE)`;
 * require '/hard/path/to/Artax.php'
 * ```
 * 
 * Examples to get you started are available in the {%ARTAX_DIRECTORY%}/examples
 * directory.
 * 
 * For more detailed discussion checkout the [github wiki][wiki]
 * 
 * [wiki]: https://github.com/rdlowrey/Artax/wiki
 * 
 * @category Artax
 * @package  Core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

/*
 * --------------------------------------------------------------------
 * CHECK CONSTANTS & DEFINE AX_SYSTEM_DIR
 * --------------------------------------------------------------------
 */

// Require PHP 5.4+
if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50400) {
    die('Artax requires PHP 5.4 or higher' . PHP_EOL);
}

// Specify debug flag if it doesn't exist yet
if (!defined('AX_DEBUG')) {
    define('AX_DEBUG', FALSE);
}

// By convention Artax lib paths are resolved with a leading slash relative to 
// directory constants. Meanwhile, the `__DIR__` magic constant will return `/`
// if the directory is root. We avoid problems when using the root directory by
// setting `ARTAX_DIR` to an empty string if it's equal to the root directory.
define('AX_SYSTEM_DIR', __DIR__ === '/' ? '' : __DIR__);

// All errors are reported, even in production (but not displayed). The Artax 
// error handler will broadcast an `error` event whenever a PHP error occurs and
// you can set up listeners to determine how you want to handle the error.
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('html_errors', FALSE);

/*
 * --------------------------------------------------------------------
 * LOAD REQUIRED ARTAX LIBS
 * --------------------------------------------------------------------
 */

require AX_SYSTEM_DIR . '/Ioc/ProviderInterface.php';
require AX_SYSTEM_DIR . '/Ioc/Provider.php';
require AX_SYSTEM_DIR . '/Events/MediatorInterface.php';
require AX_SYSTEM_DIR . '/Events/Mediator.php';
require AX_SYSTEM_DIR . '/Handlers/FatalErrorException.php';
require AX_SYSTEM_DIR . '/Handlers/ScriptHaltException.php';
require AX_SYSTEM_DIR . '/Handlers/ErrorsInterface.php';
require AX_SYSTEM_DIR . '/Handlers/Errors.php';
require AX_SYSTEM_DIR . '/Handlers/TerminationInterface.php';
require AX_SYSTEM_DIR . '/Handlers/Termination.php';

/*
 * --------------------------------------------------------------------
 * BOOT THE EVENT MEDIATOR & DEPENDENCY CONTAINER
 * --------------------------------------------------------------------
 */

$axDeps = new Artax\Ioc\Provider;
$artax  = new Artax\Events\Mediator($axDeps);
$axDeps->share('Artax\Events\Mediator', $artax);

/*
 * --------------------------------------------------------------------
 * REGISTER ERROR & TERMINATION HANDLERS
 * --------------------------------------------------------------------
 */
 
(new Artax\Handlers\Termination($artax, AX_DEBUG))->register();
(new Artax\Handlers\Errors($artax, AX_DEBUG))->register();

if ('cli' === PHP_SAPI && function_exists('pcntl_signal')) {
    require AX_SYSTEM_DIR . '/Handlers/PcntlInterruptException.php';
    require AX_SYSTEM_DIR . '/Handlers/PcntlInterrupt.php';
    (new Artax\Handlers\PcntlInterrupt)->register();
}

