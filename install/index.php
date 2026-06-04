<?php
IncludeModuleLangFile(__FILE__);

class labsu_farpost extends CModule
{
	var $MODULE_ID = "labsu.farpost";
	var $MODULE_VERSION;
	var $MODULE_VERSION_DATE;
	var $MODULE_NAME;
	var $MODULE_DESCRIPTION;
	var $MODULE_CSS;

	function __construct()
	{
		$this->MODULE_ID = "labsu.farpost";
		$this->MODULE_NAME = "Выгрузка Farpost (Transopt)";
		$this->MODULE_DESCRIPTION = "Выгрузка товаров на Farpost/Drom с исключением дублей OEM+Производитель";
		$this->PARTNER_NAME = "transopt";
		$this->PARTNER_URI = "https://transopt.net";
		$arModuleVersion = array();
		$path = str_replace("\\", "/", __FILE__);
		$path = substr($path, 0, strlen($path) - strlen("/index.php"));
		include($path."/version.php");
		if (is_array($arModuleVersion) && array_key_exists("VERSION", $arModuleVersion))
		{
			$this->MODULE_VERSION = $arModuleVersion["VERSION"];
			$this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
		}
	}

    function DoInstall()
    {
        RegisterModule("labsu.farpost");
        $this->InstallFiles();
       	LocalRedirect("/bitrix/admin/ls_farpost.php");
    }

    function DoUninstall()
    {
        UnRegisterModule("labsu.farpost");
        $this->UnInstallFiles();
        LocalRedirect("/bitrix/admin/partner_modules.php");
    }

	function InstallFiles()
	{
		CopyDirFiles(__DIR__ . "/admin", $_SERVER["DOCUMENT_ROOT"]."/bitrix/admin", true, true);
		if(!is_dir($_SERVER["DOCUMENT_ROOT"].'/farpost'))
		{
			mkdir($_SERVER["DOCUMENT_ROOT"].'/farpost');
		}
		if(is_dir(__DIR__ . "/farpost"))
		{
			CopyDirFiles(__DIR__ . "/farpost", $_SERVER["DOCUMENT_ROOT"]."/farpost", true, true);
		}
        if(!$this->isExistAgent("LSFarpostAddAgentExport();")) {
            CAgent::AddAgent(
                "LSFarpostAddAgentExport();",
                "labsu.farpost",
                "N",
                86400,
                "",
                "N",
                "",
                30
            );
        }
        if(!$this->isExistAgent("LSFarpostExport();")) {
            CAgent::AddAgent(
                "LSFarpostExport();",
                "labsu.farpost",
                "N",
                10,
                "",
                "N",
                "",
                30
            );
        }
		return true;
	}

	function UnInstallFiles()
	{
		DeleteDirFiles(__DIR__ . "/admin", $_SERVER["DOCUMENT_ROOT"]."/bitrix/admin");
        CAgent::RemoveAgent(
            "LSFarpostAddAgentExport();",
            "labsu.farpost"
        );
		CAgent::RemoveAgent(
            "LSFarpostExport();",
            "labsu.farpost"
        );
		return true;
	}

	function isExistAgent($agentName)
	{
		$res = CAgent::GetList(Array("ID" => "DESC"), array("MODULE_ID" => "labsu.farpost", "NAME" => $agentName))->Fetch();
		return is_array($res);
	}
}
