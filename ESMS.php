<?php
/**
 * @link http://github.com/yii2vn/esms
 * @copyright Copyright (c) 2017 Yii2VN
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2vn\esms;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\di\Instance;
use yii\httpclient\Client;
use yii\httpclient\Response;
use yii\httpclient\Exception as HttpClientException;

/**
 * Component hổ trợ gọi API từ dịch vụ [ESMS](http://esms.vn) để gửi tin nhắn đến khách hàng và tạo voice call.
 * để tìm hiểu thêm về API của ESMS bạn vui lòng xem tại đây https://esms.vn/TailieuAPI_V4_060215_Rest_Public.pdf
 *
 * @package yii2vn\esms
 * @author Vuong Minh <vuongxuongminh@gmail.com>
 * @since 1.0
 */
class ESMS extends Component
{

    /**
     * @event Event sự kiện trước khi thực hiện gọi API gửi sms đến ESMS. Giúp bạn kiểm tra việc gửi sms có hợp lệ hay không.
     */
    const EVENT_BEFORE_SEND_SMS = 'beforeSendSMS';

    /**
     * @event Event sự kiện sau khi thực hiện gọi API gửi sms đến ESMS. Giúp bạn kiểm soát dữ liệu phản hồi.
     */
    const EVENT_AFTER_SEND_SMS = 'afterSendSMS';

    /**
     * @event Event sự kiện trước khi thực hiện gọi API voice call đến ESMS. Giúp bạn kiểm tra việc voice call có hợp lệ hay không.
     */
    const EVENT_BEFORE_SEND_VOICE_CALL = 'beforeSendVoiceCall';

    /**
     * @event Event sự kiện sau khi thực hiện gọi API voice call đến ESMS. Giúp bạn kiểm soát dữ liệu phản hồi.
     */
    const EVENT_AFTER_SEND_VOICE_CALL = 'afterSendVoiceCall';

    /**
     * Đường dẫn Url API Endpoint của Rest trên hệ thống ESMS.
     */
    const BASE_REST_URL = 'http://rest.esms.vn/MainService.svc/json';

    /**
     * Đường dẫn Url API Endpoint của Voice trên hệ thống ESMS.
     */
    const BASE_VOICE_URL = 'http://voiceapi.esms.vn/MainService.svc/json';

    /**
     * Loại voice call đọc không ngắt khoản (dùng để đọc một đoạn văn bản, một lời nhắc)
     */
    const VOICE_CALL_TYPE_STR = 'typeStr';

    /**
     * Loại voice call đọc ngắt từng ký tự (dùng để tạo OTP)
     */
    const VOICE_CALL_TYPE_NUM = 'typeNum';


    /**
     * @var string
     *
     * Khóa api của bạn trên hệ thống ESMS. Khóa này luôn luôn sử dụng khi tạo các request đến ESMS.
     * Tìm hiểu thêm trong phần quản lý tài khoản của ESMS.
     */
    public $apiKey;

    /**
     * @var string
     *
     * Khóa secret của bạn trên hệ thống ESMS. Khóa này luôn luôn sử dụng khi tạo các request đến ESMS.
     * Tìm hiểu thêm trong phần quản lý tài khoản của ESMS.
     */
    public $secretKey;

    /**
     * @var string
     *
     * Thuộc tính xác định danh mục phiên dịch các câu thông báo trên thuộc tính đối tượng `i18n`. Mặc định sẽ là app.
     */
    public $translationCategory = 'app';

    /**
     * @var string|\yii\i18n\I18N
     *
     * Thuộc tính i18n dùng để phiên dịch đa ngôn ngữ các câu thông báo.
     * Nó có thể là đối tượng hoặc là component id trong `Yii::$app`
     */
    public $i18n = 'i18n';

    /**
     * @var array
     *
     * Mảng chứa thông tin các `code` phản hồi từ hệ thống ESMS. Code 100 nghĩa là thành công còn lại là lỗi.
     */
    public static $responseCodes = [
        100 => 'Request thành công',
        99 => 'Lỗi không xác định, thử lại sau',
        101 => 'Đăng nhập thất bại (api key hoặc secrect key không đúng)',
        102 => 'Tài khoản đã bị khóa',
        103 => 'Số dư tài khoản không đủ dể gửi tin',
        104 => 'Mã Brandname không đúng',
        105 => 'Id tin nhắn không tồn tại',
        118 => 'Loại tin nhắn không hợp lệ',
        119 => 'Brandname quảng cáo phải gửi ít nhất 20 số điện thoại',
        131 => 'Tin nhắn brandname quảng cáo độ dài tối đa 422 kí tự',
        132 => 'Không có quyền gửi tin nhắn đầu số cố định 8755'
    ];

