<?php

// Your email address, used to receive notification when you got a call or a sms
const MAIL_ADDRESS = 'youpi@localhost';

// Twilio Phone number
const PHONE_NUMBER = '+123456789';

// Recording voice language
const VOICE_LANG = 'fr';

// Recording message
const VOICE_MESSAGE = 'Vous êtes bien sur le répondeur de Fred, laissez un message après le beep sonore et appuyez sur étoile pour sauvegarder votre message';

// Message when there is no message recorded
const VOICE_MISSING_RECORD = 'Aucun message n\'a été enregistré';

// Twilio account SID
const ACCOUNT_SID = '';

// Twilio Authentication token
const AUTH_TOKEN = '';

// Your application SID created at Twilio
const APPLICATION_SID = '';

// Enable debug output, you got a file named debug.txt in the same directory
const DEBUG = true;

// Base URL of the Twilio API
const API_BASE_URL = 'https://api.twilio.com/2010-04-01/Accounts/';

// Sqlite database path
const DB_FILENAME = 'data.sqlite';

// Client name used when you perform a call from the webapp
const CLIENT_NAME = 'fred';