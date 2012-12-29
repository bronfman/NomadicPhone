<?php


function auth()
{
    if (! isset($_SERVER['PHP_AUTH_USER']) || ($_SERVER['PHP_AUTH_PW'] != APP_PASSWORD || $_SERVER['PHP_AUTH_USER'] != APP_USER)) {

        header('WWW-Authenticate: Basic realm="Nomadic Phone Authentication"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Auth failed';
        exit;
    }
}


function handler($name, \Closure $callback)
{
    $exclude_handlers = array('receive-call', 'callback-call', 'receive-sms', 'callback-sms');
    $handler = isset($_GET['handler']) ? $_GET['handler'] : 'default';

    if (! in_array($handler, $exclude_handlers)) {

        auth();
    }

    if ($handler === $name) $callback();
}


function debug($variable, $info = '')
{
    if (DEBUG) {

        file_put_contents(
            'debug.txt',
            "\n\n--- $info\n".date('Y-m-d H:i:s')."\n".var_export($variable, true),
            FILE_APPEND | LOCK_EX
        );
    }
}


function db_connect()
{
    $pdo = new PDO('sqlite:'.DB_FILENAME);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $pdo;
}


function db_init()
{
    $db = db_connect();

    $db->exec('CREATE TABLE IF NOT EXISTS sms (
        id TEXT PRIMARY KEY,
        from_number TEXT,
        to_number TEXT,
        message TEXT,
        date_created TEXT,
        from_geo TEXT,
        to_geo TEXT,
        status TEXT
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS calls (
        id TEXT PRIMARY KEY,
        from_number TEXT,
        to_number TEXT,
        date_created TEXT,
        from_geo TEXT,
        to_geo TEXT,
        status TEXT,
        call_duration TEXT,
        recording_url TEXT,
        recording_id TEXT,
        recording_duration TEXT
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS incoming_calls (
        action TEXT DEFAULT \'recording\',
        redirect_number TEXT
    )');
}


function api_call($method, $path, array $data = array())
{
    $headers = array(
        'User-Agent: NomadicPhone',
        'Connection: close'
    );

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, API_BASE_URL.ACCOUNT_SID.$path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, ACCOUNT_SID.':'.AUTH_TOKEN);

    if ($method === 'POST') {

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    $http_response = curl_exec($ch);

    curl_close($ch);

    return json_decode($http_response, true);
}


function mail_alert($subject, $message)
{
    $subject = "=?UTF-8?B?".base64_encode($subject)."?=";

    $headers = "From: Nomadic Phone <no-reply@fredericguillot.com>\r\n".
               "MIME-Version: 1.0" . "\r\n" .
               "Content-type: text/plain; charset=UTF-8" . "\r\n";

    return mail(MAIL_ADDRESS, $subject, $message, $headers);
}


function response_api($xml = '')
{
    header('Content-Type: application/xml');

    $data = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $data .= '<Response>'.$xml.'</Response>';

    debug($data, 'response api');
    echo $data;
    exit;
}


function response_html($body)
{
    echo '
    <!DOCTYPE html>
    <html>
        <head>
            <meta charset="utf-8"/>
            <title>Nomadic Phone</title>
            <link href="//netdna.bootstrapcdn.com/twitter-bootstrap/2.2.2/css/bootstrap-combined.min.css" rel="stylesheet"/>
            <style type="text/css">
                label {
                    font-weight: bold;
                    cursor: pointer;
                }

                body {
                    padding-top: 50px;
                }
            </style>
            <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
            <script src="//netdna.bootstrapcdn.com/twitter-bootstrap/2.2.2/js/bootstrap.min.js"></script>
            <script type="text/javascript" src="//static.twilio.com/libs/twiliojs/1.1/twilio.min.js"></script>
        </head>
        <body>
            <div class="container-fluid">

            <div class="navbar navbar-fixed-top navbar-inverse">
                <div class="navbar-inner">
                    <div class="container">
                        <a class="brand" href="?">Nomadic Phone</a>
                        <ul class="nav">
                            <li '.(! isset($_GET['handler']) || $_GET['handler'] == 'incoming-calls' ? 'class="active"' : '').'><a href="?">Incoming calls</a></li>
                            <li '.(isset($_GET['handler']) && substr($_GET['handler'], -4) == 'call' ? 'class="active"' : '').'><a href="?handler=create-call">Make a call</a></li>
                            <li '.(isset($_GET['handler']) && $_GET['handler'] == 'call-history' ? 'class="active"' : '').'><a href="?handler=call-history">Calls history</a></li>
                            <li '.(isset($_GET['handler']) && substr($_GET['handler'], -3) == 'sms' ? 'class="active"' : '').'><a href="?handler=create-sms">Send a SMS</a></li>
                            <li '.(isset($_GET['handler']) && $_GET['handler'] == 'sms-history' ? 'class="active"' : '').'><a href="?handler=sms-history">SMS history</a></li>
                        </ul>
                        <p class="navbar-text pull-right" style="color: red; font-size: 1.4em">'.PHONE_NUMBER.'</p>
                    </div>
                </div>
            </div>

            <div class="row-fluid">
                <div class="span12">'.$body.'</div>
            </div>

            </div>
        </body>
    </html>';

    exit;
}