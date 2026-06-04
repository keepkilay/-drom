<?php

class LSFarpostPictureType
{
    public const PROPERTY_PREFIX = 'PROP:';

    public static function isPropertyType($pictureType): bool
    {
        return is_string($pictureType) && strpos($pictureType, self::PROPERTY_PREFIX) === 0;
    }

    public static function getPropertyCode($pictureType): string
    {
        if (!self::isPropertyType($pictureType)) {
            return '';
        }

        return substr($pictureType, strlen(self::PROPERTY_PREFIX));
    }

    public static function toPropertyType(string $propertyCode): string
    {
        return self::PROPERTY_PREFIX . $propertyCode;
    }

    /**
     * @return array<int, array>
     */
    public static function getFilesFromProperty(array $item, string $propertyCode): array
    {
        if ($propertyCode === '' || empty($item['PROPERTIES'][$propertyCode]['VALUE'])) {
            return [];
        }

        $values = $item['PROPERTIES'][$propertyCode]['VALUE'];
        if (!is_array($values)) {
            $values = [$values];
        }

        $files = [];
        foreach ($values as $fileId) {
            if (empty($fileId)) {
                continue;
            }
            $file = is_array($fileId) ? $fileId : CFile::GetFileArray($fileId);
            if (!empty($file['SRC'])) {
                $files[] = $file;
            }
        }

        return $files;
    }

    protected static function ensureDependencies(): void
    {
        if (!class_exists('LSStatic', false)) {
            require_once __DIR__ . '/lsstatic.php';
        }
    }

    /** ID инфоблока catalog для списка свойств «Файл» (fallback — первый каталог в форме). */
    public static function resolveCatalogIblockId(int $fallback = 0): int
    {
        if (\Bitrix\Main\Loader::includeModule('iblock')) {
            $rs = CIBlock::GetList([], ['CODE' => 'catalog', 'ACTIVE' => 'Y']);
            if ($row = $rs->Fetch()) {
                return (int)$row['ID'];
            }
        }

        return $fallback > 0 ? $fallback : 4;
    }

    public static function renderOptionsHtml(int $iblockId, string $selected = 'PREVIEW_PICTURE'): string
    {
        self::ensureDependencies();

        $html = '<option value="PREVIEW_PICTURE"' . ($selected === 'PREVIEW_PICTURE' ? ' selected' : '') . '>'
            . htmlspecialcharsbx(LSStatic::message('LS_FARPOST_HEAD6_1_PREVIEW')) . '</option>';
        $html .= '<option value="DETAIL_PICTURE"' . ($selected === 'DETAIL_PICTURE' ? ' selected' : '') . '>'
            . htmlspecialcharsbx(LSStatic::message('LS_FARPOST_HEAD6_1_DETAIL')) . '</option>';

        if ($iblockId <= 0) {
            return $html;
        }

        $dbIBlockProperty = CIBlockProperty::GetList(
            ['SORT' => 'ASC', 'NAME' => 'ASC'],
            ['IBLOCK_ID' => $iblockId, 'PROPERTY_TYPE' => 'F', 'ACTIVE' => 'Y']
        );

        while ($arProperty = $dbIBlockProperty->Fetch()) {
            $value = self::toPropertyType((string)$arProperty['CODE']);
            $label = $arProperty['NAME'] . ' [' . $arProperty['CODE'] . ']';
            if ($arProperty['MULTIPLE'] === 'Y') {
                $label .= ' (' . LSStatic::message('LS_FARPOST_HEAD6_1_MULTIPLE') . ')';
            }
            $html .= '<option value="' . htmlspecialcharsbx($value) . '"' . ($selected === $value ? ' selected' : '') . '>'
                . htmlspecialcharsbx($label) . '</option>';
        }

        return $html;
    }
}