    /**
     * @inheritdoc
     */
    public function init()
    {
        if ($this->apiKey === null || $this->secretKey === null) {
            throw new InvalidConfigException('`apiKey` property and `secretKey` property must be set!');
        }

        $this->i18n = Instance::ensure($this->i18n, 'yii\i18n\I18N');

        parent::init();
    }

    /**
     * Đối tương http-client dùng để tạo request đến dịch vụ ESMS như gọi API gửi tin, voice call và lấy balance.
     *
     * @see getClient
     * @var Client
     */
    private $_client;

    /**
     * Phương thức dùng để lấy đối tượng http-client dùng để tạo request gửi đền ESMS.
     * Nếu giá trị không được thiết lập trước đó, mặc định nó sẽ tự động dùng lớp [[\yii\httpclient\Client]] để tạo các request.
     *
     * @return Client
     */
    public function getClient()
    {
        if ($this->_client === null) {
            $this->setClient([]);
        }

        return $this->_client;
    }

    /**
     * Phương thức dùng để thiết lập đối tượng http-client dùng để tạo request gửi đến ESMS.
     * Tham trị thiết lập có thể là mảng, chuỗi hoặc là đối tượng là thực thể của [[yii\httpclient\\Client]]
     *
     * @param array|string|Client $client tham trị thiết lập thực thể http-client
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
     * Thuộc tính dùng để lưu giá trị số dư tài khoản ESMS. Xem hàm `getBalance` để tìm hiểu thêm.
     *
     * @see getBalance
     * @var array|bool
     */
    private $_balance;

    /**
     * Phương thức dùng để kiểm tra số dư trong tài khoản ESMS.
     *
     * @param bool $force Thuộc tính để xác định có gửi lại API lên ESMS để lấy lại dữ liệu balance hay không. Mặc định là `false`
     * Có nghĩa là khi lấy được dữ liệu lần đầu, lần thứ 2 khi bạn yêu cầu lấy balance nó sẽ sử dụng lại dữ liệu cũ.
     * @return array|bool Trả về giá trị là mảng khi thành công, `false` khi thất bại.
     * Khi là `false` bạn hãy gọi phương thức `getError` để kiểm tra lỗi.
     */
    public function getBalance($force = false)
    {
        if ($this->_balance === null || $force) {
            $client = $this->getClient();
            $client->baseUrl = static::BASE_REST_URL;
            $response = $client->get('GetBalance/' . $this->apiKey . '/' . $this->secretKey)->send();
            return $this->_balance = $this->ensureResponseData($response);
        } else {
            return $this->_balance;
        }
    }

    /**
     * Phương thức gọi API gửi sms
     *
     * @param string $phone Số điện thoại muốn gửi tin nhắn đến
     * @param string $message Tin nhắn gửi đến số điện thoại
     * @param int $type Loại tin nhắn muốn gửi (1, 2, 3, 4, 6, 7, 8, 13 tìm hiểu trong tài liệu từ ESMS).
     * Mặc định sẽ là 7 (gửi bằng đầu số ngẫu nhiên)
     * @param array $params Các thông số phụ thuộc vào loại tin nhắn như `Sandbox`, `Brandname`...
     * Ví dụ:
     *
     * ```php
     * [
     *      'Brandname' => 'My Otp',
     *      'Sandbox' => 0, // thử nghiệm (mặc định là 1 chính thức)
     *      'RequestId' => 123123, // Mã tin nhắn lưu trên db của hệ thống bạn (không bắt buộc),
     *      'SendDate' => '2017-12-12 00:00:00' // thời gian sẽ gửi tin nhắn (không bắt buộc),
     *      'IsUnicode' => 0 // tin nhắn unicode (có dấu) chỉ hổ trợ khi loại tin (type) là 3. Mặc định thuộc tính này có giá trị là 1 (không unicode).
     * ]
     * ```
     * @param bool $clearErrors Tham trị xác định có xóa tất cả lỗi cũ trước khi gửi hay không
     * @return array|bool Trả về giá trị là mảng khi thành công, `false` khi thất bại.
     * Khi là `false` bạn hãy gọi phương thức `getError` để kiểm tra lỗi.
     */
    public function sendSMS($phone, $message, $type = 7, $params = [], $clearErrors = false)
    {
        if ($clearErrors) {
            $this->setErrors([]);
        }
        if ($this->beforeSend()) {
            $queryData = array_merge($params, [
                'Phone' => $phone,
                'Content' => $message,
                'APIKey' => $this->apiKey,
                'SecretKey' => $this->secretKey,
                'SmsType' => $type
            ]);
            $client = $this->getClient();
            $client->baseUrl = static::BASE_REST_URL;
            $response = $client->get('SendMultipleMessage_V4_get', $queryData)->send();
            $responseData = $this->ensureResponseData($response);
            return $this->afterSend($responseData);
        } else {
            return false;
        }
    }

