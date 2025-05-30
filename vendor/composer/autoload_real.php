<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInit498cd9734dd600df8e6d83e79142eacb
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\Autoload\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        spl_autoload_register(array('ComposerAutoloaderInit498cd9734dd600df8e6d83e79142eacb', 'loadClassLoader'), true, true);
        self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(__DIR__));
        spl_autoload_unregister(array('ComposerAutoloaderInit498cd9734dd600df8e6d83e79142eacb', 'loadClassLoader'));

        require __DIR__ . '/autoload_static.php';
        call_user_func(\Composer\Autoload\ComposerStaticInit498cd9734dd600df8e6d83e79142eacb::getInitializer($loader));

        $loader->register(true);

        return $loader;
    }
}
