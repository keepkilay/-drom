<?php
IncludeModuleLangFile(__FILE__);
$module_rights = $APPLICATION->GetGroupRight("labsu.farpost");

if ($USER->IsAdmin() || $module_rights > "D")
{
	$arItems = Array(
		Array(
			"text" => GetMessage("LS_FARPOST_MENU"),
			"url" => "ls_farpost.php",
			"more_url" => array(),
			"title" => GetMessage("LS_FARPOST_MENU_TITLE")
		),
	);
	$aMenu = array(
		"parent_menu" => "global_menu_services",
		"section" => "labsu.farpost",
		"sort" => 250,
		"text" => GetMessage("LS_FARPOST_MENU"),
		"title" => GetMessage("LS_FARPOST_MENU_TITLE"),
		"icon" => "farpost_menu_icon",
		"page_icon" => "",
		"items_id" => "ls_farpost_menu",
		"items" => $arItems,
	);
	return $aMenu;
}
else
{
	return false;
}
