<?php
//v 2.0.9-transopt.2 (дедупликация OEM+CML2_MANUFACTURER — transopt, local/modules/labsu.farpost)
//Скрипт экспорта товаров в CSV для farpost.ru
//Экспорт пошаговый
//Запуск производится по настройкам в файле system.txt.
//Шаг 1 - создание временного файла и запись заголовков
//Шаг 2 - пошаговая выгрузка товаров
//Шаг 3 - перенос данных в основной файл, удаление временного файла, деактивирование запуска
error_reporting(1);

$writeLog = false; //настройка для включения логирования в /farpost/log.html

if (!class_exists('LSFarpostRequest', false)) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/modules/labsu.farpost/lib/Request.php';
}
$profileCode = LSFarpostRequest::getProfileCode();

/** Цена из типа цен каталога без скидок и без привязки к пользователю (cron/агент). */
function getFinalPriceInCurrency($item_id, $price_type, $cnt = 1, $getName = 'N', $sale_currency = 'RUB')
{
    $productName = '';
    $basePrice = 0.0;
    $currencyCode = $sale_currency;

    if (CCatalogSku::IsExistOffers($item_id)) {
        $res = CIBlockElement::GetByID($item_id);
        if ($arProduct = $res->GetNext()) {
            $productName = (string)$arProduct['NAME'];
            $skuInfo = CCatalogSKU::GetInfoByProductIBlock((int)$arProduct['IBLOCK_ID']);
            if (is_array($skuInfo)) {
                $rsOffers = CIBlockElement::GetList(
                    [],
                    [
                        'IBLOCK_ID' => (int)$skuInfo['IBLOCK_ID'],
                        'PROPERTY_CML2_LINK' => $item_id,
                        'ACTIVE' => 'Y',
                    ],
                    false,
                    false,
                    ['ID']
                );
                while ($offer = $rsOffers->Fetch()) {
                    $dbPrice = CPrice::GetList(
                        ['ID' => 'ASC'],
                        ['PRODUCT_ID' => (int)$offer['ID'], 'CATALOG_GROUP_ID' => $price_type],
                        false,
                        false,
                        ['PRICE', 'CURRENCY']
                    );
                    if ($row = $dbPrice->Fetch()) {
                        $p = (float)$row['PRICE'];
                        if ($p > 0 && ($basePrice <= 0 || $p < $basePrice)) {
                            $basePrice = $p;
                            $currencyCode = (string)$row['CURRENCY'];
                        }
                    }
                }
            }
        }
    } else {
        $dbPrice = CPrice::GetList(
            ['ID' => 'ASC'],
            ['PRODUCT_ID' => $item_id, 'CATALOG_GROUP_ID' => $price_type],
            false,
            false,
            ['PRICE', 'CURRENCY']
        );
        if ($row = $dbPrice->Fetch()) {
            $basePrice = (float)$row['PRICE'];
            $currencyCode = (string)$row['CURRENCY'];
        }
        if ($getName === 'Y') {
            $res = CIBlockElement::GetByID($item_id);
            if ($arProduct = $res->GetNext()) {
                $productName = (string)$arProduct['NAME'];
            }
        }
    }

    $finalPrice = $basePrice;
    if ($currencyCode !== '' && $currencyCode !== $sale_currency && $basePrice > 0) {
        $finalPrice = (float)CCurrencyRates::ConvertCurrency($basePrice, $currencyCode, $sale_currency);
    }

    return [
        'PRICE' => $finalPrice,
        'FINAL_PRICE' => $finalPrice,
        'CURRENCY' => $sale_currency,
        'DISCOUNT' => [],
        'DEBUG' => ['ITEM_ID' => $item_id],
        'NAME' => $productName,
    ];
}

/** Опциональный % из настроек профиля Farpost (не скидки каталога Bitrix). */
function lsFarpostApplyProfilePriceDiscount(float $basePrice, array $iBlockConfig): float
{
    if ($basePrice <= 0 || empty($iBlockConfig['discount']) || $iBlockConfig['discount'] === '') {
        return $basePrice;
    }

    return $basePrice * (100 - (float)$iBlockConfig['discount']) / 100;
}

