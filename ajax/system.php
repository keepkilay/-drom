<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/modules/labsu.farpost/lib/Request.php';

$configPath = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/labsu.farpost/admin/system.json';
if (!file_exists($configPath)) {
    $fHdl = fopen($configPath, 'w');
    fclose($fHdl);
}

$defParams = [
    'loaded' => 0,
    'step' => 1,
    'gID' => 0,
    'finishedAt' => 0,
];

$jsonString = file_get_contents($configPath);
if (!empty($jsonString)) {
    $data = json_decode($jsonString, true);
} else {
    $data = [
        'start' => 0,
        'enabled' => 0,
        'profiles' => [],
    ];
    $jsonString = json_encode($data);
}

if (!is_array($data)) {
    $data = [];
}

if (!function_exists('filter_options')) {
    function filter_options($ar)
    {
        $res = [];
        foreach ($ar as $k => $a) {
            if ($k === 'step' || $k === 'gID' || $k === 'loaded' || $k === 'finishedAt' || $k === 'profiles') {
                $res[$k] = $a;
            }
        }
        return $res;
    }
}

$settingsAction = LSFarpostRequest::getString('settings');

if ($settingsAction === 'save') {
    $post = LSFarpostRequest::getPost();
    $arFiltered = filter_options($data);
    $profileCode = LSFarpostRequest::getProfileCode();

    if (array_key_exists('start', $post)) {
        if ((int)$post['start'] === 1) {
            enableAgent("LSFarpostExport('');", ConvertTimeStamp(microtime(true) + CTimeZone::GetOffset(), 'FULL', 'ru'));
        }
        if ((int)$post['start'] === 0) {
            $startTime = isset($post['startTime']) ? (string)$post['startTime'] : '';
            if ($startTime !== '') {
                $format = 'DD.MM.YYYY HH:MI:SS';
                $dateTime = ConvertTimeStamp(microtime(true) + CTimeZone::GetOffset(), 'FULL', 'ru');
                $arrDateTime = ParseDateTime($dateTime, $format);
                $arrScheduledDateTime = explode(':', $startTime);
                if ($arrDateTime['HH'] > $arrScheduledDateTime[0]) {
                    $arrDateTime['DD']++;
                }
                if ($arrDateTime['HH'] == $arrScheduledDateTime[0]) {
                    if ($arrDateTime['MM'] > $arrScheduledDateTime[1]) {
                        $arrDateTime['DD']++;
                    }
                }
                $arrDateTime['HH'] = $arrScheduledDateTime[0];
                $arrDateTime['MI'] = $arrScheduledDateTime[1];
                $arrDateTime['SS'] = 0;
                $startDateAgent = sprintf(
                    '%02d.%02d.%04d %02d:%02d:%02d',
                    $arrDateTime['DD'],
                    $arrDateTime['MM'],
                    $arrDateTime['YYYY'],
                    $arrDateTime['HH'],
                    $arrDateTime['MI'],
                    $arrDateTime['SS']
                );
                if (!isExistAgent("LSFarpostAddAgentExport('" . $profileCode . "');")) {
                    CAgent::AddAgent(
                        "LSFarpostAddAgentExport('" . $profileCode . "');",
                        'labsu.farpost',
                        'N',
                        86400,
                        '',
                        'Y',
                        $startDateAgent,
                        30,
                        1
                    );
                } else {
                    if (!isAgentEnabled("LSFarpostAddAgentExport('" . $profileCode . "');")) {
                        enableAgent("LSFarpostAddAgentExport('" . $profileCode . "');", $startDateAgent);
                    } else {
                        changeTimeStartAgent("LSFarpostAddAgentExport('" . $profileCode . "');", $startDateAgent);
                    }
                }
            } else {
                disableAgent("LSFarpostExport('');");
            }
        }
        if ($profileCode !== '') {
            $mas = $post;
            unset($mas['start']);
            $arFiltered['profiles'][$profileCode] = ['start' => $post['start']] + $mas;
            $res = array_merge($data, $arFiltered);
        } else {
            $mas = $post;
            unset($mas['start']);
            $res = ['start' => $post['start']] + $arFiltered;
            $res = array_merge($res, $mas);
        }
    } else {
        if (isset($post['enabled']) && (string)$post['enabled'] === '0') {
            disableAgent("LSFarpostAddAgentExport('" . $profileCode . "');");
        }
        if ($profileCode !== '') {
            $data['profiles'] = empty($data['profiles']) ? [] : $data['profiles'];
            $data['profiles'] = array_merge($data['profiles'], [$profileCode => array_merge($defParams, $post)]);
            $res = $data;
        } else {
            $res = array_merge($arFiltered, $post);
        }
    }
    $result = file_put_contents($configPath, json_encode($res, JSON_PRETTY_PRINT));
    echo $result;
}

if ($settingsAction === 'load') {
    $profile = preg_replace('/[^a-zA-Z0-9_\-]/', '', LSFarpostRequest::getString('profile'));
    if ($profile !== '' && !empty($data['profiles'][$profile])) {
        $jsonString = json_encode($data['profiles'][$profile]);
    }
    echo $jsonString;
}
