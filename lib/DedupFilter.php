<?php

/**
 * Исключение дублей в выгрузке Farpost/Drom по связке OEM + CML2_MANUFACTURER (Производитель).
 */
class LSFarpostDedup
{
    const OEM_CODE = 'OEM';
    const MANUFACTURER_CODE = 'CML2_MANUFACTURER';
    const OEM_PROPERTY_ID = 27;
    const DEFAULT_IBLOCK_ID = 4;

    public static function getStateFilePath($profileCode = '')
    {
        $suffix = ($profileCode !== '' && $profileCode !== null) ? '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $profileCode) : '';
        return $_SERVER['DOCUMENT_ROOT'] . '/farpost/.dedup_oem_manufacturer' . $suffix . '.json';
    }

    public static function reset($profileCode = '')
    {
        $file = self::getStateFilePath($profileCode);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * @param array $properties результат GetProperties()
     */
    public static function buildKey(array $properties, $elementId = 0, $iblockId = self::DEFAULT_IBLOCK_ID)
    {
        $oem = self::extractPropertyValue($properties, self::OEM_CODE, self::OEM_PROPERTY_ID, $elementId, $iblockId);
        $manufacturer = self::extractPropertyValue($properties, self::MANUFACTURER_CODE, 0, $elementId, $iblockId);

        if ($oem === '' || $manufacturer === '') {
            return null;
        }

        return $oem . '|' . $manufacturer;
    }

    public static function isDuplicateAndRemember(array $properties, $profileCode = '', $elementId = 0, $iblockId = self::DEFAULT_IBLOCK_ID)
    {
        $key = self::buildKey($properties, $elementId, $iblockId);
        if ($key === null) {
            return false;
        }

        $file = self::getStateFilePath($profileCode);
        $state = [];
        if (is_file($file)) {
            $decoded = json_decode((string)file_get_contents($file), true);
            if (is_array($decoded)) {
                $state = $decoded;
            }
        }

        if (isset($state[$key])) {
            return true;
        }

        $state[$key] = (int)$elementId;
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0755, true);
        }
        file_put_contents($file, json_encode($state, JSON_UNESCAPED_UNICODE), LOCK_EX);

        return false;
    }

    private static function extractPropertyValue(array $properties, $code, $propertyId, $elementId, $iblockId)
    {
        $resolvedPropertyId = $propertyId > 0 ? $propertyId : self::resolvePropertyIdByCode($iblockId, $code);

        $prop = self::findProperty($properties, $code, $resolvedPropertyId);
        $value = '';

        if ($prop !== null) {
            $value = self::resolvePropertyDisplayValue($prop);
        }

        if ($value === '' && $elementId > 0) {
            $value = self::loadPropertyValueFromDb($iblockId, $elementId, $code, $resolvedPropertyId);
        }

        return self::normalize($value);
    }

    private static function resolvePropertyIdByCode($iblockId, $code)
    {
        static $cache = [];

        $key = (int)$iblockId . ':' . $code;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $cache[$key] = 0;
        if (\Bitrix\Main\Loader::includeModule('iblock')) {
            $row = \CIBlockProperty::GetList([], ['IBLOCK_ID' => (int)$iblockId, 'CODE' => $code])->Fetch();
            if ($row) {
                $cache[$key] = (int)$row['ID'];
            }
        }

        return $cache[$key];
    }

    private static function findProperty(array $properties, $code, $propertyId)
    {
        if (!empty($properties[$code]) && is_array($properties[$code])) {
            return $properties[$code];
        }
        if ($propertyId > 0 && !empty($properties[$propertyId]) && is_array($properties[$propertyId])) {
            return $properties[$propertyId];
        }

        foreach ($properties as $prop) {
            if (!is_array($prop)) {
                continue;
            }
            if (!empty($prop['CODE']) && $prop['CODE'] === $code) {
                return $prop;
            }
            if ($propertyId > 0 && !empty($prop['ID']) && (int)$prop['ID'] === (int)$propertyId) {
                return $prop;
            }
        }

        return null;
    }

    private static function loadPropertyValueFromDb($iblockId, $elementId, $code, $propertyId = 0)
    {
        if (!\Bitrix\Main\Loader::includeModule('iblock')) {
            return '';
        }

        $filter = $propertyId > 0 ? ['ID' => (int)$propertyId] : ['CODE' => $code];

        $parts = [];
        $res = \CIBlockElement::GetProperty((int)$iblockId, (int)$elementId, ['sort' => 'asc'], $filter);
        while ($row = $res->Fetch()) {
            if ($row['VALUE'] === '' || $row['VALUE'] === null) {
                continue;
            }
            $part = self::resolvePropertyDisplayValue($row);
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return implode(' ', array_unique($parts));
    }

    private static function resolvePropertyDisplayValue(array $prop)
    {
        $type = (string)($prop['PROPERTY_TYPE'] ?? '');

        if ($type === 'L') {
            return self::resolveListValue($prop);
        }

        $value = $prop['~VALUE'] ?? $prop['VALUE'] ?? '';

        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                if (is_array($item)) {
                    $parts[] = (string)($item['TEXT'] ?? $item['VALUE'] ?? reset($item));
                } else {
                    $parts[] = (string)$item;
                }
            }
            $value = implode(' ', array_filter($parts, static function ($v) {
                return $v !== '';
            }));
        }

        $value = trim((string)$value);

        if ($type === 'E' && $value !== '') {
            $linkedId = (int)(is_array($prop['VALUE']) ? reset($prop['VALUE']) : $prop['VALUE']);
            if ($linkedId > 0 && \Bitrix\Main\Loader::includeModule('iblock')) {
                $linked = \CIBlockElement::GetByID($linkedId)->GetNext();
                if (!empty($linked['NAME'])) {
                    return $linked['NAME'];
                }
            }
        }

        if ($value !== '' && !ctype_digit($value)) {
            return $value;
        }

        return $value;
    }

    private static function resolveListValue(array $prop)
    {
        if (!empty($prop['VALUE_ENUM']) && !is_array($prop['VALUE_ENUM'])) {
            return trim((string)$prop['VALUE_ENUM']);
        }

        $enumId = $prop['VALUE_ENUM_ID'] ?? null;
        if ($enumId === null || $enumId === '') {
            $raw = $prop['VALUE'] ?? null;
            $enumId = is_array($raw) ? reset($raw) : $raw;
        }

        if ($enumId !== null && $enumId !== '' && \Bitrix\Main\Loader::includeModule('iblock')) {
            $enum = \CIBlockPropertyEnum::GetByID($enumId);
            if (!empty($enum['VALUE'])) {
                return trim((string)$enum['VALUE']);
            }
            if (!empty($enum['XML_ID'])) {
                return trim((string)$enum['XML_ID']);
            }
        }

        $display = $prop['~VALUE'] ?? '';
        if (is_array($display)) {
            $display = implode(' ', $display);
        }

        return trim((string)$display);
    }

    private static function normalize($value)
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value));
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }
        return strtolower($value);
    }
}