function writeDataToFile($fname, $data, $isFirst = false)
{
    $handle = (!$isFirst) ? fopen($fname, "a+") : fopen($fname, "w");
    if (!$handle) {
        return;
    }
    if(flock($handle, LOCK_EX))
    {
        fwrite($handle, $data);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
}

if(!function_exists('getProductCountStore'))
{
    function getProductCountStore($id, $stores)
    {
        $res = CCatalogStore::GetList(
            array('PRODUCT_ID'=>'ASC','ID' => 'ASC'),
            array('ACTIVE' => 'Y','PRODUCT_ID'=>$id),
            false,
            false,
            array("ID","TITLE","ACTIVE","PRODUCT_AMOUNT","ELEMENT_ID")
         );
         /*$res = CCatalogStore::GetList(
            array('PRODUCT_ID'=>'ASC','ID' => 'ASC'),
            array('ACTIVE' => 'Y'),
            false,
            false,
            array("ID","TITLE","ACTIVE")
        );*/
        $count = 0;
        while($r = $res->GetNext())
        {
            if(in_array($r["ID"], $stores))
            {
                $count += $r["PRODUCT_AMOUNT"]*1;
            }
        }
        return $count;
    }
}

function getPicturesWithCorrectFormat($item, $pictureType, $pictureType2, $configUrl, $getMorePhoto = true)
{
    $arResult = array();
    $primaryPropertyCode = '';

    if (LSFarpostPictureType::isPropertyType($pictureType)) {
        $primaryPropertyCode = LSFarpostPictureType::getPropertyCode($pictureType);
        $propertyFiles = LSFarpostPictureType::getFilesFromProperty($item, $primaryPropertyCode);
        if (!empty($propertyFiles)) {
            $firstPicture = array_shift($propertyFiles);
            $arResult[] = '<picture>' . $configUrl . $firstPicture['SRC'] . '</picture>';
            if ($getMorePhoto) {
                foreach ($propertyFiles as $image) {
                    $arResult[] = '<picture>' . $configUrl . $image['SRC'] . '</picture>';
                }
            }
        }
    } else {
        $picture = '';
        if (!empty($item[$pictureType])) {
            $picture = (is_array($item[$pictureType])) ? $item[$pictureType] : CFile::GetFileArray($item[$pictureType]);
        } else if (!empty($item[$pictureType2])) {
            $picture = (is_array($item[$pictureType2])) ? $item[$pictureType2] : CFile::GetFileArray($item[$pictureType2]);
        }
        if (!empty($picture)) {
            $arResult[] = '<picture>' . $configUrl . $picture['SRC'] . '</picture>';
        }
    }

    if ($getMorePhoto) {
        $photoPropertyCodes = ['MORE_PHOTO', 'PHOTOS'];
        foreach ($photoPropertyCodes as $photoCode) {
            if ($primaryPropertyCode !== '' && $photoCode === $primaryPropertyCode) {
                continue;
            }
            if (empty($item['PROPERTIES'][$photoCode]['VALUE'])) {
                continue;
            }
            $photoValues = $item['PROPERTIES'][$photoCode]['VALUE'];
            if (!is_array($photoValues)) {
                $photoValues = [$photoValues];
            }
            foreach ($photoValues as $imageItem) {
                if (empty($imageItem)) {
                    continue;
                }
                $image = is_array($imageItem) ? $imageItem : CFile::GetFileArray($imageItem);
                if (!empty($image['SRC'])) {
                    $arResult[] = '<picture>' . $configUrl . $image['SRC'] . '</picture>';
                }
            }
        }
    }
    return $arResult;
}



// удаляет все html тэги в тексте.
function rip_tags($string) {

    // ----- remove HTML TAGs -----
    $string = strip_tags((string)$string);

    // ----- remove control characters -----
    $string = str_replace("\r", '', $string);
    $string = str_replace("\n", ' ', $string);
    $string = str_replace("\t", ' ', $string);

    // ----- remove multiple spaces -----
    $string = trim(preg_replace('/ {2,}/', ' ', $string));

    return $string;

}

function prepareStrToXml($str)
{
    return str_replace('&', '&amp;', $str);
    //$tpl_output = preg_replace('/&[^; ]{0,6}.?/e', "((substr('\\0',-1) == ';') ? '\\0' : '&'.substr('\\0',1))", $str);
    //$tpl_output = preg_replace("/&(?![A-Za-z]{0,4}\w{2,3};|#[0-9]{2,3};)/","&" ,html_entity_decode($tpl_output, ENT_COMPAT, 'UTF-8'));
    //return $tpl_output;
}

function toUtf($str, $isCData = false)
{
    $is_utf = false;
    if(defined('BX_UTF'))
    {
        $is_utf = (BX_UTF)? true: false;
    }
    $str = prepareStrToXml($str);
    if(!$is_utf)
    {
        $str = iconv("windows-1251", "utf-8", trim($str));
    }
    if($isCData)
    {
        $str = '<![CDATA['.$str.']]>';
    }
    return $str;
}

function setConfigArray($var, $mean, $arConfig, $profileCode='')
{
    if(!empty($profileCode))
    {
        $arConfig['profiles'][$profileCode][$var] = $mean;
    }
    else
    {
        $arConfig[$var] = $mean;
    }
    return $arConfig;
}

function getConfigArray($arConfig, $profileCode)
{
    $res = array();
    if(!empty($profileCode))
    {
        $res = $arConfig['profiles'][$profileCode];
    }
    else
    {
        $res = $arConfig;
    }
    return $res;
}

function LSFarpostDebugLog($message)
{
    $logFile = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/labsu.farpost/export/debug.txt';
    file_put_contents($logFile, date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL, FILE_APPEND);
}

function LSFarpostExport($profileCode='')
{
    $start_time = microtime(true);
    LSFarpostDebugLog('start ' . $start_time);
    $system_file = $_SERVER["DOCUMENT_ROOT"].'/local/modules/labsu.farpost/admin/system.json';
    /*
    if($_REQUEST["SET_START"] === "Y")
    {	$arConfig["START"] = 1;
        file_put_contents($system_file, json_encode($arConfig));
        die("Запуск установлен");}
    */
 /*    $stDate=new Datetime();
    file_put_contents($_SERVER['DOCUMENT_ROOT'].'/local/modules/labsu.farpost/export/debug.txt', "start -".$stDate .'" '.PHP_EOL, FILE_APPEND); */

    $arConfigFull = [];
    $arConfigProfiles = [];
    if(file_exists($system_file))
    {
        $conf = file_get_contents($system_file);
        $arConfigFull = json_decode($conf, true);   //Полный конфиг для изменения параметров
        if (!is_array($arConfigFull)) {
            $arConfigFull = [];
        }
        // Блокировка параллельного запуска (сброс «зависшей» блокировки через 30 мин)
        $exportLockTimeout = 1800;
        if (isset($arConfigFull['export_started']) && $arConfigFull['export_started'] === 'Y') {
            $lockStartedAt = (int)($arConfigFull['export_started_at'] ?? 0);
            if ($lockStartedAt > 0 && (time() - $lockStartedAt) < $exportLockTimeout) {
                LSFarpostDebugLog('exit: export lock active');
                return "LSFarpostExport('');";
            }
            $arConfigFull = setConfigArray('export_started', 'N', $arConfigFull);
            $arConfigFull = setConfigArray('export_started_at', 0, $arConfigFull);
            file_put_contents($system_file, json_encode($arConfigFull, JSON_PRETTY_PRINT));
        }
        $profileCodes = [];
        foreach (($arConfigFull['profiles'] ?? []) as $k => $value) {
            if(!empty($value["start"])){
                if($value["start"]*1 === 1){
                    $profileCodes[] = $k;
                }
            }
        }
        if(!empty($arConfigFull['start']) && $arConfigFull['start']*1 == 1)
        {
            $profileCodes[] = "";
        }
        if(count($profileCodes) < 1) {
            LSFarpostDebugLog('exit: start=0, export not requested (click "Запустить выгрузку" in admin)');
            return "LSFarpostExport('');";
            //$arConfigProfile = $arConfigFull;
         } else{
            foreach($profileCodes as $profileCode)
            {
                $arConfigProfiles[$profileCode] = getConfigArray($arConfigFull, $profileCode); //конфиг профиля для работы с сохраненными данными
            }
         }

    }
    else
    {
        return "LSFarpostExport('');";
    }

    if(count($arConfigProfiles) < 1)
    {
        return "LSFarpostExport('');";
    }

    $clStatic = new LSStatic($_SERVER['DOCUMENT_ROOT'].'/farpost/log-'.date('Y-m-d').'.html');

    $arConfigFull = setConfigArray('export_started', 'Y', $arConfigFull);
    $arConfigFull = setConfigArray('export_started_at', time(), $arConfigFull);
    file_put_contents($system_file, json_encode($arConfigFull, JSON_PRETTY_PRINT));


    foreach($arConfigProfiles as $profileCode=>$arConfigProfile)
    {

        $tmp_file = (!empty($profileCode))? $_SERVER['DOCUMENT_ROOT'] . "/farpost/xml_tmp_".$profileCode.".txt": $_SERVER['DOCUMENT_ROOT'] . "/farpost/xml_tmp.txt";
        $export_file = (!empty($profileCode))? $_SERVER['DOCUMENT_ROOT'] . "/farpost/export_".$profileCode.".xml": $_SERVER['DOCUMENT_ROOT'] . "/farpost/export.xml";
        if(!file_exists($tmp_file))
        {
            $f_hdl = fopen($tmp_file, 'w');
            fclose($f_hdl);
        }
    
        if($arConfigProfile["start"]*1 === 0)
        {
            continue;
            //return "LSFarpostExport('');";
        }
        if($writeLog)
        {
            $clStatic->toLog('PROFILE_CODE = '.$profileCode);
        }

        $load_cnt = $arConfigProfile["gNumber"]; //количество одновременно выгружаемых товаров
        if ($arConfigProfile["step"] * 1 <= 1) {

            if (!is_dir($_SERVER["DOCUMENT_ROOT"].'/farpost')) {

                mkdir($_SERVER["DOCUMENT_ROOT"].'/farpost');
            }

            if (class_exists('LSFarpostDedup')) {
                LSFarpostDedup::reset($profileCode);
            }

            $head = '<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE yml_catalog SYSTEM "shops.dtd"><yml_catalog date="' . date("Y-m-d H:i") . '"><shop><name>' . $arConfigProfile["shopName"] . '</name><company>' . $arConfigProfile["company"] . '</company><url>' . $arConfigProfile["url"] . '</url><currencies><currency id="RUR" rate="1"/></currencies><categories>';
            $sections = "";
            $key_exist = false;
            if(is_array($arConfigProfile["iBlock"]))
            {
                $key_exist = (array_key_exists("sections",$arConfigProfile["iBlock"]))? true: false;
            }
            if($key_exist) {
                foreach ($arConfigProfile["iBlock"]["sections"]["id"] as $section) {
                    $rsParentSection = CIBlockSection::GetByID($section);
                    if ($arParentSection = $rsParentSection->GetNext()) {
                        $sections .= '<category id="' . $arParentSection["ID"] . '">' . toUtf($arParentSection["NAME"]) . '</category>';
                        if ($arConfigProfile["iBlock"]["sections"]["subsections"] == "Y") {
                            $arFilter = array('IBLOCK_ID' => $arParentSection['IBLOCK_ID'], '>LEFT_MARGIN' => $arParentSection['LEFT_MARGIN'], '<RIGHT_MARGIN' => $arParentSection['RIGHT_MARGIN'], '>DEPTH_LEVEL' => $arParentSection['DEPTH_LEVEL']); // выберет потомков без учета активности
                            $rsSect = CIBlockSection::GetList(array('left_margin' => 'asc'), $arFilter);
                            while ($arSect = $rsSect->GetNext()) {
                            $sections .= '<category id="' . $arSect["ID"] . '">' . toUtf($arSect["NAME"]) . '</category>';
                            }
                        }
                    }
                }
            }else {
                $rsSection = CIBlockSection::GetList(Array("ID" => "ASC"), Array("IBLOCK_ID" => $arConfigProfile["iBlock"]["id"]), false, Array("ID", "NAME"));
                while ($sec = $rsSection->Fetch()) {
                    $sections .= '<category id="' . $sec["ID"] . '">' . toUtf($sec["NAME"]) . '</category>';
                }
            }
            $head .= $sections . '</categories><offers>';

            writeDataToFile($tmp_file, $head, true);

            //количество выгруженных товаров обнуляем
            $arConfigFull = setConfigArray("step", 2, $arConfigFull, $profileCode);
        } else if ($arConfigProfile["step"] * 1 == 2) {
            $cnt = 0;
            //загрузка данных по разделам
            $arSections = array();
            $iBlockOffer = CCatalogSKU::GetInfoByProductIBlock($arConfigProfile["iBlock"]["id"]); // торговые предложения
            $rsSection = CIBlockSection::GetList(Array("ID" => "ASC"), Array("IBLOCK_ID" => $arConfigProfile["iBlock"]["id"], "ACTIVE"=>'Y'), false, Array("ID", "NAME"));
            while ($sec = $rsSection->Fetch()) {
                $arSections[$sec["ID"]] = array("NAME" => $sec["NAME"]);
            }
            $key_exist = false;
            if(is_array($arConfigProfile["iBlock"]))
            {
                $key_exist = (array_key_exists("sections", $arConfigProfile["iBlock"]))? true: false;
            }
            if($key_exist) {
                $arFilter = Array(
                    "IBLOCK_ID" => $arConfigProfile["iBlock"]["id"],
                    "SECTION_ID" => $arConfigProfile["iBlock"]["sections"]["id"],
                    "INCLUDE_SUBSECTIONS" => $arConfigProfile["iBlock"]["sections"]["subsections"],
                    "ACTIVE" => "Y"
                , ">ID" => $arConfigProfile["gID"]
                );
            } else {
                $arFilter = Array(
                    "IBLOCK_ID" => $arConfigProfile["iBlock"]["id"],
                    "ACTIVE" => "Y"
                , ">ID" => $arConfigProfile["gID"]
                );
            }
            $arSelectFields = Array();
            if(!empty($arConfigProfile["iBlock"]["properties"]))
            {
                foreach($arConfigProfile["iBlock"]["properties"] as $item){
                    $rsPropertyCode = CIBlockProperty::GetByID($item, $arConfigProfile["iBlock"]["id"]);
                    if($ar_res = $rsPropertyCode->GetNext()){
                        $arSelectFields[] = $ar_res['CODE'];
                    }
                }
            }
            if(!is_array($arConfigProfile["iBlock"])){
                $arConfigProfile["iBlock"] = [];
            }
            if(is_array($arConfigProfile["iBlock"]) AND array_key_exists("offers", $arConfigProfile["iBlock"])) {
                $arSelectOffers = Array();
                foreach ($arConfigProfile["iBlock"]["offers"] as $item) {
                    $rsOfferCode = CIBlockProperty::GetByID($item, $iBlockOffer["IBLOCK_ID"]);
                    if ($ar_res_offer = $rsOfferCode->GetNext()) {
                        $arSelectOffers[] = $ar_res_offer['CODE'];
                    }
                }
            }
            if(is_array($arConfigProfile["iBlock"]) AND  array_key_exists("offersJoin", $arConfigProfile["iBlock"])) {
                $arSelectJoinOffers = Array();
                foreach ($arConfigProfile["iBlock"]["offersJoin"] as $item) {
                    $rsOfferCode = CIBlockProperty::GetByID($item, $iBlockOffer["IBLOCK_ID"]);
                    if ($ar_res_offer = $rsOfferCode->GetNext()) {
                        $arSelectJoinOffers[] = $ar_res_offer['CODE'];
                    }
                }
            }
            if($arConfigProfile["not_check_count"]*1 !== 1 && $arConfigProfile["check_store"]*1 !== 1)
            {
                $arFilter['CATALOG_AVAILABLE'] = 'Y';
            }
            $rsItems = CIBlockElement::GetList(Array("ID" => "ASC"), $arFilter, false, Array("nTopCount" => $load_cnt));
            $arElements = array();
            $scnt = 0;
            $ocnt = 0;
            $arItems = Array();
            if($writeLog)
            {
                $clStatic->toLog('start load, cnt = '.$load_cnt);
            }
            $pictureType = $arConfigProfile['iBlock']['pictureType'] ?? 'PREVIEW_PICTURE';
            if (!LSFarpostPictureType::isPropertyType($pictureType)) {
                $pictureType = (!empty($pictureType) && $pictureType === 'DETAIL_PICTURE') ? 'DETAIL_PICTURE' : 'PREVIEW_PICTURE';
                $pictureType2 = ($pictureType === 'PREVIEW_PICTURE') ? 'DETAIL_PICTURE' : 'PREVIEW_PICTURE';
            } else {
                $pictureType2 = 'PREVIEW_PICTURE';
            }

            while ($item = $rsItems->GetNextElement()) 
            {

                $tmp = $item->GetFields();
                $existOffers = CCatalogSKU::IsExistOffers($tmp['ID']);
                //проверяем количество товара на складах
                if($writeLog)
                {
                    $clStatic->toLog('existOffers g ['.$tmp['ID'].'] = '.$existOffers);
                    $clStatic->toLog('in stores g ['.$tmp['ID'].'] = '.($arConfigProfile["not_check_count"]*1 !== 1 && $arConfigProfile["check_store"]*1 === 1 && !$existOffers));
                }
                if($arConfigProfile["not_check_count"]*1 !== 1 && $arConfigProfile["check_store"]*1 === 1 && !$existOffers)
                {
                    if(!empty($arConfigProfile['store_id']))
                    {
                        $storeCount = getProductCountStore($tmp['ID'], $arConfigProfile['store_id']);

                        if($storeCount < 0.01)
                        {
                            $cnt++;
                            $arConfigFull = setConfigArray("gID", $tmp['ID'], $arConfigFull, $profileCode);
                            $arConfigProfile["gID"] = $tmp["ID"];
                            if($writeLog)
                            {
                                $clStatic->toLog('continue 1 ['.$tmp['ID'].'] cnt='.$storeCount);
                            }
                            continue;
                        }
                        else
                        {
                            if($writeLog)
                            {
                                $clStatic->toLog('store cnt g ['.$tmp['ID'].'] = '.$storeCount);
                            }
                        }
                    }
                }
                $tmp["PROPERTIES"] = $item->GetProperties();

                $dedupIblockId = (int)($arConfigProfile["iBlock"]["id"] ?? 0);
                if (class_exists('LSFarpostDedup') && LSFarpostDedup::isDuplicateAndRemember($tmp["PROPERTIES"], $profileCode, (int)$tmp["ID"], $dedupIblockId > 0 ? $dedupIblockId : 4)) {
                    $cnt++;
                    $arConfigFull = setConfigArray("gID", $tmp['ID'], $arConfigFull, $profileCode);
                    $arConfigProfile["gID"] = $tmp["ID"];
                    if ($writeLog) {
                        $clStatic->toLog('skip duplicate OEM+CML2_MANUFACTURER [' . $tmp['ID'] . ']');
                    }
                    continue;
                }

                $tmp["DETAIL_TEXT"] = str_replace('&ndash;', '-',rip_tags($tmp["DETAIL_TEXT"]));
                $props_str = "";
                $isVendorInProps = false;

                $arPictures = getPicturesWithCorrectFormat($tmp, $pictureType, $pictureType2, $arConfigProfile['url']);
                $props_str .= implode('', $arPictures);

                foreach($arSelectFields as $code){
                    if ($code === 'MORE_PHOTO' || $code === 'PHOTOS') {
                        continue;
                    } else {
                        if(empty($tmp["PROPERTIES"][$code]["~VALUE"]))
                        {
                            continue;
                        }
                        //если выбрано свойстро свойство "Брэнд" или "Производитель"
                        if((strpos($code, 'MANUFACTURER') !== false)||(strpos($code, 'BREND') !== false) || (strpos($code, 'BRAND') !== false) || (strpos($code, 'VENDOR') !== false)){
                            $tmp["PROPERTIES"][$code]["~VALUE"] = '<vendor>'.toUtf($tmp["PROPERTIES"][$code]["~VALUE"]).'</vendor>';
                            $isVendorInProps = true;
                        } else {
                            $val = is_array($tmp["PROPERTIES"][$code]["VALUE"])
                                ? implode(", ", (array)$tmp["PROPERTIES"][$code]["~VALUE"])
                                : $tmp["PROPERTIES"][$code]["~VALUE"];
                            $tmp["PROPERTIES"][$code]["~VALUE"] = '<param name="'.toUtf($tmp["PROPERTIES"][$code]["~NAME"]).'">'.toUtf($val).'</param>';
                        }
                    }
                    //Склеиваем все свойства в одно для xml.
                    $props_str.= $tmp["PROPERTIES"][$code]["~VALUE"];
                }
                // Если существуют торговые предложения
                if(array_key_exists("offersJoin",$arConfigProfile["iBlock"]) && $arConfigProfile["isOffersJoin"]*1 == 1 && $existOffers) //Формируем объединенные торговые предложения по новым правилам farpost.ru
                {
                    if($writeLog)
                    {
                        $clStatic->toLog('item type o joined');
                    }
                    $offerFIlter = Array("IBLOCK_ID" => $iBlockOffer["IBLOCK_ID"], "PROPERTY_CML2_LINK" => $tmp["ID"], "ACTIVE" => "Y");
                    if($arConfigProfile["not_check_count"]*1 !== 1 && $arConfigProfile["check_store"]*1 !== 1)
                    {
                        $offerFIlter['CATALOG_AVAILABLE'] = 'Y';
                    }
                    $rsOffers = CIBlockElement::GetList(Array("NAME" => "ASC"), $offerFIlter, false);
                    $gocnt = 0;

                    $storeCount = 0;
                    $minPrice = 0;
                    $arOffersPropertyValues = array();
                    $arOffersPictures = array();
                    $oneAvailable = 'false';

                    while ($itemOffers = $rsOffers->GetNextElement()) 
                    {
                        $ocnt++;
                        $gocnt++;
                        $tmpOffers = $itemOffers->GetFields();
                        $offerStoreCount = 0;
                        $available = 'true';

                        //проверяем количество товара на складах
                        if($writeLog)
                        {
                            $clStatic->toLog('in stores offer ['.$tmpOffers['ID'].'] = '.($arConfigProfile["not_check_count"]*1 !== 1 && $arConfigProfile["check_store"]*1 === 1));
                        }
                        if($arConfigProfile["not_check_count"]*1 !== 1 && $arConfigProfile["check_store"]*1 === 1)
                        {
                            if(!empty($arConfigProfile['store_id']))
                            {
                                $offerStoreCount = getProductCountStore($tmpOffers['ID'], $arConfigProfile['store_id']);
                                $storeCount += $offerStoreCount;
                            }
                        }
                        $tmpOffers["PROPERTIES"] = $itemOffers->GetProperties();

                        $arOfferPictures = getPicturesWithCorrectFormat($tmpOffers, $pictureType, $pictureType2, $arConfigProfile['url'], false);
                        $arOffersPictures = array_merge($arOffersPictures, $arOfferPictures);

                        //проверяем доступность товара
                        // и считаем его количество
                        $quantity = 0;
                        if($arConfigProfile["check_store"]*1 !== 1)
                        {
                            $offerCatalog = CCatalogProduct::GetByID($tmpOffers["ID"]);
                            $available = ($offerCatalog['QUANTITY']*1 > 0)? 'true': 'false';
                            $quantity = $offerCatalog['QUANTITY']*1;
                        }
                        else if($offerStoreCount < 1)
                        {
                            $available = 'false';
                        }

                        if($available == 'true')
                        {
                            if(count($arSelectJoinOffers) > 1)
                            {
                                $xParamsNames = [];
                                $xParamsValues = [];
                                foreach($arSelectJoinOffers as $code)
                                {
                                    if(!empty($tmpOffers['PROPERTIES'][$code]['VALUE']))
                                    {
                                        $xParamsNames[] = $tmpOffers["PROPERTIES"][$code]["~NAME"];
                                        $xParamsValues[] = $tmpOffers['PROPERTIES'][$code]['VALUE'];
                                    }
                                }
                                $arOffersPropertyValues[] = '<param name="'.toUtf(implode('_x_', $xParamsNames)).'">'.toUtf(implode(' x ', $xParamsValues)).'</param>';
                            }
                            foreach($arSelectJoinOffers as $code)
                            {
                                if(!empty($tmpOffers['PROPERTIES'][$code]['VALUE']))
                                {
                                    $arOffersPropertyValues[] = '<param name="'.toUtf($tmpOffers["PROPERTIES"][$code]["~NAME"]).'">'.toUtf($tmpOffers['PROPERTIES'][$code]['VALUE']).'</param>';
                                }
                            }
                            $price = getFinalPriceInCurrency($tmp["ID"], $arConfigProfile["iBlock"]["priceType"]);
                            $fPrice = lsFarpostApplyProfilePriceDiscount(
                                (float)$price['PRICE'],
                                is_array($arConfigProfile['iBlock'] ?? null) ? $arConfigProfile['iBlock'] : []
                            );
                            $minPrice = ($minPrice == 0 || ($minPrice > $fPrice && $fPrice > 0))? $fPrice: $minPrice;
                            $oneAvailable = 'true';
                        }
                    }

                    //формируем 1 offer для всех торговых предложений товара
                    $resultName = toUtf($tmp['NAME']);
                    $resultDesc = toUtf($tmp["DETAIL_TEXT"], true);
                    $arOffersPropertyValues = array_unique($arOffersPropertyValues);

                    $s = '<offer id="'.$tmp["ID"].'" group_id="'.$tmp["ID"].'" available="'.$oneAvailable.'">';
                    $s .= '<url>'.$arConfigProfile["url"].$tmp["DETAIL_PAGE_URL"].'</url>';
                    $s .= '<price>'.$minPrice.'</price><currencyId>RUR</currencyId>';
                    $s .= '<categoryId>'.$tmp["IBLOCK_SECTION_ID"].'</categoryId>';
                    $s .= '<name>'.$resultName.'</name>'.'<description>'.$resultDesc.'</description>'.($arConfigProfile["quantity"]*1 === 1? '<quantity>'.$quantity.'</quantity>':'' ).implode('', $arOffersPictures).$props_str;
                    $s .= implode('', $arOffersPropertyValues);
                    $s .= '</offer>';
                        
                    $arItems[$tmp["IBLOCK_SECTION_ID"]][] = $s;


                    if($writeLog)
                    {
                        $clStatic->toLog('cnt offers for g ['.$tmp['ID'].'] = '.$gocnt);
                    }
                }
                else if(array_key_exists("offers",$arConfigProfile["iBlock"]) && $existOffers) 
                {
                    if($writeLog)
                    {
                        $clStatic->toLog('item type o');
                    }

                    $offerFIlter = Array("IBLOCK_ID" => $iBlockOffer["IBLOCK_ID"], "PROPERTY_CML2_LINK" => $tmp["ID"], "ACTIVE" => "Y");
                    if($arConfigProfile["not_check_count"]*1 !== 1 && $arConfigProfile["check_store"]*1 !== 1)
                    {
                        $offerFIlter['CATALOG_AVAILABLE'] = 'Y';
                    }
                    $rsOffers = CIBlockElement::GetList(Array("NAME" => "ASC"), $offerFIlter, false);
                    $gocnt = 0;
                    while ($itemOffers = $rsOffers->GetNextElement()) 
                    {
                        $ocnt++;
                        $gocnt++;
                        $tmpOffers = $itemOffers->GetFields();
                        //проверяем количество товара на складах
                        if($writeLog)
                        {
                            $clStatic->toLog('in stores offer ['.$tmpOffers['ID'].'] = '.($arConfigProfile["not_check_count"]*1 !== 1 && $arConfigProfile["check_store"]*1 === 1));
                        }
                        if($arConfigProfile["not_check_count"]*1 !== 1 && $arConfigProfile["check_store"]*1 === 1)
                        {
                            if(!empty($arConfigProfile['store_id']))
                            {
                                $storeCount = getProductCountStore($tmpOffers['ID'], $arConfigProfile['store_id']);
                                if($writeLog)
                                {
                                    $clStatic->toLog('store cnt offer ['.$tmpOffers['ID'].'] = '.$storeCount);
                                }
                                if($storeCount < 0.01)
                                {
                                    if($writeLog)
                                    {
                                        $clStatic->toLog('continue 2 ['.$tmp['ID'].']');
                                    }
                                    continue;
                                }
                            }
                        }
                        $tmpOffers["PROPERTIES"] = $itemOffers->GetProperties();
                        $offers = '';
                        $arOfferPictures = getPicturesWithCorrectFormat(
                            $tmpOffers,
                            $pictureType,
                            $pictureType2,
                            $arConfigProfile['url'],
                            true
                        );
                        if (empty($arOfferPictures) && LSFarpostPictureType::isPropertyType($pictureType)) {
                            $arOfferPictures = getPicturesWithCorrectFormat(
                                $tmp,
                                $pictureType,
                                $pictureType2,
                                $arConfigProfile['url'],
                                true
                            );
                        }
                        $offers .= implode('', $arOfferPictures);
                        foreach ($arSelectOffers as $codeOffers) {
                            if ($codeOffers !== "MORE_PHOTO" && !empty($tmpOffers["PROPERTIES"][$codeOffers]["~VALUE"])) {
                                //если выбрано свойстро свойство "Брэнд" или "Производитель"
                                if((strpos($codeOffers, 'MANUFACTURER') !== false)||(strpos($codeOffers, 'BREND') !== false) || (strpos($codeOffers, 'BRAND') !== false) || (strpos($codeOffers, 'VENDOR') !== false)){
                                    $tmpOffers["PROPERTIES"][$codeOffers]["~VALUE"] = '<vendor>'.toUtf($tmpOffers["PROPERTIES"][$codeOffers]["~VALUE"]).'</vendor>';
                                    $isVendorInProps = true;
                                } else {
                                    if(is_array($tmpOffers["PROPERTIES"][$codeOffers]["VALUE"])){
                                        $tmpOffers["PROPERTIES"][$codeOffers]["~VALUE"] = implode("",'<param name="'.toUtf($tmpOffers["PROPERTIES"][$codeOffers]["~NAME"]).'">'.toUtf($tmpOffers["PROPERTIES"][$codeOffers]["~VALUE"]).'</param>');
                                    }
                                    $tmpOffers["PROPERTIES"][$codeOffers]["~VALUE"] = '<param name="'.toUtf($tmpOffers["PROPERTIES"][$codeOffers]["~NAME"]).'">'.toUtf($tmpOffers["PROPERTIES"][$codeOffers]["~VALUE"]).'</param>';
                                }
                            }
                            //Склеиваем все свойства в торговых предложениях для xml.
                            $offers .= $tmpOffers["PROPERTIES"][$codeOffers]["~VALUE"];
                        }
                        $price = getFinalPriceInCurrency($tmpOffers["ID"], $arConfigProfile["iBlock"]["priceType"]);
                        $fPrice = lsFarpostApplyProfilePriceDiscount(
                            (float)$price['PRICE'],
                            is_array($arConfigProfile['iBlock'] ?? null) ? $arConfigProfile['iBlock'] : []
                        );
                        $resultName = toUtf($tmpOffers['NAME']);
                        $resultDesc = toUtf($tmp["DETAIL_TEXT"], true);

                        //проверяем доступность товара, если в настройках указано "Не учитывать наличие товаров"
                        $available = 'true';
                        //и заодно считаем количество товаров на складе если его нужно выгружать
                        $quantity = 0;
                        if($arConfigProfile["check_store"]*1 !== 1)
                        {
                            $offerCatalog = CCatalogProduct::GetByID($tmpOffers["ID"]);
                            $available = ($offerCatalog['QUANTITY']*1 > 0)? 'true': 'false';
                            //количество
                            $quantity = $offerCatalog['QUANTITY']*1;
                        }
                        else if(empty($storeCount) || $storeCount <= 0)
                        {
                            $available = 'false';
                        } else {
                            $quantity = $storeCount;
                        }

                        //$offers .= '<price>'.$fPrice.'</price><oldprice>'.$price["PRICE"].'</oldprice><currencyId>RUR</currencyId>';
                        $s = '<offer id="'.$tmpOffers["ID"].'" group_id="'.$tmp["ID"].'" available="'.$available.'">';
                        $s .= '<url>'.$arConfigProfile["url"].$tmp["DETAIL_PAGE_URL"].'</url>';
                        $s .= '<price>'.$fPrice.'</price><oldprice>'.$price["PRICE"].'</oldprice><currencyId>RUR</currencyId>';
                        $s .= '<categoryId>'.$tmp["IBLOCK_SECTION_ID"].'</categoryId>';
                        $s .= '<name>'.$resultName.'</name>'.$props_str.'<description>'.$resultDesc.'</description>'.($arConfigProfile["quantity"]*1 === 1? '<quantity>'.$quantity.'</quantity>':'' );
                        $s .= $offers;
                        $s .= '</offer>';
                        
                        if($writeLog)
                        {
                            $clStatic->toLog('add to arItems iblock_section_id ['.$tmp["IBLOCK_SECTION_ID"].']');
                        }
                        $arItems[$tmp["IBLOCK_SECTION_ID"]][] = $s;
                    }
                    if($writeLog)
                    {
                        $clStatic->toLog('cnt offers for g ['.$tmp['ID'].'] = '.$gocnt);
                    }
                }
                else
                {
                    if($writeLog)
                    {
                        $clStatic->toLog('item type g');
                    }
                    //цена на товар
                    $price = getFinalPriceInCurrency($tmp["ID"], $arConfigProfile["iBlock"]["priceType"]);
                    $fPrice = lsFarpostApplyProfilePriceDiscount(
                        (float)$price['PRICE'],
                        is_array($arConfigProfile['iBlock'] ?? null) ? $arConfigProfile['iBlock'] : []
                    );
                    $product = CCatalogProduct::GetByID($tmp["ID"]);

                    $resultName = toUtf($tmp['NAME']);
                    $resultDesc = toUtf($tmp["DETAIL_TEXT"], true);

                    //проверяем доступность товара, если в настройках указано "Не учитывать наличие товаров"
                    $available = 'true';
                    //и заодно считаем количество товаров на складе если его нужно выгружать
                    $quantity = 0;
                    if($arConfigProfile["check_store"]*1 !== 1)
                    {
                        $offerCatalog = CCatalogProduct::GetByID($tmp["ID"]);
                        $available = ($offerCatalog['QUANTITY']*1 > 0)? 'true': 'false';
                        $quantity = $offerCatalog['QUANTITY']*1;
                    }
                    else if(empty($storeCount) || $storeCount <= 0)
                    {
                        $available = 'false';
                    }else {
                        $quantity = $storeCount;
                    }
        
                    $s = '<offer id="'.$tmp["ID"].'" group_id="'.$tmp["ID"].'" available="'.$available.'">';
                    $s .= '<url>'.$arConfigProfile["url"].$tmp["DETAIL_PAGE_URL"].'</url>';
                    $s .= '<price>'.$fPrice.'</price><oldprice>'.$price["PRICE"].'</oldprice><currencyId>RUR</currencyId>';
                    $s .= '<categoryId>'.$tmp["IBLOCK_SECTION_ID"].'</categoryId>';
                    $s .= '<name>'.$resultName.'</name>'.$props_str . $offers.'<description>'.$resultDesc.'</description>'.($arConfigProfile["quantity"]*1 === 1? '<quantity>'.$quantity.'</quantity>':'' ).'</offer>';

                    if($writeLog)
                    {
                        $clStatic->toLog('add to arItems iblock_section_id ['.$tmp["IBLOCK_SECTION_ID"].']');
                    }
                    $arItems[$tmp["IBLOCK_SECTION_ID"]][] = $s;
                }
                $cnt++;
                $arConfigFull = setConfigArray("gID", $tmp["ID"], $arConfigFull, $profileCode);
                $arConfigProfile["gID"] = $tmp["ID"];
            }
            if($writeLog)
            {
                $clStatic->toLog('result step cnt = '.$cnt);
            }
            if ($cnt > 0) {
                $xml_info = array();
                if($writeLog)
                {
                    $clStatic->toLog('arItems cnt = '.count($arItems));
                }
                foreach ($arItems as $sid => $elements) {
                    foreach($elements as $i => $el) {
                        $xml_info[] = $el;
                    }
                }
                if($writeLog)
                {
                    $clStatic->toLog('result step xml_info cnt = '.count($xml_info));
                }
                if (count($xml_info) > 0) {
                    $xml_info = implode("", $xml_info);
                    
                    writeDataToFile($tmp_file, $xml_info);
                }
                //количество обработанных элементов
                $arConfigFull = setConfigArray("loaded", (int)$arConfigProfile["loaded"] + $cnt, $arConfigFull, $profileCode);
            } else {
                $arConfigFull = setConfigArray("step", 3, $arConfigFull, $profileCode);
            }

        } else if ($arConfigProfile["step"] * 1 == 3) {
            $totalLoaded = (int)($arConfigProfile['loaded'] ?? 0);
            writeDataToFile($tmp_file, '</offers></shop></yml_catalog>');
            if ($totalLoaded > 0 && file_exists($tmp_file)) {
                @rename($tmp_file, $export_file);
            } elseif ($totalLoaded <= 0 && file_exists($export_file)) {
                LSFarpostDebugLog('skip empty export.xml replace, loaded=0');
                if (file_exists($tmp_file)) {
                    @unlink($tmp_file);
                }
            } elseif (file_exists($tmp_file)) {
                @rename($tmp_file, $export_file);
            }

            if (class_exists('LSFarpostDedup')) {
                LSFarpostDedup::reset($profileCode);
            }

            $arConfigFull = setConfigArray("step", 1, $arConfigFull, $profileCode);
            $arConfigFull = setConfigArray("start", 0, $arConfigFull, $profileCode);
            $arConfigFull = setConfigArray("gID", 0, $arConfigFull, $profileCode);
            $arConfigFull = setConfigArray("finishedAt", ConvertTimeStamp(microtime(true) + CTimeZone::GetOffset(), "FULL", "ru"), $arConfigFull, $profileCode);
            file_put_contents($system_file, json_encode($arConfigFull, JSON_PRETTY_PRINT));
            //Выгрузка произведена выключаем агент
            //disableAgent("LSFarpostExport('');");
        }
    }
    $arConfigFull = setConfigArray('export_started', 'N', $arConfigFull);
    $arConfigFull = setConfigArray('export_started_at', 0, $arConfigFull);
    file_put_contents($system_file, json_encode($arConfigFull, JSON_PRETTY_PRINT));

    return "LSFarpostExport('');";
}
