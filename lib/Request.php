<?php

use Bitrix\Main\Application;
use Bitrix\Main\HttpRequest;


class LSFarpostRequest
{
    public static function get(): HttpRequest
    {
        return Application::getInstance()->getContext()->getRequest();
    }

    public static function getString(string $name, string $default = ''): string
    {
        $value = self::get()->get($name);
        if ($value === null || is_array($value)) {
            return $default;
        }

        return (string)$value;
    }

    public static function getInt(string $name, int $default = 0): int
    {
        return (int)self::getString($name, (string)$default);
    }

    /** Параметр из query string (GET). */
    public static function getQueryString(string $name, string $default = ''): string
    {
        $value = self::get()->getQuery($name);
        if ($value === null || is_array($value)) {
            return $default;
        }

        return (string)$value;
    }

    /** Параметр из тела POST. */
    public static function getPostString(string $name, string $default = ''): string
    {
        $value = self::get()->getPost($name);
        if ($value === null || is_array($value)) {
            return $default;
        }

        return (string)$value;
    }

    public static function getPostInt(string $name, int $default = 0): int
    {
        return (int)self::getPostString($name, (string)$default);
    }

    public static function hasPost(string $name): bool
    {
        return self::get()->getPost($name) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPost(): array
    {
        return self::get()->getPostList()->toArray();
    }

    public static function getProfileCode(): string
    {
        $code = self::getPostString('profileCode');
        if ($code === '') {
            $code = self::getString('profileCode');
        }

        return preg_replace('/[^a-zA-Z0-9_\-]/', '', $code);
    }
}
