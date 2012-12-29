<?php


function sms_save($id, $from, $to, $message, $status, $from_geo = '', $to_geo = '')
{
    $db = db_connect();

    $rq = $db->prepare('INSERT INTO sms VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $rq->execute(array(
        $id,
        $from,
        $to,
        $message,
        gmdate('c'),
        $from_geo,
        $to_geo,
        $status
    ));
}


function sms_status($id, $status)
{
    $db = db_connect();

    $rq = $db->prepare('UPDATE sms SET status=? WHERE id=?');
    $rq->execute(array($status, $id));
}


function sms_list()
{
    $db = db_connect();

    $rq = $db->prepare('SELECT * FROM sms ORDER BY date_created DESC');
    $rq->execute();

    return $rq->fetchAll(PDO::FETCH_ASSOC);
}


function sms_send($to, $message)
{
    $response = api_call('POST', '/SMS/Messages.json', array(
        'From' => PHONE_NUMBER,
        'To' => $to,
        'Body' => $message,
        'ApplicationSid' => APPLICATION_SID
    ));

    if (! $response) {

        return false;
    }

    sms_save($response['sid'], PHONE_NUMBER, $to, $message, 'pending');

    return true;
}


handler('receive-sms', function() {

    debug($_GET, 'get receive-sms');
    debug($_POST, 'post receive-sms');

    sms_save(
        $_POST['SmsSid'],
        $_POST['From'],
        $_POST['To'],
        $_POST['Body'],
        $_POST['SmsStatus'],
        @$_POST['FromCity'].' '.@$_POST['FromState'].' '.@$_POST['FromCountry'],
        @$_POST['ToCity'].' '.@$_POST['ToState'].' '.@$_POST['ToCountry']
    );

    mail_alert('SMS Received from '.$_POST['From'], "You got this SMS:\n\n".$_POST['Body']);

    response_api();
});


handler('callback-sms', function() {

    debug($_GET, 'get callback-sms');
    debug($_POST, 'post callback-sms');

    sms_status($_POST['SmsSid'], $_POST['SmsStatus']);

    response_api();
});


handler('sms-history', function() {

    $html = '<div class="page-header"><h1>SMS History</h1></div>
        <table class="table table-hover table-striped table-condensed table-bordered">
            <tr>
                <th>Status</th>
                <th>Date</th>
                <th>From</th>
                <th>To</th>
                <th>Message</th>
                <th>GeoLocation (From)</th>
                <th>GeoLocation (To)</th>
            </tr>';

    foreach (sms_list() as $sms) {

        $html .= '<tr>
            <td>'.htmlspecialchars($sms['status']).'</td>
            <td>'.date('Y-m-d H:i', strtotime($sms['date_created'])).'</td>
            <td>'.htmlspecialchars($sms['from_number']).'</td>
            <td>'.htmlspecialchars($sms['to_number']).'</td>
            <td>'.htmlspecialchars($sms['message']).'</td>
            <td>'.htmlspecialchars($sms['to_geo']).'</td>
            <td>'.htmlspecialchars($sms['from_geo']).'</td>
            </tr>';
    }

    $html .= '</table>';

    response_html($html);
});


handler('send-sms', function() {

    if (sms_send($_POST['To'], stripslashes($_POST['Body']))) {

        response_html('
            <div class="page-header"><h1>Send a SMS</h1></div>
            <p class="alert alert-success">SMS sent successfully!</p>
        ');
    }
    else {

        response_html('
            <div class="page-header"><h1>Send a SMS</h1></div>
            <p class="alert alert-error">Unable to send this SMS!</p>
        ');
    }
});


handler('create-sms', function() {

    response_html('
        <div class="page-header"><h1>Send a SMS</h1></div>
        <form action="?handler=send-sms" method="post" class="well">
            <label for="To">Phone Number:</label><input type="tel" name="To" id="To" placeholder="+123456789" required autofocus/>
            <label for="Body">Message:</label><textarea cols="30" rows="10" name="Body" id="Body" required></textarea>
            <div><button type="submit" class="btn btn-primary">Send</button></div>
        </form>
    ');
});