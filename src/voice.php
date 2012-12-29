<?php


function call_save($id, $from, $to, $status, $from_geo = '', $to_geo = '')
{
    $db = db_connect();

    $rq = $db->prepare('INSERT INTO calls VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $rq->execute(array(
        $id,
        $from,
        $to,
        gmdate('c'),
        $from_geo,
        $to_geo,
        $status,
        '',
        '',
        '',
        ''
    ));
}


function record_save($call_id, $recording_duration, $recording_id, $recording_url)
{
    $db = db_connect();

    $rq = $db->prepare('
        UPDATE calls SET
            recording_duration=?,
            recording_id=?,
            recording_url=?
        WHERE id=?
    ');

    $rq->execute(array(
        $recording_duration,
        $recording_id,
        $recording_url,
        $call_id
    ));
}


function call_status($id, $status, $duration = '')
{
    $db = db_connect();

    $rq = $db->prepare('UPDATE calls SET status=?, call_duration=? WHERE id=?');
    $rq->execute(array($status, $duration, $id));
}


function call_list()
{
    $db = db_connect();

    $rq = $db->prepare('SELECT * FROM calls ORDER BY date_created DESC');
    $rq->execute();

    return $rq->fetchAll(PDO::FETCH_ASSOC);
}


function incall_read()
{
    $db = db_connect();

    $rq = $db->prepare('SELECT * FROM incoming_calls');
    $rq->execute();

    $values = $rq->fetch(PDO::FETCH_ASSOC);

    if (! $values) {

        $values = array(
            'action' => 'recording',
            'redirect_number' => ''
        );

        $rq = $db->prepare('INSERT INTO incoming_calls (action, redirect_number) VALUES (?, ?)');
        $rq->execute(array_values($values));
    }

    return $values;
}


function incall_save($action, $redirect)
{
    $db = db_connect();

    $rq = $db->prepare('UPDATE incoming_calls SET action=?, redirect_number=?');
    $rq->execute(array($action, $redirect));
}


handler('call-history', function() {

    $html = '<div class="page-header"><h1>Calls History</h1></div>
        <table class="table table-hover table-striped table-condensed table-bordered">
            <tr>
                <th>Status</th>
                <th>Date</th>
                <th>From</th>
                <th>To</th>
                <th>Call Duration</th>
                <th>Record Duration</th>
                <th>Recording</th>
                <th>GeoLocation (From)</th>
                <th>GeoLocation (To)</th>
            </tr>';

    foreach (call_list() as $id => $call) {

        $html .= '<tr>
            <td>'.htmlspecialchars($call['status']).'</td>
            <td>'.date('Y-m-d H:i', strtotime($call['date_created'])).'</td>
            <td>'.htmlspecialchars($call['from_number']).'</td>
            <td>'.htmlspecialchars($call['to_number']).'</td>
            <td>'.($call['call_duration'] ?: '0').'s</td>
            <td>'.($call['recording_duration'] ?: '0').'s</td>
            <td>'.($call['recording_url'] ? '<audio src="'.$call['recording_url'].'" preload="metadata" id="rec-'.$id.'"></audio><button onclick="play(\'rec-'.$id.'\')" class="btn btn-inverse"><i class="icon-play icon-white"></i> Play</button>' : '').'</td>
            <td>'.htmlspecialchars($call['to_geo']).'</td>
            <td>'.htmlspecialchars($call['from_geo']).'</td>
            </tr>';
    }

    $html .= '</table>';

    $html .= '<script type="text/javascript">';
    $html .= 'function play(id) { document.getElementById(id).play(); }';
    $html .= '</script>';

    response_html($html);
});


handler('receive-call', function() {

    debug($_GET, 'get receive-call');
    debug($_POST, 'post receive-call');

    if (isset($_POST['RecordingUrl'])) {

        // Handle recording

        call_status($_POST['CallSid'], $_POST['CallStatus']);

        record_save(
            $_POST['CallSid'],
            $_POST['RecordingDuration'],
            $_POST['RecordingSid'],
            $_POST['RecordingUrl']
        );

        response_api();
    }
    else if (isset($_POST['PhoneNumber'])) {

        // Handle outbound phone call

        call_save(
            $_POST['CallSid'],
            PHONE_NUMBER,
            $_POST['PhoneNumber'],
            $_POST['CallStatus']
        );

        response_api('
            <Dial callerId="'.htmlspecialchars(PHONE_NUMBER).'">
                <Number>'.htmlspecialchars($_POST['PhoneNumber']).'</Number>
            </Dial>
        ');
    }
    else {

        // Handle incoming calls

        $incall = incall_read();

        call_save(
            $_POST['CallSid'],
            $_POST['From'],
            $_POST['To'],
            $_POST['CallStatus'],
            @$_POST['CallerCity'].' '.@$_POST['CallerState'].' '.@$_POST['CallerCountry'],
            @$_POST['CalledCity'].' '.@$_POST['CalledState'].' '.@$_POST['CalledCountry']
        );

        if ($incall['action'] === 'recording') {

            mail_alert('Call Received from '.$_POST['From'], 'You got a phone call!');

            response_api('
                <Say voice="man" language="'.VOICE_LANG.'">'.VOICE_MESSAGE.'</Say>
                    <Record maxLength="60" finishOnKey="*"/>
                <Say voice="man" language="'.VOICE_LANG.'">'.VOICE_MISSING_RECORD.'</Say>
            ');
        }
        else if ($incall['action'] === 'client') {

            response_api('
                <Dial callerId="'.htmlspecialchars(PHONE_NUMBER).'">
                    <Client>'.CLIENT_NAME.'</Client>
                </Dial>
            ');
        }
        else if ($incall['action'] === 'redirect') {

            response_api('<Dial>'.htmlspecialchars($incall['redirect_number']).'</Dial>');
        }

        response_api();
    }
});


handler('callback-call', function() {

    debug($_GET, 'get callback-call');
    debug($_POST, 'post callback-call');

    call_status($_POST['CallSid'], $_POST['CallStatus'], $_POST['CallDuration']);

    response_api();
});


handler('create-call', function() {

    $capability = new Services_Twilio_Capability(ACCOUNT_SID, AUTH_TOKEN);
    $capability->allowClientOutgoing(APPLICATION_SID);

    $token = $capability->generateToken();

    response_html('
        <div class="page-header"><h1>Make a call</h1></div>

        <form class="well">
            <input type="tel" id="number" name="number" placeholder="+123456789"/>
            <div>
            <button type="button" class="btn btn-primary" onclick="call();"><i class="icon-headphones icon-white"></i> Call</button>
            <button type="button" class="btn btn-danger" onclick="hangup();"><i class="icon-off icon-white"></i> Hangup</button>
            </div>
        </form>

        <h2>Activity</h2>
        <pre id="log">Waiting...</pre>

        <script type="text/javascript">

        $(document).ready(function() {

            Twilio.Device.setup("'.$token.'");
        });

        Twilio.Device.ready(function (device) {

            $("#log").text("Ready");
        });

        Twilio.Device.error(function (error) {

            $("#log").text("Error: " + error.message);
        });

        Twilio.Device.connect(function (conn) {

            $("#log").text("Successfully established call");
        });

        Twilio.Device.disconnect(function (conn) {

            $("#log").text("Call ended");
        });

        function call() {

            var params = {"PhoneNumber": $("#number").val()};
            Twilio.Device.connect(params);
        }

        function hangup() {

            Twilio.Device.disconnectAll();
        }

        </script>
    ');
});


handler('incoming-calls', function() {

    if ($_POST['incoming-call'] === 'redirect' && empty($_POST['redirect_number'])) {

        response_html('
            <div class="page-header"><h1>Incoming calls</h1></div>
            <p class="alert alert-error">You must set a phone number to redirect your calls!</p>
        ');
    }
    else {

        incall_save($_POST['incoming-call'], $_POST['redirect_number']);

        response_html('
            <div class="page-header"><h1>Incoming calls</h1></div>
            <p class="alert alert-success">Parameters saved successfully!</p>
        ');
    }
});


db_init();

$incall = incall_read();

$capability = new Services_Twilio_Capability(ACCOUNT_SID, AUTH_TOKEN);

if ($incall['action'] === 'client') {

    $capability->allowClientIncoming(CLIENT_NAME);
}

$token = $capability->generateToken();

response_html('
    <div class="page-header"><h1>Incoming calls</h1></div>

    <h2>Choose how to handle incoming calls</h2>

    <form class="well" action="?handler=incoming-calls" method="post">
        <label class="radio"><input '.($incall['action'] === 'recording' ? 'checked="checked"' : '').' type="radio" name="incoming-call" value="recording"/>Enable recording</label>
        <label class="radio"><input '.($incall['action'] === 'client' ? 'checked="checked"' : '').' type="radio" name="incoming-call" value="client"/>Receive here</label>
        <label class="radio"><input '.($incall['action'] === 'redirect' ? 'checked="checked"' : '').' type="radio" name="incoming-call" value="redirect"/>Redirect to this number:</label>
        <input type="tel" name="redirect_number" placeholder="+123456789" value="'.$incall['redirect_number'].'"/>
        <div>
            <button type="submit" class="btn btn-success">Save</button>
        </div>
    </form>

    <h2>Inbound call</h2>
    <form class="well">
        <div>
        <button type="button" id="btn-answer" disabled="disabled" class="btn btn-success" onclick="answer();"><i class="icon-headphones icon-white"></i> Answer</button>
        <button type="button" id="btn-hangup" disabled="disabled" class="btn btn-danger" onclick="hangup();"><i class="icon-off icon-white"></i> Hangup</button>
        </div>
    </form>

    <h2>Activity</h2>
    <pre id="log">Waiting...</pre>

    <script type="text/javascript">

    var connection = null;

    $(document).ready(function() {

        Twilio.Device.setup("'.$token.'");
    });

    Twilio.Device.ready(function (device) {

        $("#log").text("Ready");
    });

    Twilio.Device.error(function (error) {

        $("#log").text("Error: " + error.message);
    });

    Twilio.Device.connect(function (conn) {

        $("#log").text("Successfully established call");
    });

    Twilio.Device.disconnect(function (conn) {

        $("#log").text("Call ended");
    });

    Twilio.Device.incoming(function (conn) {

        $("#log").text("Incoming connection from " + conn.parameters.From);

        $("#btn-hangup").removeAttr("disabled");
        $("#btn-answer").removeAttr("disabled");

        connection = conn;
    });

    function hangup() {

        $("#btn-hangup").attr("disabled", "disabled");
        $("#btn-answer").attr("disabled", "disabled");

        Twilio.Device.disconnectAll();
        connection = null;
    }

    function answer() {

        $("#btn-answer").attr("disabled", "disabled");

        if (connection) connection.accept();
    }

    </script>
');