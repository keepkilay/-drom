<?php

class LSStatic {

    public $log_file = "";

	static function get_encoding($str)
    {
        $cp_list = array('UTF-8', 'windows-1251');
        foreach ($cp_list as $codepage){
            if (function_exists('mb_check_encoding') && mb_check_encoding($str, $codepage)) {
                return $codepage;
            }
            if (md5($str) === md5(iconv($codepage, $codepage, $str))){
                return $codepage;
            }
        }
        return null;
    }

    static function toAjax($str)
    {
    	$enc = self::get_encoding($str);
    	if($enc !== "UTF-8" && $enc !== null)
    	{
    		if (function_exists('mb_convert_encoding')) {
    			$str = mb_convert_encoding($str, "UTF-8", $enc);
    		} else {
    			$str = iconv($enc, "UTF-8", $str);
    		}
    	}
    	return $str;
    }

    static function message($str, $ajax=false)
    {
    	return ($ajax)? self::toAjax(GetMessage($str)): GetMessage($str);
    }


    public function __construct($log_file)
    {
        $this->log_file = $log_file;
    }

    public function toLog($obj)
    {
        $str = (is_array($obj))? print_r($obj, true): $obj;
        $log = "<br>".date('Y-m-d H:i:s').': '.$str . PHP_EOL;
        file_put_contents($this->log_file, $log, FILE_APPEND);
    }

}
