<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/modules/labsu.farpost/lib/Request.php';

$configPath = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/labsu.farpost/admin/system.json';
if (!file_exists($configPath)) {
    $fHdl = fopen($configPath, 'w');
    fclose($fHdl);
}
$jsonString = file_get_contents($configPath);

if (LSFarpostRequest::getString('settings') === 'delete') {
    $code = preg_replace('/[^a-zA-Z0-9_\-]/', '', LSFarpostRequest::getString('code'));
    if ($code !== '' && !empty($jsonString)) {
        $data = json_decode($jsonString, true);
        if (!empty($data['profiles'][$code])) {
            unset($data['profiles'][$code]);
            CAgent::RemoveAgent(
                "LSFarpostAddAgentExport('" . $code . "');",
                'labsu.farpost'
            );
            echo file_put_contents($configPath, json_encode($data, JSON_PRETTY_PRINT));
        }
    }
} else {
    $result = ['profiles' => []];
    if (!empty($jsonString)) {
        $data = json_decode($jsonString, true);
        $res = [];
        foreach (($data['profiles'] ?? []) as $code => $ar) {
            $res[] = [
                'name' => $ar['profileName'] ?? '',
                'code' => $code,
            ];
        }
        $result = ['profiles' => $res];
    }
    echo json_encode($result);
}
