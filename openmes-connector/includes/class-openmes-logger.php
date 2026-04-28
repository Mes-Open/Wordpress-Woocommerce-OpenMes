<?php
/**
 * Logger wrapper around WC_Logger.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class OpenMES_Logger
{
    private const SOURCE = 'openmes-connector';

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    private static function log(string $level, string $message, array $context): void
    {
        if (!function_exists('wc_get_logger')) {
            return;
        }

        $logger = wc_get_logger();
        $logger->log(
            $level,
            '[OpenMES] ' . $message,
            array_merge(['source' => self::SOURCE], $context)
        );
    }

    public static function truncate(string $text, int $max_length = 500): string
    {
        if (strlen($text) <= $max_length) {
            return $text;
        }
        return substr($text, 0, $max_length) . '... [truncated]';
    }
}
