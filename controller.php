<?php
namespace Fend;

/**
 * @author gary
 */
class Controller extends \Fend\Fend
{
    public $_tpl = '';
    public $reg  = '';

    /**
     * 获取原始HTTP请求体，并按照json格式解析返回
     * @param bool $assoc 是否返回数组格式
     * @return array|object
     * @throws \Fend\Exception
     */
    protected function getJsonRawBody($assoc = true)
    {
        $jsonBody = file_get_contents('php://input');
        $json     = json_decode($jsonBody, $assoc);
        if ($json === null) {
            throw new \Fend\Exception('json decode failed, ' . json_last_error_msg() . $jsonBody);
        }
        return $json;
    }

    /**
     * 获取x-www-form-urlencoded表单请求的data域数据，并按照json格式解析
     * @param type $assoc
     * @return type
     * @throws \Fend\Exception
     */
    public function getJsonFormData($assoc = true)
    {
        return $this->getJsonFormField('data', $assoc);
    }

    /**
     * 获取x-www-form-urlencoded表单请求的$field域数据，并按照json格式解析
     * @param type $assoc
     * @return type
     * @throws \Fend\Exception
     */
    public function getJsonFormField($field, $assoc = true)
    {
        $jsonBody = stripslashes(\Fend\Funcs\FendHttp::doPost($field));
        if (empty($jsonBody)) {
            return null;
        }
        $json = json_decode($jsonBody, $assoc);
        if ($json === null) {
            throw new \Fend\Exception('json decode failed, ' . json_last_error_msg() . $jsonBody);
        }
        return $json;
    }

}
