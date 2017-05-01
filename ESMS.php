<?php
/**
 * @link http://github.com/yii2vn/esms
 * @copyright Copyright (c) 2017 Yii2VN
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2vn\esms;

use Yii;
use yii\base\Component;
use yii\httpclient\Client;

/**
 * Component hổ trợ gọi API từ dịch vụ [ESMS](http://esms.vn) để gửi tin nhắn đến khách hàng và tạo voice call.
 * để tìm hiểu thêm về API của ESMS bạn vui lòng xem tại đây https://esms.vn/TailieuAPI_V4_060215_Rest_Public.pdf
 *
 * @package yii2vn\esms
 * @author Vuong Minh <vuongxuongminh@gmail.com>
 */
class ESMS extends Component
{

    const BASE_REST_URL = 'http://rest.esms.vn/MainService.svc/json';

    const BASE_VOICE_URL = 'http://voiceapi.esms.vn/MainService.svc/json';

    public $apiKey;

    public $secretKey;

    public static $responseCodes = [
        100 => 'Request thành công',
        99 => 'Lỗi không xác định, thử lại sau',
        101 => 'Đăng nhập thất bại (api key hoặc secrect key không đúng)',
        102 => 'Tài khoản đã bị khóa',
        103 => 'Số dư tài khoản không đủ dể gửi tin',
        104 => 'Mã Brandname không đúng',
        118 => 'Loại tin nhắn không hợp lệ',
        119 => 'Brandname quảng cáo phải gửi ít nhất 20 số điện thoại',
        131 => 'Tin nhắn brandname quảng cáo độ dài tối đa 422 kí tự',
        132 => 'Không có quyền gửi tin nhắn đầu số cố định 8755'
    ];

    /**
     * Lớp client dùng để tạo request đến dịch vụ esms
     *
     * @var Client
     */
    private $_client;

    /**
     * @return null|Client
     */
    public function getClient()
    {
        if ($this->_client === null) {
            $this->setClient([]);
        }

        return $this->_client;
    }

    /**
     * @param array|string|Client $client
     */
    public function setClient($client)
    {
        if (is_array($client) || !isset($client['class'])) {
            $client['class'] = Client::className();
        }

        if (!$client instanceof Client) {
            $this->_client = Yii::createObject($client);
        } else {
            $this->_client = $client;
        }
    }

    /**
     * @var array
     */
    private $_balanceResult;

    public function getBalance($force = false)
    {
        if ($this->_balanceResult === null || $force) {
            $client = $this->getClient();
            $client->baseUrl = static::BASE_REST_URL;
            $response = $client->get('/Balance/' . $this->apiKey . '/' . $this->secretKey)->setFormat(Client::FORMAT_JSON)->send();
            $result = $response->getData();
            return $this->_balanceResult = $this->ensureResult($result);
        } else {
            return $this->_balanceResult;
        }
    }

    public function sendSMS($phone, $message, $type = 7, $params = [])
    {
        $queryData = array_merge($params, [
            'Phone' => $phone,
            'Content' => $message,
            'APIKey' => $this->apiKey,
            'SecretKey' => $this->secretKey,
            'SmsType' => $type
        ]);
        $client = $this->getClient();
        $client->baseUrl = static::BASE_REST_URL;
        $response = $client->get('/SendMultipleMessage_V4_get', $queryData)->setFormat(Client::FORMAT_JSON)->send();
        return $this->ensureResult($response->getData());

    }

    protected function ensureResult($result)
    {
        if (is_array($result) && isset($result['CodeResponse'])) {
            if ($result['CodeResponse'] == 100) {
                return $result;
            } else {
                $this->addError($result['CodeResponse']);
            }
        } else {
            $this->addError(99);
        }

        return false;
    }

    private $_errors = [];

    public function addError($responseCode)
    {
        $responseCode = (int)$responseCode;
        $this->_errors[] = Yii::t(static::className(), static::$responseCodes[$responseCode]);
    }

    public function getError()
    {
        return end($this->_errors);
    }

    public function getErrors()
    {
        return $this->_errors;
    }

    public function setErrors($errors = [])
    {
        $this->_errors = $errors;
    }


}