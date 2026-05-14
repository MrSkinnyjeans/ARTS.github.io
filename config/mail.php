<?php
// ── ARTS · Mail Configuration ─────────────────────────────
// joshuamission17@gmail.com is the SENDER account only.
// Each user logs in with their own Gmail — OTP goes to their inbox.
//
// App Password setup:
//   1. Go to https://myaccount.google.com/apppasswords
//   2. Sign in as joshuamission17@gmail.com
//   3. Create an app password named "ARTS"
//   4. Paste the 16-char code below (with or without spaces)

return [
    'host'       => 'smtp.gmail.com',
    'port'       => 465,                          // SSL port (more reliable on localhost)
    'encryption' => 'ssl',                        // use SSL instead of STARTTLS
    'username'   => 'joshuamission17@gmail.com',
    'password'   => 'notq ijzi aqew ipro',        // your App Password
    'from_email' => 'joshuamission17@gmail.com',
    'from_name'  => 'ARTS System',
];
