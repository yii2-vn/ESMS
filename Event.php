<?php
/**
 * @link http://github.com/yii2vn/esms
 * @copyright Copyright (c) 2017 Yii2VN
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2vn\esms;

use yii\base\Event as BaseEvent;

/**
 * Lớp Event dùng để quản lý các sự kiện diễn ra trên component ESMS.
 * Ví dụ như các sự kiện trước và sau khi gửi tin sms, trước và sau khi voice call. Những sự kiện này sẽ giúp bạn ghi lại
 * lược sử hoặc thêm một vài tương tác tùy theo ý của bạn.
 *
 * @package yii2vn\esms
 * @author Vuong Minh <vuongxuongminh@gmail.com>
 * @since 1.0
 */
class Event extends BaseEvent
{

    /**
     * @var bool
     *
     * Thuộc tính để giúp xác định có thực hiện tiếp tác vụ gửi tin nhắn hoặc voice call hay không.
     * Nó được dùng để kiểm tra trước khi gửi tin, voice call
     */
    public $isValid = true;

    /**
     * @var array|bool
     *
     * Thuộc tính chứa dữ liệu phản hồi từ ESMS hoặc là `false` khi xảy ra lỗi kết nối hoặc lỗi phản hồi từ ESMS.
     */
    public $responseData;

}