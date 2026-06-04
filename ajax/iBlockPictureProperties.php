<?php
if (!\Bitrix\Main\Loader::includeModule('iblock')) {
    die();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/modules/labsu.farpost/lib/PictureType.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/modules/labsu.farpost/lib/Request.php';

$iblockId = LSFarpostRequest::getInt('IBLOCK_ID');
$selected = LSFarpostRequest::getString('SELECTED', 'PREVIEW_PICTURE');

if ($iblockId <= 0) {
    die();
}

echo LSFarpostPictureType::renderOptionsHtml($iblockId, $selected);
