<?php

use Bitrix\Main\Application;
use Bitrix\Main\Loader;

const LS_FARPOST_MODULE_ID = 'labsu.farpost';
const LS_FARPOST_SYSTEM_JSON = '/local/modules/labsu.farpost/admin/system.json';

$moduleDir = __DIR__;
require_once $moduleDir . '/lib/DedupFilter.php';
require_once $moduleDir . '/lib/Request.php';
require_once $moduleDir . '/lib/PictureType.php';
require_once $moduleDir . '/export/export.php';

Loader::includeModule('iblock');
Loader::includeModule('currency');
Loader::includeModule('catalog');
Loader::includeModule('sale');

IncludeModuleLangFile(__FILE__);

CModule::AddAutoloadClasses(
    LS_FARPOST_MODULE_ID,
    [
        'LSStatic' => 'lib/lsstatic.php',
    ]
);

function logArray(): void
{
    $args = func_get_args();
    $result = '';

    foreach ($args as $arg) {
        $result .= "\n\n" . print_r($arg, true);
    }

    if (!defined('LOG_FILENAME')) {
        define('LOG_FILENAME', Application::getDocumentRoot() . '/bitrix/log.txt');
    }

    AddMessage2Log($result, 'logArray -> ');
}

/**
 * Строка перезапуска агента для CAgent (имя функции в БД не меняется).
 */
function LSFarpostFormatAddAgentExportCall(string $profileCode = ''): string
{
    return "LSFarpostAddAgentExport('" . $profileCode . "');";
}

function LSFarpostAddAgentExport(string $profileCode = ''): string
{
    ob_start();

    try {
        $jsonPath = Application::getDocumentRoot() . LS_FARPOST_SYSTEM_JSON;
        $jsonString = file_exists($jsonPath) ? (string) file_get_contents($jsonPath) : '';
        $data = json_decode($jsonString, true);

        if (!is_array($data)) {
            $data = [];
        }

        if ($profileCode === '') {
            $data['start'] = 1;
            $data['step'] = 1;
            $data['loaded'] = 0;
            $data['gID'] = 0;
        } else {
            $data['profiles'][$profileCode]['start'] = 1;
            $data['profiles'][$profileCode]['step'] = 1;
            $data['profiles'][$profileCode]['loaded'] = 0;
            $data['profiles'][$profileCode]['gID'] = 0;
        }

        file_put_contents(
            $jsonPath,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        enableAgent(
            "LSFarpostExport('');",
            ConvertTimeStamp(microtime(true) + CTimeZone::GetOffset(), 'FULL', 'ru')
        );

        // Полный прогон за один запуск агента (cron раз в минуту иначе не успевает)
        $maxSteps = 500;

        for ($i = 0; $i < $maxSteps; $i++) {
            LSFarpostExport($profileCode);
            $state = json_decode((string) file_get_contents($jsonPath), true);
            $isRunning = $profileCode === ''
                ? ((int) ($state['start'] ?? 0) === 1)
                : ((int) ($state['profiles'][$profileCode]['start'] ?? 0) === 1);

            if (!$isRunning) {
                break;
            }
        }

        return LSFarpostFormatAddAgentExportCall($profileCode);
    } finally {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}

function enableAgent(string $agentName, string $startDate = ''): void
{
    $res = CAgent::GetList(
        ['ID' => 'DESC'],
        ['MODULE_ID' => LS_FARPOST_MODULE_ID, 'NAME' => $agentName]
    )->Fetch();

    if (!$res) {
        return;
    }

    CAgent::Update($res['ID'], [
        'ACTIVE' => 'Y',
        'USER_ID' => 1,
        'NEXT_EXEC' => $startDate,
    ]);
}

function disableAgent(string $agentName): void
{
    $res = CAgent::GetList(
        ['ID' => 'DESC'],
        ['MODULE_ID' => LS_FARPOST_MODULE_ID, 'NAME' => $agentName]
    )->Fetch();

    if (!$res) {
        return;
    }

    CAgent::Update($res['ID'], ['ACTIVE' => 'N']);
}

function isAgentEnabled(string $agentName): bool
{
    $res = CAgent::GetList(
        ['ID' => 'DESC'],
        ['MODULE_ID' => LS_FARPOST_MODULE_ID, 'NAME' => $agentName]
    )->Fetch();

    return is_array($res) && ($res['ACTIVE'] ?? '') === 'Y';
}

function isExistAgent(string $agentName): bool
{
    $res = CAgent::GetList(
        ['ID' => 'DESC'],
        ['MODULE_ID' => LS_FARPOST_MODULE_ID, 'NAME' => $agentName]
    )->Fetch();

    return is_array($res);
}

function changeTimeStartAgent(string $agentName, string $startDateTime = ''): void
{
    $res = CAgent::GetList(
        ['ID' => 'DESC'],
        ['MODULE_ID' => LS_FARPOST_MODULE_ID, 'NAME' => $agentName]
    )->Fetch();

    if (!$res) {
        return;
    }

    CAgent::Update($res['ID'], ['NEXT_EXEC' => $startDateTime]);
}