    /**
     * Phương thức gọi api gửi sms nhiều số điện thoại, nội dung cùng một lúc.
     *
     * @param string[] $phones Tập hợp số điện thoại gửi tin nhắn đến có thể kèm theo tin nhắn riêng biệt.
     *
     * + Ví dụ cấu hình khi gửi tin riêng biệt:
     * $esms->batchSendSMS([
     *      '0909113911' => 'Hop khan cap luc 8h',
     *      '0909911113' => 'Cuoc hop ngay mai luc 8h',
     *      ....
     * ]);
     *
     * + Ví dụ cấu hình khi gửi tin nhắn đến tất cả số điện thoại có cùng nội dung:
     * $esms->batchSendSMS(['0909113911', '0909911113'], 'Cuoc hop ngay mai vao luc 8h');
     *
     * + Ví dụ sử dụng kết hợp cả 2 (gửi tin riêng biệt đến một vài số còn lại sử dụng nội dung chung):
     * $esms->batchSendSMS([
     *      '0909113911' => 'Hop khan cap luc 8h',
     *      '0909911113' => 'Cuoc hop ngay mai luc 8h',
     *      '01213141516', '0909116115', '0909117118', ...
     * ], 'Cuoc hop ngay mai vao luc 8h! Nho di dung gio');
     * Như bạn thấy các thành phần không có khóa là số điện thoại thì mặc định sẽ sử dụng tin nhắn chung!
     * @param null|string $message Nội dung gửi tin nhắn được sử dụng chung cho các số điện thoại không có tin riêng biệt.
     * @param int $type Loại tin nhắn muốn gửi (1, 2, 3, 4, 6, 7, 8, 13 tìm hiểu trong tài liệu từ ESMS).
     * @param array $params Các thông số phụ thuộc vào loại tin nhắn như `Sandbox`, `Brandname`...
     * Ví dụ:
     *
     * ```php
     * [
     *      'Brandname' => 'My Otp',
     *      'Sandbox' => 0, // thử nghiệm (mặc định là 1 chính thức)
     *      'RequestId' => 123123, // Mã tin nhắn lưu trên db của hệ thống bạn (không bắt buộc),
     *      'SendDate' => '2017-12-12 00:00:00' // thời gian sẽ gửi tin nhắn (không bắt buộc),
     *      'IsUnicode' => 0 // tin nhắn unicode (có dấu) chỉ hổ trợ khi loại tin (type) là 3. Mặc định thuộc tính này có giá trị là 1 (không unicode).
     * ]
     * ```
     * @param bool $clearErrors Tham trị xác định có xóa tất cả lỗi cũ trước khi gửi hay không
     * @param bool $skipError Tham trị dùng để xác định bỏ qua lỗi hoặc dừng quá trình lại khi gửi tin.
     * Mặc định là `false` nghĩa là nếu có lỗi thì KHÔNG bỏ qua, và dừng quá trịnh lại.
     * @return bool[]|array[] Mảng kết quả trả. Các thành phần trên mảng có khóa là số điện thoại gửi tin và giá trị là kết quả của quá trình gửi tin.
     * @throws InvalidConfigException
     */
    public function batchSendSMS($phones, $message = null, $type = 7, $params = [], $clearErrors = false, $skipError = false)
    {
        if (!empty($phones)) {
            $responseData = [];
            foreach ($phones as $phone => $customMessage) {
                if (is_int($phone)) {// not is associative
                    $phone = $customMessage;
                    $customMessage = $message;
                }
                $responseData[$phone] = $this->sendSMS($phone, $customMessage, $type, $params, $clearErrors);
                if ($responseData[$phone] === false && !$skipError) {
                    return $responseData;
                }
            }
            return $responseData;
        } else {
            throw new InvalidConfigException('Param `phones` must be an array!');
        }
    }

