<?
//Скрипт экспорта товаров в CSV для farpost.ru
//Экспорт пошаговый
//Запуск производится по настройкам в файле system.txt.
//Шаг 1 - создание временного файла и запись заголовков
//Шаг 2 - пошаговая выгрузка товаров
//Шаг 3 - перенос данных в основной файл, удаление временного файла, деактивирование запуска

/***Расчет стоимость товара или предложения со всеми скидками***/
function getFinalPriceInCurrency($item_id, $price_type, $cnt = 1, $getName="N", $sale_currency = 'RUB') {
    global $USER;
    $USER = new CUSer();
    $USER->Authorize(1);

    // Проверяем, имеет ли товар торговые предложения?
    if(CCatalogSku::IsExistOffers($item_id)) {

        // Пытаемся найти цену среди торговых предложений
        $res = CIBlockElement::GetByID($item_id);

        if($ar_res = $res->GetNext()) {
            $productName = $ar_res["NAME"];
            if(isset($ar_res['IBLOCK_ID']) && $ar_res['IBLOCK_ID']) {

                // Ищем все тогровые предложения
                $offers = CIBlockPriceTools::GetOffersArray(array(
                    'IBLOCK_ID' => $ar_res['IBLOCK_ID'],
                    'HIDE_NOT_AVAILABLE' => 'Y',
                    'CHECK_PERMISSIONS' => 'Y'
                ), array($item_id), null, null, null, null, null, null, array('CURRENCY_ID' => $sale_currency), $USER->getId(), null);

                foreach($offers as $offer) {

                    $price = CCatalogProduct::GetOptimalPrice($offer['ID'], $cnt, $USER->GetUserGroupArray(), 'N');
                    if(isset($price['PRICE'])) {

                        $final_price = $price['PRICE']['PRICE'];
                        $currency_code = $price['PRICE']['CURRENCY'];

                        // Ищем скидки и высчитываем стоимость с учетом найденных
                        $arDiscounts = CCatalogDiscount::GetDiscountByProduct($item_id, $USER->GetUserGroupArray(), "N", "s1");
                        if(is_array($arDiscounts) && sizeof($arDiscounts) > 0) {
                            $final_price = CCatalogProduct::CountPriceWithDiscount($final_price, $currency_code, $arDiscounts);
                        }

                        // Конец цикла, используем найденные значения
                        break;
                    }

                }
            }
        }

    } else {

        // Простой товар, без торговых предложений (для количества равному $cnt)
        $price = CCatalogProduct::GetOptimalPrice($item_id, $cnt, $USER->GetUserGroupArray(), 'N');

        // Получили цену?
        if(!$price || !isset($price['PRICE'])) {
            return false;
        }

        // Меняем код валюты, если нашли
        if(isset($price['CURRENCY'])) {
            $currency_code = $price['CURRENCY'];
        }
        if(isset($price['PRICE']['CURRENCY'])) {
            $currency_code = $price['PRICE']['CURRENCY'];
        }

        // Получаем итоговую цену
        $final_price = $price['PRICE']['PRICE'];

        // Ищем скидки и пересчитываем цену товара с их учетом
        $arDiscounts = CCatalogDiscount::GetDiscountByProduct($item_id, $USER->GetUserGroupArray(), "N", 2);
        if(is_array($arDiscounts) && sizeof($arDiscounts) > 0) {
            $final_price = CCatalogProduct::CountPriceWithDiscount($final_price, $currency_code, $arDiscounts);
        }

        if($getName=="Y"){
            $res = CIBlockElement::GetByID($item_id);
            $ar_res = $res->GetNext();
            $productName = $ar_res["NAME"];
        }

    }

    // Если необходимо, конвертируем в нужную валюту
    if($currency_code != $sale_currency) {
        $final_price = CCurrencyRates::ConvertCurrency($final_price, $currency_code, $sale_currency);
    }

    $arRes = array(
        "PRICE"=>$price['PRICE']['PRICE'],
        "FINAL_PRICE"=>$final_price,
        "CURRENCY"=>$sale_currency,
        "DISCOUNT"=>$arDiscounts,
    );

    if($productName!="")
        $arRes['NAME']= $productName;

    return $arRes;

}
function export()
{

    $start_time = microtime(true);
    $csv_tmp_file = $_SERVER['DOCUMENT_ROOT'] . "/local/modules/labsu.farpost/export/csv/csv_tmp.txt";
    $csv_file = $_SERVER['DOCUMENT_ROOT'] . "/local/modules/labsu.farpost/export/csv/export.txt";
    $csv_system_file = $_SERVER["DOCUMENT_ROOT"].'/local/modules/labsu.farpost/admin/system.json';

    $load_cnt = 50; //количество одновременно выгружаемых товаров

    $arConfig = array(
        "start" => 1,
        "step" => 1,
        "gID" => 0
    );
    /*
    if($_REQUEST["SET_START"] === "Y")
    {	$arConfig["START"] = 1;
        file_put_contents($csv_system_file, json_encode($arConfig));
        die("Запуск установлен");}
    */
    if(file_exists($csv_system_file))
    {
        $conf = file_get_contents($csv_system_file);
        $arConfig = json_decode($conf, true);
    }

    if ($arConfig["step"] * 1 == 1) {
        $csv="Наименование;Описание;Цена";
        $dbIBlockProperty = CIBlockProperty::GetList(
            array("SORT" => "ASC"),
            array( "IBLOCK_ID" => $arConfig["iBlock"]["id"],
            )
        );
        while ($arIBlockProperty = $dbIBlockProperty ->Fetch())
        {
            if(in_array($arIBlockProperty["ID"],$arConfig["iBlock"]["properties"])){
                $csv .= trim($arIBlockProperty["NAME"]) . ";";
             }
        }
        if(array_key_exists("offers", $arConfig["iBlock"])){
            $offerProperties = CCatalogSKU::GetInfoByProductIBlock($arConfig["iBlock"]["id"]);
            $dbOffersProperty = CIBlockProperty::GetList(
                array("SORT" => "ASC"),
                array( "IBLOCK_ID" => $offerProperties['IBLOCK_ID'],
                )
            );
            while ($arOfferProperty = $dbOffersProperty ->Fetch())
            {
                if(in_array($arOfferProperty["ID"],$arConfig["iBlock"]["offers"])) {
                    $csv .= trim($arOfferProperty["NAME"]).";";
                }
            }
        }
        file_put_contents($csv_tmp_file, $csv);
        $arConfig["step"] = 2;
        file_put_contents($csv_system_file, json_encode($arConfig,JSON_PRETTY_PRINT));
        echo "END: step 1";
    } else if ($arConfig["step"] * 1 == 2) {
        $cnt = 0;

        //загрузка данных по разделам

        $arSections = array();
        $iBlockOffer = CCatalogSKU::GetInfoByProductIBlock($arConfig["iBlock"]["id"]); // торговые предложения
        $rsSection = CIBlockSection::GetList(Array("ID" => "ASC"), Array("IBLOCK_ID" => $arConfig["iBlock"]["id"]), false, Array("ID", "NAME"));
        while ($sec = $rsSection->Fetch()) {
            $arSections[$sec["ID"]] = array("NAME" => $sec["NAME"]);
        }
        if(array_key_exists("sections",$arConfig["iBlock"])) {
            $arFilter = Array(
                "IBLOCK_ID" => $arConfig["iBlock"]["id"],
                "SECTION_ID" => $arConfig["iBlock"]["sections"]["id"],
                "INCLUDE_SUBSECTIONS" => $arConfig["iBlock"]["sections"]["subsections"],
                "ACTIVE" => "Y"
            , ">ID" => $arConfig["gID"]
            );
        } else {
            $arFilter = Array(
                "IBLOCK_ID" => $arConfig["iBlock"]["id"],
                "ACTIVE" => "Y"
            , ">ID" => $arConfig["gID"]
            );
        }
        $arSelectFields = Array();
        foreach($arConfig["iBlock"]["properties"] as $item){
            $rsPropertyCode = CIBlockProperty::GetByID($item, $arConfig["iBlock"]["id"]);
            if($ar_res = $rsPropertyCode->GetNext()){
                $arSelectFields[] = $ar_res['CODE'];
            }
        }
        if(array_key_exists("offers", $arConfig["iBlock"])) {
            $arSelectOffers = Array();
            foreach ($arConfig["iBlock"]["offers"] as $item) {
                $rsOfferCode = CIBlockProperty::GetByID($item, $iBlockOffer["IBLOCK_ID"]);
                if ($ar_res_offer = $rsOfferCode->GetNext()) {
                    $arSelectOffers[] = $ar_res_offer['CODE'];
                }
            }
        }
        $rsItems = CIBlockElement::GetList(Array("ID" => "ASC"), $arFilter, false, Array("nTopCount" => $load_cnt));
        $arElements = array();
        $scnt = 0;
        $ocnt = 0;
        $arItems = Array();
        while ($item = $rsItems->GetNextElement()) {
            $cnt++;
            $tmp = $item->GetFields();
            $tmp["PROPERTIES"] = $item->GetProperties();
            $tmp["DETAIL_TEXT"] = str_replace(";", ",", $tmp["DETAIL_TEXT"]);
            $tmp["DETAIL_TEXT"] = str_replace("\n", " ", $tmp["DETAIL_TEXT"]);
            $tmp["DETAIL_TEXT"] = str_replace("\r", " ", $tmp["DETAIL_TEXT"]);
            $props_str = "";
            foreach ($arSelectFields as $code){
                if($code == "MORE_PHOTO"){
                    if(is_array($tmp["PROPERTIES"]["MORE_PHOTO"]["VALUE"])){
                        $images = Array();
                        foreach($tmp["PROPERTIES"]["MORE_PHOTO"]["VALUE"] as $imageItem){
                            $image = CFile::GetFileArray($imageItem);
                            $images[] = "http://".$_SERVER['HTTP_HOST'].$image["SRC"];
                        }
                        $tmp["PROPERTIES"]["MORE_PHOTO"]["~VALUE"] = implode(", ", $images);
                    }
                } else {
                    if(is_array($tmp["PROPERTIES"][$code]["VALUE"])){
                        $tmp["PROPERTIES"][$code]["~VALUE"] = implode(", ",$tmp["PROPERTIES"][$code]["~VALUE"]);
                    }
                }
                $props_str.= ";".$tmp["PROPERTIES"][$code]["~VALUE"];
            }
            // Если существуют торговые предложения

            if(array_key_exists("offers",$arConfig["iBlock"])) {
                $rsOffers = CIBlockElement::GetList(Array("NAME" => "ASC"), Array("IBLOCK_ID" => $iBlockOffer["IBLOCK_ID"], "PROPERTY_CML2_LINK" => $tmp["ID"], "ACTIVE" => "Y"), false);
                while ($itemOffers = $rsOffers->GetNextElement()) {
                    $ocnt++;
                    $tmpOffers = $itemOffers->GetFields();
                    $tmpOffers["PROPERTIES"] = $itemOffers->GetProperties();
                    $offers="";
                    foreach ($arSelectOffers as $codeOffers){
                        if($codeOffers == "MORE_PHOTO"){
                            if(is_array($tmpOffers["PROPERTIES"]["MORE_PHOTO"]["VALUE"])){
                                $images = Array();
                                foreach($tmpOffers["PROPERTIES"]["MORE_PHOTO"]["VALUE"] as $imageItem){
                                    $image = CFile::GetFileArray($imageItem);
                                    $images[] = "http://".$_SERVER['HTTP_HOST'].$image["SRC"];
                                }
                                $tmpOffers["PROPERTIES"]["MORE_PHOTO"]["~VALUE"] = implode(", ", $images);
                            }
                        } else {
                            if(is_array($tmpOffers["PROPERTIES"][$codeOffers]["VALUE"])){
                                $tmpOffers["PROPERTIES"][$codeOffers]["~VALUE"] = implode(", ",$tmpOffers["PROPERTIES"][$codeOffers]["~VALUE"]);
                            }
                        }
                        $offers .= ";".$tmpOffers["PROPERTIES"][$codeOffers]["~VALUE"];
                    }
                }
            }
            //цена товар
            $price = getFinalPriceInCurrency($tmp["ID"]);
            $fPrice="";
            if(array_key_exists("discount",$arConfig["iBlock"])){
                if($arConfig["iBlock"]["discount"]!=""){
                    $fPrice=$price["PRICE"]*(100-$arConfig["iBlock"]["discount"])/100;
                    if(array_key_exists("discount",$arConfig["iBlock"]["allowShopDiscount"]) && $arConfig["iBlock"]["allowShopDiscount"]=="1"){
                        $fPrice = $price["FINAL_PRICE"]*(100-$arConfig["iBlock"]["discount"])/100;
                    }
                }else {
                    $fPrice = $price["PRICE"];
                    if(array_key_exists("discount",$arConfig["iBlock"]["allowShopDiscount"]) && $arConfig["iBlock"]["allowShopDiscount"]=="1"){
                        $fPrice = $price["FINAL_PRICE"];
                    }
                }
            } else {
                $fPrice = $price["PRICE"];
            }
            $arItems[$tmp["IBLOCK_SECTION_ID"]][] = $tmp["NAME"].";".$tmp["DETAIL_TEXT"].";".$fPrice.$props_str.$offers;
            //$arItems[$tmp["IBLOCK_SECTION_ID"]][] = $price["FINAL_PRICE"];
            /*
            while ($arOffer = $rsOffers->Fetch()) {
                $size = (is_array($arOffer["PROPERTY_SIZES_CLOTHES_VALUE"])) ? $arOffer["PROPERTY_SIZES_CLOTHES_VALUE"][0] : $arOffer["PROPERTY_SIZES_CLOTHES_VALUE"];
                $discount_price = CCatalogProduct::GetOptimalPrice($arOffer["ID"], 1);
                $price = ($arOffer["CATALOG_PRICE_1"] * 1 > $discount_price["DISCOUNT_PRICE"] * 1) ? $discount_price["DISCOUNT_PRICE"] : $arOffer["CATALOG_PRICE_1"];
                $arElements[$arItem["IBLOCK_SECTION_ID"]][] = $arItem["NAME"] . ";" . $size . ";" . $arItem["DETAIL_TEXT"] . ";" . $price . ";http://maremi.ru" . $image["SRC"] . ";";
                $ocnt++;
            }
            if (!$offer_cnt) {
                $discount_price = CCatalogProduct::GetOptimalPrice($arItem["ID"], 1);
                $price = $discount_price["DISCOUNT_PRICE"];
                $arElements[$arItem["IBLOCK_SECTION_ID"]][] = $arItem["NAME"] . ";;" . $arItem["DETAIL_TEXT"] . ";" . $price . ";http://maremi.ru" . $image["SRC"] . ";";
            }
            $arConfig["GID"] = $arItem["ID"];
            */
        }
        if ($cnt > 0) {
            foreach ($arElements as $sid => $elements) {
                $csv[] = trim($arSections[$sid]["NAME"]) . ";";
                $last_sid = $sid;
                foreach ($elements as $i => $el) {
                    $csv[] = $el;
                }
            }
            if (count($csv) > 0) {
                $csv = implode("\r\n", $csv);
                $csv_content = file_get_contents($csv_tmp_file);
                $csv_content .= "\r\n" . $csv;
                file_put_contents($csv_tmp_file, $csv_content);

                file_put_contents($csv_system_file, json_encode($arConfig, JSON_PRETTY_PRINT));
            }
            echo "CONTINUE: step 2";
        } else {
            $arConfig["step"] = 3;
            file_put_contents($csv_system_file, json_encode($arConfig, JSON_PRETTY_PRINT));
            echo "END: step 2";
        }
    } else if ($arConfig["step"] * 1 == 3) {
       /* $csv_content = file_get_contents($csv_tmp_file);
        file_put_contents($csv_file, $csv_content);

        unlink($csv_tmp_file);

        $arConfig["STEP"] = 1;
        $arConfig["START"] = 0;
        $arConfig["GID"] = 0;
        file_put_contents($csv_system_file, json_encode($arConfig));
       */
    }
    $end_time = microtime(true);
    return "export();";
}
//echo "<br>Обработано разделов: ".$scnt."; элементов: ".$cnt."; торговых предложений: ".$ocnt;
//echo "<br>На генерацию затрачено: ".($end_time-$start_time)." сек";
?>