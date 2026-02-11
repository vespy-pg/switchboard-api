<?php

namespace App\Logging;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;

class Debug
{
    private static ?LoggerInterface $loggerSymfony = null;
    private static ?LoggerInterface $loggerApp = null;

    public static function setLogger(LoggerInterface $logger): void
    {
        self::$loggerSymfony = $logger;
    }

    private static function getLoggerApp(): LoggerInterface
    {
        if (self::$loggerApp === null) {
            self::$loggerApp = new Logger('app');
            self::$loggerApp->pushHandler(new StreamHandler(__DIR__ . '/../../var/log/app.log'));
        }
        return self::$loggerApp;
    }

    public static function log(string $message, array $context = []): void
    {
        if (self::$loggerSymfony) {
            self::$loggerSymfony->debug($message, $context);
        }
        self::getLoggerApp()->debug($message, $context);
    }
}