    /**
     * Phương thức kiểm tra trạng thái của tin nhắn thông qua ID của nó.
     * Kết quả trả về sẽ giúp bạn biết trạng thái của tin nhắn.
     * Ví dụ:
     * ```php
     * [
     *      "CodeResponse" => "100",
     *      "SMSID" => "XXXX",
     *      "SendFailed" => 0,
     *      "SendStatus" => 5,
     *      "SendSuccess" => 1,
     *      "TotalReceiver" => 1,
     *      "TotalSent" => 1
     * ]
     *
     * ```
     *
     * @param string $smsId của tin nhắn. Tham trị này nằm trong kết quả gửi tin nhắn của phương thức `sendSMS` hay `batchSendSMS`.
     * @return array|bool Trả về giá trị là mảng khi thành công, `false` khi thất bại.
     * Khi là `false` bạn hãy gọi phương thức `getError` để kiểm tra lỗi.
     */
    public function getSendStatus($smsId)
    {
        $queryData = [
            'APIKey' => $this->apiKey,
            'SecretKey' => $this->secretKey,
            'RefId' => $smsId
        ];
        $client = $this->getClient();
        $client->baseUrl = static::BASE_REST_URL;
        $response = $client->get('GetSendStatus', $queryData)->send();
        return $this->ensureResponseData($response);
    }

    /**
     * Phương thức lấy danh sách người nhận tin thông qua ID của tin nhắn.
     *
     * Ví dụ kết quả trả về:
     * [
     *      "CodeResult" => "100",
     *      "ReceiverList" => [
     *          ["IsSent" => true, "Phone" => "XXXX", "SentResult" => true],
     *          ["IsSent" => true, "Phone" =>"BBBB", "SentResult" => true],
     *      ]
     * ]
     * @param string $smsId của tin nhắn muốn lấy thông tin
     * @return array|bool Trả về giá trị là mảng khi thành công, `false` khi thất bại.
     * Khi là `false` bạn hãy gọi phương thức `getError` để kiểm tra lỗi.
     */
    public function getReceiverStatus($smsId)
    {
        $queryData = [
            'APIKey' => $this->apiKey,
            'SecretKey' => $this->secretKey,
            'RefId' => $smsId
        ];
        $client = $this->getClient();
        $client->baseUrl = static::BASE_REST_URL;
        $response = $client->get('GetSmsReceiverStatus_get', $queryData)->send();
        return $this->ensureResponseData($response);
    }

    /**
     * Phương thức dùng để gọi api tạo một cuộc gọi thoại đến số điện thoại khách hàng truyền vào
     *
     * @param string $phone Số điện thoại khách hàng sẽ nhận cuộc gọi thoại
     * @param string $apiCode Mã api voice call dùng để xác định mẫu âm thanh bạn đăng ký (ESMS cấp)
     * @param string $passCode Mã mật khẩu của api voice dùng để xác định mẫu âm thanh bạn đăng ký (ESMS cấp)
     * @param string $str Đoạn văn bản hoặc ký tự dùng để tạo voice call
     * @param string $type Loại voice call đọc từng ký tự một hay đọc theo kiểu văn bản (ký tự dùng cho OTP, văn bản dành cho lời nhắc)
     * @param bool $clearErrors Tham trị xác định có xóa tất cả lỗi cũ trước khi gửi hay không
     * @return array|bool Trả về giá trị là mảng khi thành công, `false` khi thất bại.
     * Khi là `false` bạn hãy gọi phương thức `getError` để kiểm tra lỗi.
     * @throws NotSupportedException
     */
    public function sendVoiceCall($phone, $apiCode, $passCode, $str, $type = self::VOICE_CALL_TYPE_STR, $clearErrors = false)
    {

        if ($clearErrors) {
            $this->setErrors([]);
        }
        if ($this->beforeSend(false)) {
            $queryData = [
                'ApiKey' => $this->apiKey,
                'SecretKey' => $this->secretKey,
                'Phone' => $phone,
                'ApiCode' => $apiCode,
                'PassCode' => $passCode
            ];

            $queryData['VarStr'] = $queryData['VarNum'] = $str;

            $client = $this->getClient();
            $client->baseUrl = static::BASE_VOICE_URL;
            $response = $client->get('MakeCall', $queryData)->send();
            $responseData = $this->ensureResponseData($response);
            return $this->afterSend($responseData, false);
        } else {
            return false;
        }
    }

