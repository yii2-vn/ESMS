ESMS API Client 
============
Đây là component hổ trợ việc tương tác với API từ dịch vụ [ESMS](http://esms.vn). Component này hổ trợ bạn có thể tương tác với các api sau:

+ Gửi tin nhắn.
+ Gửi gọi thoại (voice call).
+ Kiểm tra số dư của bạn trên hệ thống.
+ Kiểm tra trạng thái tin nhắn thông qua **id** tin nhắn.
+ Kiểm tra danh sách người nhận thông qua **id** tin nhắn.

Và để tìm hiểu nhiều hơn về các thông tin của API mời bạn xem thêm tài liệu của ESMS tại [đây](https://esms.vn/TailieuAPI_V4_060215_Rest_Public.pdf)

Cài đặt
-----------
Bạn phải cài đặt ứng dụng này thông qua [composer](http://getcomposer.org/download)

Sau khi cài đặt composer, thực hiện câu lệnh sau:

```
php composer.phar require --prefer-dist yii2-vn/esms
```

>Lưu ý đối với những ai sử dụng `window` thì thực hiện câu lệnh sau:
>```
>composer require --prefer-dist yii2-vn/esms
>```

Hoặc thêm:

```json
"yii2-vn/esms": "*"
```

vào composer.json

Cấu hình app
-------------
Bạn có thể khai báo `component` này vào bên trong cấu hình của hệ thống (app config) để sử dụng nhiều lần mà không cần khai báo lại `apiKey` và `secretKey` của `ESMS`

```php
return [
    'components' => [
        'eSMS' => [
            'class' => 'yii2vn\esms\ESMS',
            'apiKey' => 'API KEY lấy từ ESMS',
            'secretKey' => 'SECRET KEY lấy từ ESMS'
        ]
    ]
]
```

Ngay sau khi cấu hình xong bạn đã có thể tương tác với API thông qua `Yii::$app->eSMS`

Cách gửi SMS
-----------

Sau đây là phần giới thiệu các phương thức bên trong:

**1. Gửi tin nhắn đến 1 số điện thoại:**  

Với phương thức `sendSMS` bạn có thể gửi tin nhắn đến một số điện thoại bất kỳ:

```php
$phone = '0909113911';
$esms = Yii::$app->eSMS;

if ($esms->sendSMS($phone, 'Xin chao')) {
    Yii::$app->session->setFlash('Success!');
} else {
    Yii::warning('Không thể gửi tin đến sđt: ' . $phone . ' Lỗi: ' . $esms->error);
}
```

**2. Gửi tin nhắn đến nhiều số điện thoại cùng nội dung:**

Bên cạnh phương thức `sendSMS` component còn hổ trợ phương thức `batchSendSMS` dùng để tương tác gửi tin trên nhiều số điện thoại:

```php
$phones = ['0909113911', '0909911113', '0909123456'];
$esms = Yii::$app->eSMS;

if ($esms->batchSendSMS($phones, 'Xin chao')) {
    Yii::$app->session->setFlash('Success!');
} else {
    Yii::warning('Không thể gửi tin đến sđt: ' . $phone . ' Lỗi: ' . $esms->error);
}

```

**3. Gửi tin nhắn đến nhiều số điện thoại khác nội dung:**

Một cách khác để sử dụng phương thức `batchSendSMS` đó là mảng `phones` truyền vào có khóa chính là số điện thoại còn giá trị chính là tin nhắn muốn gửi:

```php
$phones = [
      '0909113911' => 'Ngay mai hop luc 8h',
      '0909911113' => 'Thu 2 lam ca sang',
      '0909123456' => '01/05 duoc nghi lam'
];
$esms = Yii::$app->eSMS;

if ($esms->batchSendSMS($phones)) {
    Yii::$app->session->setFlash('Success!');
} else {
    Yii::warning('Không thể gửi tin đến sđt: ' . $phone . ' Lỗi: ' . $esms->error);
}

```

**4. Gửi tin nhắn đến nhiều số điện thoại khác nội dung khi chỉ định:**

Và một cách khác nữa để sử dụng phương thức `batchSendSMS` đó là mảng `phones` có thể có khóa chính là số điện thoại còn giá trị chính là tin nhắn muốn gửi. Còn những thành phần có giá trị là số điện thoại thì sẽ được gửi chung 1 tin nhắn:

```php
$phones = [
      '0909113911' => 'Ngay mai nhan vien se hop luc 8h! Kinh moi sep tham gia cuoc hop', // Số của sếp
      '0909911113', '0909123321', '0909963147'
];
$esms = Yii::$app->eSMS;

if ($esms->batchSendSMS($phones, 'Ngay mai moi nguoi du hop vao luc 8h! Co sep den du.')) {
    Yii::$app->session->setFlash('Success!');
} else {
    Yii::warning('Không thể gửi tin đến sđt: ' . $phone . ' Lỗi: ' . $esms->error);
}

```

Như bạn thấy lúc này tham trị thứ 2 (param 2) sẽ mang giá trị là tin nhắn CHUNG gửi đến các thành phần trên mảng không có khóa là số điện thoại mà giá trị của nó chính là số điện thoại.

Cách gửi voice call
---------------

Sau đây là phần giới thiệu các phương thức bên trong:

**1. Gửi cuộc gọi thoại đến 1 số điện thoại:**  

Với phương thức `sendVoiceCall` bạn có thể gửi một cuộc gọi thoại đến một số điện thoại bất kỳ:

```php
$phone = '0909113911';
$apiCode = '58888'; // lấy trên hệ thống ESMS để xác định mẫu cuộc gọi thoại
$passCode = '12345'; // lấy trên hệ thống ESMS để xác định mẫu cuộc gọi thoại
$esms = Yii::$app->eSMS;

if ($esms->sendVoiceCall($phone, $apiCode, $passCode, 'Xin chao')) {
    Yii::$app->session->setFlash('Success!');
} else {
    Yii::warning('Không thể gửi tin đến sđt: ' . $phone . ' Lỗi: ' . $esms->error);
}
```

**2. Gửi cuộc gọi thoại đến nhiều số điện thoại cùng nội dung:**

Bên cạnh phương thức `sendVoiceCall` component còn hổ trợ phương thức `batchSendVoiceCall` dùng để tương tác gửi tin trên nhiều số điện thoại:

```php
$phones = ['0909113911', '0909911113', '0909123456'];
$apiCode = '58888'; // lấy trên hệ thống ESMS để xác định mẫu cuộc gọi thoại
$passCode = '12345'; // lấy trên hệ thống ESMS để xác định mẫu cuộc gọi thoại
$esms = Yii::$app->eSMS;

if ($esms->batchSendVoiceCall($phones, $apiCode, $passCode, 'Xin chao')) {
    Yii::$app->session->setFlash('Success!');
} else {
    Yii::warning('Không thể gửi tin đến sđt: ' . $phone . ' Lỗi: ' . $esms->error);
}

```

**3. Gửi cuộc gọi thoại đến nhiều số điện thoại khác nội dung:**

Một cách khác để sử dụng phương thức `batchSendVoiceCall` đó là mảng `phones` truyền vào có khóa chính là số điện thoại còn giá trị chính là nội dung cuộc gọi muốn gửi:

```php
$phones = [
      '0909113911' => 'Ngay mai hop luc 8h',
      '0909911113' => 'Thu 2 lam ca sang',
      '0909123456' => '01/05 duoc nghi lam'
];
$apiCode = '58888'; // lấy trên hệ thống ESMS để xác định mẫu cuộc gọi thoại
$passCode = '12345'; // lấy trên hệ thống ESMS để xác định mẫu cuộc gọi thoại
$esms = Yii::$app->eSMS;

if ($esms->batchSendVoiceCall($phones, $apiCode, $passCode)) {
    Yii::$app->session->setFlash('Success!');
} else {
    Yii::warning('Không thể gửi tin đến sđt: ' . $phone . ' Lỗi: ' . $esms->error);
}

```

**4. Gửi cuộc gọi thoại đến nhiều số điện thoại khác nội dung khi chỉ định:**

Và một cách khác nữa để sử dụng phương thức `batchSendVoiceCall` đó là mảng `phones` có thể có khóa chính là số điện thoại còn giá trị chính là nội dung cuộc gọi muốn gửi. Còn những thành phần có giá trị là số điện thoại thì sẽ được gửi cùng một nội dung gọi thoại:

```php
$phones = [
      '0909113911' => 'Ngay mai nhan vien se hop luc 8h! Kinh moi sep tham gia cuoc hop', // Số của sếp
      '0909911113', '0909123321', '0909963147'
];
$apiCode = '58888'; // lấy trên hệ thống ESMS để xác định mẫu cuộc gọi thoại
$passCode = '12345'; // lấy trên hệ thống ESMS để xác định mẫu cuộc gọi thoại
$esms = Yii::$app->eSMS;

if ($esms->batchSendVoiceCall($phones, $apiCode, $passCode, 'Ngay mai moi nguoi du hop vao luc 8h! Co sep den du.')) {
    Yii::$app->session->setFlash('Success!');
} else {
    Yii::warning('Không thể gửi tin đến sđt: ' . $phone . ' Lỗi: ' . $esms->error);
}

```

Như bạn thấy lúc này tham trị thứ 2 (param 2) sẽ mang giá trị là nội dung cuộc gọi CHUNG gửi đến các thành phần trên mảng không có khóa là số điện thoại mà giá trị của nó chính là số điện thoại.

Cách kiểm tra trang thái
-------------------------
Tất cả các kết quả trả về từ các phương thức gửi tin `sms` và gửi cuộc gọi thoại `voice call` từ API của `ESMS` đều gửi kèm cho bạn 1 thành phần đó là `SmsId` bạn hãy lưu nó lại và sử dụng nó để kiểm tra trang thái khi cần thiết. Sau đây là cách kiểm tra trang thái từ `SmsId`

**1. Kiểm tra trang thái tin nhắn (sms) hoặc cuộc gọi thoại (voice call)**

```php
$esms = Yii::$app->eSMS;
$smsId = '123-421-412412-123';
$statusData = $esms->getSendStatus($smsId);
var_dump($statusData);
// Kết quả:
// array(7) { ["CodeResponse"]=> string(3) "100" ["SMSID"]=> string(39) "123-421-412412-123" ["SendFailed"]=> int(0) ["SendStatus"]=> int(5) ["SendSuccess"]=> int(1) ["TotalReceiver"]=> int(1) ["TotalSent"]=> int(1) } 
```

**2. Kiểm tra danh sách người nhận và trạng thái tin nhắn (sms) hoặc cuộc gọi thoại (voice call)**

```php
$esms = Yii::$app->eSMS;
$smsId = '123-421-412412-123';
$statusData = $esms->getReceiverStatus($smsId);
var_dump($statusData);
// Kết quả:
// array(2) { ["CodeResult"]=> string(3) "100" ["ReceiverList"]=> array(1) { [0]=> array(3) { ["IsSent"]=> bool(true) ["Phone"]=> string(10) "0909113911" ["SentResult"]=> bool(true) } } } 
```

Cách kiểm tra số dư
------------------------
Để kiểm tra số dư bạn hãy sử dụng phương thức `getBalance`. Phương thức này có tham trị (param) `$force` mặc định sau khi lấy được dữ liệu `balance` component sẽ `cache` lại để sử dụng cho lần gọi kế tiếp, nếu như bạn muốn gửi api lấy lại dữ liệu thì hãy thiết lập tham trị này là `TRUE`

```php
$esms = Yii::$app->eSMS;
var_dump($esms->getBalance());
// Kết quả:
// array(3) { ["Balance"]=> int(2293566) ["CodeResponse"]=> string(3) "100" ["UserID"]=> int(999999999999) } 
```

Ví dụ về `$force`:

```php
$esms = Yii::$app->eSMS;
var_dump($esms->getBalance());
// Kết quả:
// array(3) { ["Balance"]=> int(2293566) ["CodeResponse"]=> string(3) "100" ["UserID"]=> int(999999999999) } 
$esms->sendSMS("0909113911", "hello");
var_dump($esms->getBalance());
// Kết quả số dư vẫn như cữ:
// array(3) { ["Balance"]=> int(2293566) ["CodeResponse"]=> string(3) "100" ["UserID"]=> int(999999999999) } 
var_dump($esms->getBalance(true)); // kết quả sẽ thay đổi vì chúng ta lấy lại dữ liệu sau khi gửi tin => số tiền sẽ giảm

```
Kết quả phản hồi API
--------------
Đổi với phương thức `sendSMS`, `sendVoiceCall`, `getBalance` sẽ trả về mảng kết quả theo như tài liệu về API của `ESMS` nếu như `ResponseCode` hoặc `ResultCode` là `100` còn lại sẽ là `FALSE`. Để truy xuất lỗi thì bạn hãy tương tác với phương thức `getError` hoặc thuộc tính `error` để lấy ra lỗi cuối cùng trong quá trình gửi tin.

Đổi với phương thức `batchSendSMS`, `batchSendVoiceCall` sẽ trả về tập hợp mảng kết quả có khóa là số điện thoại gửi tin hoặc gọi thoại và giá trị chính là mảng kết quả trả về hoặc là `FALSE` nếu như `ResponseCode` hoặc `ResultCode` trên mảng kết quả khác `100`.

Các sự kiện
--------------

Các sự kiện được tạo ra nhằm hổ trợ bạn thực hiện một số tác vụ đi kèm như trước và sau khi gửi tin nhắn hoặc trước và sau khi gửi cuộc gọi thoại.

Sau đây là danh sách các sự kiện bên trong lớp `ESMS`:

+ `EVENT_BEFORE_SEND_SMS` sự kiện trước khi gửi tin nhắn
+ `EVENT_AFTER_SEND_SMS` sự kiện sau khi gửi tin nhắn
+ `EVENT_BEFORE_SEND_VOICE_CALL` sự kiện trước khi gửi cuộc gọi thoại
+ `EVENT_AFTER_SEND_VOICE_CALL` sự kiện sau khi gửi cuộc gọi thoại