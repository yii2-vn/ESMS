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
use yii\httpclient\Client;
use yii\httpclient\Response;


/**
 * Component hổ trợ gọi API từ dịch vụ [ESMS](http://esms.vn) để gửi tin nhắn đến khách hàng và tạo voice call.
 * để tìm hiểu thêm về API của ESMS bạn vui lòng xem tại đây https://esms.vn/TailieuAPI_V4_060215_Rest_Public.pdf
 *
 * @package yii2vn\esms
 * @author Vuong Minh <vuongxuongminh@gmail.com>
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
     * @var array
     *
     * Mảng thiết lập cấu hình i18n để phiển dịch đa ngôn ngữ của các câu thông báo.
     */
    public $translationConfig = [];

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

        if (is_string($this->i18n)) {
            $this->i18n = Yii::$app->get($this->i18n);
        }

        $i18n = $this->i18n;
        if (empty($this->translationConfig)) {
            $i18n->translations[static::className()] = isset($i18n->translations['app']) ? $i18n->translations['app'] : $i18n->translations['app*'];
        } else {
            $i18n->translations[static::className()] = $this->translationConfig;
        }
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
        if ($this->beforeSendSMS()) {
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
            return $this->afterSendSMS($responseData);
        } else {
            return false;
        }
    }

    /**
     * Phương thức gọi api gửi sms nhiều số điện thoại, nội dung cùng một lúc.
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
     * @param null $message Nội dung gửi tin nhắn được sử dụng chung cho các số điện thoại không có tin riêng biệt.
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
     * Phương thức này được gọi trước khi gửi SMS, nhằm thực hiện các tác vụ chèn thêm của bạn trước khi gửi tin, ví dụ như ghi lại lịch sử.
     * Lưu ý: khi bạn overwrite lại phương thức này hãy chắc rằng bạn vẫn đảm bảo `trigger` sự kiện `EVENT_BEFORE_SEND_SMS`
     *
     * @return bool trả về kiểu `bool` được trích ra từ sự kiện.
     * Nếu như bạn muốn ngừng việc gửi tin ví một lý do nào đó thì hãy thiết lập thuộc tính `isValid` trong sự kiện về `false`.
     */
    public function beforeSendSMS()
    {
        $event = new Event;
        $this->trigger(static::EVENT_BEFORE_SEND_SMS, $event);
        return $event->isValid;
    }

    /**
     * Phương thức này được gọi sau khi gửi SMS, nhằm thực hiện việc kiểm soát dữ liệu phản hồi từ ESMS.
     * Từ đó bạn có thể thêm một vài thành phần vào mảng dữ liệu phản hồi hoặc thực hiện vài tác vụ liên quan đến sau khi gửi tin.
     *
     * @param array|bool $responseData dữ liệu phản hồi từ ESMS. Nếu là `false` nghĩa là có lỗi xảy ra trong quá trình gửi.
     * @return array|bool dữ liệu phản hồi sau khi được đưa vào các sự kiện diễn ra (dữ liệu trả về cuối cùng)
     */
    public function afterSendSMS($responseData)
    {
        $event = new Event(['responseData' => $responseData]);
        $this->trigger(static::EVENT_AFTER_SEND_SMS, $event);
        return $event->responseData;
    }

    /**
     * Phương thức kiểm tra trạng thái của tin nhắn thông qua ID của nó.
     * Kết quả trả về sẽ giúp bạn biết trạng thái của tin nhắn.
     *
     * @param string $smsId của tin nhắn. Tham trị này nằm trong kết quả gửi tin nhắn của phương thức `sendSMS` hay `batchSendSMS`.
     * @return array|bool Trả về giá trị là mảng khi thành công, `false` khi thất bại.
     * Khi là `false` bạn hãy gọi phương thức `getError` để kiểm tra lỗi.
     */
    public function getSendSMSStatus($smsId)
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

    public function getSMSReceiverStatus($refId)
    {
        $queryData = [
            'APIKey' => $this->apiKey,
            'SecretKey' => $this->secretKey,
            'RefId' => $refId
        ];
        $client = $this->getClient();
        $client->baseUrl = static::BASE_REST_URL;
        $response = $client->get('GetSmsReceiverStatus_get', $queryData)->send();
        return $this->ensureResponseData($response);
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

        return false;
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
        $this->_errors[] = $this->i18n->translate(static::className(), static::$responseCodes[$responseCode], [], 'vi');
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