    /**
     * Phương thức gọi api gửi gọi thoại đến nhiều số điện thoại, nội dung cùng một lúc.
     *
     * @param string[] $phones Tập hợp số điện thoại để gửi gọi thoại đến có thể kèm theo đoạn hội thoại riêng biệt
     *
     * + Ví dụ cấu hình khi tạo đoạn gọi thoại riêng biệt:
     * $esms->batchSendVoiceCall([
     *      '0909113911' => 'Hop khan cap luc 8h',
     *      '0909911113' => 'Cuoc hop ngay mai luc 8h',
     *      ....
     * ]);
     *
     * + Ví dụ cấu hình khi gửi một đoạn gọi thoại đến tất cả số điện thoại có cùng nội dung:
     * $esms->batchSendVoiceCall(['0909113911', '0909911113'], 'Cuoc hop ngay mai vao luc 8h');
     *
     * + Ví dụ sử dụng kết hợp cả 2 (gửi gọi thoại riêng biệt đến một vài số còn lại sử dụng nội dung chung):
     * $esms->batchSendVoiceCall([
     *      '0909113911' => 'Hop khan cap luc 8h',
     *      '0909911113' => 'Cuoc hop ngay mai luc 8h',
     *      '01213141516', '0909116115', '0909117118', ...
     * ], 'Cuoc hop ngay mai vao luc 8h! Nho di dung gio');
     * Như bạn thấy các thành phần không có khóa là số điện thoại thì mặc định sẽ sử dụng nội dung gửi chung!
     * @param string $apiCode Mã api voice call dùng để xác định mẫu âm thanh bạn đăng ký (ESMS cấp)
     * @param string $passCode Mã mật khẩu của api voice dùng để xác định mẫu âm thanh bạn đăng ký (ESMS cấp)
     * @param null|string $str Đoạn văn bản hoặc ký tự dùng để tạo voice call
     * @param string $type Loại voice call đọc từng ký tự một hay đọc theo kiểu văn bản (ký tự dùng cho OTP, văn bản dành cho lời nhắc)
     * @param bool $clearErrors Tham trị xác định có xóa tất cả lỗi cũ trước khi gửi hay không
     * @param bool $skipError Tham trị dùng để xác định bỏ qua lỗi hoặc dừng quá trình lại khi gửi gọi thoại hay không.
     * Mặc định là `false` nghĩa là nếu có lỗi thì KHÔNG bỏ qua, và dừng quá trịnh lại.
     * @return bool[]|array[] Mảng kết quả trả. Các thành phần trên mảng có khóa là số điện thoại đã gọi thoại và giá trị là kết quả của quá trình gọi thoại.
     * @throws InvalidConfigException
     */
    public function batchSendVoiceCall($phones, $apiCode, $passCode, $str = null, $type = self::VOICE_CALL_TYPE_STR, $clearErrors = false, $skipError = false)
    {
        if (!empty($phones)) {
            $responseData = [];
            foreach ($phones as $phone => $customStr) {
                if (is_int($phone)) {// not is associative
                    $phone = $customStr;
                    $customStr = $str;
                }
                $responseData[$phone] = $this->sendVoiceCall($phone, $apiCode, $passCode, $customStr, $type, $clearErrors);
                if ($responseData[$phone] === false && !$skipError) {
                    return $responseData;
                }
            }
            return $responseData;
        } else {
            throw new InvalidConfigException('Param `phones` must be an array!');
        }
    }

