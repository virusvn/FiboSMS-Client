Installation
=======
#1. Require
    require('fibosms-client.php');
#2. Config
    Config your Fibo information (config.php):

    define("CONFIG_ACCOUNT", "CL1234");

    define("CONFIG_SECURITY_PASSWORD", "yourpassword");

    define("CONFIG_SERVER_BASE_URL", "http://center.fibosms.com/Service.asmx/");

    define("CONFIG_PREFIX", "iMeeting.vn: "); //using with FiboSMSHosting
#3. Usage
    $fibo = new FiboSmsClient();

    $createdParams = array(

                'phoneNumber' => '0908990899',
                'smsMessage' => 'test from api',
                'smsGUID' => '1',// : ID của tin nhắn
                'serviceType' => '1'
            );
    $res = $fibo->sendSms($createdParams);




