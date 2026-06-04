<?php
// До prolog Bitrix D7 Request ещё недоступен — ajax=Y всегда в query URL
if (isset($_GET['ajax']) && $_GET['ajax'] === 'Y')
{
	require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
}
else
{
	require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin.php");
}
require_once($_SERVER["DOCUMENT_ROOT"]."/local/modules/labsu.farpost/include.php"); // инициализация модуля
require_once($_SERVER["DOCUMENT_ROOT"]."/local/modules/labsu.farpost/prolog.php"); // пролог модуля
require_once($_SERVER["DOCUMENT_ROOT"]."/local/modules/labsu.farpost/lib/PictureType.php");

IncludeModuleLangFile(__FILE__);

if (LSFarpostRequest::getString('ajax') === 'Y')
{
	$arAjax = array("system", "sections", "iBlockProperties", "iBlockPropertiesSKU", "iBlockPictureProperties", "profiles"); //обработчики ajax
	$item = LSFarpostRequest::getString('item');
	if(in_array($item, $arAjax, true) && preg_match('/^[a-zA-Z0-9_]+$/', $item))
	{
		$ajaxFile = $_SERVER["DOCUMENT_ROOT"]."/local/modules/labsu.farpost/ajax/".$item.".php";
		if(file_exists($ajaxFile))
		{
			require_once($ajaxFile); //подключаем обработчики ajax
			// LSFarpostExport только при загрузке настроек — иначе ломает HTML-ответы (iBlockPictureProperties и др.)
			if ($item === 'system' && LSFarpostRequest::getString('settings') === 'load') {
				LSFarpostExport('');
			}
		}
	}
	die();
}

ClearVars();

if(!$USER->isAdmin() && $APPLICATION->GetGroupRight("labsu.farpost") < "W")
{
	ShowError(GetMessage("LS_MARGIN_RULE_ERROR"));

	require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
	die();
}

ClearVars();
CJSCore::Init(array("jquery"));
?>

<div class="adm-info-message-wrap ls_farpost_result_wrap" style="position: relative; top: -15px; display: none;">
	<div class="adm-info-message ls_farpost_result_text"></div>
