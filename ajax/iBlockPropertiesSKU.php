<?php
if (!\Bitrix\Main\Loader::includeModule('iblock')) {
    die();
}
if (!\Bitrix\Main\Loader::includeModule('catalog')) {
    die();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/modules/labsu.farpost/lib/Request.php';

$iblock = LSFarpostRequest::getInt('IBLOCK_ID');

if ($iblock <= 0) {
    die();
}

$offerProps = "";
$offerProperties = CCatalogSKU::GetInfoByProductIBlock($iblock);
if(is_array($offerProperties)){
    $dbOffersProperty = CIBlockProperty::GetList(
        array("SORT" => "ASC"),
        array("IBLOCK_ID" => $offerProperties['IBLOCK_ID'])
    );
    while ($arOfferProperty = $dbOffersProperty->Fetch())
    {
		if($arOfferProperty["CODE"] === 'MORE_PHOTO')
		{
			continue;
		}
        $offerProps .= "<option value='".(int)$arOfferProperty["ID"]."'>".htmlspecialcharsbx($arOfferProperty["NAME"])." [ID=".(int)$arOfferProperty["ID"]."]</option>";
    }
}
echo $offerProps;
