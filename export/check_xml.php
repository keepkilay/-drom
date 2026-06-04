<?
error_reporting(E_ALL);
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Lab-Su: check XML document for farpost</title>
</head>
<body>
<?
//Тестирование файла XML на синтаксические ошибки

function echoStr($str, $error = false)
{
    if(is_array($str))
    {
        $tmp = array("START ARRAY");
        $num = 1;
        foreach($str as $s)
        {
            $tmp[] = $num.': '.$s;
        }
        $str = implode('<br>', $tmp);
    }
    if($error)
    {
        echo '<p style="color: red; font-size: 16px;">'.$str.'</p><br>';
    }
    else
    {
        echo $str."<br>";
    }
}

echoStr("START");

$xml_file = $_SERVER['DOCUMENT_ROOT'].'/farpost/export.xml';

try {
    $xml = simplexml_load_file($xml_file);
}
catch(Exception $e) {
    echoStr($e, true);
}

if(!$xml) {
    echoStr("Ошибка загрузки XML", true);
    foreach(libxml_get_errors() as $error) {
        echoStr($error->message, true);
    }
}
else {
    echoStr('ShopName: '.$xml->shop->name);
    echoStr('Company: '.$xml->shop->company);
    echoStr('Url: '.$xml->shop->url);
    echoStr('currency count: '.count($xml->shop->currencies->currency));
    echoStr('Count catgories: '.count($xml->shop->categories->category));
    echoStr('Count offers: '.count($xml->shop->offers->offer));
}
?>
</body>
</html>