</div>
<div style='clear: both;'></div>
<div class="container">
    <div class="left_column">
        
        <form method="POST" action="<?echo $APPLICATION->GetCurPage()?>" name="form1" id="ls_farpost_form">
            <input type="hidden" name='profileCode' value=''>
            <input type="hidden" name='profileName' value=''>
            <?echo GetFilterHiddens("filter_");?>
            <input type="hidden" name="last_work" id='last_work' value="">
            <input type="hidden" name="lang" value="<?echo LANGUAGE_ID ?>">
            <?=bitrix_sessid_post();

        $aTabs = array(
            array("DIV" => "edit1", "TAB" => GetMessage("LS_FARPOST_MENU_SECT"), "ICON" => "catalog", "TITLE" => GetMessage("LS_FARPOST_MENU_SECT_TITLE"))
        );

        $tabControl = new CAdminTabControl("tabControl", $aTabs);
        $tabControl->Begin();

        $tabControl->BeginNextTab();


        ?>
            <tr>            
                <h3><?=GetMessage("LS_FARPOST_PROFILE")?><span><?=GetMessage("LS_FARPOST_PROFILE_DEFAULT")?></span></h3>
            </tr>
            <tr class="adm-detail-required-field enable_block" >
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD1")?>:</td>
                <td width="60%">
                    <select size="1" name="enableagent" class="enableagent">
                        <option value="0" selected><?=LSStatic::message("LS_FARPOST_OFF")?></option>
                        <option value="1"><?=LSStatic::message("LS_FARPOST_ON")?></option>
                    </select>
                </td>
            </tr>
            <tr class="adm-detail-required-field">
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD2")?>:</td>
                <td width="60%">
                    <select size="1" name="iblock_id" class='iblockselect'>
                        <?
                        $exist = false;
                        $rsCatalogs = CIBlock::GetList(Array("NAME"=>"ASC"), Array("ACTIVE"=>"Y", "CHECK_PERMISSIONS" => "Y"));
                        $i = 0;
                        while($arCatalog = $rsCatalogs->GetNext())
                        {
                            //if(!CCatalogSKU::GetInfoByProductIBlock($arCatalog["ID"]))
                            if(!CCatalog::GetByID($arCatalog["ID"]))
                            {
                                continue;
                            }
                            if(CCatalogSKU::GetInfoByOfferIBlock($arCatalog["ID"]))
                            {
                                continue;
                            }

                            if($i==0) {
                                $iblockFirst = $arCatalog["ID"];
                            }
                            ?>
                            <option value="<?=$arCatalog["ID"]?>"><?=$arCatalog["NAME"]?> [ID=<?=$arCatalog["ID"]?>]</option>
                            <?
                            $i++;
                            $exist = true;
                        }
                        ?>
                    </select>
                </td>
            </tr>
        <?
        if($exist)
        {
        ?>
            <tr class="adm-detail-required-field">
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD3")?>:</td>
                <td width="60%">
                    <select size="1" name="section_type" class="sectiontypeselect">
                        <option value="1" selected><?=LSStatic::message("LS_FARPOST_ALL")?></option>
                        <option value="2"><?=LSStatic::message("LS_FARPOST_SOME")?></option>
                    </select>
                </td>
            </tr>
            <tr class="adm-detail-required-field sectionselect_block" style='display: none;'>
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD4")?>:</td>
                <td width="60%">
                    <select size="20" name="section_id[]" class='sectionselect' multiple>
                    </select>
                </td>
            </tr>
            <tr class="adm-detail-required-field sectionsub_block" style='display: none;'>
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD5")?>:</td>
                <td width="60%">
                    <select size="1" name="section_subsections" class='sectionsub'>
                        <option value="Y" selected><?=LSStatic::message("LS_FARPOST_YES")?></option>
                        <option value="N"><?=LSStatic::message("LS_FARPOST_NO")?></option>
                    </select>
                </td>
            </tr>
            <tr class="adm-detail-required-field">
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD6")?>:</td>
                <td width="60%">
                    <select size="1" name="price_type">
                        <?
                        $dbPriceType = CCatalogGroup::GetList(
                                array("SORT" => "ASC"),
                                array()
                            );
                        while ($arPriceType = $dbPriceType->Fetch())
                        {
                            ?>
                        <option value="<?=$arPriceType["ID"]?>" <?=(($arPriceType["BASE"] === "Y")? 'selected': '')?>><?=$arPriceType["NAME_LANG"].(($arPriceType["BASE"] === "Y")? ' ('.LSStatic::message("LS_FARPOST_BASE_PRICE").')': '')?> [ID=<?=$arPriceType["ID"]?>]</option>
                            <?
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr class="adm-detail-required-field">
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD6_1")?>:</td>
                <td width="60%">
                    <select size="1" name="picture_type" class='iblockpicturetype'>
                        <?=LSFarpostPictureType::renderOptionsHtml(LSFarpostPictureType::resolveCatalogIblockId((int)($iblockFirst ?? 0)))?>
                    </select>
                </td>
            </tr>
            <tr class="adm-detail-required-field discount_block" style='display: none;'>
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD7")?>:</td>
                <td width="60%">
                    <input name="discount" type="checkbox">
                </td>
            </tr>
            <tr class="adm-detail-required-field blocked type_kours type_percent" style='display: none;'>
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD8")?>:</td>
                <td width="60%">
                    <input style='width: 40px;' name="percent_value" type="text" value=""> %
                </td>
            </tr>
            <tr class="adm-detail-required-field shop_discount_block" style='display: none;'>
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD9")?>:</td>
                <td width="60%">
                    <input name="shop_discount" type="checkbox">
                </td>
            </tr>
            <tr class="adm-detail-required-field">
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD10")?>:</td>
                <td width="60%">
                    <select size="20" name="property_id" class="iblockpropertyselect" multiple="multiple">
                        <?
                        $dbIBlockProperty = CIBlockProperty::GetList(
                            array("SORT" => "ASC"),
                            array("IBLOCK_ID" => $iblockFirst)
                        );
                        while ($arIBlockProperty = $dbIBlockProperty->Fetch())
                        {
                            if($arIBlockProperty["CODE"] === 'MORE_PHOTO')
                            {
                                continue;
                            }
                            ?>
                            <option value="<?=$arIBlockProperty["ID"]?>"><?=$arIBlockProperty["NAME"]?> [ID=<?=$arIBlockProperty["ID"]?>]</option>";
                            <?
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <?
            $iBlockOffer = CCatalogSKU::GetInfoByProductIBlock($iblockFirst);
            if(is_array($iBlockOffer))
            {
                ?>
            <tr class="adm-detail-required-field offers_join_check">
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD11_1")?>:</td>
                <td width="60%">
                    <input style='width: 20px;' name="offers_join" type="checkbox">
                </td>
            </tr>
            <tr class="adm-detail-required-field offer_properties_join" style='display: none;'>
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD11_2")?>:</td>
                <td width="60%">
                    <select size="20" name="property_id_join" class="offerpropertyselect_join" multiple="multiple">
                        <?
                        if(is_array($iBlockOffer))
                        {
                            $dbIBlockProperty = CIBlockProperty::GetList(
                                array("SORT" => "ASC"),
                                array("IBLOCK_ID" => $iBlockOffer["IBLOCK_ID"])
                            );
                            while ($arIBlockProperty = $dbIBlockProperty->Fetch())
                            {
                                if($arIBlockProperty["CODE"] === 'MORE_PHOTO')
                                {
                                    continue;
                                }
                                ?>
                                <option value="<?=$arIBlockProperty["ID"]?>"><?=$arIBlockProperty["NAME"]?> [ID=<?=$arIBlockProperty["ID"]?>]</option>";
                                <?
                            }
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr class="adm-detail-required-field offer_properties">
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD11")?>:</td>
                <td width="60%">
                    <select size="20" name="property_id" class="offerpropertyselect" multiple="multiple">
                        <?
                        $dbIBlockProperty = CIBlockProperty::GetList(
                            array("SORT" => "ASC"),
                            array("IBLOCK_ID" => $iBlockOffer["IBLOCK_ID"])
                        );
                        while ($arIBlockProperty = $dbIBlockProperty->Fetch())
                        {
                            if($arIBlockProperty["CODE"] === 'MORE_PHOTO')
                            {
                                continue;
                            }
                            ?>
                            <option value="<?=$arIBlockProperty["ID"]?>"><?=$arIBlockProperty["NAME"]?> [ID=<?=$arIBlockProperty["ID"]?>]</option>";
                            <?
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <? }?>
            <tr class="adm-detail-required-field not_check_count_block">
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD_NOT_CHECK_COUNT")?>:</td>
                <td width="60%">
                    <input name="not_check_count" type="checkbox">
                </td>
            </tr>
            <tr class="adm-detail-required-field store_block">
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD_CHECK_STORE")?>:</td>
                <td width="60%">
                    <input name="check_store" type="checkbox">
                </td>
            </tr>
            <tr class="adm-detail-required-field quantity_block">
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD_QUANTITY")?>:</td>
                <td width="60%">
                    <input name="quantity" type="checkbox">
                </td>
            </tr>
            <?
            $rsStores = CCatalogStore::GetList(
                array('PRODUCT_ID'=>'ASC','ID' => 'ASC'),
                array('ACTIVE' => 'Y'),
                false,
                false,
                array("ID","TITLE","ACTIVE")
            );
            $arStores = array();
            while($store = $rsStores->Fetch())
            {
                $arStores[] = $store;
            }
            if(count($arStores) > 0)
            {
                ?>
            <tr class="adm-detail-required-field stores_select" style='display: none;'>
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD_STORES")?>:</td>
                <td width="60%">
                    <select size="20" name="store_id" class="store_id" multiple="multiple">
                        <?
                        foreach($arStores as $store)
                        {
                            ?>
                            <option value="<?=$store["ID"]?>"><?=$store["TITLE"]?> [ID=<?=$store["ID"]?>]</option>";
                            <?
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <? }?>
            <tr class="adm-detail-required-field">
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD12")?>
                </td>
                <td width="60%">
                    <input style='width: 40px;' name="cnt" type="text" value="50">
                </td>
            </tr>
            <tr class="adm-detail-required-field">
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD13")?>
                </td>
                <td width="60%">
                    <input style='width: 70px; padding: 1px 0px 1px 5px' name="time" type="time">
                </td>
            </tr>
            <tr class="adm-detail-required-field" style='display:none;'>
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD14")?>:</td>
                <td width="60%">
                    <select size="1" name="export_type" class="exportselect">
                        <option value="xml" selected>xml</option>
                    </select>
                </td>
            </tr>
            <tr class="adm-detail-required-field extended_field_xml">
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD15")?>:</td>
                <td width="60%">
                    <input style='width: 300px;' name="shop_name" type="text">
                </td>
            </tr>
            <tr class="adm-detail-required-field extended_field_xml">
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD16")?>:</td>
                <td width="60%">
                    <input style='width: 300px;' name="company" type="text">
                </td>
            </tr>
            <tr class="adm-detail-required-field extended_field_xml">
                <td width="40%"><?=LSStatic::message("LS_FARPOST_HEAD17")?>:</td>
                <td width="60%">
                    <input style='width: 300px;' name="url" type="text" value="<?=(CMain::IsHTTPS() ? 'https://' : 'http://').((defined('SITE_SERVER_NAME') && strlen(SITE_SERVER_NAME) > 0) ? SITE_SERVER_NAME : $_SERVER['SERVER_NAME']);?>">
                </td>
            </tr>
                <?
        }
        ?>
            <tr class="adm-detail-required-field ls_margin_options_wrap" style='display: none;'>
                <td width="100%" colspan="2">
                    <div class="adm-info-message-wrap" style="margin: 0px auto;">
                        <div class="adm-info-message ls_margin_options_text">
                        </div>
                    </div>
                </td>
            </tr>
        <?
        $tabControl->EndTab();
        if($exist)
        {
            $tabControl->Buttons();
        ?>
            <input class="adm-btn-save save_settings" type="button" name="save" value="<?=LSStatic::message("LS_FARPOST_BTN_SAVE")?>" title="<?=LSStatic::message("LS_FARPOST_BTN_SAVE")?>" />
            <input class="adm-btn-button start_work" style="display: none;" type="button" name="start" value="<?=LSStatic::message("LS_FARPOST_BTN_START")?>" title="<?=LSStatic::message("LS_FARPOST_BTN_START")?>" />
            <input class="adm-btn-button end_work" style="display: none;" type="button" name="cancel" value="<?=LSStatic::message("LS_MARGIN_BTN_END")?>" title="<?=LSStatic::message("LS_MARGIN_BTN_END")?>" />
            <input class="adm-btn-button next_work" style="display: none;" type="button" name="cancel" value="<?=LSStatic::message("LS_MARGIN_BTN_NEXT")?>" title="<?=LSStatic::message("LS_MARGIN_BTN_NEXT")?>" />
            <input class="adm-btn-button stop_work" style="display: none;" type="button" name="stop" value="<?=LSStatic::message("LS_FARPOST_BTN_STOP")?>" title="<?=LSStatic::message("LS_FARPOST_BTN_STOP")?>" />
            <input class="adm-btn-button save_settings_as_profile" type="button" name="save_profile" value="<?=LSStatic::message("LS_FARPOST_BTN_SAVE_PROFILE")?>" title="<?=LSStatic::message("LS_FARPOST_BTN_SAVE_PROFILE")?>" />
        <?
        }

        $tabControl->End();

        $base_url = "/bitrix/admin/ls_farpost.php?ajax=Y&";
        ?>
        </form>
    </div>
    <div class="right_column">
        <h3><?=LSStatic::message("LS_FARPOST_INPUT_PROFILE_LIST")?></h3>
        <div class="profile_blocks">
            <div class="profile_default"><a href="javascript:void(0);"><?=LSStatic::message("LS_FARPOST_INPUT_PROFILE_DEF_LINK")?></a></div>
            <ul></ul>
        </div>
    </div>
</div>
<div class="popover"></div>
<div class="popup_form_profile">
    <div class="close">&#10006;</div>
    <div class="body">
        <div class="item_block">
            <input name='profileName' type="text" id='proname' placeholder='<?=LSStatic::message("LS_FARPOST_INPUT_PROFILE_NAME")?>' value=''>
        </div>
        <div class="item_block">
            <input name='profileCode' placeholder='<?=LSStatic::message("LS_FARPOST_INPUT_PROFILE_CODE")?>' type="text" id='procode' value=''>
        </div>
        <div class="item_block">
            <input class="btn_save_profile adm-btn-save" type="button" name="save" value="<?=LSStatic::message("LS_FARPOST_POPUP_BTN")?>" title="<?=LSStatic::message("LS_FARPOST_POPUP_BTN")?>" />
        </div>
    </div>
</div>



<script>

    var arr = window.location.href.split("/");
    var ls_margin_step;
    var ls_margin_work_stop;
    var top_speed = 1000;
    var timerID;
    var type = $('.enableagent option:selected').val();
   // $('.ls_farpost_result_text').hide();
    if (type == '0') {
        $('.edit-table tr').not('.enable_block').hide();
    }

    function showNoticeMessage(message)
    {
        $('.ls_farpost_result_text').empty();
        $('.ls_farpost_result_text').html(message);
        $('.ls_farpost_result_wrap').show();
    }

    // функция настройки интерфейса по данным полученным из настроечного файла
    function set_settings(data = {}) {
        var i = 0;
        data.profileName = (data.profileName == undefined || data.profileName == '')? '<?=LSStatic::message("LS_FARPOST_PROFILE_DEFAULT")?>': data.profileName;
        data.profileCode = (data.profileCode == undefined)? '': data.profileCode;
        $('#ls_farpost_form h3 span').empty().html(data.profileName);
        $('#ls_farpost_form input[name="profileCode"]').val(data.profileCode);
        $('#ls_farpost_form input[name="profileName"]').val(data.profileName);

        if(data.enabled == "1")
        {
            $(".enableagent :selected").removeAttr("selected");
            $(".enableagent").val("1").change();
            $(".enableagent option[value='1']").attr('selected', true);
            if(data.start=="0"){
                $(".start_work").show();
                $(".stop_work").hide();
                clearInterval(timerID);
            }
            if(data.start==1){
                $(".start_work").hide();
                $(".stop_work").show();
                if(timerID==undefined || timerID==false){
                    //через каждые 10 сек. проверяем к-во выгруженных товаров.
                    console.log(data.profileCode);
                    timerID = setInterval(function () {
                        load_settings(set_settings, set_information, data.profileCode);
                    },10000);
                }
            }
            if(!$.isEmptyObject(data.iBlock)){
                $('.iblockselect').val(data.iBlock.id).change();
                if(!$.isEmptyObject(data.iBlock.sections)){
                    $(".sectiontypeselect :selected").removeAttr("selected");
                    $(".sectiontypeselect").val("2");
                    $('.sectiontypeselect').change();
                    for(i = 0; i < data.iBlock.sections.id; i++)
                    {
                        $('.sectionselect option').each(function(){
                            if($(this).val() == data.iBlock.sections.id[i])
                            {
                                $(this).prop('selected', true);
                            }
                            else 
                            {
                                $(this).prop('selected', false);
                            }
                        });
                    }
                    if(data.iBlock.sections.subsections == 'Y')
                    {
                        $('.sectionsub').val('Y');
                    }
                    else
                    {
                        $('.sectionsub').val('N');
                    }
                }
                else
                {
                    $(".sectiontypeselect").val("1");
                    $('.sectiontypeselect').change();
                }
                if(data.iBlock.pictureType != undefined)
                {
                    $('select[name="picture_type"]').val(data.iBlock.pictureType).change();
                }
                $('select[name="price_type"]').val(data.iBlock.priceType).change();
                if("discount" in  data.iBlock){
                    $("input[name='discount']").prop('checked', true);
                    $('.type_percent').show();
                    $('.shop_discount_block').show();
                    $('input[name="percent_value"]').val(data.iBlock.discount);
                    if("allowShopDiscount" in  data.iBlock && data.iBlock.allowShopDiscount =="1"){
                        $('input[name="shop_discount"]').prop('checked', true);
                    }
                    else
                    {
                        $('input[name="shop_discount"]').prop('checked', false);
                    }
                }
                else
                {
                    $("input[name='discount']").prop('checked', false);
                    $('.type_percent').hide();
                    $('.shop_discount_block').hide();
                }
            }
            if("offers_join" in  data){
                if(data.offers_join == 1)
                {
                    $("input[name='offers_join']").prop('checked', true);
                }
                else
                {
                    $("input[name='offers_join']").prop('checked', false);
                }
                //$("input[name='not_check_count']").click();
            }
            else 
            {
                $("input[name='offers_join']").prop('checked', false);
            }
            if("check_store" in  data){
                if(data.check_store == 1)
                {
                    $("input[name='check_store']").prop('checked', true);
                }
                else
                {
                    $("input[name='check_store']").prop('checked', false);
                }
                //$("input[name='check_store']").click();
                if("store_id" in  data){
                    $('.store_id option').each(function(){
                        if(data.store_id.indexOf($(this).val()) > -1)
                        {
                            $(this).prop('selected', true);
                        }
                        else
                        {
                            $(this).prop('selected', false);
                        }
                    });
                }
                if($("input[name='check_store']").prop('checked'))
                {
                    $('.stores_select').show();
                }
                else
                {
                    $('.stores_select').hide();
                }
            }
            else
            {
                $("input[name='check_store']").prop('checked', false);
                $('.stores_select').hide();
            }
            if("not_check_count" in  data){
                if(data.not_check_count == 1)
                {
                    $("input[name='not_check_count']").prop('checked', true);
                }
                else
                {
                    $("input[name='not_check_count']").prop('checked', false);
                }
            }
            else
            {
                $("input[name='not_check_count']").prop('checked', false);
            }
            if("quantity" in data){
                if(data.quantity == 1){
                    $("input[name='quantity']").prop('checked', true);
                } else
                {
                    $("input[name='quantity']").prop('checked', false);
                }
            }
            if("gNumber" in  data){
                $("input[name='cnt']").val(data.gNumber);
            }
            else
            {
                $("input[name='cnt']").val('50');
            }
            if("startTime" in  data){
                $("input[name='time']").val(data.startTime);
            }
            else
            {
                $("input[name='time']").val('');
            }
            if("exportFormat" in data){
                $(".exportselect :selected").removeAttr("selected");
                $(".exportselect").val(data.exportFormat).change();
                $(".exportselect option[value='"+data.exportFormat+"']").attr('selected', true);
                if(data.exportFormat == "xml"){
                    if("shopName" in data){
                        $("input[name='shop_name']").val(data.shopName);
                    }
                    if("company" in data){
                        $("input[name='company']").val(data.company);
                    }
                    if("url" in data){
                        $("input[name='url']").val(data.url);
                    }
                }
            }
        }
        else
        {
            $(".enableagent :selected").removeAttr("selected");
            $(".enableagent option[value='0']").attr('selected', true);
            $(".enableagent").val("0").change();
            $(".start_work").hide();
            $(".stop_work").hide();
            if(timerID != undefined)
            {
                clearInterval(timerID);
            }

        }
    }
    // функция установки информационного сообщения в зависимости от параметров
    function set_information(data) {
        //загрузка состояния выгрузки
        var message = "";
        if("start" in data){
            if(data.start == "1") {
                message = '<?=LSStatic::message("LS_FARPOST_HEAD1")?>'+' - '+ '<?=LSStatic::message("LS_FARPOST_WORK")?>. ';
                if("loaded" in data) {
                    message += '<?=LSStatic::message("LS_FARPOST_DONE")?>' + data.loaded + '. ';
                }
            } else {
                if("enabled" in data) {
                    if(data.enabled !="0") {
                        if ("startTime" in data) {
                            if (data.startTime != "" && data.startTime != "0") {
                                message = '<?=LSStatic::message("LS_FARPOST_HEAD1")?>' + ' - ' + '<?=LSStatic::message("LS_FARPOST_WORK_SCHEDULED")?>. ';
                                message += '<?=LSStatic::message("LS_FARPOST_SCHEDULED")?>' + data.startTime + '. ';
                            } else {
                                message = '<?=LSStatic::message("LS_FARPOST_HEAD1")?>' + ' - ' + '<?=LSStatic::message("LS_FARPOST_STOP")?>. ';
                                message += '<?=LSStatic::message("LS_FARPOST_SCHEDULED_NOT_SET")?>';
                            }
                        }
                        if ("finishedAt" in data) {
                            message += '<?=LSStatic::message("LS_FARPOST_END")?>' + data.finishedAt + '. ' ;
                            if("loaded" in data){
                                if(data.loaded !="0"){
                                    message += '<?=LSStatic::message("LS_FARPOST_DONE")?>' + data.loaded + '. ';

                                }
                            }
                            message += '<?=LSStatic::message("LS_FARPOST_DOWNLOAD")?><a href="'+ arr[0] + "//" + arr[2] +'/farpost/export' + (data.profileCode !==""?'_'+ data.profileCode:'') + '.' + data.exportFormat + '" download>export' + (data.profileCode !==""?'_'+ data.profileCode:'') +'.' + data.exportFormat + '</a>. '; //
                        }
                    }
                    else {
                        message = '<?=LSStatic::message("LS_FARPOST_HEAD1")?>' + ' - ' + '<?=LSStatic::message("LS_FARPOST_STOP")?>. ';
                    }
                }
                else {
                    message = '<?=LSStatic::message("LS_FARPOST_HEAD1")?>' + ' - ' + '<?=LSStatic::message("LS_FARPOST_STOP")?>. ';
                }
            }
        } else {
            message = '<?=LSStatic::message("LS_FARPOST_HEAD1")?>' + ' - ' + '<?=LSStatic::message("LS_FARPOST_STOP")?>. ';
        }
        showNoticeMessage(message);
    }

    //--функция загрузки данных из настроечного файла
    function load_settings(set = Function(), info = Function(), profile = '') {
        var rnd = new Date().getTime();
        var url = '<?=$base_url?>item=system&settings=load&profile='+profile+'&rnd=' + rnd;
        BX.showWait();
        $.ajax({
            url: url,
            type: 'POST',
            data: {},
            success: function (respond, textStatus, jqXHR) {
                BX.closeWait();
                // Если все ОК
                if(respond != undefined && respond != '' && (respond.error == undefined || respond.error == '')) {
                    try {
                        system = JSON.parse(respond);
                    } catch (e) {
                        console.error('Invalid JSON response', respond);
                        return;
                    }
                    set(system);
                    info(system);
                    reloadPictureTypeSelect($('.iblockselect option:selected').val());
                    if(system.enabled == 1)
                    {
                    	$('.start_work').show();
                    }
                }
                else {
                    console.log('<?=LSStatic::message("LS_FARPOST_ERROR_SERVER")?>: ' + respond.error);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                BX.closeWait();
                console.log('<?=LSStatic::message("LS_FARPOST_ERROR_AJAX")?>: ' + textStatus);
            }
        });
    }

    function deleteProfile(code)
    {
        if(code == '' || code == undefined) 
        {
            return false;
        }
        if(confirm('<?=LSStatic::message("LS_FARPOST_INPUT_PROFILE_DELETE_SUBMIT")?>'))
        {
            var rnd = new Date().getTime();
            var url = '<?=$base_url?>item=profiles&settings=delete&code='+code+'&rnd=' + rnd;
            BX.showWait();
            $.ajax({
                url: url,
                type: 'POST',
                data: {},
                success: function (respond, textStatus, jqXHR) {
                    BX.closeWait();
                    updateProfilesData();
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    BX.closeWait();
                    console.log('<?=LSStatic::message("LS_FARPOST_ERROR_AJAX")?>: ' + textStatus);
                }
            });
        }
    }

    function changeProfile(profile_code)
    {
        if(profile_code == undefined)
        {
            return false;
        }
        load_settings(set_settings, set_information, profile_code);
    }

    function updateProfilesData()
    {
        var rnd = new Date().getTime();
        var url = '<?=$base_url?>item=profiles&rnd=' + rnd;
        BX.showWait();
        $.ajax({
            url: url,
            type: 'POST',
            data: {},
            dataType: 'json',
            success: function (respond, textStatus, jqXHR) {
                BX.closeWait();
                // Если все ОК
                if (respond.error == undefined || respond.error == '') {
                    //console.log(respond);
                    var html = '';
                    for(var i = 0; i < respond.profiles.length; i++)
                    {
                        html += '<li data-code="'+respond.profiles[i].code+'"><span>'+respond.profiles[i].name+'</span><div class="actions_block">';
                        html += '<a href="javascript:void(0);" onclick="changeProfile(\''+respond.profiles[i].code+'\');"><?=LSStatic::message("LS_FARPOST_INPUT_PROFILE_LINK_CHANGE")?></a>';
                        html += ' | <a href="javascript:void(0);" onclick="deleteProfile(\''+respond.profiles[i].code+'\');"><?=LSStatic::message("LS_FARPOST_INPUT_PROFILE_LINK_DELETE")?></a>';
                        html += '</div></li>';
                    }
                    $('.profile_blocks ul').empty().html(html);
                }
                else {
                    console.log('<?=LSStatic::message("LS_FARPOST_ERROR_SERVER")?>: ' + respond.error);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                BX.closeWait();
                console.log('<?=LSStatic::message("LS_FARPOST_ERROR_AJAX")?>: ' + textStatus);
            }
        });
    }


$(document).ready(function() {

    function urlLit(w, v)
    {
        var tr = ['a','b','v','g','d','e',['zh','j'],'z','i','y','k','l','m','n','o','p','r','s','t','u','f','h','c','ch','sh',['shh','shch'],'~','y','~','e','yu','ya','~',['jo','e']];
        var ww = '';
        w = (w || '').toLowerCase();
        for (var i = 0; i < w.length; ++i)
        {
            var cc = w.charCodeAt(i);
            var ch = (cc >= 1072 ? tr[cc - 1072] : w[i]);
            if (ch === undefined) {
                continue;
            }
            if (typeof ch === 'string') {
                ww += ch;
            } else {
                ww += ch[v];
            }
        }
        return ww.replace(/[^a-zA-Z0-9\-]/g, '-').replace(/[-]{2,}/gim, '-').replace(/^\-+/g, '').replace(/\-+$/g, '');
    }

    $('.popup_form_profile input[name="profileName"]').bind('change keyup input click', function() {
        $('.popup_form_profile input[name="profileCode"]').val(urlLit($('.popup_form_profile input[name="profileName"]').val(),0))
    });    

    $('.profile_default a').click(function(){
        changeProfile('');
    });

    $('.btn_save_profile').click(function(){
        var profileName = $('.popup_form_profile input[name="profileName"]').val(), profileCode = $('.popup_form_profile input[name="profileCode"]').val();
        if(profileName.length > 0 && profileCode.length > 0)
        {
            var data = get_params();
            data.start = 0;
            data.step = 1;
            data.loaded = 0;
            data.gID = 0;
            data.profileCode = profileCode;
            data.profileName = profileName;
            save_settings(data, true);
            $('.popover').click();
        }
    });

    $('.save_settings_as_profile').click(function(){
        $('.popover').show();
        $('.popup_form_profile').show();
    });

    $('.popup_form_profile .close, .popover').click(function(){
        $('.popover').hide();
        $('.popup_form_profile').hide();
    });

    $('.sectiontypeselect').change(function(){
        var type = $('.sectiontypeselect option:selected').val();
        if(type == '2')
        {
            var iblock = $('.iblockselect option:selected').val();
            var rnd = new Date().getTime();
            var url = '<?=$base_url?>item=sections&IBLOCK_ID='+iblock+'&rnd='+rnd;
            BX.showWait();
            $.ajax({
                url: url,
                type: 'POST',
                data: {},
                cache: false,
                dataType: 'html',
                processData: false, // Не обрабатываем файлы (Don't process the files)
                contentType: false, // Так jQuery скажет серверу что это строковой запрос
                success: function(respond, textStatus, jqXHR ){
                    BX.closeWait();
                    // Если все ОК
                    if(respond != undefined) {
                        $('.sectionselect').empty().html(respond);
                        $('.sectionselect_block').show();
                        $('.sectionsub_block').show();
                        if (system.iBlock != undefined){
                            if (!$.isEmptyObject(system.iBlock.sections)) {
                                system.iBlock.sections.id.forEach(function (item) {
                                    $('.sectionselect option[value="' + item + '"]').attr('selected', true);
                                });
                                if (system.iBlock.sections.subsections == "N") {
                                    $(".sectionsub :selected").removeAttr("selected");
                                    $(".sectionsub").val("N").change();
                                    $(".sectionsub option[value='N']").attr('selected', true);
                                }
                            }
                        }
                    }
                    else{
                        console.log('<?=LSStatic::message("LS_FARPOST_ERROR_SERVER")?>: ' + respond.error );
                    }
                },
                error: function( jqXHR, textStatus, errorThrown ){
                    BX.closeWait();
                    console.log('<?=LSStatic::message("LS_FARPOST_ERROR_AJAX")?>: ' + textStatus );
                }
            });
        }
        else
        {
            $('.sectionselect_block').hide();
            $('.sectionsub_block').hide();
        }
    });
    load_settings(set_settings,set_information);

    updateProfilesData();
    //------------------------------

    function save_settings(data={}, isUpdateProfiles=false){
        var rnd = new Date().getTime();
        var url = '<?=$base_url?>item=system&settings=save&rnd=' + rnd;
        BX.showWait();
        $.ajax({
            url: url,
            type: 'POST',
            data: data,
            success: function (respond, textStatus, jqXHR) {
                BX.closeWait();
                // Если все ОК
                if (respond.error == undefined || respond.error == '') {
                    //console.log(respond);
                    showNoticeMessage('<?=LSStatic::message("LS_FARPOST_TEXT_SAVED")?>');
                    if(isUpdateProfiles)
                    {
                        updateProfilesData();
                    }
                }
                else {
                    console.log('<?=LSStatic::message("LS_FARPOST_ERROR_SERVER")?>: ' + respond.error);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                BX.closeWait();
                console.log('<?=LSStatic::message("LS_FARPOST_ERROR_AJAX")?>: ' + textStatus);
            }
        });
    }
    // функция получения параметров настроек из формы в объект
    function get_params() {
        var data = {};
        data.enabled = $('.enableagent option:selected').val();// Состояние выгрузки
        data.profileCode = $('#ls_farpost_form input[name="profileCode"]').val();
        data.profileName = $('#ls_farpost_form input[name="profileName"]').val();
        if(data.enabled !="0") {
            data.iBlock ={};
            data.iBlock.id = $('.iblockselect option:selected').val();
            if ($('.sectiontypeselect option:selected').val() == '2') {
                data.iBlock.sections = {};
                data.iBlock.sections.id = $('.sectionselect').val();//
                data.iBlock.sections.subsections = $('.sectionsub').val();
            }
            data.iBlock.priceType = $("select[name='price_type']").val();
            data.iBlock.pictureType = $('.iblockpicturetype').val();
            if ($("input[name='discount']").is(':checked')) {
                data.iBlock.discount = $("input[name='percent_value']").val();
            }
            if ($("input[name='shop_discount']").is(':checked')){
                data.iBlock.allowShopDiscount = 1;
            }else {
                data.iBlock.allowShopDiscount = 0;
            }
            data.iBlock.properties = $('.iblockpropertyselect').val();
            data.iBlock.offers = $('.offerpropertyselect').val();
            data.iBlock.offersJoin = $('.offerpropertyselect_join').val();
            if ($("input[name='offers_join']").is(':checked')) {
                data.isOffersJoin = 1;
            }
            else
            {
                data.isOffersJoin = 0;
            }
            if ($("input[name='not_check_count']").is(':checked')) {
                data.not_check_count = 1;
            }
            else
            {
                data.not_check_count = 0;
            }
            if($("input[name='quantity']").is(':checked')){
                data.quantity = 1;
            }
            else {
                data.quantity = 0;
            }
            if ($("input[name='check_store']").is(':checked')) {
                data.check_store = 1;
            }
            else
            {
                data.check_store = 0;
            }
            data.store_id = $('.store_id').val();
            data.gNumber = $("input[name='cnt']").val();
            data.startTime = $("input[name='time']").val();
            data.exportFormat = $(".exportselect").val();
            if(data.exportFormat=="xml"){
                data.shopName = $("input[name='shop_name']").val();
                data.company = $("input[name='company']").val();
                data.url = $("input[name='url']").val();
            }
        }
        return data;
    }
    //функция проверки настроек перед отправкой
    function checkOptions(data={}){
        var message = '';
        if(data.enabled !=0) {
            if (data.exportFormat == "xml") {
                if (data.shopName == "") {
                    message += '<?=LSStatic::message("LS_FARPOST_SHOP_NAME")?>';
                }
                if (data.company == "") {
                    message += '<?=LSStatic::message("LS_FARPOST_COMPANY_NAME")?>';
                }
                return message;
            }
        }
        return message;
    }
	$('.start_work').click(function(){
        var data = {};
        data = $.extend(data,get_params());
        if(checkOptions(data)=='') {
            //$('.ls_margin_options_text').empty();
            //$('.ls_margin_options_wrap').hide();
            timerID = false;
            if(data.profileCode !==''){
                data.start = 1;
                data.step = 1;
                data.gID = 0;
                data.loaded = 0;
                save_settings(data, true);
                setTimeout(function(){
                    var profileCode = data.profileCode;
                    console.log(profileCode);
                    changeProfile(data.profileCode);
                    },500);
            } else {
                data.start = 1;
                data.loaded = 0;
                data.step = 1;
                data.gID = 0;
                save_settings(data);
                setTimeout(function(){load_settings(set_settings, set_information)},500);
            }

            //var timer = setTimeout(function(){load_settings(set_settings, set_information)},1000);

            $('body,html').animate({scrollTop: 1}, top_speed);

        } else {
            $('.ls_margin_options_text').html('<?=LSStatic::message("LS_FARPOST_NEED_TO_FILL")?>' + checkOptions(data));
            $('.ls_margin_options_wrap').show();
        };
	});
    $('.stop_work').click(function(){
        var data = {start:0,step:1,gID:0};
        data = $.extend(data,get_params());
        if(checkOptions(data)=='') {
            //$('.ls_margin_options_text').empty();
            //$('.ls_margin_options_wrap').hide();
            save_settings(data);
            setTimeout(function(){load_settings(set_settings, set_information)},1000);
            $('body,html').animate({scrollTop: 1}, top_speed);
            clearInterval(timerID);
        } else {
            $('.ls_margin_options_text').html('<?=LSStatic::message("LS_FARPOST_NEED_TO_FILL")?>' + checkOptions(data));
            $('.ls_margin_options_wrap').show();
        };
    });
    $('.save_settings').click(function () {
        var data = {};
        data = get_params();
        data.start = 0;
        data.loaded = 0;
        data.step = 1;
        data.gID = 0;
        console.log(data);
        if(checkOptions(data)=='') {
            //$('.ls_margin_options_text').empty();
            //$('.ls_margin_options_wrap').hide();
            save_settings(data);
            setTimeout(function(){load_settings(undefined, set_information)},800);
            setTimeout(function () {
                $('.save_settings').prop('disabled', false);
                $('.adm-btn-load-img-green').remove();
                $('.save_settings').removeClass('save_settings adm-btn-load').addClass('save_settings');
            }, 500);
            $('.start_work').show();
            $('body,html').animate({scrollTop: 1}, top_speed);
        } else {
            $('.ls_margin_options_text').html('<?=LSStatic::message("LS_FARPOST_NEED_TO_FILL")?>' + checkOptions(data));
            $('.ls_margin_options_wrap').show();
            setTimeout(function () {
                $('.save_settings').prop('disabled', false);
                $('.adm-btn-load-img-green').remove();
                $('.save_settings').removeClass('save_settings adm-btn-load').addClass('save_settings');
            }, 500);
        }
    });
    $('.enableagent').change(function() {
        var type = $('.enableagent option:selected').val();
        if(type == '0'){
            $('.edit-table tr').not('.enable_block').hide();
        } else {
            $('.edit-table tr').not('.enable_block').not('.sectionselect_block').not('.sectionsub_block').not('.type_percent').not('.shop_discount_block').not('.stores_select').not('.ls_margin_options_wrap').show();
        }
    });
    $('.offers_join_check input').change(function(){
        var ch = $(this).prop('checked');
        if(ch)
        {
            $('.offer_properties_join').show();
            $('.offer_properties').hide();
        }
        else
        {
            $('.offer_properties_join').hide();
            $('.offer_properties').show();
        }
    });
    function reloadPictureTypeSelect(iblock, selectedValue) {
        var rnd = new Date().getTime();
        var selected = selectedValue || $('.iblockpicturetype').val() || 'PREVIEW_PICTURE';
        var urlPicture = '<?=$base_url?>item=iBlockPictureProperties&IBLOCK_ID=' + iblock + '&SELECTED=' + encodeURIComponent(selected) + '&rnd=' + rnd;
        $.ajax({
            url: urlPicture,
            type: 'POST',
            data: {},
            cache: false,
            dataType: 'html',
            success: function (respond) {
                if (respond !== undefined && respond !== '') {
                    $('.iblockpicturetype').empty().html(respond);
                    if (typeof system !== 'undefined' && system.iBlock != undefined && system.iBlock.pictureType != undefined) {
                        $('.iblockpicturetype').val(system.iBlock.pictureType);
                    }
                }
            },
            error: function (jqXHR, textStatus) {
                console.error('iBlockPictureProperties ajax error:', textStatus, jqXHR.responseText);
            }
        });
    }

    $('.iblockselect').change(function () {
        var iblock = $('.iblockselect option:selected').val();
        var rnd = new Date().getTime();
        var url = '<?=$base_url?>item=iBlockProperties&IBLOCK_ID='+iblock+'&rnd='+rnd;
        var urlOffer = '<?=$base_url?>item=iBlockPropertiesSKU&IBLOCK_ID='+iblock+'&rnd='+rnd;
        reloadPictureTypeSelect(iblock);
        BX.showWait();
        $.ajax({
            url: url,
            type: 'POST',
            data: {},
            cache: false,
            dataType: 'html',
            processData: false, // Не обрабатываем файлы (Don't process the files)
            contentType: false, // Так jQuery скажет серверу что это строковой запрос
            success: function(respond, textStatus, jqXHR ){
                // Если все ОК
                if(respond != undefined && respond != ''){
                    $('.iblockpropertyselect').empty().html(respond);
                    $('.iblockpropertyselect').show();
					if("properties" in system.iBlock && system.iBlock.properties.length ) {
		            	system.iBlock.properties.forEach(function (item) {
		                	$('.iblockpropertyselect option[value="' + item + '"]').attr('selected', true);
						});
					}

			        $.ajax({
			            url: urlOffer,
			            type: 'POST',
			            data: {},
			            cache: false,
			            dataType: 'html',
			            processData: false, // Не обрабатываем файлы (Don't process the files)
			            contentType: false, // Так jQuery скажет серверу что это строковой запрос
			            success: function(respond, textStatus, jqXHR ){
			                BX.closeWait();
			                // Если все ОК
			                if(respond != undefined && respond != ''){
		                        $('.offerpropertyselect').empty().html(respond);
                                if(system.isOffersJoin == undefined || system.isOffersJoin == 0)
                                {
		                            $('.offer_properties').show();
                                }
                                else
                                {
		                            $('.offer_properties').hide();
                                }
		                        if("offers" in system.iBlock && system.iBlock.offers.length ){
		                            system.iBlock.offers.forEach(function (item) {
		                                $('.offerpropertyselect option[value="' + item + '"]').attr('selected', true);
		                            });
		                        }

		                        $('.offerpropertyselect_join').empty().html(respond);
                                if(system.isOffersJoin != undefined && system.isOffersJoin == 1)
                                {
		                            $('.offer_properties_join').show();
                                    $('input[name="offers_join"]').prop('checked', true);
                                }
                                else
                                {
		                            $('.offer_properties_join').hide();
                                    $('input[name="offers_join"]').prop('checked', false);
                                }
		                        if("offersJoin" in system.iBlock && system.iBlock.offersJoin.length){
		                            system.iBlock.offersJoin.forEach(function (item) {
		                                $('.offerpropertyselect_join option[value="' + item + '"]').attr('selected', true);
		                            });
		                        }
			                }
			                else{
		                        $('.offer_properties').hide();
			                    //$('.iblockpropertyselect').empty();
			                    //$('.iblockpropertyselect').hide();

		                        $('.offer_properties_join').hide();
			                    //$('.iblockpropertyselect_join').empty();
			                    //$('.iblockpropertyselect_join').hide();

			                    console.log('<?=LSStatic::message("LS_FARPOST_ERROR_SERVER")?>: ' + respond.error );
			                }
			            },
			            error: function( jqXHR, textStatus, errorThrown ){
			                BX.closeWait();
			                //$('.ls_margin_options_text').empty();
			                //$('.ls_margin_options_wrap').hide();
			                console.log('<?=LSStatic::message("LS_FARPOST_ERROR_AJAX")?>: ' + textStatus );
			            }
			        });
                }
                else{
                    $('.iblockpropertyselect').empty();
                    $('.iblockpropertyselect').hide();
                    console.log('<?=LSStatic::message("LS_FARPOST_ERROR_SERVER")?>: ' + respond.error );
                }
            },
            error: function( jqXHR, textStatus, errorThrown ){
                BX.closeWait();
                //$('.ls_margin_options_text').empty();
                //$('.ls_margin_options_wrap').hide();
                console.log('<?=LSStatic::message("LS_FARPOST_ERROR_AJAX")?>: ' + textStatus );
            }
        });

    });
    $('input[name="discount"]').click(function () {
        if($('input[name="discount"]').is(":checked")){
            $('.type_percent').show();
            $('.shop_discount_block').show();
        } else {
            $('.type_percent').hide();
            $('.shop_discount_block').hide();
        }
    });
    $('input[name="not_check_count"]').click(function(){
        if($('input[name="not_check_count"]').is(":checked")){
            $('.store_block').hide();
            $('.stores_select').hide();
        } else {
            $('.store_block').show();
            if($('input[name="check_store"]').is(":checked")){
                $('.stores_select').show();
            } else {
                $('.stores_select').hide();
            }
        }
    });
    $('input[name="check_store"]').click(function () {
        if($('input[name="check_store"]').is(":checked")){
            $('.stores_select').show();
        } else {
            $('.stores_select').hide();
        }
    });
    //Обработчик события изменеия типа выгрузки
    $('.exportselect').change(function () {
        if($('.exportselect').val()=="xml"){
            $('.extended_field_xml').show();
        }else $('.extended_field_xml').hide();
    });
	$('.continue_work').click(function(){
		ls_margin_step = -1;
		$('#last_work').val('Y');
		$('body,html').animate({scrollTop: 1}, top_speed);
		$('.stop_work').show();
		$('.continue_work').hide();
		ls_margin_work_stop = false;
		ls_margin_send_data();
	});
	$('.sectiontypeselect').change();
});
</script>
<style>
    .popover {
        opacity: 0.6;
        position: fixed;
        top: 0px;
        left: 0px;
        z-index: 999;
        display: none;
        width: 100%;
        height: 100%;
        background-color: #000;
    }
    .popup_form_profile {
        position: fixed;
        top: 100px;
        left: 45%;
        z-index: 1000;
        display: none;
        padding: 15px;
        background-color: #fff;
    }
    .popup_form_profile .close {
        width: 20px;
        height: 20px;
        position: absolute;
        right: 5px;
        top: 5px;
        cursor: pointer;
    }
    .popup_form_profile .body {
        margin: 15px 15px 10px 15px;
    }
    .popup_form_profile .item_block {
        margin: 10px 0px;
    }
    .popup_form_profile .item_block .btn_save_profile {
        margin: 0px auto;
    }
    .left_column {
        width: 70%;
        display: inline-block;
    }
    .right_column {
        width: 29%;
        display: inline-block;
        vertical-align: top;
    }
    .right_column h3 {
        margin-left: 20px;
    }
    .right_column ul {
        list-style: none;
    }
    .actions_block {
        display: block;
        margin: 10px 0px;
    }
    .actions_block a {
        font-size: 12px;
        text-decoration: none;
    }
    .actions_block a:hover {
        text-decoration: underline;
    }
    .profile_default a {
        margin: 10px 20px;
        font-size: 12px;
        text-decoration: none;
    }
    .profile_default a:hover {
        text-decoration: underline;
    }
</style>
<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");