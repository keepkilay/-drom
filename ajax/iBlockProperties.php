<?php
if (!\Bitrix\Main\Loader::includeModule('iblock')) {
    die();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/modules/labsu.farpost/lib/Request.php';

$iblock = LSFarpostRequest::getInt('IBLOCK_ID');

if ($iblock <= 0) {
    die();
}
$dbIBlockProperty = CIBlockProperty::GetList(
    array("SORT" => "ASC"),
    array("IBLOCK_ID" => $iblock)
);
$props = "";
while ($arIBlockProperty = $dbIBlockProperty->Fetch())
{
	if($arIBlockProperty["CODE"] === 'MORE_PHOTO')
	{
		continue;
	}
	$props .= "<option value='".(int)$arIBlockProperty["ID"]."'>".htmlspecialcharsbx($arIBlockProperty["NAME"])." [ID=".(int)$arIBlockProperty["ID"]."]</option>";
}

echo $props;
