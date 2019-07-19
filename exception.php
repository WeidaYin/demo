<?php
namespace Fend;

class Exception extends \Exception
{
    private $_trace=array();
    private $_temp=null;

    public static function Factory($msg='')
    {
        return new self($msg);
    }
    /**
     * 激活异常消息
     *
     * @param string $msg           消息
     * @param integer|string $code  异常代码
     * @param boolean $fp           是否返回串
    **/
    public function __construct($msg, $code=0, $fp=false)
    {
        parent::__construct($msg, $code);
        $this->_trace=$this->getTrace();

        //解决函数方式调用异常处理时的多余异常消息
        $fp && array_shift($this->_trace);
    }

    /**
     * 发送异常输出
     *
     * @param integer $tp 报告级别,1-3
     * @param integer $sp 是否返回串
     * @return string|echo
    **/
    public function showTry($tp=0, $sp=false)
    {
        //样式配置
        $item="<style>"
             ."td,th{font-family: Courier New, Arial;font-size:11px;}"
             ."table{text-align:left;width:98%;border:0;border-collapse:collapse;margin-bottom:5px;table-layout:fixed;word-wrap:break-word;background:#FFF;}"
             ."table th{border:1px solid #000;background:#CCC;padding: 2px;}"
             ."table td {border:1px solid #000;background:#FFFCCC;padding: 2px;}"
             ."tr.bg th {background:#D5EAEA;}"
             ."tr.bg td {background:#FFFFFF;}"
             ."</style>\r\n";
        //普通消息
        $item.="<table>";
        $item.="<tr class='bg'><th width='65' style='color:#ff0000'>OutType:</th><td>error-message</td></tr>";
        $item.="<tr><th>#1:</th><td style='color:#ff0000'>".$this->getMessage()."</td></tr>";
        !empty($this->string) && $item.="<tr valign='top'><th>#3:</th><td>{$this->string}</td></tr>";
        $item.="</table>";

        //错误跟踪
        if ($tp>=1) {
            $item.="<table>";
            $item.="<tr class='bg'><th width='65'>OutType:</th><td>Trace</td></tr>";
            foreach ($this->_trace as $k=>$v) {
                $item.="<tr valign='top'><th>#".++$k.":</th><td>".self::toTrace(--$k)."</td></tr>";
            }
            $item.="</table>";
        }

        //当前被载入的文件
        if ($tp>=2) {
            $item.="<table>";
            $item.="<tr class='bg'><th width='65'>OutType:</th><td>Include Files</td></tr>";
            $ifile = get_included_files();
            foreach ($ifile as $k=>&$v) {
                $item.="<tr><th>#".++$k.":</th><td>{$v}</td></tr>";
            }
            $item.="</table>";
        }

        //结果类型
        if (!$sp) {
            @header("HTTP/1.0 404 Not Found");
            echo "<html>\r\n<head>\r\n<title>404 Not Found</title>\r\n</head><body>\r\n{$item}\r\n</body>\r\n</html>";
            return;
        }
        return $item;
    }



    /**
     * 格式化跟踪集合
     *
     * @param integer $tp 报告级别,1-3
     * @param integer $sp 是否返回串
     * @return string
    **/
    private function toTrace($k)
    {
        if ($k==-1) {
            $v=end($this->_trace);
            $k=key($this->_trace);
        } else {
            $v=$this->_trace[$k];
        }
        settype($v, 'array');
        $msg=null;
        $msg.=array_key_exists('file', $v) ? $v['file'] : $this->_temp." Internal Function" ;
        $this->_temp=$msg;
        $msg.=array_key_exists('line', $v) ? "[{$v['line']}]:" : ':' ;
        $msg.=array_key_exists('class', $v) ? $v['class'] : null ;
        $msg.=array_key_exists('type', $v) ? $v['type'] : null ;
        if (array_key_exists('function', $v)) {
            $msg.=$v['function']."(";
            if (array_key_exists('args', $v) && count($v['args'])>0) {
                foreach ($v['args'] as $ark=>&$arv) {
                    $type=gettype($arv);
                    $msg.=$ark>0 ? ',' : null;
                    switch ($type) {
                        case 'boolean'://布尔类型
                            $msg.=$arv ? 'True' : 'False';
                            break;
                        case 'integer'://整数型
                        case 'double'://实数
                            $msg.=strlen($arv)<=20 ? $arv : substr($arv, 0, 17).'...';
                            break;
                        case 'string'://字符串
                            $msg.="'".(strlen($arv)<=20 ? $arv : substr($arv, 0, 17).'...')."'";
                            break;
                        case 'array'://数组类型
                        case 'object'://对象类型
                        case 'resource'://结果类型
                        case 'NULL'://空类型
                        case 'unknown type'://未配置类型
                            $msg.=ucwords(strtolower($type));
                            break;
                        default:
                            $msg.='null';
                            break;
                    }
                }
            }
            $msg.=")";
        }
        return $msg;
    }
}
