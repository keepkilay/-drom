<?
//Скрипт отдачи файла
$file = $_SERVER['DOCUMENT_ROOT']."/farpost/export.xml";
$csv = $_SERVER['DOCUMENT_ROOT']."/farpost/export.csv";
if(file_exists($file))
{
	$data = file_get_contents($file);
	header("Content-Type: application/force-download");
	header("Content-Type: application/octet-stream");
	header("Content-Type: application/download");
	header("Content-Disposition: attachment;filename=farpost.xml");
	header("Content-Transfer-Encoding: binary ");
	echo $data;
}
else if(file_exists($csv))
{	$data = file_get_contents($csv);
	header("Content-Type: application/force-download");
	header("Content-Type: application/octet-stream");
	header("Content-Type: application/download");
	header("Content-Disposition: attachment;filename=farpost.csv");
	header("Content-Transfer-Encoding: binary ");
	echo $data;
}
?>