    /**
     * Phương thức dùng để kiểm duyệt kết quả từ quá trình gọi API có xảy ra lỗi hay không.
     * Nếu có thì thêm lỗi vào mảng và trả về `false`.
     *
     * @param Response $response
     * @return bool|array Trả về `false` nếu xảy ra lỗi dựa trên thành phần `CodeResponse` và nếu là mảng thì việc request diễn ra thành công.
     */
    protected function ensureResponseData($response)
    {
        try {
            $data = $response->getData();
            if (is_array($data)) {
                if (isset($data['CodeResponse'])) {
                    $code = $data['CodeResponse'];
                } elseif (isset($data['CodeResult'])) {
                    $code = $data['CodeResult'];
                } else {
                    $code = 99;
                }

                if ($code == 100) {
                    return $data;
                } else {
                    $this->addError($code);
                }
            } else {
                $this->addError(99);
            }
        } catch (HttpClientException $exception) {
            $this->addError(99);
        }
        return false;
    }

    /**
     * Phương thức này được gọi trước khi gửi SMS, nhằm thực hiện các tác vụ chèn thêm của bạn trước khi gửi tin, ví dụ như ghi lại lịch sử.
     * Lưu ý: khi bạn overwrite lại phương thức này hãy chắc rằng bạn vẫn đảm bảo `trigger` sự kiện `EVENT_BEFORE_SEND_SMS`
     *
     * @param bool $sms Tham trị xác định có phải sự kiện gửi tin hay không. Nếu không thì là sự kiện voice call
     * @return bool trả về kiểu `bool` được trích ra từ sự kiện.
     * Nếu như bạn muốn ngừng việc gửi tin ví một lý do nào đó thì hãy thiết lập thuộc tính `isValid` trong sự kiện về `false`.
     */
    public function beforeSend($sms = true)
    {
        $event = new Event;
        $this->trigger($sms ? static::EVENT_BEFORE_SEND_SMS : static::EVENT_BEFORE_SEND_VOICE_CALL, $event);
        return $event->isValid;
    }

    /**
     * Phương thức này được gọi sau khi gửi SMS, nhằm thực hiện việc kiểm soát dữ liệu phản hồi từ ESMS.
     * Từ đó bạn có thể thêm một vài thành phần vào mảng dữ liệu phản hồi hoặc thực hiện vài tác vụ liên quan đến sau khi gửi tin.
     *
     * @param array|bool $responseData dữ liệu phản hồi từ ESMS. Nếu là `false` nghĩa là có lỗi xảy ra trong quá trình gửi.
     * @param bool $sms Tham trị xác định có phải sự kiện gửi tin hay không. Nếu không thì là sự kiện voice call
     * @return array|bool dữ liệu phản hồi sau khi được đưa vào các sự kiện diễn ra (dữ liệu trả về cuối cùng)
     */
    public function afterSend($responseData, $sms = true)
    {
        $event = new Event(['responseData' => $responseData]);
        $this->trigger($sms ? static::EVENT_AFTER_SEND_SMS : static::EVENT_AFTER_SEND_VOICE_CALL, $event);
        return $event->responseData;
    }

    /**
     * @var array
     *
     * Mảng chứa các lỗi xảy ra trong quá trình gọi API
     */
    private $_errors = [];

    /**
     * Phương thức để thêm lỗi xảy ra trong quá trình gọi API. Phương thức này bạn có thể sử dụng trong các sự kiện.
     *
     * @param $responseCode
     */
    public function addError($responseCode)
    {
        $responseCode = (int)$responseCode;
        $this->_errors[] = $this->i18n->translate($this->translationCategory, static::$responseCodes[$responseCode], [], 'vi');
    }

    /**
     * Phương thức dùng để lấy lỗi xảy ra CUỐI CÙNG trong quá trình gọi API.
     * Nó giúp cho bạn xác định được lỗi phản hồi từ ESMS.
     *
     * @return string
     */
    public function getError()
    {
        return end($this->_errors);
    }

    /**
     * Phương thức dùng để lấy toàn bộ lỗi xảy ra trong quá trình gọi API.
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->_errors;
    }

    /**
     * Phương thức dùng để thiết lập lỗi (thường dùng cho việc test).
     * Hoặc cũng có thể dùng để xóa toàn bộ lỗi khi tham trị truyền vào là mảng rỗng.
     *
     * @param string[] $errors Mảng tập hợp các lỗi
     */
    public function setErrors($errors = [])
    {
        $this->_errors = $errors;
    }


}