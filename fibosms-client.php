<?php
/**

iMeeting web conferencing system - http://www.imeeting.vn/

Copyright (c) 2014 Truong Giang Holdings Inc. and by respective authors (see below).

This program is free software; you can redistribute it and/or modify it under the
terms of the GNU Lesser General Public License as published by the Free Software
Foundation; either version 3.0 of the License, or (at your option) any later
version.

This class is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public License along
with BigBlueButton; if not, see <http://www.gnu.org/licenses/>.

Versions:
   1.0  --  Initial version written by Nhan Nguyen
                   (email: nxtnhan [a t]  gmail DOT com)

*/
/** get the config values */
require_once "config.php";
/**
 * convert xml string to php array - useful to get a serializable value
 *
 * @param string $xmlstr
 * @return array
 * @author Adrien aka Gaarf
 */

function xmlstr_to_array($xmlstr) {
    $doc = new DOMDocument();
    $doc->loadXML($xmlstr);
    return domnode_to_array($doc->documentElement);
}
function domnode_to_array($node) {
    $output = array();
    switch ($node->nodeType) {
        case XML_CDATA_SECTION_NODE:
        case XML_TEXT_NODE:
            $output = trim($node->textContent);
            break;
        case XML_ELEMENT_NODE:
            for ($i=0, $m=$node->childNodes->length; $i<$m; $i++) {
                $child = $node->childNodes->item($i);
                $v = domnode_to_array($child);
                if(isset($child->tagName)) {
                    $t = $child->tagName;
                    if(!isset($output[$t])) {
                        $output[$t] = array();
                    }
                    $output[$t][] = $v;
                }
                elseif($v) {
                    $output = (string) $v;
                }
            }
            if(is_array($output)) {
                if($node->attributes->length) {
                    $a = array();
                    foreach($node->attributes as $attrName => $attrNode) {
                        $a[$attrName] = (string) $attrNode->value;
                    }
                    $output['@attributes'] = $a;
                }
                foreach ($output as $t => $v) {
                    if(is_array($v) && count($v)==1 && $t!='@attributes') {
                        $output[$t] = $v[0];
                    }
                }
            }
            break;
    }
    return $output;
}

/**
 * Class FiboSmsClient
 * @author Nhan Nguyen
 */
class FiboSmsClient {
    private $_fiboAccount;
    private $_securityPassword;
    private $_fiboServerBaseUrl;
    private $_msgPrefix;

    /* ___________ General Methods for the FiboSmsClient Class __________ */

