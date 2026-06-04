<?php
if (!\Bitrix\Main\Loader::includeModule('iblock')) {
    die();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/modules/labsu.farpost/lib/Request.php';

$iblock = LSFarpostRequest::getInt('IBLOCK_ID');

if ($iblock <= 0) {
	die();
}

$rsSections = CIBlockSection::GetList(Array("LEFT_MARGIN"=>"ASC"), Array("IBLOCK_ID"=>$iblock), false, Array("ID", "NAME", "DEPTH_LEVEL"));
$result = "";

while($arSection = $rsSections->GetNext())
{
	$result .= "<option value='".(int)$arSection["ID"]."'>".str_repeat("-> ", $arSection["DEPTH_LEVEL"] - 1).htmlspecialcharsbx($arSection["NAME"])."</option>";
}

echo $result;