    function __construct() {
        /*
        Establish just our basic elements in the constructor:
        */
        // BASE CONFIGS - set these for your fibosms server in config.php and they will
        // simply flow in here via the constants:
        $this->_securityPassword 		= CONFIG_SECURITY_PASSWORD;
        $this->_fiboServerBaseUrl 	= CONFIG_SERVER_BASE_URL;
        $this->_fiboAccount = CONFIG_ACCOUNT;
        $this->_msgPrefix = CONFIG_PREFIX;
    }
    private function _processXmlResponse($url){
        /*
        A private utility method used by other public methods to process XML responses.
        */
        if (extension_loaded('curl')) {
            $ch = curl_init() or die ( curl_error($ch) );
            $timeout = 10;
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
            curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            $data = curl_exec( $ch );
            curl_close( $ch );

            if($data){
                return xmlstr_to_array(new SimpleXMLElement($data));
            }
            else
                return false;
        }
        return xmlstr_to_array(simplexml_load_file($url));
    }
    public function testConnect(){
        //http://center.fibosms.com/Service.asmx/About

        $endUrl = $this->_fiboServerBaseUrl."About";
        $xml = $this->_processXmlResponse($endUrl);

        if($xml) {
            return array(
                'message' => $xml['Message'],
            );
        }
        else {
            return null;
        }
    }
    public function checkAccount(){
        /**
         * http://center.fibosms.com/Service.asmx/CheckClient?clientNo=string&clientPass=string
         */

        $url = $this->_fiboServerBaseUrl."CheckClient?";
        $params = "clientNo=".urlencode($this->_fiboAccount)."&clientPass=".$this->_securityPassword;

        $xml = $this->_processXmlResponse($url.$params);

        if($xml) {
            if(isset($xml['Code'])){
                return array(
                    'returnCode' => $xml['Code'],
                    'message' => $xml['Message'],
                    'time' => $xml['Time']
                );
            }
            else{

            }
        }
        else {
            return null;
        }

    }
    public function getClientBalance($createdParams){
        /**
         * http://center.fibosms.com/Service.asmx/GetClientBalance?clientNo=string&clientPass=string&serviceType=string
         */
        $url = $this->_fiboServerBaseUrl."GetClientBalance?";
        $params = "clientNo=".urlencode($this->_fiboAccount)."&clientPass=".$this->_securityPassword.'&serviceType='.$createdParams['serviceType'];

        $xml = $this->_processXmlResponse($url.$params);
        if($xml) {
            if(isset($xml['Code']))
                return array(
                    'returnCode' => $xml['Code'],
                    'message' => $xml['Message'],
                    'time' => $xml['Time']
                );
            return array(
                'returnCode' => null,
                'message' => $xml['Message'],
                'time' => null
            );
        }
        else {
            return null;
        }


    }
    public function getClientCommingSMSNotDividePage(){
        /**
         * http://center.fibosms.com/Service.asmx/GetClientCommingSMSNotDividePage?clientNo=string&clientPass=string&fromDate=string&toDate=string&smsStatus=string&serviceTypeID=string&key=string
         * Tham số:
        - clientNo
        - clientPass
        - fromDate
        - toDate
        - smsStatus
        - serviceTypeID
        - key

        Trong đó:
        - fromDate(MM-DD-YYYY) : những tin nhắn từ ngày này trở đi sẽ được gửi trả về
                - toDate(MM-DD-YYYY): những tin nhắn từ ngày này trở lại sẽ được gửi trả về
                smsStatus : trạng thái của những tin nhắn mà bạn muốn nhận về
                - serviceTypeID : Mã dịch vụ
                - key: những tin nhắn có nội dung trùng với tham số key sẽ được trả về, có thể là số điện thoại hoặc nội
        dung tin nhắn. Nếu không có thì để trống

        Chú ý, SMSStatus có ý nghĩa sau:
        Pending = 0,
         Sending = 1,
         SentSuccess = 2,
         SentFailed = 3,
         Deleted = 4,
         ToNumberNotCorrect = 5,
         CanNotSend = 6,
         OutOfDate = 7,
         SendMTFailed = 8,
         Other = 9,
         SpamSMS = 10,
        RestoreToAccountClient = 11*/

    }
    public function getClientSMSListOfPage(){
        //http://center.fibosms.com/Service.asmx/GetClientSMSListOfPage?clientNo=string&clientPass=string&pageNO=string&fromDate=string&toDate=string&smsStatus=string&serviceTypeID=string&key=string
        /**
         * Tham số:
            - clientNo
            - clientPass
            - pageNO
            - key
            -
            Trong đó:
            - Key : là điều kiện lọc, dùng điều kiện lọc này để lấy ra danh sách SMS theo một tiêu trí nào đó. Ví dụ lấy
            SMS gởi tới số SPhone thì key=”0905”. Nếu muốn lấy toàn bộ danh sách thì key=””
            - PageNO: số trang muốn lấy.

            Kết quả trả về:
            Chú ý, SMSStatus có ý nghĩa sau:
            Pending = 0,
             Sending = 1,
             SentSuccess = 2,
             SentFailed = 3,
             Deleted = 4,
             ToNumberNotCorrect = 5,
             CanNotSend = 6,
             OutOfDate = 7,
             SendMTFailed = 8,
             Other = 9,
             SpamSMS = 10,
            RestoreToAccountClient = 11

         */
    }
    public function sendBulkSms($createdParams){
        /**
         * Send multi sms with different message and phone number
         * http://center.fibosms.com/Service.asmx/SendBulkSMS?clientNo=string&clientPass=string&smsList=string&serviceType=string
         * USAGE:
            $createdParams = array(
            'smsList' => array(array("PhoneNumber"=>"1234559999", "Message"=> "test from API")),
            'serviceType' => '1');
         */

        /**
         * Rebuild smsList structure
           'smsList' => "<DocumentElement>
                            <SMSLIST>
                            <PhoneNumber>0937100759</PhoneNumber>
                            <Message>Message 1</Message>
                            </SMSLIST>
                            <SMSLIST>
                            <PhoneNumber>0937100759</PhoneNumber>
                            <Message>Message 2</Message>
                            </SMSLIST>
                         </DocumentElement>",
         */

        $smsList = "<DocumentElement>";
        foreach($createdParams["smsList"] as $k=>$v){
            $smsList .= "<SMSLIST><PhoneNumber>".urlencode($v['PhoneNumber'])."</PhoneNumber><Message>".urlencode($this->removeAccent($v['Message']))."</Message></SMSLIST>";
        }

        $smsList .="</DocumentElement>";
        $url = $this->_fiboServerBaseUrl."SendBulkSMS?";
        $params = "clientNo=".urlencode($this->_fiboAccount)."&clientPass=".$this->_securityPassword."&smsList=".$smsList.'&serviceType='.$createdParams['serviceType'];
        $xml = $this->_processXmlResponse($url.$params);

        if($xml) {
            if(isset($xml['Code']))
                return array(
                    'returnCode' => $xml['Code'],
                    'message' => $xml['Message'],
                    'time' => $xml['Time']
                );
            return array(
                'returnCode' => null,
                'message' => $xml['Message'],
                'time' => null
            );
        }
        else {
            return null;
        }

    }
    public function sendSms($createdParams){

        /*
         * Send one sms msg
         * URL: http://center.fibosms.com/Service.asmx/SendSMS?clientNo=string&clientPass=string&phoneNumber=string&smsMessage=string&smsGUID=string&serviceType=string
         * USAGE:
         *
         $createdParams = array(
            'phoneNumber' => '1234',
            'smsMessage' => 'Hello World',
            'smsGUID' => '1',// : your sms id
            'serviceType' => '1' //FiboSMSHosting: 1
        )
         */
        $createdParams['smsMessage'] = $this->_msgPrefix . $createdParams['smsMessage'];
        $url = $this->_fiboServerBaseUrl."SendSMS?";
        $params = "clientNo=".urlencode($this->_fiboAccount)."&clientPass=".$this->_securityPassword."&phoneNumber=".$createdParams['phoneNumber']."&smsMessage=".urlencode($this->removeAccent($createdParams['smsMessage'])).'&smsGUID='.$createdParams['smsGUID'].'&serviceType='.$createdParams['serviceType'];

        $xml = $this->_processXmlResponse($url.$params);
        if($xml) {
            if(isset($xml['Code']))
                return array(
                    'returnCode' => $xml['Code'],
                    'message' => $xml['Message'],
                    'time' => $xml['Time']
                );
            return array(
                'returnCode' => null,
                'message' => $xml['Message'],
                'time' => null
            );
        }
        else {
            return null;
        }
    }
    public function sendSmsToListMobilePhone($createdParams){
        //
        /**
         * Send multi sms with different message and phone number
         * http://center.fibosms.com/Service.asmx/SendSMSToListMobilePhone?clientNo=string&clientPass=string&senderName=string&smsContent=string&listPhoneNumber=string&serviceType=string
         * USAGE:
        $createdParams = array(
        'smsContent' => "Hello world",
         'listPhoneNumber' => array("01234559999", "01234559999"),
         'senderName' => 'n/a',
        'serviceType' => '1');
         */

        /**
         * Rebuild smsList structure
        'listPhoneNumber' => "<Document>
            <ListMobilePhone>
                <PhoneNumber>0937100759</PhoneNumber>
                <SMSGUID>Message 1</SMSGUID>
            </ListMobilePhone>
            <ListMobilePhone>
                <PhoneNumber>0937100759</PhoneNumber>
                <SMSGUID>Message 2</SMSGUID>
            </ListMobilePhone>
        </Document>",
         */

        $listPhoneNumber = "<DocumentElement>";
        foreach($createdParams["listPhoneNumber"] as $k=>$v){
            $listPhoneNumber .= "<ListMobilePhone><PhoneNumber>".urlencode($this->removeAccent($v))."</PhoneNumber><SMSGUID>".urlencode($this->removeAccent($v))."</SMSGUID></ListMobilePhone>";
        }

        $listPhoneNumber .="</DocumentElement>";

        $url = $this->_fiboServerBaseUrl."SendSMSToListMobilePhone?";
        //add prefix to msg
        $createdParams['smsContent'] = $this->_msgPrefix . $createdParams['smsContent'];
        $params = "clientNo=".urlencode($this->_fiboAccount)."&clientPass=".$this->_securityPassword."&smsContent=".urlencode($this->removeAccent($createdParams["smsContent"])) ."&listPhoneNumber=".$listPhoneNumber.'&serviceType='.$createdParams['serviceType']."&senderName=".urlencode($createdParams["senderName"]);
        $xml = $this->_processXmlResponse($url.$params);

        if($xml) {
            if(isset($xml['Code']))
                return array(
                    'returnCode' => $xml['Code'],
                    'message' => $xml['Message'],
                    'time' => $xml['Time']
                );
            return array(
                'returnCode' => null,
                'message' => $xml['Message'],
                'time' => null
            );
        }
        else {
            return null;
        }
    }
    public function sendSmsEmail(){

        //http://center.fibosms.com/Service.asmx/SendSMS?clientNo=string&clientPass=string&phoneNumber=string&smsMessage=string&smsGUID=string&serviceType=3

    }
    public  function sendMaskedSms(){
        //http://center.fibosms.com/service.asmx/SendMaskedSMS?clientNo=string&clientPass=string&senderName=&phoneNumber=string&smsMessage=string&smsGUID=string&serviceType=string

    }
    public function getSmsSentList(){
        //http://center.fibosms.com/Service.asmx/GetSMSSentList?clientNo=string&clientPass=string&minutes=string

    }
    public function getTotalPageOfClientSms(){
        //http://center.fibosms.com/Service.asmx/GetTotalPageOfClientSMS?clientNo=string&clientPass=string&key=string
    }
    public function getCommingSmsList(){
        //http://center.fibosms.com/Service.asmx/GetCommingSMSList?clientNo=string&clientPass=string&minutes=string

    }
    public function getSmsStatus(){
        //http://center.fibosms.com/Service.asmx/GetSMSStatus?clientNo=string&clientPass=string&smsList=string

    }
    public function getClientSenderNameList(){
        //http://center.fibosms.com/Service.asmx/GetClientSenderNameList?clientNo=string&clientPass=string

    }
    public function getListSmsHostingWithBalance(){
        //Gọi Hàm:
        //http://center.fibosms.com/Service.asmx/GetListSMSHostingWithBalance?clientNo=string&clientPass=string


    }
    public function sendSmsToMultiMessage(){
        //http://center.fibosms.com/Service.asmx/SendSMSWithMultiMessage?clientNo=string&clientPass=string&senderName=string&smsMessage=string &serviceType=string

    }
    /**
     * FStar Team
     * @author Klaus
     * July 14th 2013
     */
    public static function removeAccent( $str )
    {
        $trans = array(
            "đ" => "d", "ă" => "a", "â" => "a", "á" => "a", "à" => "a", "ả" => "a", "ã" => "a", "ạ" => "a",
            "ấ" => "a", "ầ" => "a", "ẩ" => "a", "ẫ" => "a", "ậ" => "a", "ắ" => "a", "ằ" => "a", "ẳ" => "a",
            "ẵ" => "a", "ặ" => "a", "é" => "e", "è" => "e", "ẻ" => "e", "ẽ" => "e", "ẹ" => "e", "ế" => "e",
            "ề" => "e", "ể" => "e", "ễ" => "e", "ệ" => "e", "í" => "i", "ì" => "i", "ỉ" => "i", "ĩ" => "i",
            "ị" => "i", "ư" => "u", "ô" => "o", "ơ" => "o", "ê" => "e", "Ư" => "u", "Ô" => "o", "Ơ" => "o",
            "Ê" => "e", "ú" => "u", "ù" => "u", "ủ" => "u", "ũ" => "u", "ụ" => "u", "ứ" => "u", "ừ" => "u",
            "ử" => "u", "ữ" => "u", "ự" => "u", "ó" => "o", "ò" => "o", "ỏ" => "o", "õ" => "o", "ọ" => "o",
            "ớ" => "o", "ờ" => "o", "ở" => "o", "ỡ" => "o", "ợ" => "o", "ố" => "o", "ồ" => "o", "ổ" => "o",
            "ỗ" => "o", "ộ" => "o", "ú" => "u", "ù" => "u", "ủ" => "u", "ũ" => "u", "ụ" => "u", "ứ" => "u",
            "ừ" => "u", "ử" => "u", "ữ" => "u", "ự" => "u", 'ý' => 'y', 'ỳ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y',
            'ỵ' => 'y', 'Ý' => 'Y', 'Ỳ' => 'Y', 'Ỷ' => 'Y', 'Ỹ' => 'Y', 'Ỵ' => 'Y', "Đ" => "D", "Ă" => "A",
            "Â" => "A", "Á" => "A", "À" => "A", "Ả" => "A", "Ã" => "A", "Ạ" => "A", "Ấ" => "A", "Ầ" => "A",
            "Ẩ" => "A", "Ẫ" => "A", "Ậ" => "A", "Ắ" => "A", "Ằ" => "A", "Ẳ" => "A", "Ẵ" => "A", "Ặ" => "A",
            "É" => "E", "È" => "E", "Ẻ" => "E", "Ẽ" => "E", "Ẹ" => "E", "Ế" => "E", "Ề" => "E", "Ể" => "E",
            "Ễ" => "E", "Ệ" => "E", "Í" => "I", "Ì" => "I", "Ỉ" => "I", "Ĩ" => "I", "Ị" => "I", "Ư" => "U",
            "Ô" => "O", "Ơ" => "O", "Ê" => "E", "Ư" => "U", "Ô" => "O", "Ơ" => "O", "Ê" => "E", "Ú" => "U",
            "Ù" => "U", "Ủ" => "U", "Ũ" => "U", "Ụ" => "U", "Ứ" => "U", "Ừ" => "U", "Ử" => "U", "Ữ" => "U",
            "Ự" => "U", "Ó" => "O", "Ò" => "O", "Ỏ" => "O", "Õ" => "O", "Ọ" => "O", "Ớ" => "O", "Ờ" => "O",
            "Ở" => "O", "Ỡ" => "O", "Ợ" => "O", "Ố" => "O", "Ồ" => "O", "Ổ" => "O", "Ỗ" => "O", "Ộ" => "O",
            "Ú" => "U", "Ù" => "U", "Ủ" => "U", "Ũ" => "U", "Ụ" => "U", "Ứ" => "U", "Ừ" => "U", "Ử" => "U",
            "Ữ" => "U", "Ự" => "U", "'"=> "-", '"' => '-'
        );

        return strtr($str, $trans);

    }


}